<?php
/**
 * Microsoft Teams integration for bidirectional communication
 * Supports: Bot Framework for true chat conversations
 */
class PTP_Comms_Hub_Teams_Integration {
    
    private static $access_token = null;
    private static $token_expiry = null;
    
    /**
     * Get Teams Bot App ID (from Azure Bot registration)
     */
    private static function get_bot_app_id() {
        return ptp_comms_get_setting('teams_bot_app_id');
    }
    
    /**
     * Get Teams Bot App Password (from Azure Bot registration)
     */
    private static function get_bot_app_password() {
        return ptp_comms_get_setting('teams_bot_app_password');
    }
    
    /**
     * Get Teams Service URL (usually from the activity context)
     */
    private static function get_service_url($default = 'https://smba.trafficmanager.net/amer/') {
        return ptp_comms_get_setting('teams_service_url', $default);
    }
    
    /**
     * Get Teams Tenant ID
     */
    private static function get_tenant_id() {
        return ptp_comms_get_setting('teams_tenant_id');
    }
    
    /**
     * Get webhook URL for simple notifications (fallback)
     */
    private static function get_webhook_url() {
        return ptp_comms_get_setting('teams_webhook_url');
    }
    
    /**
     * Get Bot Framework access token
     */
    private static function get_access_token($force_refresh = false) {
        // Check if we have a valid cached token
        if (!$force_refresh && self::$access_token && self::$token_expiry > time()) {
            return self::$access_token;
        }
        
        $app_id = self::get_bot_app_id();
        $app_password = self::get_bot_app_password();
        
        if (empty($app_id) || empty($app_password)) {
            error_log('[PTP Teams] Missing bot credentials');
            return false;
        }
        
        $response = wp_remote_post('https://login.microsoftonline.com/botframework.com/oauth2/v2.0/token', array(
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $app_id,
                'client_secret' => $app_password,
                'scope' => 'https://api.botframework.com/.default'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('[PTP Teams] Token request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            self::$access_token = $body['access_token'];
            self::$token_expiry = time() + ($body['expires_in'] - 300); // Expire 5 min early
            
            error_log('[PTP Teams] Successfully obtained access token');
            return self::$access_token;
        }
        
        error_log('[PTP Teams] Token response error: ' . print_r($body, true));
        return false;
    }
    
    /**
     * Store conversation reference for later proactive messaging
     */
    private static function store_conversation_reference($contact_id, $conversation_id, $service_url, $tenant_id = null) {
        global $wpdb;
        
        $wpdb->replace(
            $wpdb->prefix . 'ptp_teams_conversations',
            array(
                'contact_id' => $contact_id,
                'conversation_id' => $conversation_id,
                'service_url' => $service_url,
                'tenant_id' => $tenant_id,
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get stored conversation reference
     */
    private static function get_conversation_reference($contact_id) {
        global $wpdb;
        
        $ref = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_teams_conversations WHERE contact_id = %d",
            $contact_id
        ));
        
        return $ref;
    }
    
    /**
     * Create a new proactive conversation (1-on-1 chat) in Teams
     */
    public static function create_proactive_conversation($contact, $initial_message) {
        $token = self::get_access_token();
        if (!$token) {
            error_log('[PTP Teams] Cannot create conversation - no access token');
            return false;
        }
        
        $bot_app_id = self::get_bot_app_id();
        $service_url = self::get_service_url();
        $tenant_id = self::get_tenant_id();
        
        // Create conversation parameters
        $conversation_params = array(
            'bot' => array(
                'id' => '28:' . $bot_app_id,
                'name' => 'PTP Comms Hub'
            ),
            'isGroup' => false,
            'tenantId' => $tenant_id,
            'activity' => array(
                'type' => 'message',
                'text' => $initial_message,
                'channelData' => array(
                    'notification' => array(
                        'alert' => true
                    )
                )
            )
        );
        
        // Create the conversation
        $response = wp_remote_post($service_url . 'v3/conversations', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($conversation_params),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('[PTP Teams] Create conversation failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['id'])) {
            // Store conversation reference for future messages
            self::store_conversation_reference(
                $contact->id,
                $body['id'],
                $service_url,
                $tenant_id
            );
            
            error_log('[PTP Teams] Created new conversation: ' . $body['id']);
            return $body['id'];
        }
        
        error_log('[PTP Teams] Create conversation response: ' . print_r($body, true));
        return false;
    }
    
    /**
     * Send a message to an existing Teams conversation
     */
    public static function send_to_conversation($conversation_id, $message, $service_url = null) {
        $token = self::get_access_token();
        if (!$token) {
            error_log('[PTP Teams] Cannot send message - no access token');
            return false;
        }
        
        if (!$service_url) {
            $service_url = self::get_service_url();
        }
        
        $bot_app_id = self::get_bot_app_id();
        
        if (empty($bot_app_id)) {
            error_log('[PTP Teams] Cannot send message - no bot app ID configured');
            return false;
        }
        
        $activity = array(
            'type' => 'message',
            'from' => array(
                'id' => '28:' . $bot_app_id,
                'name' => 'PTP Comms Hub'
            ),
            'text' => $message,
            'channelData' => array(
                'notification' => array(
                    'alert' => true
                )
            )
        );
        
        $url = $service_url . 'v3/conversations/' . $conversation_id . '/activities';
        
        error_log('[PTP Teams] Sending message to: ' . $url);
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($activity),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('[PTP Teams] Send message failed: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            error_log('[PTP Teams] Message sent successfully');
            return true;
        }
        
        $body = wp_remote_retrieve_body($response);
        error_log('[PTP Teams] Send message failed with status ' . $status_code . ': ' . $body);
        return false;
    }
    
    /**
     * Send adaptive card to an existing conversation
     */
    public static function send_card_to_conversation($conversation_id, $card, $service_url = null) {
        $token = self::get_access_token();
        if (!$token) {
            return false;
        }
        
        if (!$service_url) {
            $service_url = self::get_service_url();
        }
        
        $bot_app_id = self::get_bot_app_id();
        
        $activity = array(
            'type' => 'message',
            'from' => array(
                'id' => '28:' . $bot_app_id,
                'name' => 'PTP Comms Hub'
            ),
            'attachments' => array(
                array(
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => $card
                )
            )
        );
        
        $url = $service_url . 'v3/conversations/' . $conversation_id . '/activities';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($activity),
            'timeout' => 15
        ));
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 300;
    }
    
    /**
     * Send message to Teams (outgoing)
     */
    public static function send_message($message, $card = null, $channel = null) {
        $webhook_url = self::get_webhook_url();
        if (empty($webhook_url)) {
            return false;
        }
        
        $payload = array(
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'text' => $message
        );
        
        if ($card) {
            $payload = array_merge($payload, $card);
        }
        
        $response = wp_remote_post($webhook_url, array(
            'body' => json_encode($payload),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15
        ));
        
        return !is_wp_error($response);
    }
    
    /**
     * Send adaptive card to Teams
     */
    public static function send_adaptive_card($title, $sections, $actions = null) {
        $webhook_url = self::get_webhook_url();
        if (empty($webhook_url)) {
            return false;
        }
        
        $card = array(
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => $title,
            'themeColor' => 'FCB900', // PTP Yellow
            'title' => $title,
            'sections' => $sections
        );
        
        if ($actions) {
            $card['potentialAction'] = $actions;
        }
        
        $response = wp_remote_post($webhook_url, array(
            'body' => json_encode($card),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15
        ));
        
        return !is_wp_error($response);
    }
    
    /**
     * Notify Teams of inbound SMS/Voice message
     * Creates a new 1:1 conversation or sends to existing one
     */
    public static function notify_inbound_message($contact, $message, $type = 'sms') {
        $icon = $type === 'sms' ? '💬' : '📞';
        
        // Format the message for Teams
        $formatted_message = sprintf(
            "**%s New %s from %s %s**\n\n" .
            "📱 Phone: %s\n" .
            "📧 Email: %s\n\n" .
            "**Message:**\n%s\n\n" .
            "_Reply to this chat to send an SMS back to %s_",
            $icon,
            strtoupper($type),
            $contact->parent_first_name,
            $contact->parent_last_name,
            ptp_comms_format_phone($contact->parent_phone),
            $contact->parent_email ?: 'N/A',
            $message,
            $contact->parent_first_name
        );
        
        // Check if we have an existing conversation for this contact
        $conversation_ref = self::get_conversation_reference($contact->id);
        
        if ($conversation_ref && !empty($conversation_ref->conversation_id)) {
            // Send to existing conversation
            $success = self::send_to_conversation(
                $conversation_ref->conversation_id,
                $formatted_message,
                $conversation_ref->service_url
            );
            
            if ($success) {
                error_log('[PTP Teams] Sent message to existing conversation: ' . $conversation_ref->conversation_id);
                return true;
            }
            
            error_log('[PTP Teams] Failed to send to existing conversation, creating new one');
        }
        
        // Create new proactive conversation
        $conversation_id = self::create_proactive_conversation($contact, $formatted_message);
        
        if ($conversation_id) {
            error_log('[PTP Teams] Created new Teams conversation for contact ' . $contact->id);
            return true;
        }
        
        error_log('[PTP Teams] Failed to create Teams conversation, falling back to webhook');
        
        // Fallback to webhook notification if bot framework fails
        return self::send_webhook_notification($contact, $message, $type);
    }
    
    /**
     * Fallback: Send notification via webhook (one-way)
     */
    private static function send_webhook_notification($contact, $message, $type) {
        $webhook_url = self::get_webhook_url();
        if (empty($webhook_url)) {
            return false;
        }
        
        $icon = $type === 'sms' ? '💬' : '📞';
        
        $sections = array(
            array(
                'activityTitle' => "{$icon} New " . strtoupper($type) . " Message",
                'activitySubtitle' => "From: {$contact->parent_first_name} {$contact->parent_last_name}",
                'activityImage' => 'https://ptpsoccercamps.com/wp-content/uploads/ptp-icon.png',
                'facts' => array(
                    array('name' => 'Parent Name', 'value' => "{$contact->parent_first_name} {$contact->parent_last_name}"),
                    array('name' => 'Phone', 'value' => ptp_comms_format_phone($contact->parent_phone)),
                    array('name' => 'Email', 'value' => $contact->parent_email ?: 'N/A'),
                    array('name' => 'Message Type', 'value' => strtoupper($type))
                ),
                'text' => "**Message:**\n\n" . $message
            )
        );
        
        $actions = array(
            array(
                '@type' => 'OpenUri',
                'name' => 'Reply via SMS',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-inbox&contact_id=' . $contact->id . '&action=reply'))
                )
            ),
            array(
                '@type' => 'OpenUri',
                'name' => 'View Contact',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact->id))
                )
            )
        );
        
        return self::send_adaptive_card("New Message from {$contact->parent_first_name}", $sections, $actions);
    }
    
    /**
     * Handle incoming message from Teams bot
     * Called when someone replies in a Teams conversation
     */
    public static function handle_teams_message($activity) {
        global $wpdb;
        
        error_log('[PTP Teams] Received message from Teams: ' . print_r($activity, true));
        
        // Extract message details
        $conversation_id = $activity['conversation']['id'] ?? null;
        $message_text = $activity['text'] ?? '';
        $from_name = $activity['from']['name'] ?? 'Unknown';
        
        if (empty($conversation_id) || empty($message_text)) {
            error_log('[PTP Teams] Missing conversation ID or message text');
            return false;
        }
        
        // Find the contact associated with this conversation
        $conversation_ref = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_teams_conversations WHERE conversation_id = %s",
            $conversation_id
        ));
        
        if (!$conversation_ref) {
            error_log('[PTP Teams] No contact found for conversation: ' . $conversation_id);
            
            // Send error message back to Teams
            self::send_to_conversation(
                $conversation_id,
                "⚠️ Error: This conversation is not linked to a contact. Please start a new conversation from an incoming SMS.",
                $activity['serviceUrl'] ?? null
            );
            
            return false;
        }
        
        // Get the contact
        $contact = PTP_Comms_Hub_Contacts::get_contact($conversation_ref->contact_id);
        
        if (!$contact) {
            error_log('[PTP Teams] Contact not found: ' . $conversation_ref->contact_id);
            return false;
        }
        
        // Send the message as SMS
        $sms_service = new PTP_Comms_Hub_SMS_Service();
        $result = $sms_service->send_sms($contact->parent_phone, $message_text);
        
        if ($result['success']) {
            // Log the outbound message
            $wpdb->insert(
                $wpdb->prefix . 'ptp_messages',
                array(
                    'contact_id' => $contact->id,
                    'message_type' => 'sms',
                    'direction' => 'outbound',
                    'message_body' => $message_text,
                    'status' => 'sent',
                    'sent_via' => 'teams',
                    'twilio_sid' => $result['sid'],
                    'sent_by_name' => $from_name,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            // Update conversation
            $conv_id = PTP_Comms_Hub_Conversations::get_or_create_conversation($contact->id);
            PTP_Comms_Hub_Conversations::update_conversation($conv_id, $message_text, 'outbound');
            
            // Send confirmation back to Teams
            self::send_to_conversation(
                $conversation_id,
                "✅ SMS sent to " . $contact->parent_first_name . " " . $contact->parent_last_name . " (" . ptp_comms_format_phone($contact->parent_phone) . ")",
                $activity['serviceUrl'] ?? null
            );
            
            error_log('[PTP Teams] SMS sent successfully via Teams');
            return true;
        } else {
            // Send error back to Teams
            self::send_to_conversation(
                $conversation_id,
                "❌ Failed to send SMS: " . ($result['error'] ?? 'Unknown error'),
                $activity['serviceUrl'] ?? null
            );
            
            error_log('[PTP Teams] Failed to send SMS: ' . ($result['error'] ?? 'Unknown error'));
            return false;
        }
    }
    
    /**
     * Notify Teams of new WooCommerce order
     */
    public static function notify_new_order($order, $contact_id) {
        global $wpdb;
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) return false;
        
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = '- ' . $item->get_name();
        }
        $items_text = implode("\n", $items);
        
        $sections = array(
            array(
                'activityTitle' => '🎉 New Camp Registration',
                'activitySubtitle' => "Order #{$order->get_id()}",
                'activityImage' => 'https://ptpsoccercamps.com/wp-content/uploads/ptp-icon.png',
                'facts' => array(
                    array('name' => 'Parent', 'value' => "{$contact->parent_first_name} {$contact->parent_last_name}"),
                    array('name' => 'Order ID', 'value' => "#{$order->get_id()}"),
                    array('name' => 'Total', 'value' => '$' . number_format($order->get_total(), 2)),
                    array('name' => 'Phone', 'value' => ptp_comms_format_phone($contact->parent_phone)),
                    array('name' => 'Email', 'value' => $contact->parent_email)
                ),
                'text' => "**Camp Registration:**\n\n" . $items_text
            )
        );
        
        $actions = array(
            array(
                '@type' => 'OpenUri',
                'name' => 'View Order',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'))
                )
            ),
            array(
                '@type' => 'OpenUri',
                'name' => 'View Contact',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact_id))
                )
            ),
            array(
                '@type' => 'OpenUri',
                'name' => 'Send Welcome SMS',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-inbox&contact_id=' . $contact_id . '&template=welcome'))
                )
            )
        );
        
        return self::send_adaptive_card("New Registration - Order #{$order->get_id()}", $sections, $actions);
    }
    
    /**
     * Notify Teams when campaign is complete
     */
    public static function notify_campaign_complete($campaign, $sent, $failed) {
        $success_rate = $sent > 0 ? round(($sent / ($sent + $failed)) * 100, 1) : 0;
        
        $status_color = $success_rate >= 90 ? '28a745' : ($success_rate >= 70 ? 'ffc107' : 'dc3545');
        
        $sections = array(
            array(
                'activityTitle' => '📊 Campaign Complete',
                'activitySubtitle' => $campaign->name,
                'activityImage' => 'https://ptpsoccercamps.com/wp-content/uploads/ptp-icon.png',
                'facts' => array(
                    array('name' => 'Campaign Name', 'value' => $campaign->name),
                    array('name' => 'Type', 'value' => strtoupper($campaign->message_type)),
                    array('name' => 'Messages Sent', 'value' => $sent),
                    array('name' => 'Failed', 'value' => $failed),
                    array('name' => 'Success Rate', 'value' => "{$success_rate}%")
                )
            )
        );
        
        $actions = array(
            array(
                '@type' => 'OpenUri',
                'name' => 'View Campaign Details',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-campaigns&action=view&id=' . $campaign->id))
                )
            ),
            array(
                '@type' => 'OpenUri',
                'name' => 'View Analytics',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-dashboard'))
                )
            )
        );
        
        $card = array(
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => "Campaign '{$campaign->name}' completed",
            'themeColor' => $status_color,
            'title' => "Campaign Complete: {$campaign->name}",
            'sections' => $sections,
            'potentialAction' => $actions
        );
        
        $webhook_url = self::get_webhook_url();
        if (empty($webhook_url)) {
            return false;
        }
        
        $response = wp_remote_post($webhook_url, array(
            'body' => json_encode($card),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15
        ));
        
        return !is_wp_error($response);
    }
    
    /**
     * Send error notification to Teams
     */
    public static function notify_error($error_message, $context = array()) {
        $context_facts = array();
        if (!empty($context)) {
            foreach ($context as $key => $value) {
                $context_facts[] = array('name' => $key, 'value' => $value);
            }
        }
        
        $sections = array(
            array(
                'activityTitle' => '⚠️ Error Alert',
                'activitySubtitle' => 'PTP Comms Hub',
                'facts' => $context_facts,
                'text' => "**Error Message:**\n\n```\n" . $error_message . "\n```"
            )
        );
        
        $actions = array(
            array(
                '@type' => 'OpenUri',
                'name' => 'View Logs',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-logs'))
                )
            )
        );
        
        $card = array(
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => 'Error in PTP Comms Hub',
            'themeColor' => 'dc3545',
            'title' => 'Error Alert - PTP Comms Hub',
            'sections' => $sections,
            'potentialAction' => $actions
        );
        
        $webhook_url = self::get_webhook_url();
        if (empty($webhook_url)) {
            return false;
        }
        
        $response = wp_remote_post($webhook_url, array(
            'body' => json_encode($card),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15
        ));
        
        return !is_wp_error($response);
    }
    
    /**
     * Send stats to Teams
     */
    public static function send_stats($channel = null) {
        global $wpdb;
        
        $stats = array(
            'total_contacts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts"),
            'opted_in' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1"),
            'messages_sent_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_messages WHERE created_at >= %s AND direction = 'outbound'",
                date('Y-m-d 00:00:00')
            )),
            'messages_received_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_messages WHERE created_at >= %s AND direction = 'inbound'",
                date('Y-m-d 00:00:00')
            ))
        );
        
        $sections = array(
            array(
                'activityTitle' => '📈 Communication Statistics',
                'activitySubtitle' => date('F j, Y'),
                'facts' => array(
                    array('name' => 'Total Contacts', 'value' => number_format($stats['total_contacts'])),
                    array('name' => 'Opted In', 'value' => number_format($stats['opted_in'])),
                    array('name' => 'Messages Sent Today', 'value' => number_format($stats['messages_sent_today'])),
                    array('name' => 'Messages Received Today', 'value' => number_format($stats['messages_received_today']))
                )
            )
        );
        
        $actions = array(
            array(
                '@type' => 'OpenUri',
                'name' => 'View Full Dashboard',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-dashboard'))
                )
            )
        );
        
        return self::send_adaptive_card('Daily Communication Stats', $sections, $actions);
    }
    
    /**
     * Search and display contact in Teams
     */
    public static function search_contact($search_term, $channel = null) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts 
            WHERE parent_phone LIKE %s OR parent_email LIKE %s OR parent_first_name LIKE %s OR parent_last_name LIKE %s
            LIMIT 1",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%'
        ));
        
        if (!$contact) {
            return self::send_message("No contact found matching: {$search_term}");
        }
        
        $sections = array(
            array(
                'activityTitle' => 'Contact Found',
                'activitySubtitle' => "{$contact->parent_first_name} {$contact->parent_last_name}",
                'facts' => array(
                    array('name' => 'Name', 'value' => "{$contact->parent_first_name} {$contact->parent_last_name}"),
                    array('name' => 'Phone', 'value' => ptp_comms_format_phone($contact->parent_phone)),
                    array('name' => 'Email', 'value' => $contact->parent_email ?: 'N/A'),
                    array('name' => 'Status', 'value' => $contact->opted_in ? '✅ Opted in to SMS' : '❌ Opted out of SMS')
                )
            )
        );
        
        $actions = array(
            array(
                '@type' => 'OpenUri',
                'name' => 'View Contact',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact->id))
                )
            ),
            array(
                '@type' => 'OpenUri',
                'name' => 'Send Message',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-inbox&contact_id=' . $contact->id))
                )
            )
        );
        
        return self::send_adaptive_card('Contact Information', $sections, $actions);
    }
    
    /**
     * Send SMS with actionable reply button in Teams
     * Includes Action.Input for quick reply functionality
     */
    public static function notify_inbound_message_actionable($contact, $message, $type = 'sms') {
        $icon = $type === 'sms' ? '💬' : '📞';
        
        $sections = array(
            array(
                'activityTitle' => "{$icon} New " . strtoupper($type) . " Message",
                'activitySubtitle' => "From: {$contact->parent_first_name} {$contact->parent_last_name}",
                'activityImage' => 'https://ptpsoccercamps.com/wp-content/uploads/ptp-icon.png',
                'facts' => array(
                    array('name' => 'Parent Name', 'value' => "{$contact->parent_first_name} {$contact->parent_last_name}"),
                    array('name' => 'Phone', 'value' => ptp_comms_format_phone($contact->parent_phone)),
                    array('name' => 'Email', 'value' => $contact->parent_email ?: 'N/A'),
                    array('name' => 'Message Type', 'value' => strtoupper($type))
                ),
                'text' => "**Message:**\n\n" . $message
            )
        );
        
        // Create actionable message with input field and submit button
        $actions = array(
            array(
                '@type' => 'ActionCard',
                'name' => 'Reply via SMS',
                'inputs' => array(
                    array(
                        '@type' => 'TextInput',
                        'id' => 'replyMessage',
                        'isMultiline' => true,
                        'title' => 'Your reply to ' . $contact->parent_first_name,
                        'maxLength' => 1600
                    )
                ),
                'actions' => array(
                    array(
                        '@type' => 'HttpPOST',
                        'name' => 'Send SMS Reply',
                        'target' => get_rest_url(null, 'ptp-comms/v1/teams-reply'),
                        'body' => json_encode(array(
                            'contact_id' => $contact->id,
                            'contact_phone' => $contact->parent_phone,
                            'reply_message' => '{{replyMessage.value}}'
                        )),
                        'headers' => array(
                            array('name' => 'Content-Type', 'value' => 'application/json')
                        )
                    )
                )
            ),
            array(
                '@type' => 'ActionCard',
                'name' => 'Quick Replies',
                'actions' => array(
                    array(
                        '@type' => 'HttpPOST',
                        'name' => 'Thanks!',
                        'target' => get_rest_url(null, 'ptp-comms/v1/teams-quick-reply'),
                        'body' => json_encode(array(
                            'contact_id' => $contact->id,
                            'message' => 'Thanks for reaching out! A team member will be in touch soon.'
                        ))
                    ),
                    array(
                        '@type' => 'HttpPOST',
                        'name' => 'Will Call You',
                        'target' => get_rest_url(null, 'ptp-comms/v1/teams-quick-reply'),
                        'body' => json_encode(array(
                            'contact_id' => $contact->id,
                            'message' => 'Thanks! We\'ll give you a call shortly to discuss.'
                        ))
                    ),
                    array(
                        '@type' => 'HttpPOST',
                        'name' => 'Check Website',
                        'target' => get_rest_url(null, 'ptp-comms/v1/teams-quick-reply'),
                        'body' => json_encode(array(
                            'contact_id' => $contact->id,
                            'message' => 'For more information, please visit www.ptpsoccercamps.com'
                        ))
                    )
                )
            ),
            array(
                '@type' => 'OpenUri',
                'name' => 'View Full Conversation',
                'targets' => array(
                    array('os' => 'default', 'uri' => admin_url('admin.php?page=ptp-comms-inbox&contact_id=' . $contact->id))
                )
            ),
            array(
                '@type' => 'OpenUri',
                'name' => 'Call Contact',
                'targets' => array(
                    array('os' => 'default', 'uri' => 'tel:' . $contact->parent_phone)
                )
            )
        );
        
        return self::send_adaptive_card("New Message from {$contact->parent_first_name}", $sections, $actions);
    }
    
    /**
     * Handle Teams SMS reply via webhook
     */
    public static function handle_teams_reply($contact_id, $contact_phone, $reply_message) {
        // Send SMS via Twilio
        $sms_service = new PTP_Comms_Hub_SMS_Service();
        $result = $sms_service->send_sms($contact_phone, $reply_message);
        
        if ($result['success']) {
            global $wpdb;
            
            // Log the message
            $wpdb->insert(
                $wpdb->prefix . 'ptp_messages',
                array(
                    'contact_id' => $contact_id,
                    'message_type' => 'sms',
                    'direction' => 'outbound',
                    'message_body' => $reply_message,
                    'status' => 'sent',
                    'sent_via' => 'teams',
                    'twilio_sid' => $result['sid'],
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            return array(
                'success' => true,
                'message' => 'SMS sent successfully from Teams!'
            );
        }
        
        return array(
            'success' => false,
            'error' => $result['error']
        );
    }
    
    /**
     * Handle Teams quick reply
     */
    public static function handle_teams_quick_reply($contact_id, $message) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            return array('success' => false, 'error' => 'Contact not found');
        }
        
        $sms_service = new PTP_Comms_Hub_SMS_Service();
        $result = $sms_service->send_sms($contact->parent_phone, $message);
        
        if ($result['success']) {
            $wpdb->insert(
                $wpdb->prefix . 'ptp_messages',
                array(
                    'contact_id' => $contact_id,
                    'message_type' => 'sms',
                    'direction' => 'outbound',
                    'message_body' => $message,
                    'status' => 'sent',
                    'sent_via' => 'teams_quick_reply',
                    'twilio_sid' => $result['sid'],
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            return array('success' => true, 'message' => 'Quick reply sent!');
        }
        
        return array('success' => false, 'error' => $result['error']);
    }
}
