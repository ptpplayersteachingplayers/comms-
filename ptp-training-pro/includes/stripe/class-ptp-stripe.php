<?php
/**
 * Stripe Connect Integration
 * Payment processing and trainer payouts
 */

if (!defined('ABSPATH')) exit;

class PTP_Stripe {
    
    private static $secret_key;
    private static $publishable_key;
    private static $webhook_secret;
    private static $platform_fee_percent = 20;
    
    public static function init() {
        self::$secret_key = get_option('ptp_stripe_secret_key');
        self::$publishable_key = get_option('ptp_stripe_publishable_key');
        self::$webhook_secret = get_option('ptp_stripe_webhook_secret');
        
        $custom_fee = get_option('ptp_platform_fee_percent');
        if ($custom_fee) {
            self::$platform_fee_percent = floatval($custom_fee);
        }
    }
    
    /**
     * Make Stripe API request
     */
    private static function api_request($endpoint, $method = 'POST', $data = array()) {
        $url = 'https://api.stripe.com/v1/' . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . self::$secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30
        );
        
        if (!empty($data)) {
            $args['body'] = $data;
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Create Stripe Connect account for trainer
     */
    public static function create_connect_account($trainer_id) {
        global $wpdb;
        $trainer = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, u.user_email FROM {$wpdb->prefix}ptp_trainers t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             WHERE t.id = %d",
            $trainer_id
        ));
        
        if (!$trainer) {
            return new WP_Error('not_found', 'Trainer not found');
        }
        
        // Create Express account
        $account = self::api_request('accounts', 'POST', array(
            'type' => 'express',
            'email' => $trainer->user_email,
            'capabilities[card_payments][requested]' => 'true',
            'capabilities[transfers][requested]' => 'true',
            'business_type' => 'individual',
            'metadata[trainer_id]' => $trainer_id,
            'metadata[platform]' => 'ptp_training'
        ));
        
        if (is_wp_error($account) || isset($account['error'])) {
            return new WP_Error('stripe_error', $account['error']['message'] ?? 'Failed to create Stripe account');
        }
        
        // Save account ID
        $wpdb->update(
            "{$wpdb->prefix}ptp_trainers",
            array('stripe_account_id' => $account['id']),
            array('id' => $trainer_id)
        );
        
        return $account['id'];
    }
    
    /**
     * Create onboarding link for Connect account
     */
    public static function create_connect_account_link($trainer_id) {
        global $wpdb;
        $trainer = PTP_Database::get_trainer($trainer_id);
        
        if (!$trainer) {
            return new WP_Error('not_found', 'Trainer not found');
        }
        
        // Create account if doesn't exist
        if (!$trainer->stripe_account_id) {
            $account_id = self::create_connect_account($trainer_id);
            if (is_wp_error($account_id)) {
                return $account_id;
            }
        } else {
            $account_id = $trainer->stripe_account_id;
        }
        
        // Create account link
        $link = self::api_request('account_links', 'POST', array(
            'account' => $account_id,
            'refresh_url' => home_url('/trainer-dashboard/?stripe=refresh'),
            'return_url' => home_url('/trainer-dashboard/?stripe=complete'),
            'type' => 'account_onboarding'
        ));
        
        if (is_wp_error($link) || isset($link['error'])) {
            return new WP_Error('stripe_error', $link['error']['message'] ?? 'Failed to create account link');
        }
        
        return $link['url'];
    }
    
    /**
     * Check if trainer's Stripe account is fully onboarded
     */
    public static function is_onboarding_complete($trainer_id) {
        global $wpdb;
        $trainer = PTP_Database::get_trainer($trainer_id);
        
        if (!$trainer || !$trainer->stripe_account_id) {
            return false;
        }
        
        $account = self::api_request('accounts/' . $trainer->stripe_account_id, 'GET');
        
        if (is_wp_error($account) || isset($account['error'])) {
            return false;
        }
        
        $complete = $account['charges_enabled'] && $account['payouts_enabled'];
        
        // Update database
        if ($complete && !$trainer->stripe_onboarding_complete) {
            $wpdb->update(
                "{$wpdb->prefix}ptp_trainers",
                array('stripe_onboarding_complete' => 1),
                array('id' => $trainer_id)
            );
        }
        
        return $complete;
    }
    
    /**
     * Create checkout session for lesson pack purchase
     */
    public static function create_checkout_session($data) {
        $trainer = PTP_Database::get_trainer($data['trainer_id']);
        
        if (!$trainer || !$trainer->stripe_account_id) {
            return new WP_Error('stripe_error', 'Trainer payment not configured');
        }
        
        // Calculate platform fee
        $platform_fee = round($data['price'] * (self::$platform_fee_percent / 100) * 100);
        
        // Build line item description
        $pack_names = array(
            'single' => 'Single Training Session',
            'pack_4' => '4-Session Training Pack',
            'pack_8' => '8-Session Training Pack'
        );
        
        $description = $pack_names[$data['pack_type']] ?? 'Training Sessions';
        $description .= ' with ' . $trainer->display_name;
        
        // Create checkout session
        $session_data = array(
            'mode' => 'payment',
            'success_url' => home_url('/my-training/?purchase=success&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => home_url('/trainer/' . $trainer->slug . '/?purchase=cancelled'),
            'customer_email' => wp_get_current_user()->user_email,
            'payment_intent_data[application_fee_amount]' => $platform_fee,
            'payment_intent_data[transfer_data][destination]' => $trainer->stripe_account_id,
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][unit_amount]' => round($data['price'] * 100),
            'line_items[0][price_data][product_data][name]' => $description,
            'line_items[0][price_data][product_data][description]' => $data['sessions'] . ' session(s) for ' . $data['athlete_name'],
            'line_items[0][quantity]' => 1,
            'metadata[trainer_id]' => $data['trainer_id'],
            'metadata[customer_id]' => $data['customer_id'],
            'metadata[pack_type]' => $data['pack_type'],
            'metadata[sessions]' => $data['sessions'],
            'metadata[athlete_name]' => $data['athlete_name'],
            'metadata[athlete_age]' => $data['athlete_age'],
            'metadata[athlete_skill]' => $data['athlete_skill'],
            'metadata[athlete_goals]' => $data['athlete_goals']
        );
        
        $session = self::api_request('checkout/sessions', 'POST', $session_data);
        
        if (is_wp_error($session) || isset($session['error'])) {
            return new WP_Error('stripe_error', $session['error']['message'] ?? 'Failed to create checkout session');
        }
        
        return array(
            'url' => $session['url'],
            'session_id' => $session['id']
        );
    }
    
    /**
     * Handle Stripe webhook
     */
    public static function handle_webhook($request) {
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');
        
        // Verify webhook signature
        if (self::$webhook_secret) {
            $timestamp = null;
            $signatures = array();
            
            foreach (explode(',', $sig_header) as $item) {
                $parts = explode('=', $item, 2);
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
            
            if (!$timestamp || empty($signatures)) {
                return new WP_Error('invalid_signature', 'Invalid webhook signature', array('status' => 400));
            }
            
            $signed_payload = $timestamp . '.' . $payload;
            $expected_sig = hash_hmac('sha256', $signed_payload, self::$webhook_secret);
            
            $valid = false;
            foreach ($signatures as $sig) {
                if (hash_equals($expected_sig, $sig)) {
                    $valid = true;
                    break;
                }
            }
            
            if (!$valid) {
                return new WP_Error('invalid_signature', 'Invalid webhook signature', array('status' => 400));
            }
        }
        
        $event = json_decode($payload, true);
        
        switch ($event['type']) {
            case 'checkout.session.completed':
                self::handle_checkout_completed($event['data']['object']);
                break;
                
            case 'account.updated':
                self::handle_account_updated($event['data']['object']);
                break;
                
            case 'payment_intent.succeeded':
                // Already handled by checkout.session.completed
                break;
        }
        
        return rest_ensure_response(array('received' => true));
    }
    
    /**
     * Handle successful checkout
     */
    private static function handle_checkout_completed($session) {
        global $wpdb;
        
        $metadata = $session['metadata'];
        
        // Create lesson pack
        $pack_data = array(
            'customer_id' => $metadata['customer_id'],
            'trainer_id' => $metadata['trainer_id'],
            'pack_type' => $metadata['pack_type'],
            'total_sessions' => $metadata['sessions'],
            'sessions_used' => 0,
            'sessions_remaining' => $metadata['sessions'],
            'price_paid' => $session['amount_total'] / 100,
            'price_per_session' => ($session['amount_total'] / 100) / $metadata['sessions'],
            'athlete_name' => $metadata['athlete_name'],
            'athlete_age' => $metadata['athlete_age'],
            'athlete_skill_level' => $metadata['athlete_skill'],
            'athlete_goals' => $metadata['athlete_goals'],
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+6 months'))
        );
        
        $wpdb->insert("{$wpdb->prefix}ptp_lesson_packs", $pack_data);
        $pack_id = $wpdb->insert_id;
        
        // Create placeholder sessions
        for ($i = 0; $i < $metadata['sessions']; $i++) {
            $wpdb->insert("{$wpdb->prefix}ptp_sessions", array(
                'pack_id' => $pack_id,
                'trainer_id' => $metadata['trainer_id'],
                'customer_id' => $metadata['customer_id'],
                'session_date' => '0000-00-00',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'status' => 'unscheduled'
            ));
        }
        
        // Send confirmation emails
        self::send_purchase_confirmation($pack_id);
        self::send_trainer_notification($pack_id);
    }
    
    /**
     * Handle Connect account update
     */
    private static function handle_account_updated($account) {
        global $wpdb;
        
        if ($account['charges_enabled'] && $account['payouts_enabled']) {
            $wpdb->update(
                "{$wpdb->prefix}ptp_trainers",
                array('stripe_onboarding_complete' => 1),
                array('stripe_account_id' => $account['id'])
            );
        }
    }
    
    /**
     * Process payout to trainer
     */
    public static function process_payout($payout_id) {
        global $wpdb;
        
        $payout = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, t.stripe_account_id FROM {$wpdb->prefix}ptp_payouts p
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON p.trainer_id = t.id
             WHERE p.id = %d",
            $payout_id
        ));
        
        if (!$payout || !$payout->stripe_account_id) {
            return new WP_Error('invalid_payout', 'Invalid payout or trainer not connected');
        }
        
        // Create transfer
        $transfer = self::api_request('transfers', 'POST', array(
            'amount' => round($payout->trainer_payout * 100),
            'currency' => 'usd',
            'destination' => $payout->stripe_account_id,
            'metadata[payout_id]' => $payout_id,
            'metadata[session_id]' => $payout->session_id
        ));
        
        if (is_wp_error($transfer) || isset($transfer['error'])) {
            return new WP_Error('transfer_failed', $transfer['error']['message'] ?? 'Transfer failed');
        }
        
        // Update payout record
        $wpdb->update(
            "{$wpdb->prefix}ptp_payouts",
            array(
                'stripe_transfer_id' => $transfer['id'],
                'status' => 'paid',
                'paid_at' => current_time('mysql')
            ),
            array('id' => $payout_id)
        );
        
        return $transfer['id'];
    }
    
    /**
     * Get trainer's Stripe dashboard link
     */
    public static function get_dashboard_link($trainer_id) {
        $trainer = PTP_Database::get_trainer($trainer_id);
        
        if (!$trainer || !$trainer->stripe_account_id) {
            return null;
        }
        
        $link = self::api_request('accounts/' . $trainer->stripe_account_id . '/login_links', 'POST');
        
        if (is_wp_error($link) || isset($link['error'])) {
            return null;
        }
        
        return $link['url'];
    }
    
    /**
     * Get trainer balance
     */
    public static function get_trainer_balance($trainer_id) {
        $trainer = PTP_Database::get_trainer($trainer_id);
        
        if (!$trainer || !$trainer->stripe_account_id) {
            return null;
        }
        
        $balance = self::api_request('balance', 'GET');
        
        // This gets platform balance, for connected account balance we need:
        $response = wp_remote_get('https://api.stripe.com/v1/balance', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . self::$secret_key,
                'Stripe-Account' => $trainer->stripe_account_id
            )
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Email helpers
     */
    private static function send_purchase_confirmation($pack_id) {
        $pack = PTP_Database::get_pack($pack_id);
        $customer = get_user_by('ID', $pack->customer_id);
        
        $subject = 'Training Package Confirmed - ' . $pack->trainer_name;
        $message = "Hi {$customer->display_name},\n\n";
        $message .= "Your training package with {$pack->trainer_name} has been confirmed!\n\n";
        $message .= "Package Details:\n";
        $message .= "- Sessions: {$pack->total_sessions}\n";
        $message .= "- Athlete: {$pack->athlete_name}\n";
        $message .= "- Amount Paid: $" . number_format($pack->price_paid, 2) . "\n\n";
        $message .= "Next Steps:\n";
        $message .= "1. Visit your dashboard to schedule your first session\n";
        $message .= "2. Your trainer will confirm the time and location\n\n";
        $message .= "Schedule your sessions: " . home_url('/my-training/') . "\n\n";
        $message .= "Questions? Reply to this email.\n\n";
        $message .= "See you on the field!\nThe PTP Team";
        
        wp_mail($customer->user_email, $subject, $message);
    }
    
    private static function send_trainer_notification($pack_id) {
        $pack = PTP_Database::get_pack($pack_id);
        
        global $wpdb;
        $trainer_email = $wpdb->get_var($wpdb->prepare(
            "SELECT u.user_email FROM {$wpdb->prefix}ptp_trainers t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             WHERE t.id = %d",
            $pack->trainer_id
        ));
        
        $subject = 'New Booking! ' . $pack->athlete_name . ' - ' . $pack->total_sessions . ' Sessions';
        $message = "Great news! You have a new training package booking.\n\n";
        $message .= "Athlete: {$pack->athlete_name} (Age {$pack->athlete_age})\n";
        $message .= "Skill Level: {$pack->athlete_skill_level}\n";
        $message .= "Sessions: {$pack->total_sessions}\n";
        $message .= "Goals: {$pack->athlete_goals}\n\n";
        $message .= "Earnings: $" . number_format($pack->price_paid * 0.8, 2) . "\n\n";
        $message .= "The customer will schedule their first session soon. You'll be notified when they do.\n\n";
        $message .= "View in dashboard: " . home_url('/trainer-dashboard/') . "\n\n";
        $message .= "Keep up the great work!\nThe PTP Team";
        
        wp_mail($trainer_email, $subject, $message);
    }
    
    /**
     * Get publishable key for frontend
     */
    public static function get_publishable_key() {
        return self::$publishable_key;
    }
}

PTP_Stripe::init();
