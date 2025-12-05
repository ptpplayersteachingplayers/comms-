<?php
/**
 * WooCommerce Integration
 * Optional integration with WooCommerce for cart and order management
 */

if (!defined('ABSPATH')) exit;

class PTP_WooCommerce {
    
    private static $instance = null;
    private static $product_id = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'ensure_product_exists'));
        add_filter('woocommerce_is_purchasable', array($this, 'make_hidden_product_purchasable'), 10, 2);
        add_filter('woocommerce_product_is_visible', array($this, 'hide_training_product'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_data'), 10, 4);
        add_action('woocommerce_order_status_completed', array($this, 'process_completed_order'));
        add_action('woocommerce_order_status_processing', array($this, 'process_completed_order'));
    }
    
    /**
     * Ensure a hidden product exists for private training sessions
     */
    public function ensure_product_exists() {
        self::$product_id = get_option('ptp_training_product_id');
        
        if (self::$product_id) {
            $product = wc_get_product(self::$product_id);
            if ($product) {
                return;
            }
        }
        
        // Create hidden product
        $product = new WC_Product_Simple();
        $product->set_name('Private Training Session');
        $product->set_slug('ptp-private-training-session');
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_price(0);
        $product->set_regular_price(0);
        $product->set_sold_individually(false);
        $product->set_virtual(true);
        $product->set_description('Private soccer training session with a PTP trainer.');
        $product->save();
        
        self::$product_id = $product->get_id();
        update_option('ptp_training_product_id', self::$product_id);
    }
    
    /**
     * Make sure hidden product is still purchasable
     */
    public function make_hidden_product_purchasable($purchasable, $product) {
        if ($product->get_id() == self::$product_id) {
            return true;
        }
        return $purchasable;
    }
    
    /**
     * Hide training product from shop
     */
    public function hide_training_product($visible, $product_id) {
        if ($product_id == self::$product_id) {
            return false;
        }
        return $visible;
    }
    
    /**
     * Add training session to cart
     */
    public static function add_to_cart($data) {
        if (!self::$product_id) {
            return new WP_Error('no_product', 'Training product not configured');
        }
        
        WC()->cart->empty_cart();
        
        $cart_item_data = array(
            'ptp_training' => true,
            'trainer_id' => $data['trainer_id'],
            'trainer_name' => $data['trainer_name'],
            'pack_type' => $data['pack_type'],
            'sessions' => $data['sessions'],
            'athlete_name' => $data['athlete_name'],
            'athlete_age' => $data['athlete_age'],
            'athlete_skill' => $data['athlete_skill'],
            'athlete_goals' => $data['athlete_goals'],
            'custom_price' => $data['price']
        );
        
        // Override price
        add_filter('woocommerce_product_get_price', function($price, $product) use ($data) {
            if ($product->get_id() == self::$product_id) {
                return $data['price'];
            }
            return $price;
        }, 10, 2);
        
        $cart_key = WC()->cart->add_to_cart(self::$product_id, 1, 0, array(), $cart_item_data);
        
        if (!$cart_key) {
            return new WP_Error('cart_error', 'Could not add to cart');
        }
        
        return wc_get_checkout_url();
    }
    
    /**
     * Display custom data in cart
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['ptp_training'])) {
            $item_data[] = array(
                'key' => 'Trainer',
                'value' => $cart_item['trainer_name']
            );
            $item_data[] = array(
                'key' => 'Sessions',
                'value' => $cart_item['sessions']
            );
            $item_data[] = array(
                'key' => 'Athlete',
                'value' => $cart_item['athlete_name'] . ' (Age ' . $cart_item['athlete_age'] . ')'
            );
        }
        return $item_data;
    }
    
    /**
     * Save training data to order item
     */
    public function save_order_item_data($item, $cart_item_key, $values, $order) {
        if (isset($values['ptp_training'])) {
            $item->add_meta_data('_ptp_training', true);
            $item->add_meta_data('_ptp_trainer_id', $values['trainer_id']);
            $item->add_meta_data('_ptp_pack_type', $values['pack_type']);
            $item->add_meta_data('_ptp_sessions', $values['sessions']);
            $item->add_meta_data('_ptp_athlete_name', $values['athlete_name']);
            $item->add_meta_data('_ptp_athlete_age', $values['athlete_age']);
            $item->add_meta_data('_ptp_athlete_skill', $values['athlete_skill']);
            $item->add_meta_data('_ptp_athlete_goals', $values['athlete_goals']);
        }
    }
    
    /**
     * Process completed order - create lesson pack and sessions
     */
    public function process_completed_order($order_id) {
        $order = wc_get_order($order_id);
        
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_ptp_training')) {
                $this->create_lesson_pack($order, $item);
            }
        }
    }
    
    /**
     * Create lesson pack from WooCommerce order
     */
    private function create_lesson_pack($order, $item) {
        global $wpdb;
        
        $trainer_id = $item->get_meta('_ptp_trainer_id');
        $sessions = $item->get_meta('_ptp_sessions');
        $pack_type = $item->get_meta('_ptp_pack_type');
        $price = $item->get_total();
        
        // Check if already processed
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_lesson_packs WHERE order_id = %d",
            $order->get_id()
        ));
        
        if ($existing) {
            return;
        }
        
        // Create pack
        $pack_data = array(
            'customer_id' => $order->get_customer_id(),
            'trainer_id' => $trainer_id,
            'order_id' => $order->get_id(),
            'pack_type' => $pack_type,
            'total_sessions' => $sessions,
            'sessions_used' => 0,
            'sessions_remaining' => $sessions,
            'price_paid' => $price,
            'price_per_session' => $price / $sessions,
            'athlete_name' => $item->get_meta('_ptp_athlete_name'),
            'athlete_age' => $item->get_meta('_ptp_athlete_age'),
            'athlete_skill_level' => $item->get_meta('_ptp_athlete_skill'),
            'athlete_goals' => $item->get_meta('_ptp_athlete_goals'),
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+6 months'))
        );
        
        $wpdb->insert("{$wpdb->prefix}ptp_lesson_packs", $pack_data);
        $pack_id = $wpdb->insert_id;
        
        // Create placeholder sessions
        for ($i = 0; $i < $sessions; $i++) {
            $wpdb->insert("{$wpdb->prefix}ptp_sessions", array(
                'pack_id' => $pack_id,
                'trainer_id' => $trainer_id,
                'customer_id' => $order->get_customer_id(),
                'session_date' => '0000-00-00',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'status' => 'unscheduled'
            ));
        }
        
        // Send notifications
        $this->send_purchase_confirmation($pack_id, $order);
    }
    
    /**
     * Send confirmation email
     */
    private function send_purchase_confirmation($pack_id, $order) {
        $pack = PTP_Database::get_pack($pack_id);
        
        $subject = 'Training Package Confirmed - ' . $pack->trainer_name;
        $message = "Hi {$order->get_billing_first_name()},\n\n";
        $message .= "Your training package with {$pack->trainer_name} has been confirmed!\n\n";
        $message .= "Package Details:\n";
        $message .= "- Sessions: {$pack->total_sessions}\n";
        $message .= "- Athlete: {$pack->athlete_name}\n";
        $message .= "- Order #: {$order->get_id()}\n\n";
        $message .= "Visit your dashboard to schedule your sessions: " . home_url('/my-training/') . "\n\n";
        $message .= "See you on the field!\nThe PTP Team";
        
        wp_mail($order->get_billing_email(), $subject, $message);
    }
    
    /**
     * Get product ID
     */
    public static function get_product_id() {
        return self::$product_id;
    }
}

// Initialize if WooCommerce is active
if (class_exists('WooCommerce')) {
    PTP_WooCommerce::instance();
}
