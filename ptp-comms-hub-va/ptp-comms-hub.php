<?php
/**
 * Plugin Name: PTP Comms Hub Enterprise
 * Plugin URI: https://ptpsoccercamps.com
 * Description: Enterprise VA relationship management platform with SMS, Voice, Microsoft Teams, HubSpot integration, contact notes, reminders, notifications, and advanced segmentation for PTP Soccer Camps
 * Version: 4.0.0
 * Author: PTP Soccer Camps
 * Author URI: https://ptpsoccercamps.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ptp-comms-hub
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version
define('PTP_COMMS_HUB_VERSION', '4.0.0');

// Plugin paths
define('PTP_COMMS_HUB_PATH', plugin_dir_path(__FILE__));
define('PTP_COMMS_HUB_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_ptp_comms_hub() {
    require_once PTP_COMMS_HUB_PATH . 'includes/class-activator.php';
    PTP_Comms_Hub_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ptp_comms_hub() {
    require_once PTP_COMMS_HUB_PATH . 'includes/class-deactivator.php';
    PTP_Comms_Hub_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_ptp_comms_hub');
register_deactivation_hook(__FILE__, 'deactivate_ptp_comms_hub');

/**
 * The core plugin class
 */
require PTP_COMMS_HUB_PATH . 'includes/class-loader.php';

/**
 * Enqueue admin styles
 */
function ptp_comms_hub_enqueue_admin_styles($hook) {
    if (strpos($hook, 'ptp-comms') === false) {
        return;
    }
    
    wp_enqueue_style(
        'ptp-comms-admin',
        PTP_COMMS_HUB_URL . 'admin/css/ptp-comms-admin.css',
        array(),
        PTP_COMMS_HUB_VERSION
    );
    
    // Add VA dashboard styles
    wp_enqueue_style(
        'ptp-comms-va-dashboard',
        PTP_COMMS_HUB_URL . 'admin/css/ptp-comms-va-dashboard.css',
        array(),
        PTP_COMMS_HUB_VERSION
    );
}
add_action('admin_enqueue_scripts', 'ptp_comms_hub_enqueue_admin_styles');

/**
 * AJAX handler for testing Twilio connection
 */
function ptp_comms_ajax_test_twilio_connection() {
    check_ajax_referer('ptp_test_twilio', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $sid = sanitize_text_field($_POST['sid']);
    $token = sanitize_text_field($_POST['token']);
    
    if (empty($sid) || empty($token)) {
        wp_send_json_error('Account SID and Auth Token are required');
    }
    
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '.json';
    
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token)
        ),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error('Connection failed: ' . $response->get_error_message());
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($code === 200 && isset($body['status'])) {
        wp_send_json_success(array(
            'message' => 'Connected! Account status: ' . ucfirst($body['status']),
            'friendly_name' => isset($body['friendly_name']) ? $body['friendly_name'] : ''
        ));
    } elseif ($code === 401) {
        wp_send_json_error('Invalid credentials. Please check your Account SID and Auth Token.');
    } else {
        wp_send_json_error('Connection failed with status: ' . $code);
    }
}
add_action('wp_ajax_ptp_test_twilio_connection', 'ptp_comms_ajax_test_twilio_connection');

/**
 * Developer hooks for extensibility
 */
function ptp_comms_init_hooks() {
    do_action('ptp_comms_hooks_initialized');
}
add_action('init', 'ptp_comms_init_hooks');

/**
 * Handle delayed automation execution
 */
function ptp_comms_execute_delayed_automation($automation_id, $contact_id, $event_data = array()) {
    if (class_exists('PTP_Comms_Hub_Automations')) {
        PTP_Comms_Hub_Automations::execute_automation($automation_id, $contact_id, $event_data);
    }
}
add_action('ptp_comms_execute_delayed_automation', 'ptp_comms_execute_delayed_automation', 10, 3);

/**
 * Register custom cron schedules
 */
function ptp_comms_add_cron_schedules($schedules) {
    if (!isset($schedules['every_minute'])) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'ptp-comms-hub')
        );
    }
    
    if (!isset($schedules['every_five_minutes'])) {
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'ptp-comms-hub')
        );
    }
    
    if (!isset($schedules['every_fifteen_minutes'])) {
        $schedules['every_fifteen_minutes'] = array(
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'ptp-comms-hub')
        );
    }
    
    return $schedules;
}
add_filter('cron_schedules', 'ptp_comms_add_cron_schedules');

/**
 * Schedule cron jobs on plugin load
 */
function ptp_comms_schedule_cron_jobs() {
    // Process automations every 5 minutes
    if (!wp_next_scheduled('ptp_comms_process_automations')) {
        wp_schedule_event(time(), 'every_five_minutes', 'ptp_comms_process_automations');
    }
    
    // Process campaign queue every minute
    if (!wp_next_scheduled('ptp_comms_process_campaign_queue')) {
        wp_schedule_event(time(), 'every_minute', 'ptp_comms_process_campaign_queue');
    }
    
    // Sync HubSpot contacts every 15 minutes
    if (!wp_next_scheduled('ptp_comms_sync_hubspot')) {
        wp_schedule_event(time(), 'every_fifteen_minutes', 'ptp_comms_sync_hubspot');
    }
    
    // Process reminders every 5 minutes
    if (!wp_next_scheduled('ptp_comms_process_reminders')) {
        wp_schedule_event(time(), 'every_five_minutes', 'ptp_comms_process_reminders');
    }
    
    // Send notification digests hourly
    if (!wp_next_scheduled('ptp_comms_send_notification_digest')) {
        wp_schedule_event(time(), 'hourly', 'ptp_comms_send_notification_digest');
    }
}
add_action('init', 'ptp_comms_schedule_cron_jobs');

/**
 * Process reminders cron handler
 */
function ptp_comms_process_reminders_handler() {
    if (class_exists('PTP_Comms_Hub_Reminders')) {
        PTP_Comms_Hub_Reminders::process_due_reminders();
    }
}
add_action('ptp_comms_process_reminders', 'ptp_comms_process_reminders_handler');

/**
 * Send notification digest handler
 */
function ptp_comms_send_notification_digest_handler() {
    if (class_exists('PTP_Comms_Hub_Notifications')) {
        PTP_Comms_Hub_Notifications::send_digest();
    }
}
add_action('ptp_comms_send_notification_digest', 'ptp_comms_send_notification_digest_handler');

/**
 * Install default canned replies on first activation
 */
function ptp_comms_maybe_install_defaults() {
    if (get_option('ptp_comms_defaults_installed')) {
        return;
    }
    
    if (class_exists('PTP_Comms_Hub_Canned_Replies')) {
        PTP_Comms_Hub_Canned_Replies::install_defaults();
    }
    
    update_option('ptp_comms_defaults_installed', true);
}
add_action('admin_init', 'ptp_comms_maybe_install_defaults');

/**
 * Begins execution of the plugin.
 */
function run_ptp_comms_hub() {
    $plugin = new PTP_Comms_Hub_Loader();
    $plugin->run();
}
run_ptp_comms_hub();
