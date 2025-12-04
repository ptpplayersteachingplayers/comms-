<?php
/**
 * The core plugin class
 */
class PTP_Comms_Hub_Loader {
    
    protected $version;
    
    public function __construct() {
        $this->version = PTP_COMMS_HUB_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    private function load_dependencies() {
        // Helper functions
        require_once PTP_COMMS_HUB_PATH . 'includes/helpers.php';
        
        // Core classes
        require_once PTP_COMMS_HUB_PATH . 'includes/class-settings.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-contacts.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-events-sync.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-campaigns.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-templates.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-conversations.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-automations.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-canned-replies.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-dashboard-widget.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-csv-export.php';
        
        // VA Relationship Management classes (v4.0)
        require_once PTP_COMMS_HUB_PATH . 'includes/class-contact-notes.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-reminders.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-notifications.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-saved-segments.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-activity-log.php';
        
        // Service classes
        require_once PTP_COMMS_HUB_PATH . 'includes/class-sms-service.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-voice-service.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-segmentation.php';
        
        // Integration classes
        require_once PTP_COMMS_HUB_PATH . 'includes/class-hubspot-sync.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-teams-integration.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-teams-shared-inbox.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-woocommerce.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-zoom-phone.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-whatsapp-integration.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-user-profile-whatsapp.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-private-training-integration.php';

        // API and webhooks
        require_once PTP_COMMS_HUB_PATH . 'includes/class-webhooks.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-rest-api.php';
        
        // Admin classes
        if (is_admin()) {
            require_once PTP_COMMS_HUB_PATH . 'includes/class-admin-menu.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-dashboard.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-contacts.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-campaigns.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-logs.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-templates.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-inbox.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-calls.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-automations.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-settings.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-orders.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-canned-replies.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-reminders.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-segments.php';
        }
        
        // Public-facing class
        require_once PTP_COMMS_HUB_PATH . 'public/class-public.php';
    }
    
    private function define_admin_hooks() {
        if (is_admin()) {
            // Initialize admin menu
            $admin_menu = new PTP_Comms_Hub_Admin_Menu();
            
            // Enqueue admin scripts and styles
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
    }
    
    private function define_public_hooks() {
        $public = new PTP_Comms_Hub_Public();
        
        // Enqueue public scripts and styles
        add_action('wp_enqueue_scripts', array($public, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($public, 'enqueue_scripts'));
    }
    
    public function enqueue_admin_styles() {
        wp_enqueue_style(
            'ptp-comms-hub-admin',
            PTP_COMMS_HUB_URL . 'admin/css/admin.css',
            array(),
            $this->version
        );

        // Add responsive styles
        wp_enqueue_style(
            'ptp-comms-hub-responsive',
            PTP_COMMS_HUB_URL . 'admin/css/ptp-comms-responsive.css',
            array('ptp-comms-hub-admin'),
            $this->version
        );
    }
    
    public function enqueue_admin_scripts() {
        wp_enqueue_script(
            'ptp-comms-hub-admin',
            PTP_COMMS_HUB_URL . 'admin/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );
        
        wp_localize_script('ptp-comms-hub-admin', 'ptpCommsData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ptp_comms_hub_nonce')
        ));
    }
    
    public function run() {
        // Initialize webhooks
        PTP_Comms_Hub_Webhooks::init();
        
        // Initialize REST API
        add_action('rest_api_init', array('PTP_Comms_Hub_REST_API', 'register_routes'));
        
        // Initialize WooCommerce integration (replaces old Events Sync)
        PTP_Comms_Hub_WooCommerce::init();
        
        // Initialize Teams Shared Inbox
        PTP_Comms_Hub_Teams_Shared_Inbox::init();
        
        // Initialize Campaigns (for queue-based sending)
        if (class_exists('PTP_Comms_Hub_Campaigns')) {
            PTP_Comms_Hub_Campaigns::init();
        }
        
        // Initialize cron jobs
        add_action('ptp_comms_process_automations', array('PTP_Comms_Hub_Automations', 'process_pending_automations'));
        add_action('ptp_comms_sync_hubspot', array('PTP_Comms_Hub_HubSpot_Sync', 'sync_all_contacts'));
        add_action('ptp_comms_process_campaign_queue', array('PTP_Comms_Hub_Campaigns', 'process_queue'));
        
        // Register AJAX handlers
        add_action('wp_ajax_ptp_comms_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_ptp_comms_get_new_messages', array($this, 'ajax_get_new_messages'));
        add_action('wp_ajax_ptp_comms_check_unread', array($this, 'ajax_check_unread'));
        add_action('wp_ajax_ptp_comms_start_conversation', array($this, 'ajax_start_conversation'));
    }
    
    public function ajax_send_message() {
        check_ajax_referer('ptp_comms_hub_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        global $wpdb;

        $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
        $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        $message_type = isset($_POST['message_type']) ? sanitize_text_field($_POST['message_type']) : 'sms';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (empty($message) || empty($contact_id)) {
            wp_send_json_error(array('message' => 'Message and contact required'));
        }

        // DUPLICATE PREVENTION: Create unique lock key for this message
        $lock_key = 'ptp_msg_lock_' . md5($contact_id . $message . get_current_user_id());

        // Check if this exact message was just sent (within 10 seconds)
        if (get_transient($lock_key)) {
            // Return success but don't send again - likely a duplicate request
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_messages
                 WHERE contact_id = %d AND message_body = %s AND direction = 'outbound'
                 ORDER BY created_at DESC LIMIT 1",
                $contact_id, $message
            ));
            if ($existing) {
                wp_send_json_success(array(
                    'message' => (array) $existing,
                    'sid' => $existing->twilio_sid,
                    'duplicate' => true
                ));
            }
        }

        // Set lock to prevent duplicate sends
        set_transient($lock_key, true, 10);

        // Get contact
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));

        if (!$contact) {
            delete_transient($lock_key);
            wp_send_json_error(array('message' => 'Contact not found'));
        }

        // Send message via appropriate channel
        $result = array('success' => false, 'error' => 'Invalid message type');

        if ($message_type === 'sms') {
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $result = $sms_service->send_sms($contact->parent_phone, $message);
        } elseif ($message_type === 'whatsapp') {
            if (function_exists('ptp_comms_send_whatsapp')) {
                $result = ptp_comms_send_whatsapp($contact->parent_phone, $message);
            }
        } elseif ($message_type === 'voice') {
            $voice_service = new PTP_Comms_Hub_Voice_Service();
            $result = $voice_service->make_call($contact->parent_phone, $message);
        }
        
        if (!$result['success']) {
            wp_send_json_error(array('message' => $result['error']));
        }
        
        // Insert message into database
        $message_data = array(
            'conversation_id' => $conversation_id,
            'contact_id' => $contact_id,
            'direction' => 'outbound',
            'message_type' => $message_type,
            'message_body' => $message,
            'twilio_sid' => $result['sid'],
            'status' => 'sent',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ptp_messages',
            $message_data
        );
        
        $message_id = $wpdb->insert_id;
        $message_data['id'] = $message_id;
        
        // Update conversation
        $wpdb->update(
            $wpdb->prefix . 'ptp_conversations',
            array(
                'last_message' => $message,
                'last_message_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $conversation_id)
        );
        
        wp_send_json_success(array(
            'message' => $message_data,
            'sid' => $result['sid']
        ));
    }
    
    public function ajax_get_new_messages() {
        check_ajax_referer('ptp_comms_hub_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        global $wpdb;

        $conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
        $after_id = isset($_GET['after_id']) ? intval($_GET['after_id']) : 0;

        if (empty($conversation_id)) {
            wp_send_json_error(array('message' => 'Conversation ID required'));
        }

        // Use transient cache to reduce DB load on polling
        $cache_key = 'ptp_new_msgs_' . $conversation_id . '_' . $after_id;
        $messages = get_transient($cache_key);

        if ($messages === false) {
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT id, conversation_id, contact_id, direction, message_type, message_body, status, created_at
                FROM {$wpdb->prefix}ptp_messages
                WHERE conversation_id = %d AND id > %d
                ORDER BY created_at ASC
                LIMIT 50",
                $conversation_id,
                $after_id
            ));

            // Cache for 2 seconds to handle rapid polling
            set_transient($cache_key, $messages, 2);
        }

        // Mark conversation as read (only if we have new messages)
        if (!empty($messages)) {
            $wpdb->update(
                $wpdb->prefix . 'ptp_conversations',
                array('unread_count' => 0),
                array('id' => $conversation_id)
            );
        }

        wp_send_json_success(array('messages' => $messages));
    }
    
    public function ajax_check_unread() {
        check_ajax_referer('ptp_comms_hub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }
        
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT SUM(unread_count) FROM {$wpdb->prefix}ptp_conversations WHERE status IN ('open', 'active', 'pending')"
        );
        
        wp_send_json_success(array(
            'count' => intval($count),
            'newMessages' => intval($count) > 0
        ));
    }
    
    /**
     * AJAX handler to start a new conversation with an opted-in contact
     */
    public function ajax_start_conversation() {
        check_ajax_referer('ptp_comms_hub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }
        
        global $wpdb;
        
        $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (empty($contact_id)) {
            wp_send_json_error(array('message' => 'Please select a contact'));
        }
        
        if (empty($message)) {
            wp_send_json_error(array('message' => 'Please enter a message'));
        }
        
        // Get contact
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            wp_send_json_error(array('message' => 'Contact not found'));
        }
        
        if (empty($contact->parent_phone)) {
            wp_send_json_error(array('message' => 'Contact has no phone number'));
        }
        
        if (!$contact->opted_in) {
            wp_send_json_error(array('message' => 'Contact has not opted in to receive messages'));
        }
        
        // Check for existing conversation or create new one
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_conversations WHERE contact_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
            $contact_id
        ));
        
        if (!$conversation) {
            // Create new conversation
            $wpdb->insert(
                $wpdb->prefix . 'ptp_conversations',
                array(
                    'contact_id' => $contact_id,
                    'status' => 'active',
                    'unread_count' => 0,
                    'last_message' => $message,
                    'last_message_at' => current_time('mysql'),
                    'created_at' => current_time('mysql')
                )
            );
            $conversation_id = $wpdb->insert_id;
        } else {
            $conversation_id = $conversation->id;
        }
        
        if (!$conversation_id) {
            wp_send_json_error(array('message' => 'Failed to create conversation'));
        }
        
        // Send SMS via Twilio
        $sms_service = new PTP_Comms_Hub_SMS_Service();
        $result = $sms_service->send_sms($contact->parent_phone, $message);
        
        if (!$result['success']) {
            $error_msg = isset($result['error']) ? $result['error'] : 'Failed to send SMS';
            wp_send_json_error(array('message' => $error_msg));
        }
        
        // Log the message
        $wpdb->insert(
            $wpdb->prefix . 'ptp_messages',
            array(
                'conversation_id' => $conversation_id,
                'contact_id' => $contact_id,
                'direction' => 'outbound',
                'message_type' => 'sms',
                'message_body' => $message,
                'twilio_sid' => isset($result['sid']) ? $result['sid'] : null,
                'status' => 'sent',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );
        
        // Update conversation last message
        $wpdb->update(
            $wpdb->prefix . 'ptp_conversations',
            array(
                'last_message' => $message,
                'last_message_at' => current_time('mysql')
            ),
            array('id' => $conversation_id)
        );
        
        wp_send_json_success(array(
            'conversation_id' => $conversation_id,
            'message' => 'Message sent successfully'
        ));
    }
    
    public function get_version() {
        return $this->version;
    }
}
