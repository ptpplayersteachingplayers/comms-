<?php
/**
 * Webhooks handler for Twilio and Microsoft Teams
 * v3.4.0 - Added SMS delivery status tracking, HELP keyword handling
 */
class PTP_Comms_Hub_Webhooks {
    
    public static function init() {
        add_action('init', array(__CLASS__, 'register_endpoints'));
    }
    
    public static function register_endpoints() {
        // Twilio SMS webhook
        add_rewrite_rule(
            '^ptp-comms/sms-webhook/?$',
            'index.php?ptp_comms_webhook=sms',
            'top'
        );
        
        // Twilio SMS status callback
        add_rewrite_rule(
            '^ptp-comms/sms-status/?$',
            'index.php?ptp_comms_webhook=sms_status',
            'top'
        );
        
        // Twilio Voice webhook
        add_rewrite_rule(
            '^ptp-comms/voice-webhook/?$',
            'index.php?ptp_comms_webhook=voice',
            'top'
        );
        
        // Test endpoint to verify webhooks are working
        add_rewrite_rule(
            '^ptp-comms/test-webhook/?$',
            'index.php?ptp_comms_webhook=test',
            'top'
        );
        
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'handle_webhook'));
    }
    
    public static function add_query_vars($vars) {
        $vars[] = 'ptp_comms_webhook';
        return $vars;
    }
    
    public static function handle_webhook() {
        $webhook_type = get_query_var('ptp_comms_webhook');
        
        switch ($webhook_type) {
            case 'sms':
                self::handle_sms_webhook();
                break;
            case 'sms_status':
                self::handle_sms_status_webhook();
                break;
            case 'voice':
                self::handle_voice_webhook();
                break;
            case 'test':
                self::handle_test_webhook();
                break;
        }
    }
    
    /**
     * Test webhook endpoint to verify webhooks are working
     */
    private static function handle_test_webhook() {
        header('Content-Type: application/json');
        echo json_encode(array(
            'status' => 'success',
            'message' => 'PTP Comms Hub webhooks are working!',
            'timestamp' => current_time('mysql'),
            'endpoints' => array(
                'sms_webhook' => home_url('/ptp-comms/sms-webhook'),
                'sms_status' => home_url('/ptp-comms/sms-status'),
                'voice_webhook' => home_url('/ptp-comms/voice-webhook')
            )
        ));
        exit;
    }
    
    /**
     * Handle Twilio SMS webhook
     */
    private static function handle_sms_webhook() {
        // Enhanced logging for debugging
        error_log('[PTP Webhook] ========== NEW SMS WEBHOOK RECEIVED ==========');
        error_log('[PTP Webhook] Full POST data: ' . print_r($_POST, true));
        error_log('[PTP Webhook] From: ' . ($_POST['From'] ?? 'N/A'));
        error_log('[PTP Webhook] Body: ' . ($_POST['Body'] ?? 'N/A'));
        error_log('[PTP Webhook] MessageSid: ' . ($_POST['MessageSid'] ?? 'N/A'));
        
        $from = isset($_POST['From']) ? $_POST['From'] : '';
        $body = isset($_POST['Body']) ? sanitize_text_field($_POST['Body']) : '';
        $sid = isset($_POST['MessageSid']) ? sanitize_text_field($_POST['MessageSid']) : '';
        
        if (empty($from) || empty($body)) {
            error_log('[PTP Webhook] ERROR: Missing from or body');
            error_log('[PTP Webhook] From empty: ' . (empty($from) ? 'YES' : 'NO'));
            error_log('[PTP Webhook] Body empty: ' . (empty($body) ? 'YES' : 'NO'));
            wp_die('Invalid request', '', array('response' => 400));
        }
        
        // Get or create contact
        $contact_id = ptp_comms_get_or_create_contact($from);
        error_log('[PTP Webhook] Contact ID: ' . $contact_id);
        
        if (!$contact_id) {
            error_log('[PTP Webhook] ERROR: Could not get/create contact');
            wp_die('Error processing contact', '', array('response' => 500));
        }
        
        $contact = PTP_Comms_Hub_Contacts::get_contact($contact_id);
        
        // Handle opt-out (STOP)
        if (preg_match('/\b(stop|unsubscribe|opt-out|cancel|quit|end)\b/i', $body)) {
            PTP_Comms_Hub_Contacts::opt_out($contact_id);
            
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $sms_service->send_sms($from, 'You have been unsubscribed from PTP Soccer Camps messages. Reply START to opt back in.');
            
            error_log('[PTP Webhook] Contact opted out: ' . $contact_id);
            exit;
        }
        
        // Handle opt-in (START)
        if (preg_match('/\b(start|subscribe|opt-in|yes|unstop)\b/i', $body)) {
            PTP_Comms_Hub_Contacts::opt_in($contact_id);
            
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $sms_service->send_sms($from, 'Welcome back! You are now subscribed to PTP Soccer Camps messages.');
            
            error_log('[PTP Webhook] Contact opted in: ' . $contact_id);
            exit;
        }
        
        // Handle HELP keyword
        if (preg_match('/\bhelp\b/i', $body)) {
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $help_message = "PTP Soccer Camps Help:\n";
            $help_message .= "• Reply STOP to unsubscribe\n";
            $help_message .= "• Visit ptpsoccercamps.com\n";
            $help_message .= "• Call us at (XXX) XXX-XXXX\n";
            $help_message .= "Msg & data rates may apply.";
            
            $sms_service->send_sms($from, $help_message);
            
            // Still log the message
            ptp_comms_log_message($contact_id, 'sms', 'inbound', $body, array('twilio_sid' => $sid));
            
            error_log('[PTP Webhook] HELP response sent to: ' . $from);
            exit;
        }
        
        // Log message
        $message_id = ptp_comms_log_message(
            $contact_id,
            'sms',
            'inbound',
            $body,
            array('twilio_sid' => $sid)
        );
        error_log('[PTP Webhook] Message logged with ID: ' . $message_id);
        
        // Update conversation
        $conv_id = PTP_Comms_Hub_Conversations::get_or_create_conversation($contact_id);
        error_log('[PTP Webhook] Conversation ID: ' . $conv_id);
        
        PTP_Comms_Hub_Conversations::update_conversation($conv_id, $body, 'inbound');
        error_log('[PTP Webhook] Conversation updated');
        
        // Trigger Teams Shared Inbox (iOS Messages-style)
        if (ptp_comms_get_setting('teams_inbox_webhook_url')) {
            error_log('[PTP Webhook] Triggering Teams Shared Inbox');
            do_action('ptp_comms_sms_received', $contact, $body, $from);
        }
        // Legacy: Notify Microsoft Teams with bidirectional chat (card style)
        elseif (ptp_comms_is_teams_configured()) {
            error_log('[PTP Webhook] Triggering legacy Teams notification');
            PTP_Comms_Hub_Teams_Integration::notify_inbound_message($contact, $body, 'sms');
        }
        
        error_log('[PTP Webhook] Webhook processing complete');
        exit;
    }
    
    /**
     * Handle Twilio SMS Status Callback
     * This tracks delivery status of outbound messages
     */
    private static function handle_sms_status_webhook() {
        global $wpdb;
        
        error_log('[PTP SMS Status] ========== STATUS CALLBACK RECEIVED ==========');
        error_log('[PTP SMS Status] POST data: ' . print_r($_POST, true));
        
        $message_sid = isset($_POST['MessageSid']) ? sanitize_text_field($_POST['MessageSid']) : '';
        $status = isset($_POST['MessageStatus']) ? sanitize_text_field($_POST['MessageStatus']) : '';
        $error_code = isset($_POST['ErrorCode']) ? sanitize_text_field($_POST['ErrorCode']) : '';
        $error_message = isset($_POST['ErrorMessage']) ? sanitize_text_field($_POST['ErrorMessage']) : '';
        
        if (empty($message_sid)) {
            error_log('[PTP SMS Status] ERROR: No MessageSid provided');
            wp_die('Invalid request', '', array('response' => 400));
        }
        
        // Update communication logs
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if (!empty($error_code) || !empty($error_message)) {
            $update_data['error_message'] = $error_code . ': ' . $error_message;
        }
        
        // Update in ptp_communication_logs
        $result = $wpdb->update(
            $wpdb->prefix . 'ptp_communication_logs',
            $update_data,
            array('twilio_sid' => $message_sid)
        );
        
        // Also update in ptp_messages
        $wpdb->update(
            $wpdb->prefix . 'ptp_messages',
            $update_data,
            array('twilio_sid' => $message_sid)
        );
        
        // Update campaign delivery count if this is part of a campaign
        if ($status === 'delivered') {
            $campaign_id = $wpdb->get_var($wpdb->prepare(
                "SELECT campaign_id FROM {$wpdb->prefix}ptp_communication_logs WHERE twilio_sid = %s",
                $message_sid
            ));
            
            if ($campaign_id) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ptp_campaigns SET delivered_count = delivered_count + 1 WHERE id = %d",
                    $campaign_id
                ));
            }
        }
        
        error_log("[PTP SMS Status] Updated status for {$message_sid} to {$status}");
        
        // Return empty 200 response
        header('Content-Type: text/plain');
        echo 'OK';
        exit;
    }
    
    /**
     * Handle Twilio Voice webhook
     */
    private static function handle_voice_webhook() {
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?><Response><Say>Thank you for calling PTP Soccer Camps. Please visit our website at ptpsoccercamps.com or text us for assistance.</Say></Response>';
        exit;
    }
    
    /**
     * Get webhook URLs for configuration reference
     */
    public static function get_webhook_urls() {
        return array(
            'sms' => home_url('/ptp-comms/sms-webhook'),
            'sms_status' => home_url('/ptp-comms/sms-status'),
            'voice' => home_url('/ptp-comms/voice-webhook'),
            'test' => home_url('/ptp-comms/test-webhook')
        );
    }
}
