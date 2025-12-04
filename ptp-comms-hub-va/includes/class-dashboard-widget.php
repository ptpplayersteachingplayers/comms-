<?php
/**
 * PTP Comms Hub Dashboard Widget
 * Displays key metrics on the WordPress admin dashboard
 * v3.4.0
 */
class PTP_Comms_Hub_Dashboard_Widget {
    
    /**
     * Initialize dashboard widget
     */
    public static function init() {
        add_action('wp_dashboard_setup', array(__CLASS__, 'register_widget'));
        add_action('wp_ajax_ptp_comms_dashboard_refresh', array(__CLASS__, 'ajax_refresh_stats'));
    }
    
    /**
     * Register the dashboard widget
     */
    public static function register_widget() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'ptp_comms_dashboard_widget',
            '<span class="dashicons dashicons-email-alt" style="color: #FCB900;"></span> PTP Communications Hub',
            array(__CLASS__, 'render_widget'),
            array(__CLASS__, 'widget_config')
        );
        
        // Move widget to top
        global $wp_meta_boxes;
        $dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
        $widget = array('ptp_comms_dashboard_widget' => $dashboard['ptp_comms_dashboard_widget']);
        unset($dashboard['ptp_comms_dashboard_widget']);
        $wp_meta_boxes['dashboard']['normal']['core'] = array_merge($widget, $dashboard);
    }
    
    /**
     * Render the dashboard widget
     */
    public static function render_widget() {
        $stats = self::get_stats();
        ?>
        <div id="ptp-comms-dashboard-widget" style="margin: -12px -12px -10px;">
            <style>
                #ptp-comms-dashboard-widget { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
                .ptp-dash-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; border-bottom: 1px solid #e2e4e7; }
                .ptp-dash-stat { padding: 15px 12px; text-align: center; border-right: 1px solid #e2e4e7; }
                .ptp-dash-stat:last-child { border-right: none; }
                .ptp-dash-stat-value { font-size: 24px; font-weight: 600; color: #1d2327; line-height: 1.2; }
                .ptp-dash-stat-value.highlight { color: #FCB900; }
                .ptp-dash-stat-value.warning { color: #d63638; }
                .ptp-dash-stat-label { font-size: 11px; color: #646970; text-transform: uppercase; margin-top: 4px; }
                .ptp-dash-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; border-bottom: 1px solid #f0f0f1; }
                .ptp-dash-row:hover { background: #f6f7f7; }
                .ptp-dash-row:last-child { border-bottom: none; }
                .ptp-dash-title { font-weight: 500; color: #1d2327; }
                .ptp-dash-badge { background: #f0f0f1; padding: 2px 8px; border-radius: 10px; font-size: 12px; color: #646970; }
                .ptp-dash-badge.success { background: #d4edda; color: #155724; }
                .ptp-dash-badge.danger { background: #f8d7da; color: #721c24; }
                .ptp-dash-badge.warning { background: #fff3cd; color: #856404; }
                .ptp-dash-link { color: #2271b1; text-decoration: none; font-size: 13px; }
                .ptp-dash-link:hover { color: #135e96; text-decoration: underline; }
                .ptp-dash-section { padding: 12px 15px; background: #f6f7f7; border-bottom: 1px solid #e2e4e7; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #646970; }
                .ptp-dash-actions { display: flex; gap: 8px; padding: 12px 15px; background: #f9f9f9; }
                .ptp-dash-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; font-size: 12px; border-radius: 4px; text-decoration: none; cursor: pointer; border: 1px solid #c3c4c7; background: #fff; color: #2c3338; }
                .ptp-dash-btn:hover { background: #f6f7f7; border-color: #8c8f94; color: #1d2327; }
                .ptp-dash-btn.primary { background: #FCB900; border-color: #FCB900; color: #000; }
                .ptp-dash-btn.primary:hover { background: #e0a600; border-color: #e0a600; }
            </style>
            
            <!-- Top Stats Row -->
            <div class="ptp-dash-stats">
                <div class="ptp-dash-stat">
                    <div class="ptp-dash-stat-value <?php echo $stats['unread'] > 0 ? 'warning' : ''; ?>">
                        <?php echo number_format($stats['unread']); ?>
                    </div>
                    <div class="ptp-dash-stat-label">Unread</div>
                </div>
                <div class="ptp-dash-stat">
                    <div class="ptp-dash-stat-value"><?php echo number_format($stats['sent_today']); ?></div>
                    <div class="ptp-dash-stat-label">Sent Today</div>
                </div>
                <div class="ptp-dash-stat">
                    <div class="ptp-dash-stat-value highlight"><?php echo number_format($stats['contacts']); ?></div>
                    <div class="ptp-dash-stat-label">Contacts</div>
                </div>
                <div class="ptp-dash-stat">
                    <div class="ptp-dash-stat-value"><?php echo number_format($stats['upcoming_events']); ?></div>
                    <div class="ptp-dash-stat-label">Upcoming</div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="ptp-dash-section">Recent Activity</div>
            
            <?php if (empty($stats['recent_messages'])): ?>
            <div class="ptp-dash-row" style="color: #646970; justify-content: center;">
                No recent messages
            </div>
            <?php else: ?>
            <?php foreach ($stats['recent_messages'] as $msg): ?>
            <div class="ptp-dash-row">
                <div>
                    <span class="ptp-dash-title"><?php echo esc_html($msg->name); ?></span>
                    <span style="color: #646970; font-size: 12px; margin-left: 5px;">
                        <?php echo esc_html(wp_trim_words($msg->message, 8, '...')); ?>
                    </span>
                </div>
                <div>
                    <span class="ptp-dash-badge <?php echo $msg->direction === 'inbound' ? 'warning' : 'success'; ?>">
                        <?php echo $msg->direction === 'inbound' ? 'In' : 'Out'; ?>
                    </span>
                    <span style="color: #646970; font-size: 11px; margin-left: 5px;">
                        <?php echo human_time_diff(strtotime($msg->created_at), current_time('timestamp')); ?> ago
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Automations Status -->
            <div class="ptp-dash-section">Automations</div>
            <div class="ptp-dash-row">
                <span class="ptp-dash-title">Active Automations</span>
                <span class="ptp-dash-badge success"><?php echo number_format($stats['active_automations']); ?> running</span>
            </div>
            <div class="ptp-dash-row">
                <span class="ptp-dash-title">Campaigns Today</span>
                <span class="ptp-dash-badge"><?php echo number_format($stats['campaigns_today']); ?></span>
            </div>
            
            <!-- System Status -->
            <?php if (!empty($stats['twilio_balance'])): ?>
            <div class="ptp-dash-row">
                <span class="ptp-dash-title">Twilio Balance</span>
                <span class="ptp-dash-badge <?php echo $stats['twilio_balance'] < 10 ? 'danger' : 'success'; ?>">
                    $<?php echo number_format($stats['twilio_balance'], 2); ?>
                </span>
            </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="ptp-dash-actions">
                <a href="<?php echo admin_url('admin.php?page=ptp-comms-inbox'); ?>" class="ptp-dash-btn primary">
                    <span class="dashicons dashicons-email" style="font-size: 14px;"></span>
                    View Inbox
                </a>
                <a href="<?php echo admin_url('admin.php?page=ptp-comms-campaigns&action=new'); ?>" class="ptp-dash-btn">
                    <span class="dashicons dashicons-megaphone" style="font-size: 14px;"></span>
                    New Campaign
                </a>
                <a href="<?php echo admin_url('admin.php?page=ptp-comms-contacts'); ?>" class="ptp-dash-btn">
                    <span class="dashicons dashicons-groups" style="font-size: 14px;"></span>
                    Contacts
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Widget configuration callback
     */
    public static function widget_config() {
        // Configuration form if needed
        echo '<p>Configure notification settings in the <a href="' . admin_url('admin.php?page=ptp-comms-settings') . '">PTP Comms Hub Settings</a>.</p>';
    }
    
    /**
     * Get dashboard statistics
     */
    public static function get_stats() {
        global $wpdb;
        
        $today = current_time('Y-m-d');
        
        // Unread conversations
        $unread = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE unread_count > 0 AND status IN ('open', 'active', 'pending')"
        );
        
        // Messages sent today
        $sent_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs 
             WHERE direction = 'outbound' AND DATE(created_at) = %s",
            $today
        ));
        
        // Total opted-in contacts
        $contacts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1 AND opted_out = 0"
        );
        
        // Upcoming events (next 14 days)
        $upcoming_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT product_id) FROM {$wpdb->prefix}ptp_registrations 
             WHERE event_date BETWEEN %s AND %s AND registration_status = 'confirmed'",
            $today,
            date('Y-m-d', strtotime('+14 days'))
        ));
        
        // Recent messages
        $recent_messages = $wpdb->get_results("
            SELECT m.*, c.parent_first_name, c.parent_last_name,
                   CONCAT(c.parent_first_name, ' ', c.parent_last_name) as name,
                   m.message_body as message
            FROM {$wpdb->prefix}ptp_messages m
            JOIN {$wpdb->prefix}ptp_contacts c ON m.contact_id = c.id
            ORDER BY m.created_at DESC
            LIMIT 5
        ");
        
        // Active automations
        $active_automations = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_automations WHERE is_active = 1"
        );
        
        // Campaigns sent today
        $campaigns_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_campaigns 
             WHERE status IN ('sending', 'completed') AND DATE(started_at) = %s",
            $today
        ));
        
        // Twilio balance (cached for 1 hour)
        $twilio_balance = get_transient('ptp_comms_twilio_balance');
        if ($twilio_balance === false && ptp_comms_is_twilio_configured()) {
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $balance_info = $sms_service->get_account_balance();
            if ($balance_info && isset($balance_info['balance'])) {
                $twilio_balance = $balance_info['balance'];
                set_transient('ptp_comms_twilio_balance', $twilio_balance, HOUR_IN_SECONDS);
            }
        }
        
        return array(
            'unread' => intval($unread),
            'sent_today' => intval($sent_today),
            'contacts' => intval($contacts),
            'upcoming_events' => intval($upcoming_events),
            'recent_messages' => $recent_messages,
            'active_automations' => intval($active_automations),
            'campaigns_today' => intval($campaigns_today),
            'twilio_balance' => $twilio_balance ? floatval($twilio_balance) : null
        );
    }
    
    /**
     * AJAX handler for refreshing stats
     */
    public static function ajax_refresh_stats() {
        check_ajax_referer('ptp_comms_hub_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        wp_send_json_success(self::get_stats());
    }
}

// Initialize
add_action('init', array('PTP_Comms_Hub_Dashboard_Widget', 'init'));
