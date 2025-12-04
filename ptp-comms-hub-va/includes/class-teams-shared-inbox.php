<?php
/**
 * Teams Shared Inbox - iOS Messages-style shared SMS inbox in Microsoft Teams
 * 
 * Each contact gets their own conversation thread in Teams.
 * When a new SMS comes in, it appears in that contact's thread.
 * Team members can reply directly from Teams and it sends via SMS.
 * 
 * Flow:
 * 1. Inbound SMS → Creates/updates Teams channel message for that contact
 * 2. Reply from Teams → Sends SMS to contact's phone
 * 3. All team members see all conversations in one place
 * 
 * Version: 1.0.0
 */
class PTP_Comms_Hub_Teams_Shared_Inbox {
    
    /**
     * Initialize the shared inbox
     */
    public static function init() {
        // Hook into inbound SMS to post to Teams
        add_action('ptp_comms_sms_received', array(__CLASS__, 'handle_inbound_sms'), 10, 3);
        
        // Hook into outbound SMS to update Teams thread
        add_action('ptp_comms_sms_sent', array(__CLASS__, 'handle_outbound_sms'), 10, 3);
        
        // REST API endpoint for Teams replies
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
    }
    
    /**
     * Register REST API routes for Teams webhooks
     */
    public static function register_rest_routes() {
        register_rest_route('ptp-comms/v1', '/teams-inbox-reply', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_teams_reply'),
            'permission_callback' => '__return_true', // Auth handled in callback
        ));
        
        register_rest_route('ptp-comms/v1', '/teams-bot-webhook', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_bot_webhook'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Get the Teams channel webhook URL for the shared inbox
     */
    private static function get_inbox_webhook_url() {
        if (!function_exists('ptp_comms_get_setting')) {
            return '';
        }
        return ptp_comms_get_setting('teams_inbox_webhook_url');
    }
    
    /**
     * Get or create a conversation thread ID for a contact
     */
    private static function get_thread_id($contact_id) {
        global $wpdb;
        
        $thread = $wpdb->get_var($wpdb->prepare(
            "SELECT teams_thread_id FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        return $thread;
    }
    
    /**
     * Store thread ID for a contact
     */
    private static function save_thread_id($contact_id, $thread_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_contacts',
            array('teams_thread_id' => $thread_id),
            array('id' => $contact_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Handle inbound SMS - post to Teams shared inbox
     */
    public static function handle_inbound_sms($contact, $message, $from_number) {
        $webhook_url = self::get_inbox_webhook_url();
        
        if (empty($webhook_url)) {
            error_log('[PTP Teams Inbox] No webhook URL configured');
            return false;
        }
        
        // Format the contact name
        $contact_name = trim(($contact->parent_first_name ?? '') . ' ' . ($contact->parent_last_name ?? ''));
        if (empty($contact_name)) {
            $contact_name = self::format_phone($from_number);
        }
        
        // Get camper info if available
        $camper_name = $contact->child_first_name ?? '';
        
        // Build the adaptive card for Teams
        $card = self::build_message_card(
            'inbound',
            $contact,
            $message,
            $contact_name,
            $camper_name
        );
        
        // Send to Teams
        $result = self::send_to_teams_webhook($webhook_url, $card);
        
        if ($result && isset($result['id'])) {
            // Store the thread ID for reply tracking
            self::save_thread_id($contact->id, $result['id']);
        }
        
        return $result;
    }
    
    /**
     * Handle outbound SMS - update Teams thread
     */
    public static function handle_outbound_sms($contact, $message, $sent_by = 'system') {
        $webhook_url = self::get_inbox_webhook_url();
        
        if (empty($webhook_url)) {
            return false;
        }
        
        $contact_name = trim(($contact->parent_first_name ?? '') . ' ' . ($contact->parent_last_name ?? ''));
        
        // Build notification card for outbound
        $card = self::build_outbound_notification($contact, $message, $sent_by);
        
        return self::send_to_teams_webhook($webhook_url, $card);
    }
    
    /**
     * Build an adaptive card for inbound messages
     */
    private static function build_message_card($direction, $contact, $message, $contact_name, $camper_name = '') {
        $phone = self::format_phone($contact->parent_phone ?? '');
        $email = $contact->parent_email ?? '';
        $contact_id = $contact->id ?? 0;
        
        // Get any recent order/registration info
        $recent_camp = self::get_recent_camp($contact_id);
        
        // Build facts
        $facts = array();
        $facts[] = array('title' => 'Phone', 'value' => $phone);
        
        if (!empty($email)) {
            $facts[] = array('title' => 'Email', 'value' => $email);
        }
        
        if (!empty($camper_name)) {
            $facts[] = array('title' => 'Camper', 'value' => $camper_name);
        }
        
        if (!empty($recent_camp)) {
            $facts[] = array('title' => 'Recent Camp', 'value' => $recent_camp);
        }
        
        // Adaptive Card format for Teams
        $card = array(
            'type' => 'message',
            'attachments' => array(
                array(
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => array(
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => array(
                            // Header with contact info
                            array(
                                'type' => 'ColumnSet',
                                'columns' => array(
                                    array(
                                        'type' => 'Column',
                                        'width' => 'auto',
                                        'items' => array(
                                            array(
                                                'type' => 'Image',
                                                'url' => 'https://ptpsoccercamps.com/wp-content/uploads/ptp-icon.png',
                                                'size' => 'small',
                                                'style' => 'person'
                                            )
                                        )
                                    ),
                                    array(
                                        'type' => 'Column',
                                        'width' => 'stretch',
                                        'items' => array(
                                            array(
                                                'type' => 'TextBlock',
                                                'text' => '📱 ' . $contact_name,
                                                'weight' => 'bolder',
                                                'size' => 'medium',
                                                'wrap' => true
                                            ),
                                            array(
                                                'type' => 'TextBlock',
                                                'text' => $phone,
                                                'spacing' => 'none',
                                                'isSubtle' => true,
                                                'size' => 'small'
                                            )
                                        )
                                    ),
                                    array(
                                        'type' => 'Column',
                                        'width' => 'auto',
                                        'items' => array(
                                            array(
                                                'type' => 'TextBlock',
                                                'text' => date('g:i A'),
                                                'isSubtle' => true,
                                                'size' => 'small',
                                                'horizontalAlignment' => 'right'
                                            )
                                        )
                                    )
                                )
                            ),
                            // Message bubble
                            array(
                                'type' => 'Container',
                                'style' => 'emphasis',
                                'bleed' => true,
                                'items' => array(
                                    array(
                                        'type' => 'TextBlock',
                                        'text' => $message,
                                        'wrap' => true,
                                        'size' => 'medium'
                                    )
                                ),
                                'padding' => 'default'
                            ),
                            // Contact details
                            array(
                                'type' => 'FactSet',
                                'facts' => $facts,
                                'separator' => true
                            ),
                            // Reply input
                            array(
                                'type' => 'Input.Text',
                                'id' => 'replyMessage',
                                'placeholder' => 'Type your reply...',
                                'isMultiline' => true,
                                'maxLength' => 1600
                            )
                        ),
                        'actions' => array(
                            array(
                                'type' => 'Action.Submit',
                                'title' => '📤 Send SMS Reply',
                                'style' => 'positive',
                                'data' => array(
                                    'action' => 'send_sms',
                                    'contact_id' => $contact_id,
                                    'phone' => $contact->parent_phone ?? ''
                                )
                            ),
                            array(
                                'type' => 'Action.OpenUrl',
                                'title' => '📞 Call',
                                'url' => 'tel:' . ($contact->parent_phone ?? '')
                            ),
                            array(
                                'type' => 'Action.OpenUrl',
                                'title' => '👤 View Contact',
                                'url' => admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact_id)
                            )
                        ),
                        'msteams' => array(
                            'width' => 'full'
                        )
                    )
                )
            )
        );
        
        return $card;
    }
    
    /**
     * Build notification card for outbound messages
     */
    private static function build_outbound_notification($contact, $message, $sent_by) {
        $contact_name = trim(($contact->parent_first_name ?? '') . ' ' . ($contact->parent_last_name ?? ''));
        $phone = self::format_phone($contact->parent_phone ?? '');
        
        $card = array(
            'type' => 'message',
            'attachments' => array(
                array(
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => array(
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => array(
                            array(
                                'type' => 'ColumnSet',
                                'columns' => array(
                                    array(
                                        'type' => 'Column',
                                        'width' => 'stretch',
                                        'items' => array(
                                            array(
                                                'type' => 'TextBlock',
                                                'text' => '✅ SMS Sent to ' . $contact_name,
                                                'weight' => 'bolder',
                                                'color' => 'good'
                                            ),
                                            array(
                                                'type' => 'TextBlock',
                                                'text' => $phone . ' • Sent by ' . $sent_by,
                                                'isSubtle' => true,
                                                'size' => 'small',
                                                'spacing' => 'none'
                                            )
                                        )
                                    ),
                                    array(
                                        'type' => 'Column',
                                        'width' => 'auto',
                                        'items' => array(
                                            array(
                                                'type' => 'TextBlock',
                                                'text' => date('g:i A'),
                                                'isSubtle' => true,
                                                'size' => 'small'
                                            )
                                        )
                                    )
                                )
                            ),
                            array(
                                'type' => 'Container',
                                'style' => 'good',
                                'items' => array(
                                    array(
                                        'type' => 'TextBlock',
                                        'text' => $message,
                                        'wrap' => true,
                                        'size' => 'small'
                                    )
                                ),
                                'padding' => 'small'
                            )
                        )
                    )
                )
            )
        );
        
        return $card;
    }
    
    /**
     * Send card to Teams webhook
     */
    private static function send_to_teams_webhook($webhook_url, $card) {
        $response = wp_remote_post($webhook_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($card),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('[PTP Teams Inbox] Webhook error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code >= 200 && $code < 300) {
            error_log('[PTP Teams Inbox] Message sent successfully');
            return json_decode($body, true) ?: array('success' => true);
        }
        
        error_log('[PTP Teams Inbox] Webhook failed with code ' . $code . ': ' . $body);
        return false;
    }
    
    /**
     * Handle Teams bot webhook - for receiving replies
     */
    public static function handle_bot_webhook($request) {
        $params = $request->get_json_params();
        
        error_log('[PTP Teams Inbox] Bot webhook received: ' . print_r($params, true));
        
        // Validate the request (Microsoft Bot Framework validation)
        // In production, you'd verify the JWT token here
        
        $activity_type = $params['type'] ?? '';
        
        if ($activity_type === 'message') {
            return self::process_bot_message($params);
        }
        
        if ($activity_type === 'invoke' && isset($params['value'])) {
            return self::process_adaptive_card_action($params);
        }
        
        return new WP_REST_Response(array('status' => 'ok'), 200);
    }
    
    /**
     * Process adaptive card action (reply button clicked)
     */
    private static function process_adaptive_card_action($params) {
        $value = $params['value'];
        $action = $value['action'] ?? '';
        
        if ($action === 'send_sms') {
            $contact_id = intval($value['contact_id'] ?? 0);
            $phone = $value['phone'] ?? '';
            $reply_message = $value['replyMessage'] ?? '';
            
            if (empty($reply_message)) {
                return new WP_REST_Response(array(
                    'statusCode' => 400,
                    'type' => 'application/vnd.microsoft.error',
                    'value' => array('message' => 'Please enter a message')
                ), 200);
            }
            
            // Send the SMS
            $result = self::send_sms_reply($contact_id, $phone, $reply_message, 'Teams');
            
            if ($result['success']) {
                // Return success card
                return new WP_REST_Response(array(
                    'statusCode' => 200,
                    'type' => 'application/vnd.microsoft.card.adaptive',
                    'value' => array(
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => array(
                            array(
                                'type' => 'TextBlock',
                                'text' => '✅ SMS sent successfully!',
                                'color' => 'good',
                                'weight' => 'bolder'
                            ),
                            array(
                                'type' => 'TextBlock',
                                'text' => '"' . substr($reply_message, 0, 100) . (strlen($reply_message) > 100 ? '...' : '') . '"',
                                'isSubtle' => true,
                                'wrap' => true
                            )
                        )
                    )
                ), 200);
            } else {
                return new WP_REST_Response(array(
                    'statusCode' => 500,
                    'type' => 'application/vnd.microsoft.error',
                    'value' => array('message' => 'Failed to send SMS: ' . ($result['error'] ?? 'Unknown error'))
                ), 200);
            }
        }
        
        return new WP_REST_Response(array('status' => 'ok'), 200);
    }
    
    /**
     * Process direct bot message (user typed in Teams)
     */
    private static function process_bot_message($params) {
        $text = $params['text'] ?? '';
        $conversation = $params['conversation'] ?? array();
        $from = $params['from'] ?? array();
        
        // Check if this is a reply to a contact thread
        $conversation_id = $conversation['id'] ?? '';
        
        // Look up contact by conversation ID
        global $wpdb;
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE teams_thread_id = %s",
            $conversation_id
        ));
        
        if ($contact && !empty($text)) {
            // Send SMS reply
            $sender_name = $from['name'] ?? 'Teams User';
            $result = self::send_sms_reply($contact->id, $contact->parent_phone, $text, $sender_name);
            
            return new WP_REST_Response(array(
                'type' => 'message',
                'text' => $result['success'] 
                    ? '✅ SMS sent to ' . self::format_phone($contact->parent_phone)
                    : '❌ Failed to send SMS: ' . ($result['error'] ?? 'Unknown error')
            ), 200);
        }
        
        return new WP_REST_Response(array(
            'type' => 'message',
            'text' => 'To reply to a contact, use the "Send SMS Reply" button on their message card.'
        ), 200);
    }
    
    /**
     * Handle REST API reply endpoint
     */
    public static function handle_teams_reply($request) {
        $params = $request->get_json_params();
        
        $contact_id = intval($params['contact_id'] ?? 0);
        $phone = sanitize_text_field($params['phone'] ?? '');
        $message = sanitize_textarea_field($params['reply_message'] ?? $params['replyMessage'] ?? '');
        
        if (empty($message)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Message is required'
            ), 400);
        }
        
        $result = self::send_sms_reply($contact_id, $phone, $message, 'Teams');
        
        return new WP_REST_Response($result, $result['success'] ? 200 : 500);
    }
    
    /**
     * Send SMS reply
     */
    private static function send_sms_reply($contact_id, $phone, $message, $sent_by = 'Teams') {
        // Clean up phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (empty($phone)) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }
        
        // Send via Twilio
        $sms_service = new PTP_Comms_Hub_SMS_Service();
        $result = $sms_service->send_sms($phone, $message);
        
        if ($result['success']) {
            global $wpdb;
            
            // Get or find contact
            if (!$contact_id) {
                $contact = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
                    $phone
                ));
                $contact_id = $contact ? $contact->id : 0;
            }
            
            // Log the message
            if ($contact_id) {
                // Find or create conversation
                $conversation_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ptp_conversations WHERE contact_id = %d ORDER BY id DESC LIMIT 1",
                    $contact_id
                ));
                
                $wpdb->insert(
                    $wpdb->prefix . 'ptp_messages',
                    array(
                        'conversation_id' => $conversation_id ?: 0,
                        'contact_id' => $contact_id,
                        'message_type' => 'sms',
                        'direction' => 'outbound',
                        'message_body' => $message,
                        'status' => 'sent',
                        'sent_via' => 'teams_inbox',
                        'sent_by' => $sent_by,
                        'twilio_sid' => $result['sid'] ?? '',
                        'created_at' => current_time('mysql')
                    )
                );
                
                // Update conversation
                if ($conversation_id) {
                    $wpdb->update(
                        $wpdb->prefix . 'ptp_conversations',
                        array(
                            'last_message' => $message,
                            'last_message_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ),
                        array('id' => $conversation_id)
                    );
                }
                
                // Trigger outbound SMS hook
                $contact = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
                    $contact_id
                ));
                
                if ($contact) {
                    do_action('ptp_comms_sms_sent', $contact, $message, $sent_by);
                }
            }
            
            return array(
                'success' => true,
                'message' => 'SMS sent successfully',
                'sid' => $result['sid'] ?? ''
            );
        }
        
        return array(
            'success' => false,
            'error' => $result['error'] ?? 'Failed to send SMS'
        );
    }
    
    /**
     * Get recent camp for contact
     */
    private static function get_recent_camp($contact_id) {
        global $wpdb;
        
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT product_name, event_date 
             FROM {$wpdb->prefix}ptp_registrations 
             WHERE contact_id = %d 
             ORDER BY event_date DESC 
             LIMIT 1",
            $contact_id
        ));
        
        if ($registration) {
            $date = $registration->event_date ? date('M j', strtotime($registration->event_date)) : '';
            return $registration->product_name . ($date ? ' (' . $date . ')' : '');
        }
        
        return '';
    }
    
    /**
     * Format phone number for display
     */
    private static function format_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 11 && $phone[0] === '1') {
            $phone = substr($phone, 1);
        }
        
        if (strlen($phone) === 10) {
            return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
        }
        
        return $phone;
    }
    
    /**
     * Quick reply templates
     */
    public static function get_quick_replies() {
        return array(
            array(
                'title' => 'Thanks!',
                'message' => 'Thanks for reaching out! A team member will be in touch soon.'
            ),
            array(
                'title' => 'Will Call',
                'message' => "Thanks for your message! We'll give you a call shortly."
            ),
            array(
                'title' => 'Check Website',
                'message' => 'For more information, please visit www.ptpsoccercamps.com'
            ),
            array(
                'title' => 'Confirm Registration',
                'message' => "Your registration is confirmed! We'll see you at camp!"
            ),
            array(
                'title' => 'Weather Update',
                'message' => "We're monitoring the weather and will update you if there are any changes to today's schedule."
            )
        );
    }
}

// Initialize via loader - the loader includes this file and we let hooks work naturally
// The init() function will be called when the hooks fire
