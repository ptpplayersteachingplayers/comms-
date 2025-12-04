<?php
/**
 * Admin menu structure
 */
class PTP_Comms_Hub_Admin_Menu {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
    }
    
    public function add_menu_pages() {
        add_menu_page(
            'PTP Comms Hub',
            'PTP Comms',
            'manage_options',
            'ptp-comms-dashboard',
            array('PTP_Comms_Hub_Admin_Page_Dashboard', 'render'),
            'dashicons-email',
            30
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'ptp-comms-dashboard',
            array('PTP_Comms_Hub_Admin_Page_Dashboard', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Contacts',
            'Contacts',
            'manage_options',
            'ptp-comms-contacts',
            array('PTP_Comms_Hub_Admin_Page_Contacts', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Segments',
            '🎯 Segments',
            'manage_options',
            'ptp-comms-segments',
            array('PTP_Comms_Hub_Admin_Page_Segments', 'render')
        );
        
        // Get reminder counts for badge
        $reminder_count = '';
        if (class_exists('PTP_Comms_Hub_Reminders')) {
            $counts = PTP_Comms_Hub_Reminders::get_counts(get_current_user_id());
            $pending = $counts['overdue'] + $counts['today'];
            if ($pending > 0) {
                $reminder_count = ' <span class="awaiting-mod">' . $pending . '</span>';
            }
        }
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Reminders',
            '🔔 Reminders' . $reminder_count,
            'manage_options',
            'ptp-comms-reminders',
            array('PTP_Comms_Hub_Admin_Page_Reminders', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Campaigns',
            'Campaigns',
            'manage_options',
            'ptp-comms-campaigns',
            array('PTP_Comms_Hub_Admin_Page_Campaigns', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Orders & Camps',
            '🛒 Orders & Camps',
            'manage_options',
            'ptp-comms-orders',
            array('PTP_Comms_Hub_Admin_Page_Orders', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Communication Logs',
            'Logs',
            'manage_options',
            'ptp-comms-logs',
            array('PTP_Comms_Hub_Admin_Page_Logs', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Templates',
            'Templates',
            'manage_options',
            'ptp-comms-templates',
            array('PTP_Comms_Hub_Admin_Page_Templates', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Canned Replies',
            'Quick Replies',
            'manage_options',
            'ptp-comms-canned-replies',
            array('PTP_Comms_Hub_Admin_Page_Canned_Replies', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Inbox',
            'Inbox',
            'manage_options',
            'ptp-comms-inbox',
            array('PTP_Comms_Hub_Admin_Page_Inbox', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Voice Calls',
            'Calls',
            'manage_options',
            'ptp-comms-calls',
            array('PTP_Comms_Hub_Admin_Page_Calls', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Automations',
            'Automations',
            'manage_options',
            'ptp-comms-automations',
            array('PTP_Comms_Hub_Admin_Page_Automations', 'render')
        );
        
        add_submenu_page(
            'ptp-comms-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'ptp-comms-settings',
            array('PTP_Comms_Hub_Admin_Page_Settings', 'render')
        );
    }
}
