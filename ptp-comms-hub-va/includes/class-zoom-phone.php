<?php
/**
 * Zoom Phone Integration for PTP Comms Hub
 * Supports: Making calls, receiving calls, call recording, SMS via Zoom
 * Version: 1.0.0
 */
class PTP_Comms_Hub_Zoom_Phone {
    
    private $account_id;
    private $client_id;
    private $client_secret;
    private $access_token;
    
    public function __construct() {
        $this->account_id = ptp_comms_get_setting('zoom_account_id');
        $this->client_id = ptp_comms_get_setting('zoom_client_id');
        $this->client_secret = ptp_comms_get_setting('zoom_client_secret');
    }
    
    /**
     * Check if Zoom Phone is configured
     */
    public function is_configured() {
        return !empty($this->account_id) && !empty($this->client_id) && !empty($this->client_secret);
    }
    
    /**
     * Get OAuth access token using Server-to-Server OAuth
     */
    private function get_access_token() {
        if ($this->access_token) {
            return $this->access_token;
        }
        
        // Check cached token
        $cached = get_transient('ptp_zoom_access_token');
        if ($cached) {
            $this->access_token = $cached;
            return $cached;
        }
        
        $url = 'https://zoom.us/oauth/token';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$this->client_id}:{$this->client_secret}"),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'account_credentials',
                'account_id' => $this->account_id
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('[PTP Zoom] Token error: ' . $response->get_error_message());
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            // Cache for 55 minutes (token valid for 60)
            set_transient('ptp_zoom_access_token', $this->access_token, 55 * MINUTE_IN_SECONDS);
            return $this->access_token;
        }
        
        error_log('[PTP Zoom] Token response: ' . print_r($body, true));
        return null;
    }
    
    /**
     * Make API request to Zoom
     */
    private function api_request($endpoint, $method = 'GET', $data = null) {
        $token = $this->get_access_token();
        if (!$token) {
            return array('success' => false, 'error' => 'Failed to get access token');
        }
        
        $url = 'https://api.zoom.us/v2' . $endpoint;
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'method' => $method,
            'timeout' => 30
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code >= 200 && $code < 300) {
            return array('success' => true, 'data' => $body);
        }
        
        return array('success' => false, 'error' => $body['message'] ?? 'API error', 'code' => $code);
    }
    
    /**
     * Get list of Zoom Phone users
     */
    public function get_phone_users() {
        return $this->api_request('/phone/users');
    }
    
    /**
     * Get phone numbers
     */
    public function get_phone_numbers() {
        return $this->api_request('/phone/numbers');
    }
    
    /**
     * Make outbound call via Zoom Phone
     * This initiates a call from a Zoom Phone user to an external number
     */
    public function make_call($to, $from_user_id = null) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'Zoom Phone not configured');
        }
        
        // Get default caller if not specified
        if (!$from_user_id) {
            $from_user_id = ptp_comms_get_setting('zoom_default_caller_id');
        }
        
        if (!$from_user_id) {
            return array('success' => false, 'error' => 'No caller ID configured');
        }
        
        // Normalize phone number
        $to = ptp_comms_normalize_phone($to);
        if (!$to) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }
        
        // Zoom Phone Call API
        $result = $this->api_request("/phone/users/{$from_user_id}/calls", 'POST', array(
            'callee' => $to,
            'call_type' => 1 // 1 = outbound
        ));
        
        if ($result['success']) {
            // Log the call
            $this->log_call($to, 'outbound', $result['data']['call_id'] ?? '', 'initiated', 'zoom');
        }
        
        return $result;
    }
    
    /**
     * Get call recordings
     */
    public function get_call_recordings($call_id) {
        return $this->api_request("/phone/call_logs/{$call_id}/recordings");
    }
    
    /**
     * Get call history
     */
    public function get_call_history($user_id = null, $from_date = null, $to_date = null) {
        $params = array();
        
        if ($from_date) $params['from'] = $from_date;
        if ($to_date) $params['to'] = $to_date;
        
        $query = !empty($params) ? '?' . http_build_query($params) : '';
        
        if ($user_id) {
            return $this->api_request("/phone/users/{$user_id}/call_logs{$query}");
        }
        
        return $this->api_request("/phone/call_logs{$query}");
    }
    
    /**
     * Send SMS via Zoom Phone
     */
    public function send_sms($to, $message, $from_number = null) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'Zoom Phone not configured');
        }
        
        if (!$from_number) {
            $from_number = ptp_comms_get_setting('zoom_sms_number');
        }
        
        $to = ptp_comms_normalize_phone($to);
        if (!$to) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }
        
        $result = $this->api_request('/phone/sms', 'POST', array(
            'from' => $from_number,
            'to' => $to,
            'message' => $message
        ));
        
        return $result;
    }
    
    /**
     * Handle incoming Zoom webhook
     */
    public function handle_webhook($payload) {
        $event = $payload['event'] ?? '';
        $data = $payload['payload'] ?? array();
        
        error_log("[PTP Zoom] Webhook event: {$event}");
        
        switch ($event) {
            case 'phone.callee_answered':
            case 'phone.call_connected':
                $this->handle_call_answered($data);
                break;
                
            case 'phone.call_ended':
            case 'phone.callee_ended':
            case 'phone.caller_ended':
                $this->handle_call_ended($data);
                break;
                
            case 'phone.call_ringing':
                $this->handle_call_ringing($data);
                break;
                
            case 'phone.voicemail_received':
                $this->handle_voicemail($data);
                break;
                
            case 'phone.sms_received':
                $this->handle_sms_received($data);
                break;
                
            case 'phone.recording_completed':
                $this->handle_recording_completed($data);
                break;
        }
        
        return array('success' => true);
    }
    
    /**
     * Handle call ringing
     */
    private function handle_call_ringing($data) {
        $call_id = $data['object']['call_id'] ?? '';
        $caller = $data['object']['caller']['phone_number'] ?? '';
        $callee = $data['object']['callee']['phone_number'] ?? '';
        $direction = $data['object']['direction'] ?? 'inbound';
        
        $this->log_call(
            $direction === 'inbound' ? $caller : $callee,
            $direction,
            $call_id,
            'ringing',
            'zoom'
        );
        
        // Send notification for inbound calls
        if ($direction === 'inbound') {
            $this->notify_incoming_call($caller, $callee);
        }
    }
    
    /**
     * Handle call answered
     */
    private function handle_call_answered($data) {
        $call_id = $data['object']['call_id'] ?? '';
        
        $this->update_call_log($call_id, array(
            'status' => 'in-progress',
            'answered_at' => current_time('mysql')
        ));
    }
    
    /**
     * Handle call ended
     */
    private function handle_call_ended($data) {
        $call_id = $data['object']['call_id'] ?? '';
        $duration = $data['object']['duration'] ?? 0;
        $result = $data['object']['result'] ?? 'completed';
        
        // Map Zoom result to status
        $status_map = array(
            'call_connected' => 'completed',
            'call_missed' => 'no-answer',
            'call_cancelled' => 'canceled',
            'voicemail' => 'voicemail',
            'busy' => 'busy'
        );
        
        $status = $status_map[$result] ?? 'completed';
        
        $this->update_call_log($call_id, array(
            'status' => $status,
            'duration' => $duration,
            'ended_at' => current_time('mysql')
        ));
        
        // If missed, send notification
        if ($status === 'no-answer') {
            $caller = $data['object']['caller']['phone_number'] ?? '';
            $this->notify_missed_call($caller);
        }
    }
    
    /**
     * Handle voicemail received
     */
    private function handle_voicemail($data) {
        global $wpdb;
        
        $caller = $data['object']['caller']['phone_number'] ?? '';
        $voicemail_id = $data['object']['voicemail_id'] ?? '';
        $download_url = $data['object']['download_url'] ?? '';
        $duration = $data['object']['duration'] ?? 0;
        $transcript = $data['object']['transcript'] ?? '';
        
        // Find or create contact
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $caller
        ));
        
        // Log voicemail
        $meta = array(
            'voicemail_id' => $voicemail_id,
            'recording_url' => $download_url,
            'duration' => $duration,
            'source' => 'zoom'
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ptp_communication_logs',
            array(
                'contact_id' => $contact ? $contact->id : 0,
                'message_type' => 'voicemail',
                'direction' => 'inbound',
                'message_content' => $transcript,
                'status' => 'new',
                'meta_data' => json_encode($meta),
                'created_at' => current_time('mysql')
            )
        );
        
        // Notify
        $this->notify_voicemail($caller, $transcript, $download_url);
    }
    
    /**
     * Handle SMS received
     */
    private function handle_sms_received($data) {
        global $wpdb;
        
        $from = $data['object']['from']['phone_number'] ?? '';
        $to = $data['object']['to']['phone_number'] ?? '';
        $message = $data['object']['message'] ?? '';
        $sms_id = $data['object']['sms_id'] ?? '';
        
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
                    'source' => 'zoom_sms',
                    'created_at' => current_time('mysql')
                )
            );
            $contact_id = $wpdb->insert_id;
        } else {
            $contact_id = $contact->id;
        }
        
        // Log SMS
        $wpdb->insert(
            $wpdb->prefix . 'ptp_communication_logs',
            array(
                'contact_id' => $contact_id,
                'message_type' => 'sms',
                'direction' => 'inbound',
                'message_content' => $message,
                'status' => 'received',
                'meta_data' => json_encode(array('sms_id' => $sms_id, 'source' => 'zoom')),
                'created_at' => current_time('mysql')
            )
        );
        
        // Forward to Teams if configured
        if (class_exists('PTP_Comms_Hub_Teams_Integration')) {
            $contact_name = $contact ? trim("{$contact->parent_first_name} {$contact->parent_last_name}") : 'Unknown';
            PTP_Comms_Hub_Teams_Integration::send_message(
                "📱 **New SMS via Zoom** from {$contact_name}\n\n" .
                "📞 " . ptp_comms_format_phone($from) . "\n\n" .
                "💬 {$message}"
            );
        }
    }
    
    /**
     * Handle recording completed
     */
    private function handle_recording_completed($data) {
        $call_id = $data['object']['call_id'] ?? '';
        $download_url = $data['object']['download_url'] ?? '';
        
        if ($call_id && $download_url) {
            $this->update_call_log($call_id, array(
                'recording_url' => $download_url
            ));
        }
    }
    
    /**
     * Log call to database
     */
    private function log_call($phone, $direction, $call_id, $status, $source = 'zoom') {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $phone
        ));
        
        $meta = array(
            'call_id' => $call_id,
            'source' => $source
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ptp_communication_logs',
            array(
                'contact_id' => $contact ? $contact->id : 0,
                'message_type' => 'voice',
                'direction' => $direction,
                'message_content' => '',
                'status' => $status,
                'meta_data' => json_encode($meta),
                'created_at' => current_time('mysql')
            )
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update call log
     */
    private function update_call_log($call_id, $data) {
        global $wpdb;
        
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_communication_logs 
             WHERE message_type = 'voice' AND meta_data LIKE %s
             ORDER BY created_at DESC LIMIT 1",
            '%' . $wpdb->esc_like($call_id) . '%'
        ));
        
        if ($log) {
            $meta = json_decode($log->meta_data, true) ?: array();
            $meta = array_merge($meta, $data);
            
            $update = array('meta_data' => json_encode($meta));
            
            if (isset($data['status'])) {
                $update['status'] = $data['status'];
            }
            
            $wpdb->update(
                $wpdb->prefix . 'ptp_communication_logs',
                $update,
                array('id' => $log->id)
            );
        }
    }
    
    /**
     * Notify incoming call via Teams
     */
    private function notify_incoming_call($from, $to) {
        if (!class_exists('PTP_Comms_Hub_Teams_Integration')) return;
        
        global $wpdb;
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $from
        ));
        
        $contact_name = $contact ? trim("{$contact->parent_first_name} {$contact->parent_last_name}") : 'Unknown';
        if (empty($contact_name) || $contact_name === 'Unknown') {
            $contact_name = ptp_comms_format_phone($from);
        }
        
        PTP_Comms_Hub_Teams_Integration::send_message(
            "🔔 **Incoming Call** from {$contact_name}\n\n" .
            "📞 " . ptp_comms_format_phone($from)
        );
    }
    
    /**
     * Notify missed call
     */
    private function notify_missed_call($from) {
        if (!class_exists('PTP_Comms_Hub_Teams_Integration')) return;
        
        global $wpdb;
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $from
        ));
        
        $contact_name = $contact ? trim("{$contact->parent_first_name} {$contact->parent_last_name}") : 'Unknown';
        
        PTP_Comms_Hub_Teams_Integration::send_message(
            "📵 **Missed Call** from {$contact_name}\n\n" .
            "📞 " . ptp_comms_format_phone($from) . "\n\n" .
            "⏰ " . current_time('g:i A')
        );
        
        // Also send SMS to notify staff
        $notify_numbers = ptp_comms_get_setting('missed_call_notify_numbers', '');
        if (!empty($notify_numbers)) {
            $numbers = array_map('trim', explode(',', $notify_numbers));
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            
            foreach ($numbers as $number) {
                $sms_service->send_sms(
                    $number,
                    "PTP Missed Call from {$contact_name} - " . ptp_comms_format_phone($from) . " at " . current_time('g:i A')
                );
            }
        }
    }
    
    /**
     * Notify voicemail
     */
    private function notify_voicemail($from, $transcript, $recording_url) {
        if (!class_exists('PTP_Comms_Hub_Teams_Integration')) return;
        
        global $wpdb;
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $from
        ));
        
        $contact_name = $contact ? trim("{$contact->parent_first_name} {$contact->parent_last_name}") : 'Unknown';
        
        $message = "📞 **New Voicemail** from {$contact_name}\n\n" .
                   "📱 " . ptp_comms_format_phone($from) . "\n\n";
        
        if ($transcript) {
            $message .= "📝 **Transcript:**\n{$transcript}\n\n";
        }
        
        if ($recording_url) {
            $message .= "🎵 [Listen to Recording]({$recording_url})";
        }
        
        PTP_Comms_Hub_Teams_Integration::send_message($message);
    }
}
