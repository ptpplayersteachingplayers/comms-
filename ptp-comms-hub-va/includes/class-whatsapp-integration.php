<?php
/**
 * WhatsApp Integration for PTP Communications Hub
 * Uses Twilio WhatsApp Business API
 * Version: 1.0.0
 */

class PTP_Comms_Hub_WhatsApp_Integration {

    private $twilio_sid;
    private $twilio_token;
    private $whatsapp_from;
    private $is_sandbox;

    public function __construct() {
        $this->twilio_sid = ptp_comms_get_setting('twilio_account_sid');
        $this->twilio_token = ptp_comms_get_setting('twilio_auth_token');
        $this->whatsapp_from = ptp_comms_get_setting('whatsapp_phone_number');
        $this->is_sandbox = ptp_comms_get_setting('whatsapp_sandbox_mode', 'yes') === 'yes';

        // Register webhook handlers
        add_action('rest_api_init', array($this, 'register_webhooks'));

        // Hook into notification system
        add_action('ptp_comms_notification_created', array($this, 'maybe_send_whatsapp_notification'), 10, 2);
    }

    /**
     * Check if WhatsApp is configured
     */
    public function is_configured() {
        return !empty($this->twilio_sid) &&
               !empty($this->twilio_token) &&
               !empty($this->whatsapp_from);
    }

    /**
     * Get the WhatsApp number in proper format
     */
    private function get_whatsapp_from() {
        $number = $this->whatsapp_from;

        // For sandbox, use the sandbox number
        if ($this->is_sandbox) {
            return 'whatsapp:+14155238886'; // Twilio sandbox number
        }

        // Ensure proper format
        if (strpos($number, 'whatsapp:') !== 0) {
            $number = 'whatsapp:' . $number;
        }

        return $number;
    }

    /**
     * Format phone number for WhatsApp
     */
    private function format_whatsapp_number($phone) {
        $phone = ptp_comms_normalize_phone($phone);
        if (!$phone) {
            return false;
        }

        return 'whatsapp:' . $phone;
    }

    /**
     * Send WhatsApp message
     */
    public function send_message($to, $message, $media_url = null) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'WhatsApp not configured');
        }

        $to_formatted = $this->format_whatsapp_number($to);
        if (!$to_formatted) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Messages.json";

        $data = array(
            'From' => $this->get_whatsapp_from(),
            'To' => $to_formatted,
            'Body' => $message
        );

        // Add status callback
        $status_callback = home_url('/wp-json/ptp-comms/v1/whatsapp-status');
        $data['StatusCallback'] = $status_callback;

        // Add media URL if provided
        if ($media_url) {
            $data['MediaUrl'] = $media_url;
        }

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$this->twilio_sid}:{$this->twilio_token}")
            ),
            'body' => $data,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['error_code'])) {
            return array(
                'success' => false,
                'error' => $body['message'],
                'error_code' => $body['error_code']
            );
        }

        return array(
            'success' => true,
            'sid' => $body['sid'],
            'status' => $body['status'],
            'channel' => 'whatsapp'
        );
    }

    /**
     * Send WhatsApp template message (for business initiated conversations)
     */
    public function send_template_message($to, $template_sid, $template_variables = array()) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'WhatsApp not configured');
        }

        $to_formatted = $this->format_whatsapp_number($to);
        if (!$to_formatted) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Messages.json";

        $data = array(
            'From' => $this->get_whatsapp_from(),
            'To' => $to_formatted,
            'ContentSid' => $template_sid
        );

        // Add template variables if provided
        if (!empty($template_variables)) {
            $data['ContentVariables'] = json_encode($template_variables);
        }

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$this->twilio_sid}:{$this->twilio_token}")
            ),
            'body' => $data,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['error_code'])) {
            return array(
                'success' => false,
                'error' => $body['message'],
                'error_code' => $body['error_code']
            );
        }

        return array(
            'success' => true,
            'sid' => $body['sid'],
            'status' => $body['status'],
            'channel' => 'whatsapp'
        );
    }

    /**
     * Register REST API webhooks
     */
    public function register_webhooks() {
        // WhatsApp incoming message webhook
        register_rest_route('ptp-comms/v1', '/whatsapp-incoming', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_incoming_message'),
            'permission_callback' => '__return_true'
        ));

        // WhatsApp status callback webhook
        register_rest_route('ptp-comms/v1', '/whatsapp-status', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_status_callback'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Handle incoming WhatsApp messages
     */
    public function handle_incoming_message($request) {
        global $wpdb;

        // Verify Twilio signature if in production
        if (!$this->is_sandbox) {
            $valid = $this->verify_twilio_signature($request);
            if (!$valid) {
                return new WP_REST_Response(array('error' => 'Invalid signature'), 403);
            }
        }

        $params = $request->get_params();

        // Extract phone number (remove 'whatsapp:' prefix)
        $from = isset($params['From']) ? str_replace('whatsapp:', '', $params['From']) : '';
        $body = isset($params['Body']) ? sanitize_textarea_field($params['Body']) : '';
        $message_sid = isset($params['MessageSid']) ? sanitize_text_field($params['MessageSid']) : '';
        $num_media = isset($params['NumMedia']) ? intval($params['NumMedia']) : 0;

        if (empty($from)) {
            return new WP_REST_Response(array('error' => 'Invalid sender'), 400);
        }

        // Normalize the phone number
        $normalized_phone = ptp_comms_normalize_phone($from);

        // Find or create contact
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $normalized_phone
        ));

        if (!$contact) {
            // Create a new contact
            $wpdb->insert(
                $wpdb->prefix . 'ptp_contacts',
                array(
                    'parent_phone' => $normalized_phone,
                    'parent_first_name' => 'WhatsApp',
                    'parent_last_name' => 'Contact',
                    'source' => 'whatsapp_inbound',
                    'opted_in' => 1,
                    'created_at' => current_time('mysql')
                )
            );
            $contact_id = $wpdb->insert_id;
        } else {
            $contact_id = $contact->id;
        }

        // Find or create conversation (unified - same conversation for SMS and WhatsApp)
        // Look for any active conversation for this contact, regardless of channel
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_conversations
             WHERE contact_id = %d AND status = 'active'
             ORDER BY last_message_at DESC LIMIT 1",
            $contact_id
        ));

        if (!$conversation) {
            $wpdb->insert(
                $wpdb->prefix . 'ptp_conversations',
                array(
                    'contact_id' => $contact_id,
                    'status' => 'active',
                    'last_message' => $body,
                    'last_message_at' => current_time('mysql'),
                    'last_message_direction' => 'inbound',
                    'unread_count' => 1,
                    'channel' => 'whatsapp',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
            $conversation_id = $wpdb->insert_id;
        } else {
            $conversation_id = $conversation->id;

            // Update conversation - keep unified messaging (update channel to show last used)
            $wpdb->update(
                $wpdb->prefix . 'ptp_conversations',
                array(
                    'last_message' => $body,
                    'last_message_at' => current_time('mysql'),
                    'last_message_direction' => 'inbound',
                    'unread_count' => $conversation->unread_count + 1,
                    'channel' => 'whatsapp', // Update to show last message channel
                    'status' => 'active',
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $conversation_id)
            );
        }

        // Update contact's preferred channel and last interaction
        $wpdb->update(
            $wpdb->prefix . 'ptp_contacts',
            array(
                'preferred_contact_method' => 'whatsapp',
                'last_interaction_at' => current_time('mysql')
            ),
            array('id' => $contact_id)
        );

        // Store the message
        $media_urls = array();
        for ($i = 0; $i < $num_media; $i++) {
            if (isset($params["MediaUrl{$i}"])) {
                $media_urls[] = $params["MediaUrl{$i}"];
            }
        }

        $wpdb->insert(
            $wpdb->prefix . 'ptp_messages',
            array(
                'conversation_id' => $conversation_id,
                'message_type' => 'whatsapp',
                'message_body' => $body,
                'direction' => 'inbound',
                'status' => 'received',
                'twilio_sid' => $message_sid,
                'media_urls' => !empty($media_urls) ? json_encode($media_urls) : null,
                'created_at' => current_time('mysql')
            )
        );

        // Create notification for assigned VA or admins
        if (class_exists('PTP_Comms_Hub_Notifications')) {
            PTP_Comms_Hub_Notifications::notify_sms_reply($contact_id, $body);
        }

        // Log communication
        if (function_exists('ptp_comms_log_communication')) {
            ptp_comms_log_communication(array(
                'contact_id' => $contact_id,
                'type' => 'whatsapp',
                'direction' => 'inbound',
                'content' => $body,
                'status' => 'received'
            ));
        }

        // Notify shared inbox subscribers (VAs) via WhatsApp
        $contact_name = $contact ? trim($contact->parent_first_name . ' ' . $contact->parent_last_name) : 'Unknown';
        if (empty($contact_name) || $contact_name === 'WhatsApp Contact') {
            $contact_name = ptp_comms_format_phone($normalized_phone);
        }

        $alert_message = "📱 *New WhatsApp Message*\n";
        $alert_message .= "From: {$contact_name}\n";
        $alert_message .= "Phone: " . ptp_comms_format_phone($normalized_phone) . "\n\n";
        $alert_message .= "\"" . substr($body, 0, 200) . (strlen($body) > 200 ? '...' : '') . "\"\n\n";
        $alert_message .= "Reply via: " . admin_url('admin.php?page=ptp-comms-inbox&action=view&conversation=' . $conversation_id);

        $this->notify_shared_inbox($alert_message, array(
            'contact_id' => $contact_id,
            'phone' => $normalized_phone,
            'name' => $contact_name
        ));

        // Fire action for other integrations (Teams, etc.)
        do_action('ptp_comms_whatsapp_received', $contact_id, $body, $normalized_phone);

        return new WP_REST_Response(array('success' => true), 200);
    }

    /**
     * Handle status callback from Twilio
     */
    public function handle_status_callback($request) {
        global $wpdb;

        $params = $request->get_params();

        $message_sid = isset($params['MessageSid']) ? sanitize_text_field($params['MessageSid']) : '';
        $status = isset($params['MessageStatus']) ? sanitize_text_field($params['MessageStatus']) : '';
        $error_code = isset($params['ErrorCode']) ? sanitize_text_field($params['ErrorCode']) : null;

        if (!empty($message_sid) && !empty($status)) {
            $wpdb->update(
                $wpdb->prefix . 'ptp_messages',
                array(
                    'status' => $status,
                    'error_code' => $error_code
                ),
                array('twilio_sid' => $message_sid)
            );
        }

        return new WP_REST_Response(array('success' => true), 200);
    }

    /**
     * Verify Twilio signature
     */
    private function verify_twilio_signature($request) {
        $signature = $request->get_header('X-Twilio-Signature');
        if (!$signature) {
            return false;
        }

        $url = home_url($request->get_route());
        $params = $request->get_params();

        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $this->twilio_token, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Maybe send WhatsApp notification based on user preferences
     */
    public function maybe_send_whatsapp_notification($notification_id, $data) {
        if (!$this->is_configured()) {
            return;
        }

        $user_id = isset($data['user_id']) ? $data['user_id'] : 0;
        if (!$user_id) {
            return;
        }

        // Check user preference for WhatsApp notifications
        $whatsapp_enabled = get_user_meta($user_id, 'ptp_whatsapp_notifications', true);
        $whatsapp_phone = get_user_meta($user_id, 'ptp_whatsapp_phone', true);

        if ($whatsapp_enabled !== 'yes' || empty($whatsapp_phone)) {
            return;
        }

        // Check notification types that should trigger WhatsApp
        $whatsapp_types = get_user_meta($user_id, 'ptp_whatsapp_notification_types', true);
        if (empty($whatsapp_types)) {
            $whatsapp_types = array('new_message', 'reminder_due', 'contact_replied', 'voicemail');
        }

        if (!in_array($data['notification_type'], $whatsapp_types)) {
            return;
        }

        // Build the message
        $message = "🔔 *PTP Comms Alert*\n\n";
        $message .= "*" . $data['title'] . "*\n";
        if (!empty($data['message'])) {
            $message .= $data['message'] . "\n";
        }
        if (!empty($data['action_url'])) {
            $message .= "\n" . $data['action_url'];
        }

        // Send the WhatsApp notification
        $this->send_message($whatsapp_phone, $message);
    }

    /**
     * Send WhatsApp notification to shared inbox team
     */
    public function notify_shared_inbox($message, $contact_info = array()) {
        if (!$this->is_configured()) {
            return false;
        }

        // Get users subscribed to shared inbox WhatsApp notifications
        $users = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'ptp_whatsapp_shared_inbox',
                    'value' => 'yes'
                )
            )
        ));

        $results = array();

        foreach ($users as $user) {
            $phone = get_user_meta($user->ID, 'ptp_whatsapp_phone', true);
            if (!empty($phone)) {
                $result = $this->send_message($phone, $message);
                $results[$user->ID] = $result;
            }
        }

        return $results;
    }

    /**
     * Get WhatsApp message history for a contact
     */
    public function get_contact_messages($contact_id, $limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.* FROM {$wpdb->prefix}ptp_messages m
             JOIN {$wpdb->prefix}ptp_conversations c ON m.conversation_id = c.id
             WHERE c.contact_id = %d AND m.message_type = 'whatsapp'
             ORDER BY m.created_at DESC
             LIMIT %d",
            $contact_id, $limit
        ));
    }

    /**
     * Check if contact has WhatsApp conversation
     */
    public function contact_has_whatsapp($contact_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_messages m
             JOIN {$wpdb->prefix}ptp_conversations c ON m.conversation_id = c.id
             WHERE c.contact_id = %d AND m.message_type = 'whatsapp'",
            $contact_id
        ));
    }

    /**
     * Get WhatsApp conversation stats
     */
    public function get_stats() {
        global $wpdb;

        return array(
            'total_messages' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_messages WHERE message_type = 'whatsapp'"
            ),
            'inbound_messages' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_messages WHERE message_type = 'whatsapp' AND direction = 'inbound'"
            ),
            'outbound_messages' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_messages WHERE message_type = 'whatsapp' AND direction = 'outbound'"
            ),
            'unique_contacts' => $wpdb->get_var(
                "SELECT COUNT(DISTINCT c.contact_id) FROM {$wpdb->prefix}ptp_conversations c
                 WHERE c.channel = 'whatsapp'"
            ),
            'today_messages' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_messages
                 WHERE message_type = 'whatsapp' AND DATE(created_at) = CURDATE()"
            )
        );
    }
}

// Initialize WhatsApp integration
add_action('init', function() {
    global $ptp_whatsapp;
    $ptp_whatsapp = new PTP_Comms_Hub_WhatsApp_Integration();
});

/**
 * Helper function to get WhatsApp integration instance
 */
function ptp_comms_whatsapp() {
    global $ptp_whatsapp;
    return $ptp_whatsapp;
}

/**
 * Helper function to send WhatsApp message
 */
function ptp_comms_send_whatsapp($to, $message, $media_url = null) {
    $whatsapp = ptp_comms_whatsapp();
    if ($whatsapp && $whatsapp->is_configured()) {
        return $whatsapp->send_message($to, $message, $media_url);
    }
    return array('success' => false, 'error' => 'WhatsApp not configured');
}
// Note: ptp_comms_is_whatsapp_configured() is defined in helpers.php
