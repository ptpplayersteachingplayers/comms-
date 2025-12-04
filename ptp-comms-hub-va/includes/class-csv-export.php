<?php
/**
 * CSV Export functionality
 * v3.4.0 - Export contacts, campaigns, logs to CSV
 */
class PTP_Comms_Hub_CSV_Export {
    
    /**
     * Initialize export handlers
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'handle_export_request'));
    }
    
    /**
     * Handle export request from admin
     */
    public static function handle_export_request() {
        if (!isset($_GET['ptp_export']) || !current_user_can('manage_options')) {
            return;
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ptp_export_csv')) {
            wp_die('Security check failed');
        }
        
        $export_type = sanitize_text_field($_GET['ptp_export']);
        
        switch ($export_type) {
            case 'contacts':
                self::export_contacts();
                break;
            case 'campaign':
                $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
                self::export_campaign($campaign_id);
                break;
            case 'logs':
                self::export_logs();
                break;
            case 'registrations':
                self::export_registrations();
                break;
            default:
                wp_die('Invalid export type');
        }
    }
    
    /**
     * Export contacts to CSV
     */
    public static function export_contacts() {
        global $wpdb;
        
        $contacts = $wpdb->get_results("
            SELECT 
                id,
                parent_first_name,
                parent_last_name,
                parent_email,
                parent_phone,
                child_first_name,
                child_last_name,
                child_age,
                city,
                state,
                zip_code,
                segments,
                opted_in,
                opted_out,
                hubspot_id,
                created_at,
                updated_at
            FROM {$wpdb->prefix}ptp_contacts
            ORDER BY created_at DESC
        ", ARRAY_A);
        
        $headers = array(
            'ID', 'Parent First Name', 'Parent Last Name', 'Email', 'Phone',
            'Child First Name', 'Child Last Name', 'Child Age',
            'City', 'State', 'ZIP', 'Segments',
            'Opted In', 'Opted Out', 'HubSpot ID', 'Created', 'Updated'
        );
        
        self::output_csv('ptp-contacts', $headers, $contacts);
    }
    
    /**
     * Export campaign results to CSV
     */
    public static function export_campaign($campaign_id) {
        if (!$campaign_id) {
            wp_die('Campaign ID required');
        }
        
        global $wpdb;
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign) {
            wp_die('Campaign not found');
        }
        
        // Check if campaign queue table exists
        $queue_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ptp_campaign_queue'");
        
        if ($queue_exists) {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    q.id as queue_id,
                    c.id as contact_id,
                    c.parent_first_name,
                    c.parent_last_name,
                    c.parent_phone,
                    c.parent_email,
                    q.status,
                    q.error_message,
                    q.created_at as queued_at,
                    q.processed_at
                FROM {$wpdb->prefix}ptp_campaign_queue q
                LEFT JOIN {$wpdb->prefix}ptp_contacts c ON q.contact_id = c.id
                WHERE q.campaign_id = %d
                ORDER BY q.id ASC
            ", $campaign_id), ARRAY_A);
            
            $headers = array(
                'Queue ID', 'Contact ID', 'First Name', 'Last Name', 'Phone', 'Email',
                'Status', 'Error', 'Queued At', 'Processed At'
            );
        } else {
            // Fallback to logs table
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    l.id as log_id,
                    c.id as contact_id,
                    c.parent_first_name,
                    c.parent_last_name,
                    c.parent_phone,
                    c.parent_email,
                    l.status,
                    l.error_message,
                    l.created_at
                FROM {$wpdb->prefix}ptp_communication_logs l
                LEFT JOIN {$wpdb->prefix}ptp_contacts c ON l.contact_id = c.id
                WHERE l.campaign_id = %d
                ORDER BY l.id ASC
            ", $campaign_id), ARRAY_A);
            
            $headers = array(
                'Log ID', 'Contact ID', 'First Name', 'Last Name', 'Phone', 'Email',
                'Status', 'Error', 'Sent At'
            );
        }
        
        $filename = 'campaign-' . $campaign_id . '-' . sanitize_title($campaign->name);
        self::output_csv($filename, $headers, $results);
    }
    
    /**
     * Export communication logs to CSV
     */
    public static function export_logs() {
        global $wpdb;
        
        // Get date range if provided
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
        
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT 
                l.id,
                c.parent_first_name,
                c.parent_last_name,
                c.parent_phone,
                l.message_type,
                l.direction,
                l.message_content,
                l.twilio_sid,
                l.status,
                l.error_message,
                l.created_at
            FROM {$wpdb->prefix}ptp_communication_logs l
            LEFT JOIN {$wpdb->prefix}ptp_contacts c ON l.contact_id = c.id
            WHERE DATE(l.created_at) BETWEEN %s AND %s
            ORDER BY l.created_at DESC
        ", $start_date, $end_date), ARRAY_A);
        
        $headers = array(
            'ID', 'First Name', 'Last Name', 'Phone', 'Type', 'Direction',
            'Message', 'Twilio SID', 'Status', 'Error', 'Created At'
        );
        
        $filename = 'communication-logs-' . $start_date . '-to-' . $end_date;
        self::output_csv($filename, $headers, $logs);
    }
    
    /**
     * Export registrations to CSV
     */
    public static function export_registrations() {
        global $wpdb;
        
        $registrations = $wpdb->get_results("
            SELECT 
                r.id,
                r.order_id,
                c.parent_first_name,
                c.parent_last_name,
                c.parent_phone,
                c.parent_email,
                c.child_first_name,
                c.child_last_name,
                c.child_age,
                r.product_id,
                r.event_name,
                r.event_date,
                r.event_location,
                r.registration_status,
                r.reminder_7day_sent,
                r.reminder_3day_sent,
                r.reminder_1day_sent,
                r.created_at
            FROM {$wpdb->prefix}ptp_registrations r
            LEFT JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
            ORDER BY r.event_date DESC, r.created_at DESC
        ", ARRAY_A);
        
        $headers = array(
            'ID', 'Order ID', 'Parent First', 'Parent Last', 'Phone', 'Email',
            'Child First', 'Child Last', 'Child Age', 'Product ID',
            'Event Name', 'Event Date', 'Location', 'Status',
            '7-Day Reminder', '3-Day Reminder', '1-Day Reminder', 'Created'
        );
        
        self::output_csv('registrations', $headers, $registrations);
    }
    
    /**
     * Output CSV file
     */
    private static function output_csv($filename, $headers, $data) {
        $filename = sanitize_file_name($filename . '-' . date('Y-m-d') . '.csv');
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, array_values($row));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Generate export URL
     */
    public static function get_export_url($type, $args = array()) {
        $url = add_query_arg(array_merge(array(
            'ptp_export' => $type,
            '_wpnonce' => wp_create_nonce('ptp_export_csv')
        ), $args), admin_url('admin.php'));
        
        return $url;
    }
    
    /**
     * Render export button
     */
    public static function render_export_button($type, $label = 'Export CSV', $args = array(), $class = 'button') {
        $url = self::get_export_url($type, $args);
        printf(
            '<a href="%s" class="%s"><span class="dashicons dashicons-download" style="vertical-align: middle;"></span> %s</a>',
            esc_url($url),
            esc_attr($class),
            esc_html($label)
        );
    }
}

// Initialize
add_action('init', array('PTP_Comms_Hub_CSV_Export', 'init'));
