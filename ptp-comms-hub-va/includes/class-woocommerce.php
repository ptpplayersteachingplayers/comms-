<?php
/**
 * Enhanced WooCommerce Integration for PTP Comms Hub
 * Tracks products, camps, registrations, and child information
 * Version: 3.6.3 - Fixed Google Sheets sync to include ALL orders (21 vs 20 fix)
 */
class PTP_Comms_Hub_WooCommerce {
    
    /**
     * Initialize WooCommerce hooks
     */
    public static function init() {
        // AJAX handlers must be registered regardless of WooCommerce status
        // because during AJAX requests, WooCommerce might not be fully loaded yet
        add_action('wp_ajax_ptp_sync_single_order', array(__CLASS__, 'ajax_sync_single_order'));
        add_action('wp_ajax_ptp_sync_all_orders', array(__CLASS__, 'ajax_sync_all_orders'));
        add_action('wp_ajax_ptp_get_camp_registrations', array(__CLASS__, 'ajax_get_camp_registrations'));
        add_action('wp_ajax_ptp_sync_google_sheets_now', array(__CLASS__, 'ajax_sync_google_sheets'));
        add_action('wp_ajax_ptp_test_google_sheets', array(__CLASS__, 'ajax_test_google_sheets'));
        
        // Only run WooCommerce-specific hooks if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Order hooks - use priority 20 to run after WooCommerce
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'process_order'), 20, 1);
        add_action('woocommerce_order_status_processing', array(__CLASS__, 'process_order'), 20, 1);
        add_action('woocommerce_order_status_on-hold', array(__CLASS__, 'process_order'), 20, 1);
        add_action('woocommerce_order_status_cancelled', array(__CLASS__, 'handle_cancellation'), 20, 1);
        add_action('woocommerce_order_status_refunded', array(__CLASS__, 'handle_refund'), 20, 1);
        
        // New order created hook - catches orders immediately
        add_action('woocommerce_new_order', array(__CLASS__, 'on_new_order'), 20, 1);
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'on_checkout_processed'), 20, 1);
        
        // Checkout hooks - capture additional data
        add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'save_checkout_fields'), 10, 1);
        
        // Add custom fields to checkout
        add_filter('woocommerce_checkout_fields', array(__CLASS__, 'add_checkout_fields'));
        
        // Add SMS opt-in to checkout
        add_action('woocommerce_after_checkout_billing_form', array(__CLASS__, 'add_sms_optin_checkbox'));
        add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'save_sms_optin'), 10, 1);
        
        // Product meta box for camp details
        add_action('woocommerce_product_options_general_product_data', array(__CLASS__, 'add_product_camp_fields'));
        add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_camp_fields'));
        
        // Order meta box for PTP status
        add_action('add_meta_boxes', array(__CLASS__, 'add_order_meta_box'));
        
        // Admin columns for products
        add_filter('manage_edit-product_columns', array(__CLASS__, 'add_product_columns'));
        add_action('manage_product_posts_custom_column', array(__CLASS__, 'render_product_columns'), 10, 2);
        
        // Cron for event reminders
        add_action('ptp_comms_check_event_reminders', array(__CLASS__, 'check_event_reminders'));
        
        // Schedule reminder check if not already scheduled
        if (!wp_next_scheduled('ptp_comms_check_event_reminders')) {
            wp_schedule_event(time(), 'hourly', 'ptp_comms_check_event_reminders');
        }
        
        // Google Sheets sync cron
        add_action('ptp_comms_sync_google_sheets', array(__CLASS__, 'sync_to_google_sheets'));
        if (!wp_next_scheduled('ptp_comms_sync_google_sheets')) {
            wp_schedule_event(time(), 'hourly', 'ptp_comms_sync_google_sheets');
        }
    }
    
    /**
     * On new order created
     */
    public static function on_new_order($order_id) {
        error_log('[PTP WooCommerce] New order created: ' . $order_id);
        self::process_order($order_id);
    }
    
    /**
     * On checkout processed
     */
    public static function on_checkout_processed($order_id) {
        error_log('[PTP WooCommerce] Checkout processed: ' . $order_id);
        self::process_order($order_id);
    }
    
    /**
     * Add custom checkout fields for child information
     */
    public static function add_checkout_fields($fields) {
        $fields['ptp_camper'] = array(
            'ptp_child_first_name' => array(
                'type' => 'text',
                'label' => __('Camper First Name', 'ptp-comms-hub'),
                'placeholder' => __('Child\'s first name', 'ptp-comms-hub'),
                'required' => true,
                'class' => array('form-row-first'),
                'priority' => 10,
            ),
            'ptp_child_last_name' => array(
                'type' => 'text',
                'label' => __('Camper Last Name', 'ptp-comms-hub'),
                'placeholder' => __('Child\'s last name', 'ptp-comms-hub'),
                'required' => true,
                'class' => array('form-row-last'),
                'priority' => 20,
            ),
            'ptp_child_age' => array(
                'type' => 'number',
                'label' => __('Camper Age', 'ptp-comms-hub'),
                'placeholder' => __('Age', 'ptp-comms-hub'),
                'required' => true,
                'class' => array('form-row-first'),
                'custom_attributes' => array('min' => '4', 'max' => '18'),
                'priority' => 30,
            ),
            'ptp_child_birthdate' => array(
                'type' => 'date',
                'label' => __('Camper Birth Date', 'ptp-comms-hub'),
                'required' => false,
                'class' => array('form-row-last'),
                'priority' => 40,
            ),
            'ptp_tshirt_size' => array(
                'type' => 'select',
                'label' => __('T-Shirt Size', 'ptp-comms-hub'),
                'required' => false,
                'class' => array('form-row-first'),
                'options' => array(
                    '' => __('Select size...', 'ptp-comms-hub'),
                    'YXS' => __('Youth XS', 'ptp-comms-hub'),
                    'YS' => __('Youth S', 'ptp-comms-hub'),
                    'YM' => __('Youth M', 'ptp-comms-hub'),
                    'YL' => __('Youth L', 'ptp-comms-hub'),
                    'YXL' => __('Youth XL', 'ptp-comms-hub'),
                    'AS' => __('Adult S', 'ptp-comms-hub'),
                    'AM' => __('Adult M', 'ptp-comms-hub'),
                    'AL' => __('Adult L', 'ptp-comms-hub'),
                    'AXL' => __('Adult XL', 'ptp-comms-hub'),
                ),
                'priority' => 50,
            ),
            'ptp_special_needs' => array(
                'type' => 'textarea',
                'label' => __('Medical/Special Needs', 'ptp-comms-hub'),
                'placeholder' => __('Allergies, medical conditions, or special accommodations needed', 'ptp-comms-hub'),
                'required' => false,
                'class' => array('form-row-wide'),
                'priority' => 60,
            ),
            'ptp_emergency_contact' => array(
                'type' => 'text',
                'label' => __('Emergency Contact Name', 'ptp-comms-hub'),
                'required' => false,
                'class' => array('form-row-first'),
                'priority' => 70,
            ),
            'ptp_emergency_phone' => array(
                'type' => 'tel',
                'label' => __('Emergency Contact Phone', 'ptp-comms-hub'),
                'required' => false,
                'class' => array('form-row-last'),
                'priority' => 80,
            ),
        );
        
        return $fields;
    }
    
    /**
     * Add SMS opt-in checkbox to checkout
     */
    public static function add_sms_optin_checkbox($checkout) {
        echo '<div id="ptp-sms-optin" style="margin: 20px 0; padding: 15px; background: #f8f8f8; border-radius: 5px;">';
        
        woocommerce_form_field('ptp_sms_optin', array(
            'type' => 'checkbox',
            'class' => array('form-row-wide'),
            'label' => __('📱 Yes, send me SMS updates about my registration, event reminders, and important camp information.', 'ptp-comms-hub'),
            'required' => false,
        ), $checkout->get_value('ptp_sms_optin'));
        
        echo '<small style="color: #666; display: block; margin-top: 5px;">' . 
             __('Message and data rates may apply. Reply STOP to unsubscribe at any time.', 'ptp-comms-hub') . 
             '</small>';
        echo '</div>';
    }
    
    /**
     * Save SMS opt-in choice
     */
    public static function save_sms_optin($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $optin = !empty($_POST['ptp_sms_optin']) ? 'yes' : 'no';
        $order->update_meta_data('_ptp_sms_optin', $optin);
        $order->save();
    }
    
    /**
     * Save custom checkout fields to order
     */
    public static function save_checkout_fields($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $fields = array(
            'ptp_child_first_name',
            'ptp_child_last_name',
            'ptp_child_age',
            'ptp_child_birthdate',
            'ptp_tshirt_size',
            'ptp_special_needs',
            'ptp_emergency_contact',
            'ptp_emergency_phone',
        );
        
        foreach ($fields as $field) {
            if (!empty($_POST[$field])) {
                $order->update_meta_data('_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
        $order->save();
    }
    
    /**
     * Add product fields for camp details
     */
    public static function add_product_camp_fields() {
        global $post;
        
        echo '<div class="options_group ptp-camp-fields">';
        echo '<h4 style="padding-left: 12px; margin-top: 10px;">🏕️ PTP Camp Details</h4>';
        
        woocommerce_wp_text_input(array(
            'id' => '_ptp_camp_date',
            'label' => __('Camp Date', 'ptp-comms-hub'),
            'placeholder' => 'YYYY-MM-DD',
            'desc_tip' => true,
            'description' => __('The date of the camp/event', 'ptp-comms-hub'),
            'type' => 'date',
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_ptp_camp_end_date',
            'label' => __('Camp End Date', 'ptp-comms-hub'),
            'placeholder' => 'YYYY-MM-DD',
            'desc_tip' => true,
            'description' => __('Leave blank for single-day camps', 'ptp-comms-hub'),
            'type' => 'date',
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_ptp_camp_time',
            'label' => __('Camp Time', 'ptp-comms-hub'),
            'placeholder' => '9:00 AM - 12:00 PM',
            'desc_tip' => true,
            'description' => __('Time range for the camp', 'ptp-comms-hub'),
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_ptp_camp_location',
            'label' => __('Location Name', 'ptp-comms-hub'),
            'placeholder' => 'Example: Central Park Soccer Field',
            'desc_tip' => true,
            'description' => __('Name of the venue/field', 'ptp-comms-hub'),
        ));
        
        woocommerce_wp_textarea_input(array(
            'id' => '_ptp_camp_address',
            'label' => __('Full Address', 'ptp-comms-hub'),
            'placeholder' => '123 Main St, City, State ZIP',
            'desc_tip' => true,
            'description' => __('Complete address for directions', 'ptp-comms-hub'),
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_ptp_camp_maps_link',
            'label' => __('Google Maps Link', 'ptp-comms-hub'),
            'placeholder' => 'https://maps.google.com/...',
            'desc_tip' => true,
            'description' => __('Direct link to Google Maps location', 'ptp-comms-hub'),
        ));
        
        woocommerce_wp_select(array(
            'id' => '_ptp_program_type',
            'label' => __('Program Type', 'ptp-comms-hub'),
            'options' => array(
                '' => __('Select...', 'ptp-comms-hub'),
                'half_day' => __('Half Day Camp', 'ptp-comms-hub'),
                'full_day' => __('Full Day Camp', 'ptp-comms-hub'),
                'week_camp' => __('Week-Long Camp', 'ptp-comms-hub'),
                'clinic' => __('Skills Clinic', 'ptp-comms-hub'),
                'league' => __('League/Season', 'ptp-comms-hub'),
                'private' => __('Private Training', 'ptp-comms-hub'),
                'tournament' => __('Tournament', 'ptp-comms-hub'),
            ),
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_ptp_age_range',
            'label' => __('Age Range', 'ptp-comms-hub'),
            'placeholder' => '6-10',
            'desc_tip' => true,
            'description' => __('Recommended age range (e.g., 6-10)', 'ptp-comms-hub'),
        ));
        
        woocommerce_wp_select(array(
            'id' => '_ptp_camp_market',
            'label' => __('Market/Region', 'ptp-comms-hub'),
            'options' => array(
                '' => __('Select...', 'ptp-comms-hub'),
                'PA' => __('Pennsylvania', 'ptp-comms-hub'),
                'NJ' => __('New Jersey', 'ptp-comms-hub'),
                'DE' => __('Delaware', 'ptp-comms-hub'),
                'MD' => __('Maryland', 'ptp-comms-hub'),
                'NY' => __('New York', 'ptp-comms-hub'),
            ),
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_ptp_camp_capacity',
            'label' => __('Max Capacity', 'ptp-comms-hub'),
            'placeholder' => '30',
            'desc_tip' => true,
            'description' => __('Maximum number of campers', 'ptp-comms-hub'),
            'type' => 'number',
        ));
        
        woocommerce_wp_textarea_input(array(
            'id' => '_ptp_what_to_bring',
            'label' => __('What to Bring', 'ptp-comms-hub'),
            'placeholder' => 'Soccer cleats, shin guards, water bottle, snack...',
            'desc_tip' => true,
            'description' => __('Items campers should bring', 'ptp-comms-hub'),
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_ptp_head_coach',
            'label' => __('Head Coach', 'ptp-comms-hub'),
            'placeholder' => 'Coach Name',
            'desc_tip' => true,
            'description' => __('Lead coach for this camp', 'ptp-comms-hub'),
        ));
        
        echo '</div>';
    }
    
    /**
     * Save product camp fields
     */
    public static function save_product_camp_fields($post_id) {
        $fields = array(
            '_ptp_camp_date',
            '_ptp_camp_end_date',
            '_ptp_camp_time',
            '_ptp_camp_location',
            '_ptp_camp_address',
            '_ptp_camp_maps_link',
            '_ptp_program_type',
            '_ptp_age_range',
            '_ptp_camp_market',
            '_ptp_camp_capacity',
            '_ptp_what_to_bring',
            '_ptp_head_coach',
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Process WooCommerce order - IMPROVED VERSION
     */
    public static function process_order($order_id, $force = false) {
        try {
            global $wpdb;
            
            // Get order - handle both ID and object
            if (is_object($order_id) && method_exists($order_id, 'get_id')) {
                $order = $order_id;
                $order_id = $order->get_id();
            } else {
                if (!function_exists('wc_get_order')) {
                    error_log('[PTP WooCommerce] wc_get_order function not available');
                    return false;
                }
                $order = wc_get_order($order_id);
            }
            
            if (!$order) {
                error_log('[PTP WooCommerce] Order not found: ' . $order_id);
                return false;
            }
            
            // Check if already processed (skip if force)
            if (!$force) {
                $processed = $order->get_meta('_ptp_comms_processed');
                if ($processed) {
                    return true;
                }
            }
            
            error_log('[PTP WooCommerce] Processing order: ' . $order_id . ' (Status: ' . $order->get_status() . ')');
        
        // Get billing info
        $billing_phone = $order->get_billing_phone();
        $billing_email = $order->get_billing_email();
        $billing_first = $order->get_billing_first_name();
        $billing_last = $order->get_billing_last_name();
        $billing_city = $order->get_billing_city();
        $billing_state = $order->get_billing_state();
        $billing_zip = $order->get_billing_postcode();
        
        if (empty($billing_phone) && empty($billing_email)) {
            error_log('[PTP WooCommerce] No phone or email on order: ' . $order_id);
            return false;
        }
        
        // Helper function to get ACF/meta field with multiple possible names
        $get_field = function($order, $names) {
            foreach ((array)$names as $name) {
                $val = $order->get_meta($name);
                if (!empty($val)) return $val;
                // Try with underscore prefix
                $val = $order->get_meta('_' . $name);
                if (!empty($val)) return $val;
            }
            return '';
        };
        
        // Get child/camper info from ACF order meta
        $child_first = $get_field($order, ['camper_first_name', 'camper-first-name', 'child_first_name', 'ptp_child_first_name']);
        $child_last = $get_field($order, ['camper_last_name', 'camper-last-name', 'child_last_name', 'ptp_child_last_name']);
        $child_age = $get_field($order, ['camper_age', 'camper-age', 'child_age', 'ptp_child_age']);
        $child_name = trim($child_first . ' ' . $child_last);
        
        // Get additional ACF camper fields
        $camper_level = $get_field($order, ['camper_level', 'camper-level', 'skill_level']);
        $camper_team = $get_field($order, ['camper_team', 'camper-team', 'current_team', 'current-team', 'team']);
        $shirt_size = $get_field($order, ['shirt_size', 'shirt-size', 'tshirt_size', 't_shirt_size', 'ptp_tshirt_size']);
        $skills_to_improve = $get_field($order, ['skills_to_improve', 'skills-to-improve', 'improvement_areas', 'improvement-areas', 'areas_to_improve']);
        
        // Get referral info
        $how_did_you_hear = $get_field($order, ['how_did_you_hear', 'how-did-you-hear', 'how_did_you_hear_about_ptp', 'referral_source']);
        $friend_name = $get_field($order, ['friend_name', 'friend-name', 'referred_by_name']);
        $friend_email = $get_field($order, ['friend_email', 'friend-email', 'referred_by_email']);
        $ptp_referral = $get_field($order, ['ptp_referral', 'ptp-referral', 'referral', 'referral_code']);
        
        // Get emergency contact info - check multiple possible field names
        $emergency_first = $get_field($order, ['emergency_contact_first_name', 'emergency-contact-first-name']);
        $emergency_last = $get_field($order, ['emergency_contact_last_name', 'emergency-contact-last-name']);
        $emergency_name = trim($emergency_first . ' ' . $emergency_last);
        if (empty($emergency_name)) {
            $emergency_name = $get_field($order, ['emergency_contact', 'emergency-contact', 'ptp_emergency_contact']);
        }
        $emergency_contact_phone_number = $get_field($order, ['emergency_contact_phone_number', 'emergency-contact-phone-number', 'emergency_contact_phone', 'emergency-contact-phone', 'emergency_phone', 'ptp_emergency_phone']);
        
        // Get waiver/permission fields
        $waiver_agreed = $get_field($order, ['waiver', 'liability_waiver', 'ptp_waiver', 'i_agree_to_the_ptp_policies_and_liability_waiver']);
        $refund_policy = $get_field($order, ['refund_policy', 'refund-policy', 'i_agree_to_the_ptp_refund_policy']);
        $photo_permission = $get_field($order, ['photo_permission', 'photo-permission', 'media_release', 'i_grant_ptp_permission_to_use_photos']);
        
        // Get medical info
        $medical_allergies = $get_field($order, ['medical_allergies', 'medical-allergies', 'allergies', 'food_allergies']);
        $special_needs = $get_field($order, ['special_needs', 'special-needs', 'medical_info', 'medical_conditions', 'ptp_special_needs']);
        
        // Check SMS opt-in
        $sms_optin = $order->get_meta('_ptp_sms_optin');
        $settings = class_exists('PTP_Comms_Hub_Settings') ? PTP_Comms_Hub_Settings::get_all() : array();
        $auto_optin = isset($settings['woo_auto_opt_in']) && $settings['woo_auto_opt_in'] === 'yes';
        $opted_in = ($sms_optin === 'yes' || $auto_optin) ? 1 : 0;
        
        // Create or update contact
        $contact_id = null;
        if (function_exists('ptp_comms_get_or_create_contact')) {
            $contact_id = ptp_comms_get_or_create_contact($billing_phone ?: $billing_email, array(
                'parent_first_name' => $billing_first,
                'parent_last_name' => $billing_last,
                'parent_phone' => $billing_phone,
                'parent_email' => $billing_email,
                'child_name' => $child_name ?: null,
                'child_age' => $child_age ?: null,
                'city' => $billing_city,
                'state' => $billing_state,
                'zip_code' => $billing_zip,
                'woo_order_id' => $order_id,
                'opted_in' => $opted_in,
                'source' => 'woocommerce',
            ));
        }
        
        if (!$contact_id) {
            // Try direct insert if helper function not available
            $contact_id = self::create_contact_direct($billing_phone, $billing_email, array(
                'parent_first_name' => $billing_first,
                'parent_last_name' => $billing_last,
                'child_name' => $child_name,
                'child_age' => $child_age,
                'city' => $billing_city,
                'state' => $billing_state,
                'zip_code' => $billing_zip,
                'opted_in' => $opted_in,
            ));
        }
        
        error_log('[PTP WooCommerce] Contact ID: ' . $contact_id);
        
        // Process each order item
        $registrations = array();
        $items = $order->get_items();
        
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            $product_id = $product ? $product->get_id() : $item->get_product_id();
            $product_name = $item->get_name();
            
            // Get product camp details
            $camp_date = get_post_meta($product_id, '_ptp_camp_date', true);
            $camp_end_date = get_post_meta($product_id, '_ptp_camp_end_date', true);
            $camp_time = get_post_meta($product_id, '_ptp_camp_time', true);
            $camp_location = get_post_meta($product_id, '_ptp_camp_location', true);
            $camp_address = get_post_meta($product_id, '_ptp_camp_address', true);
            $program_type = get_post_meta($product_id, '_ptp_program_type', true);
            $market = get_post_meta($product_id, '_ptp_camp_market', true);
            $what_to_bring = get_post_meta($product_id, '_ptp_what_to_bring', true);
            $head_coach = get_post_meta($product_id, '_ptp_head_coach', true);
            
            // Registration data
            $registration_data = array(
                'contact_id' => $contact_id ?: 0,
                'order_id' => $order_id,
                'order_item_id' => $item_id,
                'product_id' => $product_id,
                'product_name' => $product_name,
                'product_sku' => $product ? $product->get_sku() : '',
                'quantity' => $item->get_quantity(),
                'line_total' => $item->get_total(),
                'child_name' => $child_name,
                'child_age' => $child_age ? intval($child_age) : 0,
                'camper_level' => $camper_level,
                'camper_team' => $camper_team,
                'tshirt_size' => $shirt_size,
                'skills_to_improve' => $skills_to_improve,
                'medical_allergies' => $medical_allergies,
                'special_needs' => $special_needs,
                'emergency_contact' => $emergency_name,
                'emergency_contact_phone_number' => $emergency_contact_phone_number,
                'how_did_you_hear' => $how_did_you_hear,
                'friend_name' => $friend_name,
                'friend_email' => $friend_email,
                'ptp_referral' => $ptp_referral,
                'waiver_agreed' => $waiver_agreed,
                'refund_policy_agreed' => $refund_policy,
                'photo_permission' => $photo_permission,
                'event_date' => $camp_date ?: null,
                'event_end_date' => $camp_end_date ?: null,
                'event_time' => $camp_time,
                'event_location' => $camp_location,
                'event_address' => $camp_address,
                'program_type' => $program_type,
                'market' => $market,
                'what_to_bring' => $what_to_bring,
                'head_coach' => $head_coach,
                'registration_status' => self::map_order_status($order->get_status()),
                'updated_at' => current_time('mysql'),
            );
            
            // Check if registration already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ptp_registrations 
                 WHERE order_id = %d AND order_item_id = %d",
                $order_id,
                $item_id
            ));
            
            if ($existing) {
                $wpdb->update(
                    $wpdb->prefix . 'ptp_registrations',
                    $registration_data,
                    array('id' => $existing)
                );
                error_log('[PTP WooCommerce] Updated registration: ' . $existing);
            } else {
                $registration_data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($wpdb->prefix . 'ptp_registrations', $registration_data);
                
                if ($result === false) {
                    error_log('[PTP WooCommerce] Failed to insert registration: ' . $wpdb->last_error);
                } else {
                    error_log('[PTP WooCommerce] Created registration: ' . $wpdb->insert_id);
                }
            }
            
            $registrations[] = $registration_data;
        }
        
        // Update contact segments
        if ($contact_id) {
            self::update_contact_segments($contact_id, $registrations);
        }
        
        // Mark order as processed
        $order->update_meta_data('_ptp_comms_processed', current_time('mysql'));
        $order->update_meta_data('_ptp_comms_contact_id', $contact_id);
        $order->save();
        
        // Queue for Google Sheets sync
        self::queue_google_sheets_sync($order_id);
        
        error_log('[PTP WooCommerce] Order processing complete: ' . $order_id . ' (' . count($registrations) . ' registrations)');
        
        return true;
        
        } catch (Exception $e) {
            error_log('[PTP WooCommerce] Exception processing order ' . $order_id . ': ' . $e->getMessage());
            return false;
        } catch (Error $e) {
            error_log('[PTP WooCommerce] Fatal error processing order ' . $order_id . ': ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create contact directly if helper not available
     */
    private static function create_contact_direct($phone, $email, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ptp_contacts';
        
        // Check if contact exists
        $existing = null;
        if ($phone) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE parent_phone = %s",
                $phone
            ));
        }
        if (!$existing && $email) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE parent_email = %s",
                $email
            ));
        }
        
        if ($existing) {
            $wpdb->update($table, array_merge($data, array(
                'parent_phone' => $phone,
                'parent_email' => $email,
                'updated_at' => current_time('mysql'),
            )), array('id' => $existing));
            return $existing;
        }
        
        $wpdb->insert($table, array_merge($data, array(
            'parent_phone' => $phone,
            'parent_email' => $email,
            'source' => 'woocommerce',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        )));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Sync all orders - IMPROVED VERSION
     */
    public static function sync_all_orders($limit = 0, $offset = 0) {
        // Ensure WooCommerce is loaded
        if (!class_exists('WooCommerce')) {
            error_log('[PTP Sync] WooCommerce not loaded');
            return array('synced' => 0, 'errors' => 0, 'total' => 0, 'message' => 'WooCommerce not active');
        }
        
        if (!function_exists('wc_get_orders')) {
            error_log('[PTP Sync] wc_get_orders function not available');
            return array('synced' => 0, 'errors' => 0, 'total' => 0, 'message' => 'WooCommerce functions not available');
        }
        
        $args = array(
            'status' => array('completed', 'processing', 'on-hold', 'pending'),
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
        );
        
        if ($limit > 0) {
            $args['limit'] = $limit;
            $args['offset'] = $offset;
        } else {
            $args['limit'] = -1;
        }
        
        try {
            $order_ids = wc_get_orders($args);
        } catch (Exception $e) {
            error_log('[PTP Sync] Error getting orders: ' . $e->getMessage());
            return array('synced' => 0, 'errors' => 1, 'total' => 0, 'message' => $e->getMessage());
        }
        
        if (!is_array($order_ids)) {
            error_log('[PTP Sync] wc_get_orders did not return array');
            return array('synced' => 0, 'errors' => 0, 'total' => 0, 'message' => 'No orders found');
        }
        
        $synced = 0;
        $errors = 0;
        $total = count($order_ids);
        
        error_log('[PTP Sync] Starting sync of ' . $total . ' orders');
        
        foreach ($order_ids as $order_id) {
            try {
                $result = self::process_order($order_id, true);
                if ($result) {
                    $synced++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                error_log('[PTP Sync] Error syncing order ' . $order_id . ': ' . $e->getMessage());
                $errors++;
            }
        }
        
        error_log('[PTP Sync] Complete: ' . $synced . ' synced, ' . $errors . ' errors');
        
        return array('synced' => $synced, 'errors' => $errors, 'total' => $total);
    }
    
    /**
     * AJAX handler for syncing single order
     */
    public static function ajax_sync_single_order() {
        error_log('[PTP AJAX] ajax_sync_single_order called');
        
        // Check nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'ptp_comms_hub_nonce') && !wp_verify_nonce($nonce, 'ptp_comms_nonce')) {
            error_log('[PTP AJAX] Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(array('message' => 'Invalid order ID'));
            return;
        }
        
        $result = self::process_order($order_id, true);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Order synced successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to sync order'));
        }
    }
    
    /**
     * AJAX handler for syncing all orders
     */
    public static function ajax_sync_all_orders() {
        // Wrap everything in try-catch to catch any fatal errors
        try {
            error_log('[PTP AJAX] ajax_sync_all_orders called');
            
            // Check nonce - be more flexible
            $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
            
            $nonce_valid = wp_verify_nonce($nonce, 'ptp_comms_hub_nonce') || wp_verify_nonce($nonce, 'ptp_comms_nonce');
            
            if (!$nonce_valid) {
                error_log('[PTP AJAX] Nonce verification failed');
                wp_send_json_error(array('message' => 'Security check failed - please refresh the page and try again'));
                return;
            }
            
            if (!current_user_can('manage_options')) {
                error_log('[PTP AJAX] Permission denied');
                wp_send_json_error(array('message' => 'Permission denied'));
                return;
            }
            
            // Increase time limit for large syncs
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
            
            // Check if WooCommerce is available
            if (!class_exists('WooCommerce')) {
                error_log('[PTP AJAX] WooCommerce not loaded');
                wp_send_json_error(array('message' => 'WooCommerce is not active'));
                return;
            }
            
            // Check if wc_get_orders exists
            if (!function_exists('wc_get_orders')) {
                error_log('[PTP AJAX] wc_get_orders not available');
                wp_send_json_error(array('message' => 'WooCommerce functions not available'));
                return;
            }
            
            // Check if registrations table exists
            global $wpdb;
            $table = $wpdb->prefix . 'ptp_registrations';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            
            if (!$table_exists) {
                error_log('[PTP AJAX] Registrations table does not exist - creating it');
                // Try to create the table
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                $charset_collate = $wpdb->get_charset_collate();
                
                $sql = "CREATE TABLE {$wpdb->prefix}ptp_registrations (
                    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    contact_id bigint(20) UNSIGNED NOT NULL,
                    order_id bigint(20) UNSIGNED DEFAULT NULL,
                    order_item_id bigint(20) UNSIGNED DEFAULT NULL,
                    product_id bigint(20) UNSIGNED DEFAULT NULL,
                    product_name varchar(255) DEFAULT '',
                    product_sku varchar(100) DEFAULT '',
                    quantity int(11) DEFAULT 1,
                    line_total decimal(10,2) DEFAULT 0.00,
                    child_name varchar(200) DEFAULT '',
                    child_age int(3) DEFAULT 0,
                    tshirt_size varchar(10) DEFAULT '',
                    special_needs text DEFAULT NULL,
                    emergency_contact varchar(200) DEFAULT '',
                    emergency_phone varchar(20) DEFAULT '',
                    event_date date DEFAULT NULL,
                    event_end_date date DEFAULT NULL,
                    event_time varchar(100) DEFAULT '',
                    event_location varchar(255) DEFAULT '',
                    event_address text DEFAULT NULL,
                    program_type varchar(100) DEFAULT '',
                    market varchar(50) DEFAULT '',
                    what_to_bring text DEFAULT NULL,
                    head_coach varchar(100) DEFAULT '',
                    registration_status varchar(50) DEFAULT 'pending',
                    reminder_1day_sent datetime DEFAULT NULL,
                    reminder_3day_sent datetime DEFAULT NULL,
                    reminder_7day_sent datetime DEFAULT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY contact_id (contact_id),
                    KEY order_id (order_id),
                    KEY product_id (product_id),
                    KEY event_date (event_date),
                    KEY registration_status (registration_status),
                    KEY market (market)
                ) $charset_collate;";
                
                dbDelta($sql);
                error_log('[PTP AJAX] Created registrations table');
            }
            
            // Also ensure contacts table exists
            $contacts_table = $wpdb->prefix . 'ptp_contacts';
            $contacts_exists = $wpdb->get_var("SHOW TABLES LIKE '{$contacts_table}'") === $contacts_table;
            
            if (!$contacts_exists) {
                error_log('[PTP AJAX] Contacts table does not exist');
                wp_send_json_error(array('message' => 'Database tables not set up. Please deactivate and reactivate the plugin.'));
                return;
            }
            
            // Run the sync
            $result = self::sync_all_orders();
            
            error_log('[PTP AJAX] Sync result: ' . print_r($result, true));
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            error_log('[PTP AJAX] Exception: ' . $e->getMessage());
            error_log('[PTP AJAX] Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        } catch (Error $e) {
            error_log('[PTP AJAX] Fatal Error: ' . $e->getMessage());
            error_log('[PTP AJAX] Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Fatal Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for Google Sheets sync
     */
    public static function ajax_sync_google_sheets() {
        error_log('[PTP AJAX] ajax_sync_google_sheets called');
        
        try {
            $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
            if (!wp_verify_nonce($nonce, 'ptp_comms_hub_nonce') && !wp_verify_nonce($nonce, 'ptp_comms_nonce')) {
                wp_send_json_error(array('message' => 'Security check failed'));
                return;
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Permission denied'));
                return;
            }
            
            $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
            $result = self::bulk_sync_to_google_sheets($days);
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            error_log('[PTP AJAX] Exception in ajax_sync_google_sheets: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        } catch (Error $e) {
            error_log('[PTP AJAX] Error in ajax_sync_google_sheets: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'PHP Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler to test Google Sheets connection
     */
    public static function ajax_test_google_sheets() {
        error_log('[PTP AJAX] ajax_test_google_sheets called');
        
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'ptp_comms_hub_nonce') && !wp_verify_nonce($nonce, 'ptp_comms_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $settings = class_exists('PTP_Comms_Hub_Settings') ? PTP_Comms_Hub_Settings::get_all() : array();
        
        if (empty($settings['google_sheets_webhook_url'])) {
            wp_send_json_success(array(
                'success' => false, 
                'message' => 'Webhook URL not configured. Please enter your Google Apps Script Web App URL.'
            ));
            return;
        }
        
        $webhook_url = trim($settings['google_sheets_webhook_url']);
        
        // Validate URL
        if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            wp_send_json_success(array('success' => false, 'message' => 'Invalid URL format'));
            return;
        }
        
        // Send a test payload with one row
        $test_data = array(array(
            'test' => 'true',
            'timestamp' => current_time('mysql'),
            'message' => 'PTP Comms Hub connection test'
        ));
        
        error_log('[PTP Google Sheets] Testing connection to: ' . $webhook_url);
        
        $response = wp_remote_post($webhook_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array('rows' => $test_data, 'test' => true)),
            'timeout' => 30,
            'redirection' => 5,
            'sslverify' => false,
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_success(array(
                'success' => false, 
                'message' => 'Connection failed: ' . $response->get_error_message()
            ));
            return;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('[PTP Google Sheets] Test response code: ' . $code);
        error_log('[PTP Google Sheets] Test response: ' . substr($body, 0, 500));
        
        if ($code >= 200 && $code < 400) {
            wp_send_json_success(array(
                'success' => true, 
                'message' => 'Connection successful! HTTP ' . $code
            ));
        } else {
            wp_send_json_success(array(
                'success' => false, 
                'message' => 'HTTP ' . $code . ' - Check your Web App deployment settings'
            ));
        }
    }
    
    /**
     * Get registrations for a specific product/camp
     */
    public static function get_product_registrations($product_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, c.parent_phone, c.parent_email, c.parent_first_name, c.parent_last_name
             FROM {$wpdb->prefix}ptp_registrations r
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
             WHERE r.product_id = %d
             ORDER BY r.created_at DESC",
            $product_id
        ));
    }
    
    /**
     * Get registration count for a product
     */
    public static function get_product_registration_count($product_id, $status = 'confirmed') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ptp_registrations';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        
        if (!$table_exists) {
            return 0;
        }
        
        if ($status === 'all') {
            return intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE product_id = %d",
                $product_id
            )));
        }
        
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND registration_status = %s",
            $product_id,
            $status
        )));
    }
    
    /**
     * Map WooCommerce order status to registration status
     */
    private static function map_order_status($woo_status) {
        $map = array(
            'pending' => 'pending',
            'processing' => 'confirmed',
            'on-hold' => 'pending',
            'completed' => 'confirmed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'cancelled',
        );
        
        return isset($map[$woo_status]) ? $map[$woo_status] : 'pending';
    }
    
    /**
     * Update contact segments
     */
    private static function update_contact_segments($contact_id, $registrations) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT segments FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        $existing = $contact && $contact->segments ? array_map('trim', explode(',', $contact->segments)) : array();
        $new = array('woo_customer');
        
        foreach ($registrations as $reg) {
            if (!empty($reg['market'])) {
                $new[] = 'market_' . strtolower($reg['market']);
            }
            if (!empty($reg['program_type'])) {
                $new[] = 'program_' . strtolower($reg['program_type']);
            }
            if (!empty($reg['event_date'])) {
                $new[] = 'registered_' . date('Y', strtotime($reg['event_date']));
            }
            if (!empty($reg['child_age'])) {
                $age = intval($reg['child_age']);
                if ($age <= 6) $new[] = 'age_u6';
                elseif ($age <= 8) $new[] = 'age_u8';
                elseif ($age <= 10) $new[] = 'age_u10';
                elseif ($age <= 12) $new[] = 'age_u12';
                else $new[] = 'age_teen';
            }
        }
        
        $all = array_unique(array_filter(array_merge($existing, $new)));
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_contacts',
            array('segments' => implode(',', $all)),
            array('id' => $contact_id)
        );
    }
    
    /**
     * Handle order cancellation
     */
    public static function handle_cancellation($order_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_registrations',
            array('registration_status' => 'cancelled', 'updated_at' => current_time('mysql')),
            array('order_id' => $order_id)
        );
    }
    
    /**
     * Handle order refund
     */
    public static function handle_refund($order_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_registrations',
            array('registration_status' => 'refunded', 'updated_at' => current_time('mysql')),
            array('order_id' => $order_id)
        );
    }
    
    /**
     * Queue for Google Sheets sync
     */
    private static function queue_google_sheets_sync($order_id) {
        $queue = get_option('ptp_google_sheets_queue', array());
        if (!in_array($order_id, $queue)) {
            $queue[] = $order_id;
            update_option('ptp_google_sheets_queue', $queue);
        }
    }
    
    /**
     * Auto sync to Google Sheets
     */
    public static function sync_to_google_sheets() {
        $settings = class_exists('PTP_Comms_Hub_Settings') ? PTP_Comms_Hub_Settings::get_all() : array();
        
        if (empty($settings['google_sheets_enabled']) || $settings['google_sheets_enabled'] !== 'yes') {
            return;
        }
        
        if (empty($settings['google_sheets_webhook_url'])) {
            return;
        }
        
        $queue = get_option('ptp_google_sheets_queue', array());
        if (empty($queue)) {
            return;
        }
        
        $webhook_url = $settings['google_sheets_webhook_url'];
        $batch = array_slice($queue, 0, 50);
        
        foreach ($batch as $order_id) {
            $data = self::get_order_for_sheets($order_id);
            if ($data) {
                self::send_to_google_sheets($webhook_url, $data);
            }
        }
        
        $remaining = array_diff($queue, $batch);
        update_option('ptp_google_sheets_queue', array_values($remaining));
    }
    
    /**
     * Get order data for Google Sheets - includes all ACF fields, organized by product
     */
    public static function get_order_for_sheets($order_id) {
        if (!function_exists('wc_get_order')) {
            return null;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) return null;
        
        // Skip refund orders - they don't have billing info
        if ($order->get_type() === 'shop_order_refund' || is_a($order, 'WC_Order_Refund') || is_a($order, 'Automattic\WooCommerce\Admin\Overrides\OrderRefund')) {
            error_log('[PTP Google Sheets] Skipping refund order: ' . $order_id);
            return null;
        }
        
        // Verify this is a standard order with billing methods
        if (!method_exists($order, 'get_billing_first_name')) {
            error_log('[PTP Google Sheets] Skipping order without billing methods: ' . $order_id . ' (type: ' . get_class($order) . ')');
            return null;
        }
        
        global $wpdb;
        
        $rows = array();
        
        // Helper function to get ACF/meta field with multiple possible names
        $get_field = function($order, $names) {
            foreach ((array)$names as $name) {
                $val = $order->get_meta($name);
                if (!empty($val)) return $val;
                // Try with underscore prefix
                $val = $order->get_meta('_' . $name);
                if (!empty($val)) return $val;
            }
            return '';
        };
        
        // Parent Info
        $parent_first_name = $get_field($order, ['parent_first_name', 'parent-first-name']) ?: $order->get_billing_first_name();
        $parent_last_name = $get_field($order, ['parent_last_name', 'parent-last-name']) ?: $order->get_billing_last_name();
        
        // Camper Info (ACF fields from screenshot)
        $camper_first_name = $get_field($order, ['camper_first_name', 'camper-first-name', 'child_first_name', 'ptp_child_first_name']);
        $camper_last_name = $get_field($order, ['camper_last_name', 'camper-last-name', 'child_last_name', 'ptp_child_last_name']);
        $camper_age = $get_field($order, ['camper_age', 'camper-age', 'child_age', 'ptp_child_age']);
        $camper_level = $get_field($order, ['camper_level', 'camper-level', 'skill_level']);
        $camper_team = $get_field($order, ['camper_team', 'camper-team', 'current_team', 'current-team', 'team']);
        $shirt_size = $get_field($order, ['shirt_size', 'shirt-size', 'tshirt_size', 't_shirt_size', 'ptp_tshirt_size']);
        $skills_to_improve = $get_field($order, ['skills_to_improve', 'skills-to-improve', 'improvement_areas', 'improvement-areas', 'areas_to_improve']);
        
        // Referral Info
        $how_did_you_hear = $get_field($order, ['how_did_you_hear', 'how-did-you-hear', 'how_did_you_hear_about_ptp', 'referral_source']);
        $friend_name = $get_field($order, ['friend_name', 'friend-name', 'referred_by_name']);
        $friend_email = $get_field($order, ['friend_email', 'friend-email', 'referred_by_email']);
        $ptp_referral = $get_field($order, ['ptp_referral', 'ptp-referral', 'referral', 'referral_code']);
        
        // Emergency Contact
        $emergency_first = $get_field($order, ['emergency_contact_first_name', 'emergency-contact-first-name']);
        $emergency_last = $get_field($order, ['emergency_contact_last_name', 'emergency-contact-last-name']);
        $emergency_contact = trim($emergency_first . ' ' . $emergency_last);
        if (empty($emergency_contact)) {
            $emergency_contact = $get_field($order, ['emergency_contact', 'emergency-contact', 'ptp_emergency_contact']);
        }
        $emergency_contact_phone_number = $get_field($order, ['emergency_contact_phone_number', 'emergency-contact-phone-number', 'emergency_contact_phone', 'emergency-contact-phone', 'emergency_phone', 'ptp_emergency_phone']);
        
        // Medical Info
        $medical_allergies = $get_field($order, ['medical_allergies', 'medical-allergies', 'allergies', 'food_allergies']);
        $special_needs = $get_field($order, ['special_needs', 'special-needs', 'medical_info', 'medical_conditions', 'ptp_special_needs']);
        
        // Waivers & Permissions (ACF fields)
        $waiver_agreed = $get_field($order, ['waiver', 'liability_waiver', 'ptp_waiver', 'i_agree_to_the_ptp_policies_and_liability_waiver']);
        $refund_policy_agreed = $get_field($order, ['refund_policy', 'refund-policy', 'i_agree_to_the_ptp_refund_policy']);
        $photo_permission = $get_field($order, ['photo_permission', 'photo-permission', 'media_release', 'i_grant_ptp_permission_to_use_photos']);
        
        // SMS opt-in
        $sms_optin = $get_field($order, ['sms_optin', 'sms_opt_in', 'ptp_sms_optin']);
        
        // Build child_name from first + last
        $child_name = trim($camper_first_name . ' ' . $camper_last_name);
        
        // Process each order item
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_id = $product ? $product->get_id() : $item->get_product_id();
            
            // Get camp details from product meta
            $camp_date = get_post_meta($product_id, '_ptp_camp_date', true);
            $camp_time = get_post_meta($product_id, '_ptp_camp_time', true);
            $camp_location = get_post_meta($product_id, '_ptp_camp_location', true);
            $market = get_post_meta($product_id, '_ptp_camp_market', true);
            $program_type = get_post_meta($product_id, '_ptp_program_type', true);
            
            // Create a sanitized product name for the sheet tab
            $product_name_raw = $item->get_name();
            $product_sheet_name = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $product_name_raw);
            $product_sheet_name = substr(trim($product_sheet_name), 0, 30); // Max 30 chars for sheet name
            
            $rows[] = array(
                // Sheet name for organizing (first column - will be used to route to correct tab)
                'sheet_name' => $product_sheet_name,
                
                // Order Info
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'order_date' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : '',
                'order_status' => $order->get_status(),
                
                // Parent Info
                'parent_first_name' => $parent_first_name,
                'parent_last_name' => $parent_last_name,
                'parent_email' => $order->get_billing_email(),
                'parent_phone' => $order->get_billing_phone(),
                'billing_city' => $order->get_billing_city(),
                'billing_state' => $order->get_billing_state(),
                'billing_zip' => $order->get_billing_postcode(),
                
                // Camper Info
                'camper_first_name' => $camper_first_name,
                'camper_last_name' => $camper_last_name,
                'child_name' => $child_name,
                'child_age' => $camper_age,
                'camper_level' => $camper_level,
                'camper_team' => $camper_team,
                'tshirt_size' => $shirt_size,
                'skills_to_improve' => $skills_to_improve,
                
                // Emergency & Medical
                'emergency_contact' => $emergency_contact,
                'emergency_contact_phone_number' => $emergency_contact_phone_number,
                'medical_allergies' => $medical_allergies,
                'special_needs' => $special_needs,
                
                // Referral
                'how_did_you_hear' => $how_did_you_hear,
                'friend_name' => $friend_name,
                'friend_email' => $friend_email,
                'ptp_referral' => $ptp_referral,
                
                // Waivers
                'waiver_agreed' => $waiver_agreed,
                'refund_policy_agreed' => $refund_policy_agreed,
                'photo_permission' => $photo_permission,
                
                // Product Info
                'product_name' => $product_name_raw,
                'product_id' => $product_id,
                'quantity' => $item->get_quantity(),
                'line_total' => $item->get_total(),
                
                // Event Info
                'event_date' => $camp_date,
                'event_time' => $camp_time,
                'event_location' => $camp_location,
                'market' => $market,
                'program_type' => $program_type,
                
                // Status
                'registration_status' => self::map_order_status($order->get_status()),
                'sms_opted_in' => ($sms_optin === 'yes' || $sms_optin === '1' || $sms_optin === 'Yes') ? 'Yes' : 'No',
            );
        }
        
        return $rows;
    }
    
    /**
     * Send to Google Sheets
     */
    private static function send_to_google_sheets($webhook_url, $data) {
        error_log('[PTP Google Sheets] Sending ' . count($data) . ' rows to: ' . $webhook_url);
        
        // Sanitize data - convert nulls to empty strings for Google Sheets compatibility
        $sanitized_data = array();
        foreach ($data as $row) {
            $sanitized_row = array();
            foreach ($row as $key => $value) {
                // Convert null, false, and arrays to strings
                if (is_null($value)) {
                    $sanitized_row[$key] = '';
                } elseif (is_bool($value)) {
                    $sanitized_row[$key] = $value ? 'Yes' : 'No';
                } elseif (is_array($value)) {
                    $sanitized_row[$key] = implode(', ', $value);
                } else {
                    $sanitized_row[$key] = (string) $value;
                }
            }
            $sanitized_data[] = $sanitized_row;
        }
        
        $json_body = json_encode(array('rows' => $sanitized_data), JSON_UNESCAPED_UNICODE);
        
        if ($json_body === false) {
            error_log('[PTP Google Sheets] JSON encode failed: ' . json_last_error_msg());
            return array('success' => false, 'message' => 'Failed to encode data as JSON');
        }
        
        error_log('[PTP Google Sheets] Payload size: ' . strlen($json_body) . ' bytes');
        
        $response = wp_remote_post($webhook_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => $json_body,
            'timeout' => 60,
            'redirection' => 5, // Follow redirects
            'sslverify' => false, // Sometimes needed for Google
        ));
        
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            error_log('[PTP Google Sheets] WP Error: ' . $error_msg);
            return array('success' => false, 'message' => 'Connection failed: ' . $error_msg);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('[PTP Google Sheets] Response code: ' . $code);
        error_log('[PTP Google Sheets] Response body: ' . substr($body, 0, 500));
        
        // Google Apps Script can return various success codes
        if ($code >= 200 && $code < 400) {
            // Try to parse JSON response
            $json = json_decode($body, true);
            if ($json && isset($json['success']) && $json['success'] === false) {
                return array(
                    'success' => false, 
                    'message' => isset($json['error']) ? $json['error'] : 'Script returned error'
                );
            }
            return array('success' => true, 'message' => 'Data sent successfully', 'count' => count($data));
        }
        
        // Error response
        return array(
            'success' => false, 
            'message' => 'HTTP ' . $code . ': ' . substr($body, 0, 200)
        );
    }
    
    /**
     * Bulk sync to Google Sheets
     */
    public static function bulk_sync_to_google_sheets($days = 30) {
        global $wpdb;
        
        error_log('[PTP Google Sheets] Starting bulk sync for ' . $days . ' days');
        
        // Wrap everything in try-catch for safety
        try {
            $settings = class_exists('PTP_Comms_Hub_Settings') ? PTP_Comms_Hub_Settings::get_all() : array();
            
            // Check if enabled
            if (empty($settings['google_sheets_enabled']) || $settings['google_sheets_enabled'] !== 'yes') {
                return array('success' => false, 'message' => 'Google Sheets sync is not enabled. Go to Settings to enable it.');
            }
            
            // Check webhook URL
            if (empty($settings['google_sheets_webhook_url'])) {
                return array('success' => false, 'message' => 'Google Sheets webhook URL not configured. Add your Web App URL in Settings.');
            }
            
            $webhook_url = trim($settings['google_sheets_webhook_url']);
            
            // Validate URL format
            if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
                return array('success' => false, 'message' => 'Invalid webhook URL format');
            }
            
            // Check if it's a Google script URL
            if (strpos($webhook_url, 'script.google.com') === false) {
                error_log('[PTP Google Sheets] Warning: URL does not appear to be a Google Script: ' . $webhook_url);
            }
            
            // Calculate date range
            if ($days >= 9999) {
                $date_from = '2000-01-01'; // All time
            } else {
                $date_from = date('Y-m-d', strtotime("-{$days} days"));
            }
            
            error_log('[PTP Google Sheets] Fetching data since: ' . $date_from);
            
            $order_ids = array();
            
            // Get order IDs from registrations table
            $table = $wpdb->prefix . 'ptp_registrations';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            
            if ($table_exists) {
                $reg_order_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT order_id FROM {$table} 
                     WHERE created_at >= %s AND order_id IS NOT NULL
                     ORDER BY created_at DESC",
                    $date_from
                ));
                if (!empty($reg_order_ids) && is_array($reg_order_ids)) {
                    $order_ids = array_map('intval', $reg_order_ids); // Ensure integers
                }
                error_log('[PTP Google Sheets] Found ' . count($order_ids) . ' orders from registrations table');
            } else {
                error_log('[PTP Google Sheets] Registrations table does not exist yet');
            }
            
            // ALWAYS also check WooCommerce orders to catch any that might be missing from registrations table
            // This fixes the "21 orders but only 20 synced" issue
            if (function_exists('wc_get_orders')) {
                $wc_orders = wc_get_orders(array(
                    'limit' => 500,
                    'status' => array('processing', 'completed', 'on-hold'),
                    'date_created' => '>=' . $date_from,
                    'return' => 'ids',
                ));
                
                error_log('[PTP Google Sheets] WooCommerce query returned: ' . (is_array($wc_orders) ? count($wc_orders) . ' orders' : 'not an array'));
                
                if (!empty($wc_orders) && is_array($wc_orders)) {
                    $wc_orders = array_map('intval', $wc_orders); // Ensure integers
                    
                    // Merge WooCommerce orders with registration orders, removing duplicates
                    $combined_orders = array_values(array_unique(array_merge($order_ids, $wc_orders)));
                    $new_orders_found = count($combined_orders) - count($order_ids);
                    
                    if ($new_orders_found > 0) {
                        error_log('[PTP Google Sheets] Found ' . $new_orders_found . ' additional orders from WooCommerce not in registrations table');
                    }
                    
                    $order_ids = $combined_orders;
                    error_log('[PTP Google Sheets] Total unique orders to sync: ' . count($order_ids));
                }
            } else {
                error_log('[PTP Google Sheets] WooCommerce wc_get_orders not available');
            }
            
            if (empty($order_ids)) {
                return array('success' => false, 'message' => 'No orders found in the selected time period. Make sure you have WooCommerce orders.');
            }
            
            $all_rows = array();
            $errors = 0;
            
            foreach ($order_ids as $order_id) {
                if (empty($order_id)) {
                    $errors++;
                    continue;
                }
                
                try {
                    $rows = self::get_order_for_sheets($order_id);
                    if ($rows && is_array($rows)) {
                        $all_rows = array_merge($all_rows, $rows);
                    } else {
                        $errors++;
                    }
                } catch (Exception $e) {
                    error_log('[PTP Google Sheets] Error processing order ' . $order_id . ': ' . $e->getMessage());
                    $errors++;
                }
            }
            
            error_log('[PTP Google Sheets] Prepared ' . count($all_rows) . ' rows for sync');
            
            if (empty($all_rows)) {
                return array('success' => false, 'message' => 'No valid data found to sync (' . $errors . ' orders had issues)');
            }
            
            // Send to Google Sheets
            $result = self::send_to_google_sheets($webhook_url, $all_rows);
            
            if ($result['success']) {
                return array(
                    'success' => true,
                    'count' => count($all_rows),
                    'message' => 'Successfully synced ' . count($all_rows) . ' registrations to Google Sheets'
                );
            }
            
            return array(
                'success' => false,
                'count' => 0,
                'message' => isset($result['message']) ? $result['message'] : 'Unknown error sending to Google Sheets'
            );
            
        } catch (Exception $e) {
            error_log('[PTP Google Sheets] Exception: ' . $e->getMessage());
            return array('success' => false, 'message' => 'Error: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('[PTP Google Sheets] PHP Error: ' . $e->getMessage());
            return array('success' => false, 'message' => 'PHP Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check and send event reminders
     */
    public static function check_event_reminders() {
        global $wpdb;
        
        $settings = class_exists('PTP_Comms_Hub_Settings') ? PTP_Comms_Hub_Settings::get_all() : array();
        
        if (empty($settings['woo_enable_reminders']) || $settings['woo_enable_reminders'] !== 'yes') {
            return;
        }
        
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $in_3_days = date('Y-m-d', strtotime('+3 days'));
        $in_7_days = date('Y-m-d', strtotime('+7 days'));
        
        $registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, c.parent_phone, c.parent_first_name, c.opted_in, c.opted_out
             FROM {$wpdb->prefix}ptp_registrations r
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
             WHERE r.registration_status = 'confirmed'
             AND r.event_date IN (%s, %s, %s)
             AND c.opted_in = 1 AND c.opted_out = 0
             AND c.parent_phone IS NOT NULL AND c.parent_phone != ''",
            $tomorrow, $in_3_days, $in_7_days
        ));
        
        foreach ($registrations as $reg) {
            $reminder_type = null;
            
            if ($reg->event_date === $tomorrow && empty($reg->reminder_1day_sent)) {
                $reminder_type = '1_day';
            } elseif ($reg->event_date === $in_3_days && empty($reg->reminder_3day_sent)) {
                $reminder_type = '3_day';
            } elseif ($reg->event_date === $in_7_days && empty($reg->reminder_7day_sent)) {
                $reminder_type = '7_day';
            }
            
            if ($reminder_type && class_exists('PTP_Comms_Hub_SMS_Service')) {
                $message = self::get_reminder_message($reg, $reminder_type);
                PTP_Comms_Hub_SMS_Service::send($reg->parent_phone, $message, $reg->contact_id);
                
                $field = 'reminder_' . str_replace('_', '', $reminder_type) . '_sent';
                $wpdb->update(
                    $wpdb->prefix . 'ptp_registrations',
                    array($field => current_time('mysql')),
                    array('id' => $reg->id)
                );
            }
        }
    }
    
    /**
     * Get reminder message
     */
    private static function get_reminder_message($reg, $type) {
        $date = date('l, F j', strtotime($reg->event_date));
        
        switch ($type) {
            case '1_day':
                return "⚽ Tomorrow's the day! {$reg->child_name}'s {$reg->product_name} is {$date} at {$reg->event_time}.\n📍 {$reg->event_location}\nDon't forget: water, cleats, shin guards!";
            case '3_day':
                return "⚽ Reminder: {$reg->child_name}'s {$reg->product_name} is coming up on {$date}!\n📍 {$reg->event_location}\n⏰ {$reg->event_time}";
            case '7_day':
                return "Hi {$reg->parent_first_name}! 🗓️ {$reg->child_name}'s {$reg->product_name} is one week away ({$date}).\n📍 {$reg->event_location}\n⏰ {$reg->event_time}";
            default:
                return "Reminder: {$reg->product_name} on {$date} at {$reg->event_location}.";
        }
    }
    
    /**
     * Add order meta box
     */
    public static function add_order_meta_box() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
        
        add_meta_box(
            'ptp_comms_order_info',
            '📱 PTP Communications',
            array(__CLASS__, 'render_order_meta_box'),
            $screen,
            'side',
            'default'
        );
    }
    
    /**
     * Render order meta box
     */
    public static function render_order_meta_box($post_or_order) {
        $order = $post_or_order instanceof WP_Post ? wc_get_order($post_or_order->ID) : $post_or_order;
        if (!$order) return;
        
        $order_id = $order->get_id();
        $contact_id = $order->get_meta('_ptp_comms_contact_id');
        $processed = $order->get_meta('_ptp_comms_processed');
        $sms_optin = $order->get_meta('_ptp_sms_optin');
        
        echo '<div class="ptp-order-info">';
        
        if ($contact_id) {
            echo '<p><strong>Contact:</strong> <a href="' . admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact_id) . '">#' . $contact_id . '</a></p>';
        } else {
            echo '<p><strong>Contact:</strong> Not created</p>';
        }
        
        echo '<p><strong>Processed:</strong> ' . ($processed ? '✅ ' . $processed : '❌ Not yet') . '</p>';
        echo '<p><strong>SMS Opt-in:</strong> ' . ($sms_optin === 'yes' ? '✅ Yes' : '❌ No') . '</p>';
        
        $child = trim($order->get_meta('_ptp_child_first_name') . ' ' . $order->get_meta('_ptp_child_last_name'));
        $age = $order->get_meta('_ptp_child_age');
        if ($child) {
            echo '<p><strong>Camper:</strong> ' . esc_html($child);
            if ($age) echo ' (age ' . esc_html($age) . ')';
            echo '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Add product columns
     */
    public static function add_product_columns($columns) {
        $new = array();
        foreach ($columns as $key => $value) {
            $new[$key] = $value;
            if ($key === 'price') {
                $new['ptp_camp_date'] = __('Camp Date', 'ptp-comms-hub');
                $new['ptp_registrations'] = __('Registrations', 'ptp-comms-hub');
            }
        }
        return $new;
    }
    
    /**
     * Render product columns
     */
    public static function render_product_columns($column, $post_id) {
        switch ($column) {
            case 'ptp_camp_date':
                $date = get_post_meta($post_id, '_ptp_camp_date', true);
                if ($date) {
                    echo date('M j, Y', strtotime($date));
                    $end = get_post_meta($post_id, '_ptp_camp_end_date', true);
                    if ($end && $end !== $date) echo ' - ' . date('M j', strtotime($end));
                } else {
                    echo '—';
                }
                break;
                
            case 'ptp_registrations':
                $count = self::get_product_registration_count($post_id, 'confirmed');
                $capacity = get_post_meta($post_id, '_ptp_camp_capacity', true);
                
                echo '<strong>' . $count . '</strong>';
                if ($capacity) {
                    echo ' / ' . $capacity;
                    if ($count >= $capacity) echo ' <span style="color: red;">FULL</span>';
                }
                break;
        }
    }
    
    /**
     * Get contact registrations
     */
    public static function get_contact_registrations($contact_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_registrations WHERE contact_id = %d ORDER BY event_date DESC",
            $contact_id
        ));
    }
    
    /**
     * Get registration stats
     */
    public static function get_registration_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ptp_registrations';
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        
        if (!$exists) {
            return array('total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0, 'upcoming' => 0);
        }
        
        return array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'confirmed' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE registration_status = 'confirmed'"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE registration_status = 'pending'"),
            'cancelled' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE registration_status IN ('cancelled', 'refunded')"),
            'upcoming' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE registration_status = 'confirmed' AND event_date >= %s",
                date('Y-m-d')
            )),
        );
    }
    
    /**
     * Get camps with registrations
     */
    public static function get_camps_with_registrations($show_past = false) {
        global $wpdb;
        
        if (!function_exists('wc_get_products')) {
            return array();
        }
        
        $products = wc_get_products(array('limit' => -1, 'status' => 'publish'));
        $camps = array();
        
        foreach ($products as $product) {
            $id = $product->get_id();
            $date = get_post_meta($id, '_ptp_camp_date', true);
            
            if (!$show_past && $date && strtotime($date) < strtotime('today')) {
                continue;
            }
            
            $confirmed = self::get_product_registration_count($id, 'confirmed');
            $pending = self::get_product_registration_count($id, 'pending');
            $total = self::get_product_registration_count($id, 'all');
            
            $revenue = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(line_total) FROM {$wpdb->prefix}ptp_registrations 
                 WHERE product_id = %d AND registration_status IN ('confirmed', 'pending')",
                $id
            ));
            
            $camps[] = array(
                'product' => $product,
                'product_id' => $id,
                'name' => $product->get_name(),
                'camp_date' => $date,
                'camp_end_date' => get_post_meta($id, '_ptp_camp_end_date', true),
                'camp_time' => get_post_meta($id, '_ptp_camp_time', true),
                'location' => get_post_meta($id, '_ptp_camp_location', true),
                'market' => get_post_meta($id, '_ptp_camp_market', true),
                'capacity' => get_post_meta($id, '_ptp_camp_capacity', true),
                'confirmed' => $confirmed,
                'pending' => $pending,
                'total_registrations' => $total,
                'revenue' => $revenue ?: 0,
                'is_past' => $date && strtotime($date) < strtotime('today'),
            );
        }
        
        usort($camps, function($a, $b) {
            if (!$a['camp_date'] && !$b['camp_date']) return 0;
            if (!$a['camp_date']) return 1;
            if (!$b['camp_date']) return -1;
            return strtotime($a['camp_date']) - strtotime($b['camp_date']);
        });
        
        return $camps;
    }
}
