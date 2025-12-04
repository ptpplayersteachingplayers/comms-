<?php
/**
 * Enhanced voice service using Twilio with IVR and Recording capabilities
 * Supports: Automated calls, IVR menus, forwarding, voicemail, call recording
 * Version: 3.6.3 - Fixed business hours handling in IVR responses
 */
class PTP_Comms_Hub_Voice_Service {
    
    private $twilio_sid;
    private $twilio_token;
    private $twilio_from;
    
    public function __construct() {
        $this->twilio_sid = ptp_comms_get_setting('twilio_account_sid');
        $this->twilio_token = ptp_comms_get_setting('twilio_auth_token');
        $this->twilio_from = ptp_comms_get_setting('twilio_phone_number');
    }
    
    /**
     * Make automated call with message and optional recording
     */
    public function make_call($to, $message, $record = true) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'Twilio not configured');
        }
        
        $to = ptp_comms_normalize_phone($to);
        if (!$to) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }
        
        $twiml_url = add_query_arg(
            array('message' => urlencode($message)),
            get_rest_url(null, 'ptp-comms/v1/twiml')
        );
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Calls.json";
        
        $data = array(
            'From' => $this->twilio_from,
            'To' => $to,
            'Url' => $twiml_url,
            'StatusCallback' => get_rest_url(null, 'ptp-comms/v1/call-status'),
            'StatusCallbackEvent' => 'initiated ringing answered completed',
            'StatusCallbackMethod' => 'POST',
        );
        
        // Enable recording - default ON
        if ($record) {
            $data['Record'] = 'true';
            $data['RecordingStatusCallback'] = get_rest_url(null, 'ptp-comms/v1/recording-status');
            $data['RecordingStatusCallbackMethod'] = 'POST';
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
            return array('success' => false, 'error' => $body['message']);
        }
        
        // Log the call
        $this->log_call($to, 'outbound', $body['sid'], 'initiated');
        
        return array('success' => true, 'sid' => $body['sid'], 'status' => $body['status']);
    }
    
    /**
     * Make call with IVR menu - ALWAYS records
     */
    public function make_ivr_call($to, $menu_options = array()) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'Twilio not configured');
        }
        
        $to = ptp_comms_normalize_phone($to);
        if (!$to) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }
        
        $twiml_url = get_rest_url(null, 'ptp-comms/v1/ivr-menu');
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Calls.json";
        
        $data = array(
            'From' => $this->twilio_from,
            'To' => $to,
            'Url' => $twiml_url,
            'StatusCallback' => get_rest_url(null, 'ptp-comms/v1/call-status'),
            'StatusCallbackEvent' => 'initiated ringing answered completed',
            'StatusCallbackMethod' => 'POST',
            'Record' => 'true',
            'RecordingStatusCallback' => get_rest_url(null, 'ptp-comms/v1/recording-status'),
            'RecordingStatusCallbackMethod' => 'POST',
        );
        
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
            return array('success' => false, 'error' => $body['message']);
        }
        
        // Log the call
        $this->log_call($to, 'outbound', $body['sid'], 'initiated');
        
        return array('success' => true, 'sid' => $body['sid'], 'status' => $body['status']);
    }
    
    /**
     * Log call to database
     */
    public function log_call($phone, $direction, $call_sid, $status, $duration = 0, $recording_url = '') {
        global $wpdb;
        
        // Find contact
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $phone
        ));
        
        $meta = array(
            'call_sid' => $call_sid,
            'duration' => $duration,
            'recording_url' => $recording_url
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
     * Update call log with status/recording
     */
    public function update_call_log($call_sid, $data) {
        global $wpdb;
        
        // Find existing log by call_sid in meta
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_communication_logs 
             WHERE message_type = 'voice' AND meta_data LIKE %s
             ORDER BY created_at DESC LIMIT 1",
            '%' . $wpdb->esc_like($call_sid) . '%'
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
            
            return true;
        }
        
        return false;
    }
    
    public function is_configured() {
        return !empty($this->twilio_sid) && !empty($this->twilio_token) && !empty($this->twilio_from);
    }
    
    /**
     * Generate simple TwiML for automated message
     */
    public function generate_twiml($message) {
        header('Content-Type: text/xml');
        return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">' . esc_html($message) . '</Say>
</Response>';
    }
    
    /**
     * Generate IVR menu TwiML with recording enabled
     * IMPORTANT: This now rings staff FIRST, IVR only if no answer
     * This is the MAIN entry point for incoming calls
     */
    public function generate_ivr_menu() {
        $greeting = ptp_comms_get_setting('ivr_greeting', 'Thank you for calling PTP Soccer Camps.');
        $forwarding_numbers = ptp_comms_get_setting('ivr_forwarding_numbers', '');
        $ring_first = ptp_comms_get_setting('ivr_ring_staff_first', 'yes');
        
        // RING STAFF FIRST - before any IVR menu (recommended flow)
        if ($ring_first === 'yes' && !empty($forwarding_numbers)) {
            header('Content-Type: text/xml');
            return $this->generate_ring_first_twiml($forwarding_numbers, $greeting);
        }
        
        // Check if we should auto-forward during business hours (legacy behavior)
        $auto_forward = ptp_comms_get_setting('ivr_auto_forward_business_hours', 'yes');
        
        if ($auto_forward === 'yes' && $this->is_business_hours() && !empty($forwarding_numbers)) {
            header('Content-Type: text/xml');
            $forward_message = ptp_comms_get_setting('ivr_forward_message', 
                'Thank you for calling PTP Soccer Camps. Please hold while we connect you to a camp coordinator.'
            );
            return $this->generate_forward_twiml($forwarding_numbers, $forward_message);
        }
        
        // Fall back to IVR menu with business hours awareness
        header('Content-Type: text/xml');
        return $this->generate_smart_ivr_menu_internal($greeting);
    }
    
    /**
     * Internal: Generate the actual IVR menu content
     * Separated to avoid recursive header issues
     */
    private function generate_smart_ivr_menu_internal($greeting = '') {
        if (empty($greeting)) {
            $greeting = ptp_comms_get_setting('ivr_greeting', 'Thank you for calling PTP Soccer Camps.');
        }
        
        if ($this->is_business_hours()) {
            $menu_prompt = ptp_comms_get_setting('ivr_menu_prompt', 
                'Press 1 to speak with a camp coordinator. Press 2 for registration information. Press 3 for camp locations and dates. Press 0 to repeat this menu.'
            );
        } else {
            $after_hours = ptp_comms_get_setting('ivr_after_hours_message', 
                'Our office is currently closed.'
            );
            $menu_prompt = $after_hours . ' Press 1 to leave a voicemail. Press 2 for registration information. Press 3 for our website information. Press 0 to repeat this menu.';
        }
        
        return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">' . esc_html($greeting) . '</Say>
    <Gather action="' . esc_url(get_rest_url(null, 'ptp-comms/v1/ivr-response')) . '" numDigits="1" timeout="5" method="POST">
        <Say voice="Polly.Joanna">' . esc_html($menu_prompt) . '</Say>
    </Gather>
    <Say voice="Polly.Joanna">We did not receive any input. Please call back. Goodbye.</Say>
</Response>';
    }
    
    /**
     * NEW: Ring staff phones FIRST, then go to IVR if no answer
     * This ensures a human can answer before the caller hears any menu
     */
    public function generate_ring_first_twiml($forwarding_numbers, $greeting = '') {
        $numbers = is_array($forwarding_numbers) ? $forwarding_numbers : array_map('trim', explode(',', $forwarding_numbers));
        $timeout = ptp_comms_get_setting('ivr_dial_timeout', '20');
        $ring_message = ptp_comms_get_setting('ivr_ring_message', 'Please hold while we connect your call.');
        
        $dial_numbers = '';
        foreach ($numbers as $number) {
            $clean_number = ptp_comms_normalize_phone($number);
            if ($clean_number) {
                // simultaneousRing - all phones ring at once
                $dial_numbers .= '<Number>' . esc_html($clean_number) . '</Number>';
            }
        }
        
        if (empty($dial_numbers)) {
            return $this->generate_smart_ivr_menu();
        }
        
        // Ring staff first, if no answer go to IVR fallback
        return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">' . esc_html($ring_message) . '</Say>
    <Dial timeout="' . esc_attr($timeout) . '" 
          action="' . esc_url(get_rest_url(null, 'ptp-comms/v1/ring-first-fallback')) . '" 
          method="POST"
          record="record-from-answer-dual"
          recordingStatusCallback="' . esc_url(get_rest_url(null, 'ptp-comms/v1/recording-status')) . '"
          recordingStatusCallbackMethod="POST"
          callerId="' . esc_attr($this->twilio_from) . '">
        ' . $dial_numbers . '
    </Dial>
</Response>';
    }
    
    /**
     * Handle fallback when staff don't answer - go to IVR or voicemail
     */
    public function handle_ring_first_fallback($dial_status) {
        header('Content-Type: text/xml');
        
        if ($dial_status === 'completed') {
            // Call was answered and completed
            return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">Thank you for calling PTP Soccer Camps. Goodbye.</Say>
</Response>';
        }
        
        // Call was not answered - check what to do next
        $fallback_action = ptp_comms_get_setting('ivr_no_answer_action', 'voicemail');
        
        if ($fallback_action === 'ivr') {
            // Show IVR menu
            $greeting = ptp_comms_get_setting('ivr_greeting', 'Thank you for calling PTP Soccer Camps.');
            $menu_prompt = ptp_comms_get_setting('ivr_menu_prompt', 
                'Press 1 to leave a voicemail. Press 2 for registration information. Press 3 for camp locations. Press 0 to repeat.'
            );
            
            return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">We\'re sorry, all of our coordinators are currently unavailable.</Say>
    <Gather action="' . esc_url(get_rest_url(null, 'ptp-comms/v1/ivr-response')) . '" numDigits="1" timeout="5" method="POST">
        <Say voice="Polly.Joanna">' . esc_html($menu_prompt) . '</Say>
    </Gather>
    <Redirect>' . esc_url(get_rest_url(null, 'ptp-comms/v1/voicemail')) . '</Redirect>
</Response>';
        }
        
        // Default: go straight to voicemail
        return $this->generate_voicemail_twiml();
    }
    
    /**
     * Handle IVR response - FIXED to handle business hours vs after-hours correctly
     */
    public function handle_ivr_response($digit) {
        header('Content-Type: text/xml');
        
        $is_business_hours = $this->is_business_hours();
        
        switch ($digit) {
            case '1':
                if ($is_business_hours) {
                    // During business hours: try to connect to a coordinator
                    $forwarding_numbers = ptp_comms_get_setting('ivr_forwarding_numbers', '');
                    if (!empty($forwarding_numbers)) {
                        return $this->generate_forward_twiml($forwarding_numbers);
                    }
                }
                // After hours or no forwarding numbers: go to voicemail
                return $this->generate_voicemail_twiml();
                
            case '2':
                $registration_info = ptp_comms_get_setting('ivr_registration_message', 
                    'To register for our camps, please visit www.ptpsoccercamps.com or call back during business hours to speak with a coordinator.'
                );
                return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">' . esc_html($registration_info) . '</Say>
    <Say voice="Polly.Joanna">Thank you for calling PTP Soccer Camps. Goodbye.</Say>
</Response>';
                
            case '3':
                if ($is_business_hours) {
                    $camp_info = ptp_comms_get_setting('ivr_camp_info_message',
                        'For camp locations and dates, please visit www.ptpsoccercamps.com or check your email for our latest camp schedule.'
                    );
                } else {
                    $camp_info = ptp_comms_get_setting('ivr_website_message',
                        'Visit our website at www.ptpsoccercamps.com for camp locations, dates, and registration information.'
                    );
                }
                return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">' . esc_html($camp_info) . '</Say>
    <Say voice="Polly.Joanna">Thank you for calling. Goodbye.</Say>
</Response>';
                
            case '0':
                // Repeat menu - use internal method to avoid recursion issues
                return $this->generate_smart_ivr_menu_internal();
                
            default:
                return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">Invalid option. Please try again.</Say>
    <Redirect>' . esc_url(get_rest_url(null, 'ptp-comms/v1/ivr-menu')) . '</Redirect>
</Response>';
        }
    }
    
    /**
     * Generate forwarding TwiML with RECORDING ENABLED
     */
    public function generate_forward_twiml($forwarding_numbers, $message = '') {
        $numbers = is_array($forwarding_numbers) ? $forwarding_numbers : array_map('trim', explode(',', $forwarding_numbers));
        $timeout = ptp_comms_get_setting('ivr_dial_timeout', '20');
        
        if (empty($message)) {
            $message = ptp_comms_get_setting('ivr_forward_message', 'Please hold while we connect you to a camp coordinator.');
        }
        
        $dial_numbers = '';
        foreach ($numbers as $number) {
            $clean_number = ptp_comms_normalize_phone($number);
            if ($clean_number) {
                $dial_numbers .= '<Number>' . esc_html($clean_number) . '</Number>';
            }
        }
        
        if (empty($dial_numbers)) {
            return $this->generate_voicemail_twiml();
        }
        
        // IMPORTANT: record="record-from-answer-dual" enables call recording for forwarded calls
        return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">' . esc_html($message) . '</Say>
    <Dial timeout="' . esc_attr($timeout) . '" 
          action="' . esc_url(get_rest_url(null, 'ptp-comms/v1/dial-status')) . '" 
          method="POST"
          record="record-from-answer-dual"
          recordingStatusCallback="' . esc_url(get_rest_url(null, 'ptp-comms/v1/recording-status')) . '"
          recordingStatusCallbackMethod="POST">
        ' . $dial_numbers . '
    </Dial>
</Response>';
    }
    
    /**
     * Generate voicemail TwiML with transcription
     */
    public function generate_voicemail_twiml() {
        $voicemail_message = ptp_comms_get_setting('ivr_voicemail_message', 
            'All of our coordinators are currently busy. Please leave a message after the beep, and we will return your call as soon as possible.'
        );
        
        return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">' . esc_html($voicemail_message) . '</Say>
    <Record maxLength="120" 
            action="' . esc_url(get_rest_url(null, 'ptp-comms/v1/voicemail-complete')) . '" 
            method="POST"
            transcribe="true"
            transcribeCallback="' . esc_url(get_rest_url(null, 'ptp-comms/v1/voicemail-transcription')) . '"
            playBeep="true" />
    <Say voice="Polly.Joanna">We did not receive a recording. Goodbye.</Say>
</Response>';
    }
    
    /**
     * Handle incoming call - main entry point
     */
    public function handle_incoming_call($from, $to) {
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
        }
        
        return $this->generate_smart_ivr_menu();
    }
    
    /**
     * Handle voicemail completion
     */
    public function handle_voicemail_complete($recording_url, $recording_sid, $from) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
            $from
        ));
        
        // Log voicemail
        $wpdb->insert(
            $wpdb->prefix . 'ptp_voicemails',
            array(
                'contact_id' => $contact ? $contact->id : null,
                'from_number' => $from,
                'recording_url' => $recording_url,
                'recording_sid' => $recording_sid,
                'status' => 'new',
                'created_at' => current_time('mysql')
            )
        );
        
        $voicemail_id = $wpdb->insert_id;
        
        // Also log in communication logs
        $meta = array(
            'recording_url' => $recording_url,
            'recording_sid' => $recording_sid,
            'voicemail_id' => $voicemail_id
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ptp_communication_logs',
            array(
                'contact_id' => $contact ? $contact->id : 0,
                'message_type' => 'voicemail',
                'direction' => 'inbound',
                'message_content' => 'Voicemail received',
                'status' => 'new',
                'meta_data' => json_encode($meta),
                'created_at' => current_time('mysql')
            )
        );
        
        // Teams notification
        if (class_exists('PTP_Comms_Hub_Teams_Integration')) {
            $contact_name = $contact ? trim("{$contact->parent_first_name} {$contact->parent_last_name}") : "Unknown";
            if (empty($contact_name) || $contact_name === 'Unknown') {
                $contact_name = ptp_comms_format_phone($from);
            }
            
            PTP_Comms_Hub_Teams_Integration::send_message(
                "📞 **New Voicemail** from {$contact_name}\n\n" .
                "🎵 [Listen to Recording]({$recording_url})\n\n" .
                "📱 Phone: " . ptp_comms_format_phone($from)
            );
        }
        
        header('Content-Type: text/xml');
        return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">Thank you for your message. A coordinator will return your call soon. Goodbye.</Say>
</Response>';
    }
    
    /**
     * Handle voicemail transcription
     */
    public function handle_voicemail_transcription($transcription_text, $recording_sid) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_voicemails',
            array('transcription' => $transcription_text),
            array('recording_sid' => $recording_sid)
        );
        
        // Update communication log
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ptp_communication_logs 
             SET message_content = %s 
             WHERE message_type = 'voicemail' AND meta_data LIKE %s",
            $transcription_text,
            '%' . $wpdb->esc_like($recording_sid) . '%'
        ));
    }
    
    /**
     * Handle dial status callback
     */
    public function handle_dial_status($dial_call_status) {
        header('Content-Type: text/xml');
        
        if ($dial_call_status === 'completed') {
            return '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Joanna">Thank you for calling PTP Soccer Camps. Goodbye.</Say>
</Response>';
        }
        
        return $this->generate_voicemail_twiml();
    }
    
    /**
     * Check business hours
     */
    private function is_business_hours() {
        $start_time = ptp_comms_get_setting('ivr_business_start', '09:00');
        $end_time = ptp_comms_get_setting('ivr_business_end', '17:00');
        $days = ptp_comms_get_setting('ivr_business_days', 'mon,tue,wed,thu,fri');
        $timezone = ptp_comms_get_setting('ivr_timezone', 'America/New_York');
        
        try {
            $tz = new DateTimeZone($timezone);
            $now = new DateTime('now', $tz);
        } catch (Exception $e) {
            $now = new DateTime('now', new DateTimeZone('America/New_York'));
        }
        
        $current_day = strtolower(substr($now->format('D'), 0, 3));
        $current_time = $now->format('H:i');
        
        $business_days = array_map('trim', explode(',', strtolower($days)));
        
        if (!in_array($current_day, $business_days)) {
            return false;
        }
        
        return ($current_time >= $start_time && $current_time <= $end_time);
    }
    
    /**
     * Smart IVR menu based on business hours
     * Public wrapper that sets headers and calls internal method
     */
    public function generate_smart_ivr_menu() {
        header('Content-Type: text/xml');
        return $this->generate_smart_ivr_menu_internal();
    }
}
