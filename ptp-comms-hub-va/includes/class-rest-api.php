<?php
/**
 * REST API endpoints
 */
class PTP_Comms_Hub_REST_API {
    
    public static function register_routes() {
        register_rest_route('ptp-comms/v1', '/send-sms', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'send_sms'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/send-message', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'send_message'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/messages/(?P<conversation_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_messages'),
            'permission_callback' => function($request) {
                // Allow if user is logged in and has admin capabilities
                // This bypasses the cookie nonce check
                return is_user_logged_in() && current_user_can('manage_options');
            },
            'args' => array(
                'conversation_id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
                'since_id' => array(
                    'required' => false,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        register_rest_route('ptp-comms/v1', '/contacts', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_contacts'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/twiml', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_twiml'),
            'permission_callback' => '__return_true'
        ));
        
        // Teams integration endpoints
        register_rest_route('ptp-comms/v1', '/teams-reply', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_teams_reply'),
            'permission_callback' => '__return_true' // Teams webhooks don't support auth
        ));
        
        register_rest_route('ptp-comms/v1', '/teams-quick-reply', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_teams_quick_reply'),
            'permission_callback' => '__return_true'
        ));
        
        // Teams Bot Framework webhook (receives messages from Teams)
        register_rest_route('ptp-comms/v1', '/teams-bot', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_teams_bot_message'),
            'permission_callback' => '__return_true'
        ));
        
        // Voice/IVR endpoints
        register_rest_route('ptp-comms/v1', '/ivr-menu', array(
            'methods' => array('GET', 'POST'),
            'callback' => array(__CLASS__, 'get_ivr_menu'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('ptp-comms/v1', '/ivr-response', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_ivr_response'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('ptp-comms/v1', '/call-status', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_call_status'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('ptp-comms/v1', '/voicemail-complete', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_voicemail_complete'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('ptp-comms/v1', '/voicemail-transcription', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_voicemail_transcription'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('ptp-comms/v1', '/dial-status', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_dial_status'),
            'permission_callback' => '__return_true'
        ));
        
        // Recording status callback endpoint
        register_rest_route('ptp-comms/v1', '/recording-status', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_recording_status'),
            'permission_callback' => '__return_true'
        ));
        
        // Incoming call handler
        register_rest_route('ptp-comms/v1', '/incoming-call', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_incoming_call'),
            'permission_callback' => '__return_true'
        ));
        
        // Ring-first fallback (when staff don't answer)
        register_rest_route('ptp-comms/v1', '/ring-first-fallback', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_ring_first_fallback'),
            'permission_callback' => '__return_true'
        ));
        
        // Direct voicemail endpoint
        register_rest_route('ptp-comms/v1', '/voicemail', array(
            'methods' => array('GET', 'POST'),
            'callback' => array(__CLASS__, 'get_voicemail_twiml'),
            'permission_callback' => '__return_true'
        ));
        
        // Zoom Phone webhook
        register_rest_route('ptp-comms/v1', '/zoom-webhook', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_zoom_webhook'),
            'permission_callback' => '__return_true'
        ));
        
        // Call notes endpoint
        register_rest_route('ptp-comms/v1', '/call-notes', array(
            'methods' => array('GET', 'POST'),
            'callback' => array(__CLASS__, 'handle_call_notes'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        // ==========================================
        // VA RELATIONSHIP MANAGEMENT ENDPOINTS v4.0
        // ==========================================
        
        // Contact Notes
        register_rest_route('ptp-comms/v1', '/contacts/(?P<contact_id>\d+)/notes', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_contact_notes'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/contacts/(?P<contact_id>\d+)/notes', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'add_contact_note'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/notes/(?P<note_id>\d+)', array(
            'methods' => array('PUT', 'PATCH'),
            'callback' => array(__CLASS__, 'update_contact_note'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/notes/(?P<note_id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array(__CLASS__, 'delete_contact_note'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/notes/(?P<note_id>\d+)/toggle-pin', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'toggle_note_pin'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        // Reminders
        register_rest_route('ptp-comms/v1', '/reminders', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_reminders'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/reminders', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_reminder'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/reminders/(?P<reminder_id>\d+)/complete', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'complete_reminder'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/reminders/(?P<reminder_id>\d+)/snooze', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'snooze_reminder'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        // Notifications
        register_rest_route('ptp-comms/v1', '/notifications', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_notifications'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/notifications/(?P<notification_id>\d+)/read', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'mark_notification_read'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/notifications/mark-all-read', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'mark_all_notifications_read'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/notifications/count', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_notification_count'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        // Activity Timeline
        register_rest_route('ptp-comms/v1', '/contacts/(?P<contact_id>\d+)/timeline', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_contact_timeline'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        // Segments
        register_rest_route('ptp-comms/v1', '/segments/(?P<segment_id>\d+)/contacts', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_segment_contacts'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
        
        register_rest_route('ptp-comms/v1', '/segments/(?P<segment_id>\d+)/count', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_segment_count'),
            'permission_callback' => array(__CLASS__, 'check_permission')
        ));
    }
    
    public static function check_permission() {
        return current_user_can('manage_options');
    }
    
    public static function send_sms($request) {
        $to = $request->get_param('to');
        $message = $request->get_param('message');
        
        if (empty($to) || empty($message)) {
            return new WP_Error('missing_params', 'Phone and message required', array('status' => 400));
        }
        
        $sms_service = new PTP_Comms_Hub_SMS_Service();
        $result = $sms_service->send_sms($to, $message);
        
        if ($result['success']) {
            return rest_ensure_response(array('success' => true, 'sid' => $result['sid']));
        }
        
        return new WP_Error('send_failed', $result['error'], array('status' => 500));
    }
    
    public static function send_message($request) {
        global $wpdb;
        
        $conversation_id = intval($request->get_param('conversation_id'));
        $contact_id = intval($request->get_param('contact_id'));
        $message = sanitize_textarea_field($request->get_param('message'));
        $message_type = sanitize_text_field($request->get_param('message_type'));
        
        if (empty($conversation_id) || empty($contact_id) || empty($message) || empty($message_type)) {
            return new WP_Error('missing_params', 'All parameters required', array('status' => 400));
        }
        
        // Get contact to retrieve phone number
        $contact = PTP_Comms_Hub_Contacts::get_contact($contact_id);
        if (!$contact || empty($contact->parent_phone)) {
            return new WP_Error('invalid_contact', 'Contact not found or missing phone number', array('status' => 404));
        }
        
        // Send via Twilio
        if ($message_type === 'sms') {
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $result = $sms_service->send_sms($contact->parent_phone, $message);
        } else {
            $voice_service = new PTP_Comms_Hub_Voice_Service();
            $result = $voice_service->make_call($contact->parent_phone, $message);
        }
        
        if (!$result['success']) {
            return new WP_Error('send_failed', $result['error'], array('status' => 500));
        }
        
        // Log the message in the messages table
        $wpdb->insert(
            $wpdb->prefix . 'ptp_messages',
            array(
                'conversation_id' => $conversation_id,
                'message_type' => $message_type,
                'message_body' => $message,
                'direction' => 'outbound',
                'status' => isset($result['status']) ? $result['status'] : 'sent',
                'twilio_sid' => isset($result['sid']) ? $result['sid'] : null,
                'created_at' => current_time('mysql')
            )
        );
        
        $message_id = $wpdb->insert_id;
        
        // Update conversation
        PTP_Comms_Hub_Conversations::update_conversation($conversation_id, $message, 'outbound');
        
        // Get the inserted message for response
        $new_message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_messages WHERE id = %d",
            $message_id
        ));
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => $new_message,
            'sid' => $result['sid']
        ));
    }
    
    public static function get_messages($request) {
        global $wpdb;
        
        $conversation_id = intval($request->get_param('conversation_id'));
        $since_id = $request->get_param('since_id') ? intval($request->get_param('since_id')) : 0;
        
        error_log('[PTP REST API] get_messages called - Conversation: ' . $conversation_id . ', Since ID: ' . $since_id);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_messages 
            WHERE conversation_id = %d AND id > %d
            ORDER BY created_at ASC",
            $conversation_id,
            $since_id
        );
        
        error_log('[PTP REST API] Query: ' . $query);
        
        $messages = $wpdb->get_results($query);
        
        error_log('[PTP REST API] Messages found: ' . count($messages));
        if (count($messages) > 0) {
            error_log('[PTP REST API] First message: ' . print_r($messages[0], true));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'messages' => $messages,
            'count' => count($messages)
        ));
    }
    
    public static function get_contacts($request) {
        $contacts = PTP_Comms_Hub_Contacts::get_all_contacts(array('limit' => 100));
        return rest_ensure_response($contacts);
    }
    
    public static function get_twiml($request) {
        $message = $request->get_param('message');
        if (empty($message)) {
            $message = 'Thank you for calling PTP Soccer Camps.';
        }
        
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        echo $voice_service->generate_twiml($message);
        exit;
    }
    
    /**
     * Handle Teams SMS reply
     */
    public static function handle_teams_reply($request) {
        $data = $request->get_json_params();
        
        if (empty($data['contact_id']) || empty($data['reply_message'])) {
            return new WP_Error('missing_params', 'Contact ID and message required', array('status' => 400));
        }
        
        $contact_id = intval($data['contact_id']);
        $contact_phone = sanitize_text_field($data['contact_phone']);
        $reply_message = sanitize_textarea_field($data['reply_message']);
        
        $result = PTP_Comms_Hub_Teams_Integration::handle_teams_reply($contact_id, $contact_phone, $reply_message);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        }
        
        return new WP_Error('send_failed', $result['error'], array('status' => 500));
    }
    
    /**
     * Handle Teams quick reply
     */
    public static function handle_teams_quick_reply($request) {
        $data = $request->get_json_params();
        
        if (empty($data['contact_id']) || empty($data['message'])) {
            return new WP_Error('missing_params', 'Contact ID and message required', array('status' => 400));
        }
        
        $contact_id = intval($data['contact_id']);
        $message = sanitize_textarea_field($data['message']);
        
        $result = PTP_Comms_Hub_Teams_Integration::handle_teams_quick_reply($contact_id, $message);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        }
        
        return new WP_Error('send_failed', $result['error'], array('status' => 500));
    }
    
    /**
     * Handle incoming message from Teams bot
     */
    public static function handle_teams_bot_message($request) {
        $activity = $request->get_json_params();
        
        error_log('[PTP REST API] Teams bot message received: ' . print_r($activity, true));
        
        // Verify it's a message activity
        if (empty($activity) || !isset($activity['type'])) {
            return new WP_Error('invalid_activity', 'Invalid activity', array('status' => 400));
        }
        
        // Handle different activity types
        switch ($activity['type']) {
            case 'message':
                // Only process if there's actual text (ignore bot messages and system messages)
                if (!empty($activity['text']) && 
                    (!isset($activity['from']['id']) || 
                     strpos($activity['from']['id'], '28:') !== 0)) { // Not from a bot
                    
                    PTP_Comms_Hub_Teams_Integration::handle_teams_message($activity);
                    return rest_ensure_response(array('success' => true));
                }
                break;
                
            case 'conversationUpdate':
                // Handle bot being added to conversation
                error_log('[PTP REST API] Conversation update received');
                return rest_ensure_response(array('success' => true));
                
            case 'invoke':
                // Handle adaptive card actions
                error_log('[PTP REST API] Invoke activity received');
                return rest_ensure_response(array('success' => true));
        }
        
        return rest_ensure_response(array('success' => true, 'message' => 'Activity processed'));
    }
    
    /**
     * Get IVR menu TwiML
     */
    public static function get_ivr_menu($request) {
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        echo $voice_service->generate_smart_ivr_menu();
        exit;
    }
    
    /**
     * Handle IVR digit response
     */
    public static function handle_ivr_response($request) {
        $digit = $request->get_param('Digits');
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        echo $voice_service->handle_ivr_response($digit);
        exit;
    }
    
    /**
     * Handle call status updates from Twilio
     */
    public static function handle_call_status($request) {
        $call_sid = $request->get_param('CallSid');
        $call_status = $request->get_param('CallStatus');
        $call_duration = $request->get_param('CallDuration');
        $from = $request->get_param('From');
        $to = $request->get_param('To');
        $direction = $request->get_param('Direction');
        
        error_log("[PTP Voice] Call Status Update - SID: {$call_sid}, Status: {$call_status}, Duration: {$call_duration}");
        
        global $wpdb;
        
        // Try to find existing log for this call
        $existing_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_communication_logs 
             WHERE message_type = 'voice' AND meta_data LIKE %s
             ORDER BY created_at DESC LIMIT 1",
            '%' . $wpdb->esc_like($call_sid) . '%'
        ));
        
        if ($existing_log) {
            // Update existing log
            $meta = json_decode($existing_log->meta_data, true) ?: array();
            $meta['call_sid'] = $call_sid;
            $meta['duration'] = intval($call_duration);
            $meta['status'] = $call_status;
            
            $wpdb->update(
                $wpdb->prefix . 'ptp_communication_logs',
                array(
                    'status' => $call_status,
                    'meta_data' => json_encode($meta)
                ),
                array('id' => $existing_log->id)
            );
        } else {
            // Create new log entry for inbound calls
            $phone = ($direction === 'inbound') ? $from : $to;
            $contact = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
                $phone
            ));
            
            $meta = array(
                'call_sid' => $call_sid,
                'duration' => intval($call_duration),
                'from' => $from,
                'to' => $to
            );
            
            $wpdb->insert(
                $wpdb->prefix . 'ptp_communication_logs',
                array(
                    'contact_id' => $contact ? $contact->id : 0,
                    'message_type' => 'voice',
                    'direction' => ($direction === 'inbound') ? 'inbound' : 'outbound',
                    'message_content' => '',
                    'status' => $call_status,
                    'meta_data' => json_encode($meta),
                    'created_at' => current_time('mysql')
                )
            );
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Handle recording status callback from Twilio
     */
    public static function handle_recording_status($request) {
        $call_sid = $request->get_param('CallSid');
        $recording_sid = $request->get_param('RecordingSid');
        $recording_url = $request->get_param('RecordingUrl');
        $recording_status = $request->get_param('RecordingStatus');
        $recording_duration = $request->get_param('RecordingDuration');
        
        error_log("[PTP Voice] Recording Status - CallSID: {$call_sid}, RecSID: {$recording_sid}, Status: {$recording_status}, URL: {$recording_url}");
        
        if ($recording_status === 'completed' && !empty($recording_url)) {
            global $wpdb;
            
            // Find and update the call log
            $log = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_communication_logs 
                 WHERE message_type = 'voice' AND meta_data LIKE %s
                 ORDER BY created_at DESC LIMIT 1",
                '%' . $wpdb->esc_like($call_sid) . '%'
            ));
            
            if ($log) {
                $meta = json_decode($log->meta_data, true) ?: array();
                $meta['recording_sid'] = $recording_sid;
                $meta['recording_url'] = $recording_url . '.mp3'; // Add .mp3 for direct playback
                $meta['recording_duration'] = $recording_duration;
                
                $wpdb->update(
                    $wpdb->prefix . 'ptp_communication_logs',
                    array('meta_data' => json_encode($meta)),
                    array('id' => $log->id)
                );
                
                error_log("[PTP Voice] Recording URL saved to log ID: {$log->id}");
            }
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Handle incoming calls - main entry point for inbound calls
     */
    public static function handle_incoming_call($request) {
        $from = $request->get_param('From');
        $to = $request->get_param('To');
        $call_sid = $request->get_param('CallSid');
        
        error_log("[PTP Voice] Incoming call from: {$from} to: {$to}");
        
        global $wpdb;
        
        // Find or create contact
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $from
        ));
        
        if (!$contact) {
            $wpdb->insert(
                $wpdb->prefix . 'ptp_contacts',
                array(
                    'parent_phone' => $from,
                    'source' => 'inbound_call',
                    'created_at' => current_time('mysql')
                )
            );
            $contact_id = $wpdb->insert_id;
        } else {
            $contact_id = $contact->id;
        }
        
        // Log the incoming call
        $meta = array(
            'call_sid' => $call_sid,
            'from' => $from,
            'to' => $to
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ptp_communication_logs',
            array(
                'contact_id' => $contact_id,
                'message_type' => 'voice',
                'direction' => 'inbound',
                'message_content' => '',
                'status' => 'ringing',
                'meta_data' => json_encode($meta),
                'created_at' => current_time('mysql')
            )
        );
        
        // Return the IVR menu (checks Ring Staff First setting)
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        echo $voice_service->generate_ivr_menu();
        exit;
    }
    
    /**
     * Handle voicemail completion
     */
    public static function handle_voicemail_complete($request) {
        $recording_url = $request->get_param('RecordingUrl');
        $recording_sid = $request->get_param('RecordingSid');
        $from = $request->get_param('From');
        
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        echo $voice_service->handle_voicemail_complete($recording_url, $recording_sid, $from);
        exit;
    }
    
    /**
     * Handle voicemail transcription
     */
    public static function handle_voicemail_transcription($request) {
        $transcription_text = $request->get_param('TranscriptionText');
        $recording_sid = $request->get_param('RecordingSid');
        
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        $voice_service->handle_voicemail_transcription($transcription_text, $recording_sid);
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Handle dial status callback
     */
    public static function handle_dial_status($request) {
        $dial_call_status = $request->get_param('DialCallStatus');
        
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        echo $voice_service->handle_dial_status($dial_call_status);
        exit;
    }
    
    /**
     * Handle ring-first fallback (when staff phones don't answer)
     */
    public static function handle_ring_first_fallback($request) {
        $dial_call_status = $request->get_param('DialCallStatus');
        $call_sid = $request->get_param('CallSid');
        $from = $request->get_param('From');
        
        error_log("[PTP Voice] Ring-first fallback - Status: {$dial_call_status}, From: {$from}");
        
        // Send missed call notification if no answer
        if ($dial_call_status !== 'completed') {
            self::send_missed_call_notification($from);
        }
        
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        echo $voice_service->handle_ring_first_fallback($dial_call_status);
        exit;
    }
    
    /**
     * Get voicemail TwiML directly
     */
    public static function get_voicemail_twiml($request) {
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        echo $voice_service->generate_voicemail_twiml();
        exit;
    }
    
    /**
     * Send missed call notification
     */
    private static function send_missed_call_notification($from) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $from
        ));
        
        $contact_name = $contact ? trim("{$contact->parent_first_name} {$contact->parent_last_name}") : 'Unknown';
        if (empty($contact_name) || $contact_name === 'Unknown') {
            $contact_name = function_exists('ptp_comms_format_phone') ? ptp_comms_format_phone($from) : $from;
        }
        
        // Notify via Teams
        if (class_exists('PTP_Comms_Hub_Teams_Integration')) {
            PTP_Comms_Hub_Teams_Integration::send_message(
                "📵 **Missed Call** from {$contact_name}\n\n" .
                "📞 " . (function_exists('ptp_comms_format_phone') ? ptp_comms_format_phone($from) : $from) . "\n\n" .
                "⏰ " . current_time('g:i A')
            );
        }
        
        // SMS notification to staff
        $notify_numbers = ptp_comms_get_setting('missed_call_notify_sms', '');
        if (!empty($notify_numbers) && class_exists('PTP_Comms_Hub_SMS_Service')) {
            $numbers = array_map('trim', explode(',', $notify_numbers));
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            
            foreach ($numbers as $number) {
                if (!empty($number)) {
                    $sms_service->send_sms(
                        $number,
                        "PTP Missed Call: {$contact_name} - " . (function_exists('ptp_comms_format_phone') ? ptp_comms_format_phone($from) : $from) . " at " . current_time('g:i A')
                    );
                }
            }
        }
    }
    
    /**
     * Handle Zoom Phone webhook
     */
    public static function handle_zoom_webhook($request) {
        // Verify webhook (Zoom sends verification challenge)
        $body = $request->get_body();
        $payload = json_decode($body, true);
        
        // Handle URL validation challenge
        if (isset($payload['event']) && $payload['event'] === 'endpoint.url_validation') {
            $plain_token = $payload['payload']['plainToken'] ?? '';
            $secret = ptp_comms_get_setting('zoom_webhook_secret', '');
            
            if ($plain_token && $secret) {
                $hash = hash_hmac('sha256', $plain_token, $secret);
                return rest_ensure_response(array(
                    'plainToken' => $plain_token,
                    'encryptedToken' => $hash
                ));
            }
        }
        
        // Process actual webhook events
        if (class_exists('PTP_Comms_Hub_Zoom_Phone')) {
            $zoom = new PTP_Comms_Hub_Zoom_Phone();
            $zoom->handle_webhook($payload);
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Handle call notes - add/get notes for calls
     */
    public static function handle_call_notes($request) {
        global $wpdb;
        
        $call_id = $request->get_param('call_id');
        
        if ($request->get_method() === 'POST') {
            // Add/update note
            $note = sanitize_textarea_field($request->get_param('note'));
            
            $log = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_communication_logs WHERE id = %d",
                $call_id
            ));
            
            if ($log) {
                $meta = json_decode($log->meta_data, true) ?: array();
                $meta['notes'] = $note;
                $meta['notes_updated_at'] = current_time('mysql');
                $meta['notes_updated_by'] = get_current_user_id();
                
                $wpdb->update(
                    $wpdb->prefix . 'ptp_communication_logs',
                    array('meta_data' => json_encode($meta)),
                    array('id' => $call_id)
                );
                
                return rest_ensure_response(array('success' => true));
            }
            
            return new WP_Error('not_found', 'Call not found', array('status' => 404));
        }
        
        // GET - retrieve note
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_data FROM {$wpdb->prefix}ptp_communication_logs WHERE id = %d",
            $call_id
        ));
        
        if ($log) {
            $meta = json_decode($log->meta_data, true) ?: array();
            return rest_ensure_response(array(
                'note' => $meta['notes'] ?? '',
                'updated_at' => $meta['notes_updated_at'] ?? null
            ));
        }
        
        return new WP_Error('not_found', 'Call not found', array('status' => 404));
    }
    
    // ==========================================
    // VA RELATIONSHIP MANAGEMENT CALLBACKS v4.0
    // ==========================================
    
    /**
     * Get notes for a contact
     */
    public static function get_contact_notes($request) {
        $contact_id = intval($request->get_param('contact_id'));
        $limit = intval($request->get_param('limit') ?: 50);
        $type = $request->get_param('type');
        
        if (!class_exists('PTP_Comms_Hub_Contact_Notes')) {
            return new WP_Error('not_available', 'Notes feature not available', array('status' => 501));
        }
        
        $args = array('limit' => $limit);
        if ($type) {
            $args['type'] = $type;
        }
        
        $notes = PTP_Comms_Hub_Contact_Notes::get_notes($contact_id, $args);
        
        return rest_ensure_response(array(
            'success' => true,
            'notes' => $notes,
            'total' => count($notes)
        ));
    }
    
    /**
     * Add note to contact
     */
    public static function add_contact_note($request) {
        $contact_id = intval($request->get_param('contact_id'));
        
        if (!class_exists('PTP_Comms_Hub_Contact_Notes')) {
            return new WP_Error('not_available', 'Notes feature not available', array('status' => 501));
        }
        
        $data = array(
            'note_type' => sanitize_text_field($request->get_param('note_type') ?: 'general'),
            'title' => sanitize_text_field($request->get_param('title')),
            'content' => sanitize_textarea_field($request->get_param('content')),
            'is_pinned' => intval($request->get_param('is_pinned')),
            'sentiment' => sanitize_text_field($request->get_param('sentiment') ?: 'neutral'),
            'follow_up_date' => sanitize_text_field($request->get_param('follow_up_date'))
        );
        
        $note_id = PTP_Comms_Hub_Contact_Notes::add_note($contact_id, $data);
        
        if ($note_id) {
            return rest_ensure_response(array(
                'success' => true,
                'note_id' => $note_id,
                'message' => 'Note added successfully'
            ));
        }
        
        return new WP_Error('create_failed', 'Failed to create note', array('status' => 500));
    }
    
    /**
     * Update a note
     */
    public static function update_contact_note($request) {
        $note_id = intval($request->get_param('note_id'));
        
        if (!class_exists('PTP_Comms_Hub_Contact_Notes')) {
            return new WP_Error('not_available', 'Notes feature not available', array('status' => 501));
        }
        
        $data = array();
        
        $fields = array('title', 'content', 'note_type', 'sentiment', 'is_pinned', 'follow_up_date', 'follow_up_completed');
        foreach ($fields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $field === 'content' ? sanitize_textarea_field($value) : sanitize_text_field($value);
            }
        }
        
        $result = PTP_Comms_Hub_Contact_Notes::update_note($note_id, $data);
        
        return rest_ensure_response(array(
            'success' => $result !== false,
            'message' => $result !== false ? 'Note updated' : 'Update failed'
        ));
    }
    
    /**
     * Delete a note
     */
    public static function delete_contact_note($request) {
        $note_id = intval($request->get_param('note_id'));
        
        if (!class_exists('PTP_Comms_Hub_Contact_Notes')) {
            return new WP_Error('not_available', 'Notes feature not available', array('status' => 501));
        }
        
        $result = PTP_Comms_Hub_Contact_Notes::delete_note($note_id);
        
        return rest_ensure_response(array(
            'success' => (bool)$result,
            'message' => $result ? 'Note deleted' : 'Delete failed'
        ));
    }
    
    /**
     * Toggle note pin status
     */
    public static function toggle_note_pin($request) {
        $note_id = intval($request->get_param('note_id'));
        
        if (!class_exists('PTP_Comms_Hub_Contact_Notes')) {
            return new WP_Error('not_available', 'Notes feature not available', array('status' => 501));
        }
        
        PTP_Comms_Hub_Contact_Notes::toggle_pin($note_id);
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Get reminders
     */
    public static function get_reminders($request) {
        if (!class_exists('PTP_Comms_Hub_Reminders')) {
            return new WP_Error('not_available', 'Reminders feature not available', array('status' => 501));
        }
        
        $args = array(
            'limit' => intval($request->get_param('limit') ?: 50),
            'status' => $request->get_param('status'),
            'assigned_to' => $request->get_param('assigned_to') ?: get_current_user_id(),
            'contact_id' => $request->get_param('contact_id')
        );
        
        $reminders = PTP_Comms_Hub_Reminders::get_reminders(array_filter($args));
        $counts = PTP_Comms_Hub_Reminders::get_counts($args['assigned_to']);
        
        return rest_ensure_response(array(
            'success' => true,
            'reminders' => $reminders,
            'counts' => $counts
        ));
    }
    
    /**
     * Create reminder
     */
    public static function create_reminder($request) {
        if (!class_exists('PTP_Comms_Hub_Reminders')) {
            return new WP_Error('not_available', 'Reminders feature not available', array('status' => 501));
        }
        
        $data = array(
            'title' => sanitize_text_field($request->get_param('title')),
            'description' => sanitize_textarea_field($request->get_param('description')),
            'contact_id' => intval($request->get_param('contact_id')),
            'reminder_type' => sanitize_text_field($request->get_param('reminder_type') ?: 'follow_up'),
            'priority' => sanitize_text_field($request->get_param('priority') ?: 'normal'),
            'due_date' => sanitize_text_field($request->get_param('due_date')),
            'assigned_to' => intval($request->get_param('assigned_to') ?: get_current_user_id())
        );
        
        $reminder_id = PTP_Comms_Hub_Reminders::create($data);
        
        if ($reminder_id) {
            return rest_ensure_response(array(
                'success' => true,
                'reminder_id' => $reminder_id
            ));
        }
        
        return new WP_Error('create_failed', 'Failed to create reminder', array('status' => 500));
    }
    
    /**
     * Complete reminder
     */
    public static function complete_reminder($request) {
        $reminder_id = intval($request->get_param('reminder_id'));
        
        if (!class_exists('PTP_Comms_Hub_Reminders')) {
            return new WP_Error('not_available', 'Reminders feature not available', array('status' => 501));
        }
        
        $result = PTP_Comms_Hub_Reminders::complete($reminder_id);
        
        return rest_ensure_response(array(
            'success' => (bool)$result
        ));
    }
    
    /**
     * Snooze reminder
     */
    public static function snooze_reminder($request) {
        $reminder_id = intval($request->get_param('reminder_id'));
        $snooze_until = sanitize_text_field($request->get_param('snooze_until'));
        
        if (!class_exists('PTP_Comms_Hub_Reminders')) {
            return new WP_Error('not_available', 'Reminders feature not available', array('status' => 501));
        }
        
        $result = PTP_Comms_Hub_Reminders::snooze($reminder_id, $snooze_until);
        
        return rest_ensure_response(array(
            'success' => (bool)$result
        ));
    }
    
    /**
     * Get notifications
     */
    public static function get_notifications($request) {
        if (!class_exists('PTP_Comms_Hub_Notifications')) {
            return new WP_Error('not_available', 'Notifications feature not available', array('status' => 501));
        }
        
        $args = array(
            'limit' => intval($request->get_param('limit') ?: 20),
            'unread_only' => (bool)$request->get_param('unread_only')
        );
        
        $notifications = PTP_Comms_Hub_Notifications::get_notifications(null, $args);
        $unread_count = PTP_Comms_Hub_Notifications::get_unread_count();
        
        return rest_ensure_response(array(
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ));
    }
    
    /**
     * Mark notification as read
     */
    public static function mark_notification_read($request) {
        $notification_id = intval($request->get_param('notification_id'));
        
        if (!class_exists('PTP_Comms_Hub_Notifications')) {
            return new WP_Error('not_available', 'Notifications feature not available', array('status' => 501));
        }
        
        PTP_Comms_Hub_Notifications::mark_read($notification_id);
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Mark all notifications as read
     */
    public static function mark_all_notifications_read($request) {
        if (!class_exists('PTP_Comms_Hub_Notifications')) {
            return new WP_Error('not_available', 'Notifications feature not available', array('status' => 501));
        }
        
        PTP_Comms_Hub_Notifications::mark_all_read();
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Get notification count
     */
    public static function get_notification_count($request) {
        if (!class_exists('PTP_Comms_Hub_Notifications')) {
            return rest_ensure_response(array('count' => 0));
        }
        
        $count = PTP_Comms_Hub_Notifications::get_unread_count();
        
        return rest_ensure_response(array('count' => $count));
    }
    
    /**
     * Get contact timeline
     */
    public static function get_contact_timeline($request) {
        $contact_id = intval($request->get_param('contact_id'));
        $limit = intval($request->get_param('limit') ?: 50);
        
        if (!class_exists('PTP_Comms_Hub_Activity_Log')) {
            return new WP_Error('not_available', 'Activity log feature not available', array('status' => 501));
        }
        
        $timeline = PTP_Comms_Hub_Activity_Log::get_full_timeline($contact_id, $limit);
        $stats = PTP_Comms_Hub_Activity_Log::get_contact_stats($contact_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'timeline' => $timeline,
            'stats' => $stats
        ));
    }
    
    /**
     * Get segment contacts
     */
    public static function get_segment_contacts($request) {
        $segment_id = intval($request->get_param('segment_id'));
        $limit = intval($request->get_param('limit') ?: 100);
        $offset = intval($request->get_param('offset') ?: 0);
        
        if (!class_exists('PTP_Comms_Hub_Saved_Segments')) {
            return new WP_Error('not_available', 'Segments feature not available', array('status' => 501));
        }
        
        $contacts = PTP_Comms_Hub_Saved_Segments::get_contacts($segment_id, $limit, $offset);
        
        return rest_ensure_response(array(
            'success' => true,
            'contacts' => $contacts,
            'count' => count($contacts)
        ));
    }
    
    /**
     * Get segment contact count
     */
    public static function get_segment_count($request) {
        $segment_id = intval($request->get_param('segment_id'));
        
        if (!class_exists('PTP_Comms_Hub_Saved_Segments')) {
            return rest_ensure_response(array('count' => 0));
        }
        
        $count = PTP_Comms_Hub_Saved_Segments::count_contacts($segment_id);
        
        return rest_ensure_response(array('count' => $count));
    }
}
