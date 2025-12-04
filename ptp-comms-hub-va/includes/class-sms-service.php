<?php
/**
 * SMS service using Twilio
 * v3.4.0 - Added status callback, MMS support, lookup API
 */
class PTP_Comms_Hub_SMS_Service {
    
    private $twilio_sid;
    private $twilio_token;
    private $twilio_from;
    
    public function __construct() {
        $this->twilio_sid = ptp_comms_get_setting('twilio_account_sid');
        $this->twilio_token = ptp_comms_get_setting('twilio_auth_token');
        $this->twilio_from = ptp_comms_get_setting('twilio_phone_number');
    }
    
    /**
     * Send SMS with status callback
     */
    public function send_sms($to, $message, $media_url = null) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'Twilio not configured');
        }
        
        $to = ptp_comms_normalize_phone($to);
        if (!$to) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Messages.json";
        
        $data = array(
            'From' => $this->twilio_from,
            'To' => $to,
            'Body' => $message
        );
        
        // Add status callback URL for delivery tracking
        $status_callback = home_url('/ptp-comms/sms-status');
        $data['StatusCallback'] = $status_callback;
        
        // Add media URL for MMS
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
            return array('success' => false, 'error' => $body['message'], 'error_code' => $body['error_code']);
        }
        
        return array(
            'success' => true, 
            'sid' => $body['sid'], 
            'status' => $body['status'],
            'segments' => isset($body['num_segments']) ? $body['num_segments'] : 1
        );
    }
    
    /**
     * Send MMS with image
     */
    public function send_mms($to, $message, $media_url) {
        return $this->send_sms($to, $message, $media_url);
    }
    
    /**
     * Lookup phone number for validation and carrier info
     */
    public function lookup_phone($phone_number) {
        if (!$this->is_configured()) {
            return array('success' => false, 'error' => 'Twilio not configured');
        }
        
        $phone = ptp_comms_normalize_phone($phone_number);
        if (!$phone) {
            return array('success' => false, 'error' => 'Invalid phone number');
        }
        
        $url = "https://lookups.twilio.com/v2/PhoneNumbers/{$phone}?Fields=line_type_intelligence";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$this->twilio_sid}:{$this->twilio_token}")
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['code'])) {
            return array('success' => false, 'error' => $body['message']);
        }
        
        return array(
            'success' => true,
            'phone_number' => $body['phone_number'],
            'country_code' => $body['country_code'],
            'valid' => $body['valid'],
            'line_type' => isset($body['line_type_intelligence']['type']) ? $body['line_type_intelligence']['type'] : 'unknown',
            'carrier' => isset($body['line_type_intelligence']['carrier_name']) ? $body['line_type_intelligence']['carrier_name'] : ''
        );
    }
    
    /**
     * Get message status from Twilio
     */
    public function get_message_status($message_sid) {
        if (!$this->is_configured() || empty($message_sid)) {
            return null;
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Messages/{$message_sid}.json";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$this->twilio_sid}:{$this->twilio_token}")
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return array(
            'status' => isset($body['status']) ? $body['status'] : 'unknown',
            'error_code' => isset($body['error_code']) ? $body['error_code'] : null,
            'error_message' => isset($body['error_message']) ? $body['error_message'] : null,
            'date_sent' => isset($body['date_sent']) ? $body['date_sent'] : null
        );
    }
    
    /**
     * Get account balance
     */
    public function get_account_balance() {
        if (!$this->is_configured()) {
            return null;
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Balance.json";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$this->twilio_sid}:{$this->twilio_token}")
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return array(
            'balance' => isset($body['balance']) ? floatval($body['balance']) : 0,
            'currency' => isset($body['currency']) ? $body['currency'] : 'USD'
        );
    }
    
    public function is_configured() {
        return !empty($this->twilio_sid) && !empty($this->twilio_token) && !empty($this->twilio_from);
    }
}
