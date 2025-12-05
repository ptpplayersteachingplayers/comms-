<?php
/**
 * Twilio SMS Integration
 * Session reminders, post-training check-ins, notifications, Google Review requests
 * 
 * Automated Flow:
 * 1. 24hr before session → Reminder to parent
 * 2. 2hr before session → Final reminder to parent + trainer
 * 3. 1hr after session → Post-training check-in to parent
 * 4. 24hr after session → Google Review request
 * 5. Booking confirmation → Immediate
 * 6. Cancellation → Immediate
 */

if (!defined('ABSPATH')) exit;

class PTP_Twilio {
    
    private static $account_sid;
    private static $auth_token;
    private static $phone_number;
    private static $google_review_url;
    
    /**
     * Initialize Twilio
     */
    public static function init() {
        self::$account_sid = get_option('ptp_twilio_sid');
        self::$auth_token = get_option('ptp_twilio_token');
        self::$phone_number = get_option('ptp_twilio_phone');
        self::$google_review_url = get_option('ptp_google_review_url');
        
        // Register cron hooks
        add_action('ptp_send_session_reminders', array(__CLASS__, 'process_24hr_reminders'));
        add_action('ptp_send_final_reminders', array(__CLASS__, 'process_2hr_reminders'));
        add_action('ptp_send_post_training_checkins', array(__CLASS__, 'process_post_training_checkins'));
        add_action('ptp_send_review_requests', array(__CLASS__, 'process_review_requests'));
        
        // Schedule cron events if not scheduled
        if (!wp_next_scheduled('ptp_send_session_reminders')) {
            wp_schedule_event(time(), 'hourly', 'ptp_send_session_reminders');
        }
        if (!wp_next_scheduled('ptp_send_final_reminders')) {
            wp_schedule_event(time(), 'fifteen_minutes', 'ptp_send_final_reminders');
        }
        if (!wp_next_scheduled('ptp_send_post_training_checkins')) {
            wp_schedule_event(time(), 'hourly', 'ptp_send_post_training_checkins');
        }
        if (!wp_next_scheduled('ptp_send_review_requests')) {
            wp_schedule_event(time(), 'daily', 'ptp_send_review_requests');
        }
        
        // Add custom cron interval
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_intervals'));
    }
    
    /**
     * Add custom cron intervals
     */
    public static function add_cron_intervals($schedules) {
        $schedules['fifteen_minutes'] = array(
            'interval' => 900,
            'display' => 'Every 15 Minutes'
        );
        return $schedules;
    }
    
    /**
     * Check if Twilio is configured
     */
    public static function is_configured() {
        return !empty(self::$account_sid) && !empty(self::$auth_token) && !empty(self::$phone_number);
    }
    
    /**
     * Send SMS via Twilio
     */
    public static function send_sms($to, $message) {
        if (!self::is_configured()) {
            return new WP_Error('twilio_not_configured', 'Twilio is not configured');
        }
        
        // Format phone number
        $to = self::format_phone($to);
        if (!$to) {
            return new WP_Error('invalid_phone', 'Invalid phone number');
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/" . self::$account_sid . "/Messages.json";
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode(self::$account_sid . ':' . self::$auth_token)
            ),
            'body' => array(
                'From' => self::$phone_number,
                'To' => $to,
                'Body' => $message
            )
        ));
        
        if (is_wp_error($response)) {
            self::log_sms($to, $message, 'failed', $response->get_error_message());
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error_code'])) {
            self::log_sms($to, $message, 'failed', $body['message']);
            return new WP_Error('twilio_error', $body['message']);
        }
        
        self::log_sms($to, $message, 'sent', $body['sid']);
        return $body;
    }
    
    /**
     * Format phone number to E.164
     */
    private static function format_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add US country code if needed
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }
        
        // Validate length
        if (strlen($phone) !== 11 || $phone[0] !== '1') {
            return false;
        }
        
        return '+' . $phone;
    }
    
    /**
     * Log SMS for tracking
     */
    private static function log_sms($to, $message, $status, $reference = '') {
        global $wpdb;
        
        // Create log table if not exists
        $table = $wpdb->prefix . 'ptp_sms_log';
        
        $wpdb->insert($table, array(
            'phone_number' => $to,
            'message' => $message,
            'status' => $status,
            'reference' => $reference,
            'created_at' => current_time('mysql')
        ));
    }
    
    // ================================================
    // AUTOMATED MESSAGE FLOWS
    // ================================================
    
    /**
     * 24-hour session reminder to parent
     */
    public static function process_24hr_reminders() {
        global $wpdb;
        
        // Get sessions happening in 23-25 hours that haven't had 24hr reminder sent
        $sessions = $wpdb->get_results(
            "SELECT s.*, p.athlete_name, t.display_name as trainer_name, 
                    u.user_email, um.meta_value as phone
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
             WHERE s.status = 'scheduled'
             AND s.reminder_24hr_sent = 0
             AND CONCAT(s.session_date, ' ', s.start_time) BETWEEN 
                 DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)"
        );
        
        foreach ($sessions as $session) {
            if (empty($session->phone)) continue;
            
            $time = date('g:i A', strtotime($session->start_time));
            $date = date('l, F j', strtotime($session->session_date));
            
            $message = "⚽ PTP Training Reminder\n\n";
            $message .= "{$session->athlete_name}'s session with {$session->trainer_name} is tomorrow!\n\n";
            $message .= "📅 {$date}\n";
            $message .= "🕐 {$time}\n\n";
            $message .= "Please arrive 5-10 minutes early. Reply HELP for assistance.";
            
            $result = self::send_sms($session->phone, $message);
            
            if (!is_wp_error($result)) {
                $wpdb->update(
                    "{$wpdb->prefix}ptp_sessions",
                    array('reminder_24hr_sent' => 1),
                    array('id' => $session->id)
                );
            }
        }
    }
    
    /**
     * 2-hour final reminder to parent AND trainer
     */
    public static function process_2hr_reminders() {
        global $wpdb;
        
        // Get sessions happening in 1.75-2.25 hours
        $sessions = $wpdb->get_results(
            "SELECT s.*, p.athlete_name, t.display_name as trainer_name, t.user_id as trainer_user_id,
                    tl.address as location_address, tl.name as location_name,
                    u.user_email, um.meta_value as parent_phone
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->prefix}ptp_trainer_locations tl ON s.location_id = tl.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
             WHERE s.status = 'scheduled'
             AND s.reminder_2hr_sent = 0
             AND CONCAT(s.session_date, ' ', s.start_time) BETWEEN 
                 DATE_ADD(NOW(), INTERVAL 105 MINUTE) AND DATE_ADD(NOW(), INTERVAL 135 MINUTE)"
        );
        
        foreach ($sessions as $session) {
            $time = date('g:i A', strtotime($session->start_time));
            $location = $session->location_name ?: $session->location_address ?: 'TBD';
            
            // Send to parent
            if (!empty($session->parent_phone)) {
                $parent_msg = "⚽ Session in 2 hours!\n\n";
                $parent_msg .= "{$session->athlete_name} with {$session->trainer_name}\n";
                $parent_msg .= "🕐 {$time}\n";
                $parent_msg .= "📍 {$location}\n\n";
                $parent_msg .= "See you soon!";
                
                self::send_sms($session->parent_phone, $parent_msg);
            }
            
            // Send to trainer
            $trainer_phone = get_user_meta($session->trainer_user_id, 'billing_phone', true);
            if (!empty($trainer_phone)) {
                $trainer_msg = "⚽ Session in 2 hours!\n\n";
                $trainer_msg .= "Athlete: {$session->athlete_name}\n";
                $trainer_msg .= "🕐 {$time}\n";
                $trainer_msg .= "📍 {$location}";
                
                self::send_sms($trainer_phone, $trainer_msg);
            }
            
            $wpdb->update(
                "{$wpdb->prefix}ptp_sessions",
                array('reminder_2hr_sent' => 1),
                array('id' => $session->id)
            );
        }
    }
    
    /**
     * Post-training check-in (1 hour after session)
     */
    public static function process_post_training_checkins() {
        global $wpdb;
        
        // Get sessions completed 45min-1.5hr ago
        $sessions = $wpdb->get_results(
            "SELECT s.*, p.athlete_name, t.display_name as trainer_name,
                    u.display_name as parent_name, um.meta_value as phone
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
             WHERE s.status = 'completed'
             AND s.checkin_sent = 0
             AND s.completed_at BETWEEN 
                 DATE_SUB(NOW(), INTERVAL 90 MINUTE) AND DATE_SUB(NOW(), INTERVAL 45 MINUTE)"
        );
        
        foreach ($sessions as $session) {
            if (empty($session->phone)) continue;
            
            $message = "Hi {$session->parent_name}! 👋\n\n";
            $message .= "How was {$session->athlete_name}'s session with {$session->trainer_name}?\n\n";
            $message .= "Reply:\n";
            $message .= "⭐ GREAT - Loved it!\n";
            $message .= "👍 GOOD - Went well\n";
            $message .= "🤔 OK - Could be better\n\n";
            $message .= "Your feedback helps us improve!";
            
            $result = self::send_sms($session->phone, $message);
            
            if (!is_wp_error($result)) {
                $wpdb->update(
                    "{$wpdb->prefix}ptp_sessions",
                    array('checkin_sent' => 1),
                    array('id' => $session->id)
                );
            }
        }
    }
    
    /**
     * Google Review request (24 hours after session)
     */
    public static function process_review_requests() {
        if (empty(self::$google_review_url)) return;
        
        global $wpdb;
        
        // Get sessions completed 22-26 hours ago
        $sessions = $wpdb->get_results(
            "SELECT s.*, p.athlete_name, t.display_name as trainer_name,
                    u.display_name as parent_name, um.meta_value as phone
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
             WHERE s.status = 'completed'
             AND s.review_request_sent = 0
             AND s.completed_at BETWEEN 
                 DATE_SUB(NOW(), INTERVAL 26 HOUR) AND DATE_SUB(NOW(), INTERVAL 22 HOUR)"
        );
        
        foreach ($sessions as $session) {
            if (empty($session->phone)) continue;
            
            // Check if customer already left review
            $has_review = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_reviews 
                 WHERE customer_id = %d AND trainer_id = %d",
                $session->customer_id, $session->trainer_id
            ));
            
            if ($has_review) {
                $wpdb->update(
                    "{$wpdb->prefix}ptp_sessions",
                    array('review_request_sent' => 1),
                    array('id' => $session->id)
                );
                continue;
            }
            
            $message = "Hi {$session->parent_name}! ⭐\n\n";
            $message .= "We hope {$session->athlete_name} enjoyed training with PTP!\n\n";
            $message .= "Would you take 30 seconds to leave us a Google review? It helps other families find great training.\n\n";
            $message .= self::$google_review_url . "\n\n";
            $message .= "Thank you! 🙏";
            
            $result = self::send_sms($session->phone, $message);
            
            if (!is_wp_error($result)) {
                $wpdb->update(
                    "{$wpdb->prefix}ptp_sessions",
                    array('review_request_sent' => 1),
                    array('id' => $session->id)
                );
            }
        }
    }
    
    // ================================================
    // IMMEDIATE NOTIFICATIONS
    // ================================================
    
    /**
     * Booking confirmation
     */
    public static function send_booking_confirmation($pack_id) {
        global $wpdb;
        
        $pack = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, t.display_name as trainer_name, 
                    u.display_name as parent_name, um.meta_value as phone
             FROM {$wpdb->prefix}ptp_lesson_packs p
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON p.trainer_id = t.id
             LEFT JOIN {$wpdb->users} u ON p.customer_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
             WHERE p.id = %d",
            $pack_id
        ));
        
        if (!$pack || empty($pack->phone)) return;
        
        $sessions_text = $pack->total_sessions == 1 ? '1 session' : $pack->total_sessions . ' sessions';
        
        $message = "🎉 Booking Confirmed!\n\n";
        $message .= "Hi {$pack->parent_name}, your training package is ready!\n\n";
        $message .= "👤 Trainer: {$pack->trainer_name}\n";
        $message .= "⚽ Athlete: {$pack->athlete_name}\n";
        $message .= "📦 Package: {$sessions_text}\n\n";
        $message .= "Schedule your first session at:\n";
        $message .= home_url('/my-training/') . "\n\n";
        $message .= "Questions? Reply to this message.";
        
        return self::send_sms($pack->phone, $message);
    }
    
    /**
     * Session scheduled notification
     */
    public static function send_session_scheduled($session_id) {
        global $wpdb;
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, p.athlete_name, t.display_name as trainer_name,
                    tl.name as location_name, tl.address as location_address,
                    u.display_name as parent_name, um.meta_value as phone
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->prefix}ptp_trainer_locations tl ON s.location_id = tl.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
             WHERE s.id = %d",
            $session_id
        ));
        
        if (!$session || empty($session->phone)) return;
        
        $date = date('l, F j', strtotime($session->session_date));
        $time = date('g:i A', strtotime($session->start_time));
        $location = $session->location_name ?: $session->location_address ?: 'TBD';
        
        $message = "✅ Session Scheduled!\n\n";
        $message .= "{$session->athlete_name} with {$session->trainer_name}\n\n";
        $message .= "📅 {$date}\n";
        $message .= "🕐 {$time}\n";
        $message .= "📍 {$location}\n\n";
        $message .= "We'll send a reminder before the session!";
        
        return self::send_sms($session->phone, $message);
    }
    
    /**
     * Session cancelled notification
     */
    public static function send_session_cancelled($session_id, $reason = '') {
        global $wpdb;
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, p.athlete_name, t.display_name as trainer_name,
                    u.display_name as parent_name, um.meta_value as phone
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
             WHERE s.id = %d",
            $session_id
        ));
        
        if (!$session || empty($session->phone)) return;
        
        $date = date('l, F j', strtotime($session->session_date));
        $time = date('g:i A', strtotime($session->start_time));
        
        $message = "❌ Session Cancelled\n\n";
        $message .= "{$session->athlete_name}'s session on {$date} at {$time} has been cancelled.\n\n";
        
        if ($reason) {
            $message .= "Reason: {$reason}\n\n";
        }
        
        $message .= "Reschedule at:\n";
        $message .= home_url('/my-training/');
        
        return self::send_sms($session->phone, $message);
    }
    
    /**
     * Notify trainer of new booking
     */
    public static function notify_trainer_new_booking($pack_id) {
        global $wpdb;
        
        $pack = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, t.user_id as trainer_user_id, t.display_name as trainer_name,
                    u.display_name as parent_name
             FROM {$wpdb->prefix}ptp_lesson_packs p
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON p.trainer_id = t.id
             LEFT JOIN {$wpdb->users} u ON p.customer_id = u.ID
             WHERE p.id = %d",
            $pack_id
        ));
        
        if (!$pack) return;
        
        $trainer_phone = get_user_meta($pack->trainer_user_id, 'billing_phone', true);
        if (empty($trainer_phone)) return;
        
        $sessions_text = $pack->total_sessions == 1 ? '1 session' : $pack->total_sessions . ' sessions';
        $earnings = number_format($pack->price_paid * 0.8, 0); // 80% to trainer
        
        $message = "💰 New Booking!\n\n";
        $message .= "Athlete: {$pack->athlete_name}\n";
        $message .= "Parent: {$pack->parent_name}\n";
        $message .= "Package: {$sessions_text}\n";
        $message .= "Your earnings: \${$earnings}\n\n";
        $message .= "View details in your dashboard:\n";
        $message .= home_url('/trainer-dashboard/');
        
        return self::send_sms($trainer_phone, $message);
    }
    
    // ================================================
    // CAMP/CLINIC REFERRAL SMS
    // ================================================
    
    /**
     * Send camp/clinic referral to trainer's clients
     */
    public static function send_camp_referral($trainer_id, $camp_name, $camp_url, $discount_code = '') {
        global $wpdb;
        
        $trainer = PTP_Database::get_trainer($trainer_id);
        if (!$trainer) return array('sent' => 0, 'failed' => 0);
        
        // Get all customers who have trained with this trainer
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.ID, u.display_name, um.meta_value as phone
             FROM {$wpdb->prefix}ptp_lesson_packs p
             LEFT JOIN {$wpdb->users} u ON p.customer_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
             WHERE p.trainer_id = %d AND um.meta_value IS NOT NULL",
            $trainer_id
        ));
        
        $sent = 0;
        $failed = 0;
        
        foreach ($customers as $customer) {
            if (empty($customer->phone)) {
                $failed++;
                continue;
            }
            
            $message = "⚽ Special from {$trainer->display_name}!\n\n";
            $message .= "I wanted to share this with my training families:\n\n";
            $message .= "🏕️ {$camp_name}\n\n";
            
            if ($discount_code) {
                $message .= "Use code {$discount_code} for a special discount!\n\n";
            }
            
            $message .= "Register: {$camp_url}\n\n";
            $message .= "See you on the field!";
            
            $result = self::send_sms($customer->phone, $message);
            
            if (is_wp_error($result)) {
                $failed++;
            } else {
                $sent++;
            }
        }
        
        return array('sent' => $sent, 'failed' => $failed);
    }
    
    // ================================================
    // INBOUND SMS HANDLING
    // ================================================
    
    /**
     * Handle inbound SMS webhook from Twilio
     */
    public static function handle_inbound_sms($data) {
        $from = isset($data['From']) ? $data['From'] : '';
        $body = isset($data['Body']) ? strtoupper(trim($data['Body'])) : '';
        
        // Log inbound
        self::log_sms($from, 'INBOUND: ' . $body, 'received');
        
        // Handle check-in responses
        if (in_array($body, array('GREAT', 'GOOD', 'OK'))) {
            self::handle_checkin_response($from, $body);
            return;
        }
        
        // Handle STOP/unsubscribe
        if (in_array($body, array('STOP', 'UNSUBSCRIBE', 'CANCEL'))) {
            self::handle_unsubscribe($from);
            return;
        }
        
        // Handle HELP
        if ($body === 'HELP') {
            self::send_sms($from, "PTP Soccer Training\n\nFor support, email info@ptpsummercamps.com or call (555) 123-4567.\n\nReply STOP to unsubscribe.");
            return;
        }
        
        // Default: forward to support
        self::forward_to_support($from, $body);
    }
    
    /**
     * Handle check-in response
     */
    private static function handle_checkin_response($phone, $response) {
        global $wpdb;
        
        // Find most recent session for this phone
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT s.id, s.trainer_id FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->users} u ON p.customer_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_phone'
             WHERE um.meta_value LIKE %s
             AND s.status = 'completed'
             AND s.checkin_sent = 1
             ORDER BY s.completed_at DESC
             LIMIT 1",
            '%' . substr($phone, -10) . '%'
        ));
        
        if (!$session) return;
        
        // Store feedback
        $wpdb->update(
            "{$wpdb->prefix}ptp_sessions",
            array('checkin_response' => $response),
            array('id' => $session->id)
        );
        
        // Send thank you
        $thank_you = "Thanks for the feedback! ";
        if ($response === 'GREAT') {
            $thank_you .= "We're so glad to hear it! 🎉";
        } elseif ($response === 'GOOD') {
            $thank_you .= "We're happy it went well! 👍";
        } else {
            $thank_you .= "We appreciate you letting us know. Our team will follow up.";
            
            // Alert admin about OK response
            self::alert_admin_checkin($session->id, $response);
        }
        
        self::send_sms($phone, $thank_you);
    }
    
    /**
     * Handle unsubscribe
     */
    private static function handle_unsubscribe($phone) {
        global $wpdb;
        
        // Find user by phone
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'billing_phone' AND meta_value LIKE %s",
            '%' . substr($phone, -10) . '%'
        ));
        
        if ($user_id) {
            update_user_meta($user_id, 'ptp_sms_opted_out', 1);
        }
        
        self::send_sms($phone, "You've been unsubscribed from PTP SMS notifications. Reply START to re-subscribe.");
    }
    
    /**
     * Forward unknown message to support
     */
    private static function forward_to_support($from, $message) {
        $admin_email = get_option('ptp_admin_email', get_option('admin_email'));
        
        wp_mail(
            $admin_email,
            'PTP SMS - Inbound Message',
            "From: {$from}\n\nMessage: {$message}\n\nReply via the Twilio console or call the customer."
        );
    }
    
    /**
     * Alert admin about concerning check-in response
     */
    private static function alert_admin_checkin($session_id, $response) {
        global $wpdb;
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, p.athlete_name, t.display_name as trainer_name,
                    u.display_name as parent_name, u.user_email
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             WHERE s.id = %d",
            $session_id
        ));
        
        if (!$session) return;
        
        $admin_email = get_option('ptp_admin_email', get_option('admin_email'));
        
        $subject = "⚠️ Session Feedback: {$response} - Follow up needed";
        
        $body = "A customer indicated their session could have been better.\n\n";
        $body .= "Response: {$response}\n";
        $body .= "Parent: {$session->parent_name} ({$session->user_email})\n";
        $body .= "Athlete: {$session->athlete_name}\n";
        $body .= "Trainer: {$session->trainer_name}\n";
        $body .= "Session Date: {$session->session_date}\n\n";
        $body .= "Please follow up with the family.";
        
        wp_mail($admin_email, $subject, $body);
    }
}

// Initialize
add_action('init', array('PTP_Twilio', 'init'));
