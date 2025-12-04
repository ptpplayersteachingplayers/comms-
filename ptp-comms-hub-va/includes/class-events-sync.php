<?php
/**
 * WooCommerce events synchronization
 * v3.4.0 - Fixed meta-key mismatches, added order lifecycle handling
 */
class PTP_Comms_Hub_Events_Sync {
    
    public static function init() {
        // Order processing hooks
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'sync_order'), 10, 1);
        add_action('woocommerce_order_status_processing', array(__CLASS__, 'sync_order'), 10, 1);
        
        // Order cancellation/refund hooks
        add_action('woocommerce_order_status_cancelled', array(__CLASS__, 'handle_order_cancelled'), 10, 1);
        add_action('woocommerce_order_status_refunded', array(__CLASS__, 'handle_order_refunded'), 10, 1);
        add_action('woocommerce_order_status_failed', array(__CLASS__, 'handle_order_failed'), 10, 1);
        
        // Single source of truth for status changes
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'on_order_status_changed'), 10, 4);
    }
    
    /**
     * Sync order when completed or processing
     */
    public static function sync_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Get order details
        $billing_first = $order->get_billing_first_name();
        $billing_last = $order->get_billing_last_name();
        $billing_phone = $order->get_billing_phone();
        $billing_email = $order->get_billing_email();
        
        if (empty($billing_phone)) {
            return;
        }
        
        // Get or create contact
        $contact_id = ptp_comms_get_or_create_contact($billing_phone, array(
            'parent_first_name' => $billing_first,
            'parent_last_name' => $billing_last,
            'parent_email' => $billing_email,
            'opted_in' => 1
        ));
        
        if (!$contact_id) {
            return;
        }
        
        global $wpdb;
        
        // Process order items
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $product_id = $product->get_id();
            
            // Use correct PTP meta keys (matching class-woocommerce.php)
            $camp_date = get_post_meta($product_id, '_ptp_camp_date', true);
            $camp_end_date = get_post_meta($product_id, '_ptp_camp_end_date', true);
            $camp_time = get_post_meta($product_id, '_ptp_camp_time', true);
            $camp_location = get_post_meta($product_id, '_ptp_camp_location', true);
            $camp_address = get_post_meta($product_id, '_ptp_camp_address', true);
            $program_type = get_post_meta($product_id, '_ptp_program_type', true);
            $market = get_post_meta($product_id, '_ptp_camp_market', true);
            $what_to_bring = get_post_meta($product_id, '_ptp_what_to_bring', true);
            $head_coach = get_post_meta($product_id, '_ptp_head_coach', true);
            $age_range = get_post_meta($product_id, '_ptp_age_range', true);
            $maps_link = get_post_meta($product_id, '_ptp_camp_maps_link', true);
            
            // Get child info from order
            $child_name = trim($order->get_meta('_ptp_child_first_name') . ' ' . $order->get_meta('_ptp_child_last_name'));
            $child_age = $order->get_meta('_ptp_child_age');
            
            // Build complete event data for automations
            $event_data = array(
                'contact_id' => $contact_id,
                'order_id' => $order_id,
                'order_item_id' => $item_id,
                'product_id' => $product_id,
                'event_name' => $product->get_name(),
                'product_name' => $product->get_name(),
                'event_date' => $camp_date ?: null,
                'event_end_date' => $camp_end_date ?: null,
                'event_time' => $camp_time,
                'camp_time' => $camp_time,
                'event_location' => $camp_location,
                'event_address' => $camp_address,
                'maps_link' => $maps_link,
                'program_type' => $program_type,
                'market_slug' => $market,
                'market' => $market,
                'what_to_bring' => $what_to_bring,
                'head_coach' => $head_coach,
                'age_range' => $age_range,
                'child_name' => $child_name,
                'child_age' => $child_age,
                'registration_status' => 'confirmed'
            );
            
            // Check if registration already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ptp_registrations 
                 WHERE order_id = %d AND order_item_id = %d",
                $order_id,
                $item_id
            ));
            
            // Registration data for DB
            $registration_data = array(
                'contact_id' => $contact_id,
                'order_id' => $order_id,
                'order_item_id' => $item_id,
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'product_sku' => $product->get_sku(),
                'quantity' => $item->get_quantity(),
                'line_total' => $item->get_total(),
                'child_name' => $child_name,
                'child_age' => $child_age ? intval($child_age) : 0,
                'event_date' => $camp_date ?: null,
                'event_end_date' => $camp_end_date ?: null,
                'event_time' => $camp_time,
                'event_location' => $camp_location,
                'event_address' => $camp_address,
                'program_type' => $program_type,
                'market' => $market,
                'what_to_bring' => $what_to_bring,
                'head_coach' => $head_coach,
                'registration_status' => 'confirmed',
                'updated_at' => current_time('mysql')
            );
            
            if ($existing) {
                $wpdb->update(
                    $wpdb->prefix . 'ptp_registrations',
                    $registration_data,
                    array('id' => $existing)
                );
            } else {
                $registration_data['created_at'] = current_time('mysql');
                $wpdb->insert($wpdb->prefix . 'ptp_registrations', $registration_data);
            }
            
            // Trigger automations with complete event data
            PTP_Comms_Hub_Automations::trigger('order_placed', $contact_id, $event_data);
        }
        
        // Sync to HubSpot
        if (function_exists('ptp_comms_is_hubspot_configured') && ptp_comms_is_hubspot_configured()) {
            if (class_exists('PTP_Comms_Hub_HubSpot_Sync')) {
                PTP_Comms_Hub_HubSpot_Sync::sync_contact($contact_id, $order_id);
            }
        }
        
        // Send Teams notification
        if (function_exists('ptp_comms_is_teams_configured') && ptp_comms_is_teams_configured()) {
            if (class_exists('PTP_Comms_Hub_Teams_Integration')) {
                PTP_Comms_Hub_Teams_Integration::notify_new_order($order, $contact_id);
            }
        }
    }
    
    /**
     * Handle order cancellation
     */
    public static function handle_order_cancelled($order_id) {
        self::update_registration_status($order_id, 'cancelled');
        self::cancel_pending_automations($order_id);
    }
    
    /**
     * Handle order refund
     */
    public static function handle_order_refunded($order_id) {
        self::update_registration_status($order_id, 'refunded');
        self::cancel_pending_automations($order_id);
    }
    
    /**
     * Handle order failure
     */
    public static function handle_order_failed($order_id) {
        self::update_registration_status($order_id, 'failed');
        self::cancel_pending_automations($order_id);
    }
    
    /**
     * Single handler for all order status changes
     */
    public static function on_order_status_changed($order_id, $old_status, $new_status, $order) {
        global $wpdb;
        
        // Map WooCommerce statuses to registration statuses
        $status_map = array(
            'completed' => 'confirmed',
            'processing' => 'confirmed',
            'on-hold' => 'pending',
            'pending' => 'pending',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed'
        );
        
        $reg_status = isset($status_map[$new_status]) ? $status_map[$new_status] : 'pending';
        
        // Update all registrations for this order
        $wpdb->update(
            $wpdb->prefix . 'ptp_registrations',
            array(
                'registration_status' => $reg_status,
                'updated_at' => current_time('mysql')
            ),
            array('order_id' => $order_id)
        );
        
        error_log("[PTP Events Sync] Order {$order_id} status changed: {$old_status} -> {$new_status} (registration: {$reg_status})");
    }
    
    /**
     * Update registration status for an order
     */
    private static function update_registration_status($order_id, $status) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_registrations',
            array(
                'registration_status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('order_id' => $order_id)
        );
        
        error_log("[PTP Events Sync] Updated registrations for order {$order_id} to status: {$status}");
    }
    
    /**
     * Cancel pending automations for cancelled/refunded orders
     */
    private static function cancel_pending_automations($order_id) {
        global $wpdb;
        
        // Get all registrations for this order
        $registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT contact_id, event_date FROM {$wpdb->prefix}ptp_registrations WHERE order_id = %d",
            $order_id
        ));
        
        // Clear any scheduled automation cron jobs for these contacts/events
        foreach ($registrations as $reg) {
            // Remove scheduled single events for this contact
            $args = array($reg->contact_id);
            wp_clear_scheduled_hook('ptp_comms_execute_delayed_automation', $args);
        }
        
        error_log("[PTP Events Sync] Cancelled pending automations for order {$order_id}");
    }
    
    /**
     * Get registration data by order ID (for use in automations)
     */
    public static function get_registration_event_data($order_id) {
        global $wpdb;
        
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_registrations WHERE order_id = %d LIMIT 1",
            $order_id
        ));
        
        if (!$registration) {
            return array();
        }
        
        return array(
            'event_name' => $registration->product_name,
            'event_date' => $registration->event_date,
            'event_end_date' => $registration->event_end_date,
            'event_time' => $registration->event_time,
            'camp_time' => $registration->event_time,
            'event_location' => $registration->event_location,
            'event_address' => $registration->event_address,
            'program_type' => $registration->program_type,
            'market_slug' => $registration->market,
            'market' => $registration->market,
            'what_to_bring' => $registration->what_to_bring,
            'head_coach' => $registration->head_coach,
            'child_name' => $registration->child_name,
            'child_age' => $registration->child_age
        );
    }
}
