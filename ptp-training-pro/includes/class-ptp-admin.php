<?php
/**
 * PTP Training Pro - Admin Dashboard & Settings v4.0
 * Polished admin interface with organized sections and consistent styling
 */

if (!defined('ABSPATH')) exit;

class PTP_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_ptp_approve_application', array($this, 'approve_application'));
        add_action('wp_ajax_ptp_reject_application', array($this, 'reject_application'));
        add_action('wp_ajax_ptp_process_payout', array($this, 'process_payout'));
        add_action('wp_ajax_ptp_update_trainer_status', array($this, 'update_trainer_status'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'PTP Training',
            'PTP Training',
            'manage_options',
            'ptp-training',
            array($this, 'render_dashboard'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page('ptp-training', 'Dashboard', 'Dashboard', 'manage_options', 'ptp-training', array($this, 'render_dashboard'));
        add_submenu_page('ptp-training', 'Trainers', 'Trainers', 'manage_options', 'ptp-training-trainers', array($this, 'render_trainers'));
        add_submenu_page('ptp-training', 'Applications', 'Applications', 'manage_options', 'ptp-training-applications', array($this, 'render_applications'));
        add_submenu_page('ptp-training', 'Sessions', 'Sessions', 'manage_options', 'ptp-training-sessions', array($this, 'render_sessions'));
        add_submenu_page('ptp-training', 'Payouts', 'Payouts', 'manage_options', 'ptp-training-payouts', array($this, 'render_payouts'));
        add_submenu_page('ptp-training', 'Settings', 'Settings', 'manage_options', 'ptp-training-settings', array($this, 'render_settings'));
    }
    
    public function register_settings() {
        // Platform settings
        register_setting('ptp_training_settings', 'ptp_platform_fee_percent', array('type' => 'number', 'default' => 25, 'sanitize_callback' => 'absint'));
        register_setting('ptp_training_settings', 'ptp_trainer_referral_commission', array('type' => 'number', 'default' => 10, 'sanitize_callback' => 'absint'));
        register_setting('ptp_training_settings', 'ptp_admin_email');
        
        // Stripe settings
        register_setting('ptp_training_settings', 'ptp_stripe_publishable_key');
        register_setting('ptp_training_settings', 'ptp_stripe_secret_key');
        register_setting('ptp_training_settings', 'ptp_stripe_webhook_secret');
        
        // Google settings
        register_setting('ptp_training_settings', 'ptp_google_maps_key');
        register_setting('ptp_training_settings', 'ptp_google_client_id');
        register_setting('ptp_training_settings', 'ptp_google_client_secret');
        
        // SMS settings
        register_setting('ptp_training_settings', 'ptp_twilio_sid');
        register_setting('ptp_training_settings', 'ptp_twilio_token');
        register_setting('ptp_training_settings', 'ptp_twilio_phone');
        register_setting('ptp_training_settings', 'ptp_sms_enabled', array('type' => 'boolean', 'default' => false));
    }
    
    /**
     * Get admin statistics
     */
    private function get_stats() {
        global $wpdb;
        
        return array(
            'total_trainers' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_trainers WHERE status = 'approved'") ?: 0,
            'pending_applications' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_applications WHERE status = 'pending'") ?: 0,
            'total_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions") ?: 0,
            'upcoming_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions WHERE status = 'scheduled' AND session_date >= CURDATE()") ?: 0,
            'total_revenue' => $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}ptp_sessions WHERE status IN ('completed', 'paid')") ?: 0,
            'platform_revenue' => $wpdb->get_var("SELECT COALESCE(SUM(platform_fee), 0) FROM {$wpdb->prefix}ptp_sessions WHERE status IN ('completed', 'paid')") ?: 0,
            'pending_payouts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_payouts WHERE status = 'pending'") ?: 0,
            'active_packs' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_lesson_packs WHERE sessions_remaining > 0") ?: 0,
        );
    }
    
    /**
     * SVG icon helper
     */
    private function icon($name) {
        $icons = array(
            'users' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'clock' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>',
            'dollar' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
            'briefcase' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
            'inbox' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
            'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'x-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>',
            'alert-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>',
            'mail' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
            'phone' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
            'map-pin' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            'eye' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
            'edit' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
            'trash' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>',
            'external-link' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>',
            'settings' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
            'package' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
        );
        
        return $icons[$name] ?? '';
    }

    /* ======================================
       DASHBOARD PAGE
       ====================================== */
    public function render_dashboard() {
        $stats = $this->get_stats();
        ?>
        <div class="wrap ptp-admin">
            <!-- Header -->
            <div class="ptp-admin-header">
                <div class="ptp-admin-header-left">
                    <h1>PTP Private Training <span class="ptp-version">v4.0</span></h1>
                    <p class="ptp-admin-subtitle">Manage your trainer marketplace, bookings, and payouts</p>
                </div>
                <div class="ptp-admin-header-actions">
                    <a href="<?php echo home_url('/private-training/'); ?>" target="_blank" class="ptp-btn ptp-btn-outline">
                        <?php echo $this->icon('external-link'); ?> View Marketplace
                    </a>
                </div>
            </div>
            
            <!-- Welcome Banner -->
            <div class="ptp-welcome-banner">
                <div class="ptp-welcome-content">
                    <h2>Welcome back!</h2>
                    <p>You have <?php echo $stats['pending_applications']; ?> pending applications and <?php echo $stats['upcoming_sessions']; ?> upcoming sessions to manage.</p>
                </div>
                <span class="ptp-welcome-badge">⚽ Players Teaching Players</span>
            </div>
            
            <!-- Stats Grid -->
            <div class="ptp-admin-stats">
                <div class="ptp-admin-stat">
                    <div class="ptp-admin-stat-icon green"><?php echo $this->icon('users'); ?></div>
                    <span class="ptp-admin-stat-value"><?php echo $stats['total_trainers']; ?></span>
                    <span class="ptp-admin-stat-label">Active Trainers</span>
                </div>
                <div class="ptp-admin-stat">
                    <div class="ptp-admin-stat-icon orange"><?php echo $this->icon('inbox'); ?></div>
                    <span class="ptp-admin-stat-value"><?php echo $stats['pending_applications']; ?></span>
                    <span class="ptp-admin-stat-label">Pending Applications</span>
                </div>
                <div class="ptp-admin-stat">
                    <div class="ptp-admin-stat-icon blue"><?php echo $this->icon('calendar'); ?></div>
                    <span class="ptp-admin-stat-value"><?php echo $stats['total_sessions']; ?></span>
                    <span class="ptp-admin-stat-label">Total Sessions</span>
                </div>
                <div class="ptp-admin-stat">
                    <div class="ptp-admin-stat-icon yellow"><?php echo $this->icon('dollar'); ?></div>
                    <span class="ptp-admin-stat-value">$<?php echo number_format($stats['total_revenue'], 0); ?></span>
                    <span class="ptp-admin-stat-label">Total Revenue</span>
                </div>
                <div class="ptp-admin-stat">
                    <div class="ptp-admin-stat-icon green"><?php echo $this->icon('briefcase'); ?></div>
                    <span class="ptp-admin-stat-value">$<?php echo number_format($stats['platform_revenue'], 0); ?></span>
                    <span class="ptp-admin-stat-label">Platform Revenue</span>
                </div>
                <div class="ptp-admin-stat">
                    <div class="ptp-admin-stat-icon blue"><?php echo $this->icon('package'); ?></div>
                    <span class="ptp-admin-stat-value"><?php echo $stats['active_packs']; ?></span>
                    <span class="ptp-admin-stat-label">Active Packs</span>
                </div>
            </div>
            
            <?php if ($stats['pending_applications'] > 0): ?>
            <div class="ptp-alert ptp-alert-warning">
                <div class="ptp-alert-icon"><?php echo $this->icon('alert-circle'); ?></div>
                <div class="ptp-alert-content">
                    <strong><?php echo $stats['pending_applications']; ?> applications waiting for review</strong>
                    <p>New trainers are ready to join the platform. Review their applications to get them started.</p>
                </div>
                <a href="<?php echo admin_url('admin.php?page=ptp-training-applications'); ?>" class="ptp-btn ptp-btn-primary">Review Now</a>
            </div>
            <?php endif; ?>
            
            <!-- Two-Column Layout -->
            <div class="ptp-admin-grid">
                <!-- Quick Links -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2><?php echo $this->icon('settings'); ?> Quick Actions</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <div class="ptp-admin-links">
                            <a href="<?php echo admin_url('admin.php?page=ptp-training-trainers'); ?>" class="ptp-admin-link">
                                <span class="dashicons dashicons-admin-users"></span>
                                <span>Manage Trainers</span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=ptp-training-applications'); ?>" class="ptp-admin-link">
                                <span class="dashicons dashicons-clipboard"></span>
                                <span>Applications</span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=ptp-training-sessions'); ?>" class="ptp-admin-link">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <span>View Sessions</span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=ptp-training-payouts'); ?>" class="ptp-admin-link">
                                <span class="dashicons dashicons-money-alt"></span>
                                <span>Process Payouts</span>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=ptp-training-settings'); ?>" class="ptp-admin-link">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <span>Settings</span>
                            </a>
                            <a href="<?php echo home_url('/become-a-trainer/'); ?>" target="_blank" class="ptp-admin-link">
                                <span class="dashicons dashicons-external"></span>
                                <span>Application Form</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2><?php echo $this->icon('clock'); ?> Recent Activity</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <?php
                        global $wpdb;
                        $recent = $wpdb->get_results("
                            (SELECT 'application' as type, CONCAT(first_name, ' ', last_name) as name, created_at as date, status 
                             FROM {$wpdb->prefix}ptp_applications ORDER BY created_at DESC LIMIT 3)
                            UNION ALL
                            (SELECT 'session' as type, 
                                    CONCAT((SELECT display_name FROM {$wpdb->prefix}ptp_trainers WHERE id = trainer_id), ' - Session') as name, 
                                    created_at as date, status 
                             FROM {$wpdb->prefix}ptp_sessions ORDER BY created_at DESC LIMIT 3)
                            ORDER BY date DESC LIMIT 5
                        ");
                        ?>
                        <?php if ($recent): ?>
                        <ul class="ptp-activity-list">
                            <?php foreach ($recent as $item): ?>
                            <li class="ptp-activity-item">
                                <div class="ptp-activity-icon <?php echo $item->type; ?>">
                                    <?php echo $item->type === 'application' ? $this->icon('inbox') : $this->icon('calendar'); ?>
                                </div>
                                <div class="ptp-activity-content">
                                    <p class="ptp-activity-text">
                                        <?php echo $item->type === 'application' ? 'New application from' : 'Session booked:'; ?>
                                        <strong><?php echo esc_html($item->name); ?></strong>
                                    </p>
                                    <span class="ptp-activity-time"><?php echo human_time_diff(strtotime($item->date)); ?> ago</span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="ptp-empty-state">
                            <?php echo $this->icon('inbox'); ?>
                            <h3>No recent activity</h3>
                            <p>Activity will appear here as trainers apply and sessions are booked.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ======================================
       TRAINERS PAGE
       ====================================== */
    public function render_trainers() {
        global $wpdb;
        
        // Handle single trainer view
        if (isset($_GET['trainer_id'])) {
            $this->render_single_trainer(intval($_GET['trainer_id']));
            return;
        }
        
        // Get filter params
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        // Build query
        $where = "WHERE 1=1";
        if ($status) {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }
        if ($search) {
            $where .= $wpdb->prepare(" AND (display_name LIKE %s OR primary_location_city LIKE %s)", "%{$search}%", "%{$search}%");
        }
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_trainers {$where}");
        $trainers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ptp_trainers {$where} ORDER BY created_at DESC LIMIT {$offset}, {$per_page}");
        $total_pages = ceil($total / $per_page);
        ?>
        <div class="wrap ptp-admin">
            <div class="ptp-admin-header">
                <div class="ptp-admin-header-left">
                    <h1>Trainers <span class="ptp-version"><?php echo $total; ?> total</span></h1>
                    <p class="ptp-admin-subtitle">Manage your trainer roster and profiles</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="ptp-filters">
                <div class="ptp-filter-group">
                    <label>Status:</label>
                    <select onchange="window.location.href='<?php echo admin_url('admin.php?page=ptp-training-trainers'); ?>&status='+this.value">
                        <option value="">All Status</option>
                        <option value="approved" <?php selected($status, 'approved'); ?>>Active</option>
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                        <option value="inactive" <?php selected($status, 'inactive'); ?>>Inactive</option>
                    </select>
                </div>
                <div class="ptp-search-box">
                    <form method="get">
                        <input type="hidden" name="page" value="ptp-training-trainers">
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search trainers...">
                    </form>
                </div>
            </div>
            
            <!-- Trainers Table -->
            <div class="ptp-admin-card">
                <div class="ptp-admin-card-body" style="padding: 0;">
                    <?php if ($trainers): ?>
                    <table class="ptp-admin-table">
                        <thead>
                            <tr>
                                <th>Trainer</th>
                                <th>Location</th>
                                <th>Rate</th>
                                <th>Sessions</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trainers as $trainer): 
                                $session_count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions WHERE trainer_id = %d",
                                    $trainer->id
                                ));
                            ?>
                            <tr>
                                <td>
                                    <div class="ptp-user-row">
                                        <?php if ($trainer->headshot_url): ?>
                                            <img src="<?php echo esc_url($trainer->headshot_url); ?>" class="ptp-user-avatar" alt="">
                                        <?php else: ?>
                                            <div class="ptp-user-avatar-placeholder"><?php echo strtoupper(substr($trainer->display_name, 0, 1)); ?></div>
                                        <?php endif; ?>
                                        <div class="ptp-user-info">
                                            <div class="ptp-user-name"><?php echo esc_html($trainer->display_name); ?></div>
                                            <div class="ptp-user-email"><?php echo esc_html($trainer->slug); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($trainer->primary_location_city . ', ' . $trainer->primary_location_state); ?></td>
                                <td>$<?php echo number_format($trainer->hourly_rate, 0); ?>/hr</td>
                                <td><?php echo $session_count; ?></td>
                                <td>
                                    <span class="ptp-badge ptp-badge-<?php echo $trainer->status === 'approved' ? 'active' : $trainer->status; ?>">
                                        <?php echo ucfirst($trainer->status === 'approved' ? 'Active' : $trainer->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="ptp-table-actions">
                                        <a href="<?php echo admin_url('admin.php?page=ptp-training-trainers&trainer_id=' . $trainer->id); ?>" class="ptp-btn ptp-btn-sm ptp-btn-outline">View</a>
                                        <a href="<?php echo home_url('/trainer/' . $trainer->slug); ?>" target="_blank" class="ptp-btn ptp-btn-sm ptp-btn-outline"><?php echo $this->icon('external-link'); ?></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="ptp-empty-state">
                        <?php echo $this->icon('users'); ?>
                        <h3>No trainers found</h3>
                        <p>Trainers will appear here once applications are approved.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="ptp-admin-card-footer">
                    <div class="ptp-pagination">
                        <div class="ptp-pagination-info">
                            Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?>
                        </div>
                        <div class="ptp-pagination-links">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $paged): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo add_query_arg('paged', $i); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Single trainer detail view
     */
    private function render_single_trainer($trainer_id) {
        global $wpdb;
        
        $trainer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_trainers WHERE id = %d",
            $trainer_id
        ));
        
        if (!$trainer) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Trainer not found.</p></div></div>';
            return;
        }
        
        $session_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions WHERE trainer_id = %d",
            $trainer_id
        ));
        
        $total_earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(trainer_payout), 0) FROM {$wpdb->prefix}ptp_sessions WHERE trainer_id = %d AND status IN ('completed', 'paid')",
            $trainer_id
        ));
        ?>
        <div class="wrap ptp-admin">
            <div class="ptp-admin-header">
                <div class="ptp-admin-header-left">
                    <a href="<?php echo admin_url('admin.php?page=ptp-training-trainers'); ?>" class="ptp-btn ptp-btn-outline ptp-btn-sm" style="margin-bottom: 16px;">← Back to Trainers</a>
                    <h1><?php echo esc_html($trainer->display_name); ?></h1>
                </div>
                <div class="ptp-admin-header-actions">
                    <a href="<?php echo home_url('/trainer/' . $trainer->slug); ?>" target="_blank" class="ptp-btn ptp-btn-outline">
                        <?php echo $this->icon('external-link'); ?> View Profile
                    </a>
                </div>
            </div>
            
            <!-- Trainer Detail Header -->
            <div class="ptp-admin-card ptp-mb-24">
                <div class="ptp-admin-card-body">
                    <div class="ptp-detail-header">
                        <?php if ($trainer->headshot_url): ?>
                            <img src="<?php echo esc_url($trainer->headshot_url); ?>" class="ptp-detail-photo" alt="">
                        <?php else: ?>
                            <div class="ptp-user-avatar-placeholder" style="width: 80px; height: 80px; font-size: 32px;">
                                <?php echo strtoupper(substr($trainer->display_name, 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="ptp-detail-info">
                            <h2><?php echo esc_html($trainer->display_name); ?></h2>
                            <span class="ptp-badge ptp-badge-<?php echo $trainer->status === 'approved' ? 'active' : $trainer->status; ?>" style="margin-top: 4px;">
                                <?php echo ucfirst($trainer->status === 'approved' ? 'Active' : $trainer->status); ?>
                            </span>
                            <div class="ptp-detail-meta">
                                <div class="ptp-detail-meta-item">
                                    <?php echo $this->icon('map-pin'); ?>
                                    <?php echo esc_html($trainer->primary_location_city . ', ' . $trainer->primary_location_state); ?>
                                </div>
                                <div class="ptp-detail-meta-item">
                                    <?php echo $this->icon('dollar'); ?>
                                    $<?php echo number_format($trainer->hourly_rate, 0); ?>/hour
                                </div>
                                <div class="ptp-detail-meta-item">
                                    <?php echo $this->icon('calendar'); ?>
                                    <?php echo $session_count; ?> sessions
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="ptp-admin-grid">
                <!-- Profile Details -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2>Profile Details</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <div class="ptp-detail-grid">
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Bio</div>
                                <div class="ptp-detail-value"><?php echo esc_html($trainer->bio ?: 'Not provided'); ?></div>
                            </div>
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Credentials</div>
                                <div class="ptp-detail-value"><?php echo esc_html($trainer->credentials ?: 'Not provided'); ?></div>
                            </div>
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Specialties</div>
                                <div class="ptp-detail-value"><?php echo esc_html($trainer->specialties ?: 'Not provided'); ?></div>
                            </div>
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Age Range</div>
                                <div class="ptp-detail-value"><?php echo esc_html($trainer->age_range ?: 'Not provided'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Earnings -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2>Earnings Summary</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <div class="ptp-admin-stats" style="margin-bottom: 0;">
                            <div class="ptp-admin-stat">
                                <span class="ptp-admin-stat-value"><?php echo $session_count; ?></span>
                                <span class="ptp-admin-stat-label">Total Sessions</span>
                            </div>
                            <div class="ptp-admin-stat">
                                <span class="ptp-admin-stat-value">$<?php echo number_format($total_earnings, 0); ?></span>
                                <span class="ptp-admin-stat-label">Total Earnings</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status Actions -->
            <div class="ptp-admin-card ptp-mt-24">
                <div class="ptp-admin-card-header">
                    <h2>Trainer Status</h2>
                </div>
                <div class="ptp-admin-card-body">
                    <form method="post" class="ptp-form-row">
                        <div class="ptp-form-group">
                            <label>Current Status</label>
                            <select name="trainer_status" id="trainer-status">
                                <option value="approved" <?php selected($trainer->status, 'approved'); ?>>Active</option>
                                <option value="pending" <?php selected($trainer->status, 'pending'); ?>>Pending</option>
                                <option value="inactive" <?php selected($trainer->status, 'inactive'); ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="ptp-form-group" style="display: flex; align-items: flex-end;">
                            <button type="button" class="ptp-btn ptp-btn-primary" onclick="updateTrainerStatus(<?php echo $trainer_id; ?>)">
                                Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        function updateTrainerStatus(trainerId) {
            const status = document.getElementById('trainer-status').value;
            jQuery.post(ajaxurl, {
                action: 'ptp_update_trainer_status',
                id: trainerId,
                status: status,
                nonce: '<?php echo wp_create_nonce('ptp_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }

    /* ======================================
       APPLICATIONS PAGE
       ====================================== */
    public function render_applications() {
        global $wpdb;
        
        // Handle single application view
        if (isset($_GET['app_id'])) {
            $this->render_single_application(intval($_GET['app_id']));
            return;
        }
        
        // Get filter params
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending';
        $paged = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        // Build query
        $where = $status ? $wpdb->prepare("WHERE status = %s", $status) : "";
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_applications {$where}");
        $applications = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ptp_applications {$where} ORDER BY created_at DESC LIMIT {$offset}, {$per_page}");
        $total_pages = ceil($total / $per_page);
        
        // Get status counts
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_applications WHERE status = 'pending'");
        $approved_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_applications WHERE status = 'approved'");
        $rejected_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_applications WHERE status = 'rejected'");
        ?>
        <div class="wrap ptp-admin">
            <div class="ptp-admin-header">
                <div class="ptp-admin-header-left">
                    <h1>Trainer Applications <span class="ptp-version"><?php echo $total; ?> total</span></h1>
                    <p class="ptp-admin-subtitle">Review and manage incoming trainer applications</p>
                </div>
            </div>
            
            <!-- Status Tabs -->
            <div class="ptp-admin-tabs">
                <a href="<?php echo admin_url('admin.php?page=ptp-training-applications&status=pending'); ?>" 
                   class="ptp-admin-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $pending_count; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=ptp-training-applications&status=approved'); ?>" 
                   class="ptp-admin-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">
                    Approved (<?php echo $approved_count; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=ptp-training-applications&status=rejected'); ?>" 
                   class="ptp-admin-tab <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                    Rejected (<?php echo $rejected_count; ?>)
                </a>
            </div>
            
            <!-- Applications Table -->
            <div class="ptp-admin-card">
                <div class="ptp-admin-card-body" style="padding: 0;">
                    <?php if ($applications): ?>
                    <table class="ptp-admin-table">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Contact</th>
                                <th>Location</th>
                                <th>Role</th>
                                <th>Applied</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <div class="ptp-user-row">
                                        <div class="ptp-user-avatar-placeholder">
                                            <?php echo strtoupper(substr($app->first_name, 0, 1)); ?>
                                        </div>
                                        <div class="ptp-user-info">
                                            <div class="ptp-user-name"><?php echo esc_html($app->first_name . ' ' . $app->last_name); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <?php echo esc_html($app->email); ?><br>
                                        <span class="ptp-text-muted"><?php echo esc_html($app->phone); ?></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html(($app->location_city ? $app->location_city . ', ' : '') . $app->location_state); ?></td>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $app->role_type))); ?></td>
                                <td><?php echo date('M j, Y', strtotime($app->created_at)); ?></td>
                                <td>
                                    <span class="ptp-badge ptp-badge-<?php echo $app->status; ?>">
                                        <?php echo ucfirst($app->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="ptp-table-actions">
                                        <a href="<?php echo admin_url('admin.php?page=ptp-training-applications&app_id=' . $app->id); ?>" class="ptp-btn ptp-btn-sm ptp-btn-outline">Review</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="ptp-empty-state">
                        <?php echo $this->icon('inbox'); ?>
                        <h3>No <?php echo $status; ?> applications</h3>
                        <p>Applications will appear here when trainers apply.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="ptp-admin-card-footer">
                    <div class="ptp-pagination">
                        <div class="ptp-pagination-info">
                            Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?>
                        </div>
                        <div class="ptp-pagination-links">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $paged): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo add_query_arg('paged', $i); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Single application detail view
     */
    private function render_single_application($app_id) {
        global $wpdb;
        
        $app = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_applications WHERE id = %d",
            $app_id
        ));
        
        if (!$app) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Application not found.</p></div></div>';
            return;
        }
        ?>
        <div class="wrap ptp-admin">
            <div class="ptp-admin-header">
                <div class="ptp-admin-header-left">
                    <a href="<?php echo admin_url('admin.php?page=ptp-training-applications'); ?>" class="ptp-btn ptp-btn-outline ptp-btn-sm" style="margin-bottom: 16px;">← Back to Applications</a>
                    <h1>Application: <?php echo esc_html($app->first_name . ' ' . $app->last_name); ?></h1>
                </div>
                <?php if ($app->status === 'pending'): ?>
                <div class="ptp-admin-header-actions">
                    <button type="button" class="ptp-btn ptp-btn-danger" onclick="rejectApplication(<?php echo $app_id; ?>)">
                        <?php echo $this->icon('x-circle'); ?> Reject
                    </button>
                    <button type="button" class="ptp-btn ptp-btn-success" onclick="approveApplication(<?php echo $app_id; ?>)">
                        <?php echo $this->icon('check-circle'); ?> Approve
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Status Banner -->
            <?php if ($app->status !== 'pending'): ?>
            <div class="ptp-alert ptp-alert-<?php echo $app->status === 'approved' ? 'success' : 'warning'; ?>">
                <div class="ptp-alert-icon"><?php echo $app->status === 'approved' ? $this->icon('check-circle') : $this->icon('x-circle'); ?></div>
                <div class="ptp-alert-content">
                    <strong>This application was <?php echo $app->status; ?></strong>
                    <?php if ($app->reviewed_at): ?>
                    <p>Reviewed on <?php echo date('F j, Y \a\t g:i a', strtotime($app->reviewed_at)); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="ptp-admin-grid">
                <!-- Contact Information -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2><?php echo $this->icon('users'); ?> Contact Information</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <div class="ptp-detail-grid">
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Full Name</div>
                                <div class="ptp-detail-value"><?php echo esc_html($app->first_name . ' ' . $app->last_name); ?></div>
                            </div>
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Email</div>
                                <div class="ptp-detail-value">
                                    <a href="mailto:<?php echo esc_attr($app->email); ?>"><?php echo esc_html($app->email); ?></a>
                                </div>
                            </div>
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Phone</div>
                                <div class="ptp-detail-value">
                                    <a href="tel:<?php echo esc_attr($app->phone); ?>"><?php echo esc_html($app->phone); ?></a>
                                </div>
                            </div>
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Location</div>
                                <div class="ptp-detail-value">
                                    <?php 
                                    $location_parts = array_filter(array($app->location_city, $app->location_state, $app->location_zip));
                                    echo esc_html(implode(', ', $location_parts) ?: 'Not provided');
                                    ?>
                                </div>
                            </div>
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Role Type</div>
                                <div class="ptp-detail-value"><?php echo esc_html(ucfirst(str_replace('_', ' ', $app->role_type))); ?></div>
                            </div>
                            <div class="ptp-detail-item">
                                <div class="ptp-detail-label">Applied</div>
                                <div class="ptp-detail-value"><?php echo date('F j, Y \a\t g:i a', strtotime($app->created_at)); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Experience & Background -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2><?php echo $this->icon('briefcase'); ?> Experience & Background</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <div class="ptp-detail-item">
                            <div class="ptp-detail-label">Playing Background</div>
                            <div class="ptp-detail-value"><?php echo nl2br(esc_html($app->playing_background ?: 'Not provided')); ?></div>
                        </div>
                        <div class="ptp-detail-item">
                            <div class="ptp-detail-label">Coaching Experience</div>
                            <div class="ptp-detail-value"><?php echo nl2br(esc_html($app->coaching_experience ?: 'Not provided')); ?></div>
                        </div>
                        <div class="ptp-detail-item">
                            <div class="ptp-detail-label">Certifications</div>
                            <div class="ptp-detail-value"><?php echo nl2br(esc_html($app->certifications ?: 'Not provided')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Details -->
            <div class="ptp-admin-card ptp-mt-24">
                <div class="ptp-admin-card-header">
                    <h2><?php echo $this->icon('edit'); ?> Additional Details</h2>
                </div>
                <div class="ptp-admin-card-body">
                    <div class="ptp-admin-grid">
                        <div class="ptp-detail-item">
                            <div class="ptp-detail-label">Experience Summary</div>
                            <div class="ptp-detail-value"><?php echo nl2br(esc_html($app->experience_summary ?: 'Not provided')); ?></div>
                        </div>
                        <div class="ptp-detail-item">
                            <div class="ptp-detail-label">Why Join PTP?</div>
                            <div class="ptp-detail-value"><?php echo nl2br(esc_html($app->why_join ?: 'Not provided')); ?></div>
                        </div>
                        <div class="ptp-detail-item">
                            <div class="ptp-detail-label">Availability Notes</div>
                            <div class="ptp-detail-value"><?php echo nl2br(esc_html($app->availability_notes ?: 'Not provided')); ?></div>
                        </div>
                        <div class="ptp-detail-item">
                            <div class="ptp-detail-label">Referral Source</div>
                            <div class="ptp-detail-value"><?php echo esc_html($app->referral_source ?: 'Not provided'); ?></div>
                        </div>
                        <?php if ($app->intro_video_url): ?>
                        <div class="ptp-detail-item">
                            <div class="ptp-detail-label">Intro Video</div>
                            <div class="ptp-detail-value">
                                <a href="<?php echo esc_url($app->intro_video_url); ?>" target="_blank" class="ptp-btn ptp-btn-sm ptp-btn-outline">
                                    <?php echo $this->icon('external-link'); ?> Watch Video
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($app->instagram_handle): ?>
                        <div class="ptp-detail-item">
                            <div class="ptp-detail-label">Instagram</div>
                            <div class="ptp-detail-value">
                                <a href="https://instagram.com/<?php echo esc_attr(ltrim($app->instagram_handle, '@')); ?>" target="_blank">
                                    @<?php echo esc_html(ltrim($app->instagram_handle, '@')); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rejection Modal -->
        <div id="reject-modal" class="ptp-modal-overlay" style="display: none;">
            <div class="ptp-modal">
                <div class="ptp-modal-header">
                    <h3>Reject Application</h3>
                    <button type="button" class="ptp-modal-close" onclick="closeRejectModal()">&times;</button>
                </div>
                <div class="ptp-modal-body">
                    <div class="ptp-form-group">
                        <label>Reason for rejection (optional)</label>
                        <textarea id="rejection-reason" rows="4" placeholder="Provide feedback for the applicant..."></textarea>
                    </div>
                </div>
                <div class="ptp-modal-footer">
                    <button type="button" class="ptp-btn ptp-btn-outline" onclick="closeRejectModal()">Cancel</button>
                    <button type="button" class="ptp-btn ptp-btn-danger" onclick="confirmReject(<?php echo $app_id; ?>)">Confirm Rejection</button>
                </div>
            </div>
        </div>
        
        <script>
        function approveApplication(appId) {
            if (!confirm('Approve this application? This will create a trainer account and send a welcome email.')) return;
            
            jQuery.post(ajaxurl, {
                action: 'ptp_approve_application',
                id: appId,
                nonce: '<?php echo wp_create_nonce('ptp_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Application approved! The trainer has been sent their login credentials.');
                    location.href = '<?php echo admin_url('admin.php?page=ptp-training-applications'); ?>';
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
        
        function rejectApplication(appId) {
            document.getElementById('reject-modal').style.display = 'flex';
        }
        
        function closeRejectModal() {
            document.getElementById('reject-modal').style.display = 'none';
        }
        
        function confirmReject(appId) {
            const reason = document.getElementById('rejection-reason').value;
            
            jQuery.post(ajaxurl, {
                action: 'ptp_reject_application',
                id: appId,
                reason: reason,
                nonce: '<?php echo wp_create_nonce('ptp_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Application rejected.');
                    location.href = '<?php echo admin_url('admin.php?page=ptp-training-applications'); ?>';
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }

    /* ======================================
       SESSIONS PAGE
       ====================================== */
    public function render_sessions() {
        global $wpdb;
        
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $paged = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        $where = $status ? $wpdb->prepare("WHERE s.status = %s", $status) : "";
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions s {$where}");
        $sessions = $wpdb->get_results("
            SELECT s.*, t.display_name as trainer_name, t.headshot_url as trainer_photo
            FROM {$wpdb->prefix}ptp_sessions s
            LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
            {$where}
            ORDER BY s.session_date DESC, s.session_time DESC
            LIMIT {$offset}, {$per_page}
        ");
        $total_pages = ceil($total / $per_page);
        ?>
        <div class="wrap ptp-admin">
            <div class="ptp-admin-header">
                <div class="ptp-admin-header-left">
                    <h1>Training Sessions <span class="ptp-version"><?php echo $total; ?> total</span></h1>
                    <p class="ptp-admin-subtitle">View and manage all booked training sessions</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="ptp-filters">
                <div class="ptp-filter-group">
                    <label>Status:</label>
                    <select onchange="window.location.href='<?php echo admin_url('admin.php?page=ptp-training-sessions'); ?>&status='+this.value">
                        <option value="">All Status</option>
                        <option value="scheduled" <?php selected($status, 'scheduled'); ?>>Scheduled</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>Completed</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                        <option value="paid" <?php selected($status, 'paid'); ?>>Paid</option>
                    </select>
                </div>
            </div>
            
            <!-- Sessions Table -->
            <div class="ptp-admin-card">
                <div class="ptp-admin-card-body" style="padding: 0;">
                    <?php if ($sessions): ?>
                    <table class="ptp-admin-table">
                        <thead>
                            <tr>
                                <th>Trainer</th>
                                <th>Date & Time</th>
                                <th>Duration</th>
                                <th>Amount</th>
                                <th>Platform Fee</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td>
                                    <div class="ptp-user-row">
                                        <?php if ($session->trainer_photo): ?>
                                            <img src="<?php echo esc_url($session->trainer_photo); ?>" class="ptp-user-avatar" alt="">
                                        <?php else: ?>
                                            <div class="ptp-user-avatar-placeholder"><?php echo strtoupper(substr($session->trainer_name, 0, 1)); ?></div>
                                        <?php endif; ?>
                                        <div class="ptp-user-info">
                                            <div class="ptp-user-name"><?php echo esc_html($session->trainer_name); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($session->session_date)); ?><br>
                                    <span class="ptp-text-muted"><?php echo date('g:i A', strtotime($session->session_time)); ?></span>
                                </td>
                                <td><?php echo $session->duration; ?> min</td>
                                <td>$<?php echo number_format($session->amount, 2); ?></td>
                                <td>$<?php echo number_format($session->platform_fee, 2); ?></td>
                                <td>
                                    <span class="ptp-badge ptp-badge-<?php echo $session->status; ?>">
                                        <?php echo ucfirst($session->status); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="ptp-empty-state">
                        <?php echo $this->icon('calendar'); ?>
                        <h3>No sessions found</h3>
                        <p>Sessions will appear here when athletes book training.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="ptp-admin-card-footer">
                    <div class="ptp-pagination">
                        <div class="ptp-pagination-info">
                            Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?>
                        </div>
                        <div class="ptp-pagination-links">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $paged): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo add_query_arg('paged', $i); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* ======================================
       PAYOUTS PAGE
       ====================================== */
    public function render_payouts() {
        global $wpdb;
        
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending';
        $paged = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        $where = $status ? $wpdb->prepare("WHERE p.status = %s", $status) : "";
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_payouts p {$where}");
        $payouts = $wpdb->get_results("
            SELECT p.*, t.display_name as trainer_name, t.headshot_url as trainer_photo
            FROM {$wpdb->prefix}ptp_payouts p
            LEFT JOIN {$wpdb->prefix}ptp_trainers t ON p.trainer_id = t.id
            {$where}
            ORDER BY p.created_at DESC
            LIMIT {$offset}, {$per_page}
        ");
        $total_pages = ceil($total / $per_page);
        
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_payouts WHERE status = 'pending'");
        $processing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_payouts WHERE status = 'processing'");
        $completed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_payouts WHERE status = 'completed'");
        
        $pending_total = $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}ptp_payouts WHERE status = 'pending'");
        ?>
        <div class="wrap ptp-admin">
            <div class="ptp-admin-header">
                <div class="ptp-admin-header-left">
                    <h1>Trainer Payouts <span class="ptp-version"><?php echo $total; ?> total</span></h1>
                    <p class="ptp-admin-subtitle">Manage trainer earnings and payouts</p>
                </div>
            </div>
            
            <!-- Payout Stats -->
            <div class="ptp-admin-stats" style="margin-bottom: 24px;">
                <div class="ptp-admin-stat">
                    <div class="ptp-admin-stat-icon orange"><?php echo $this->icon('clock'); ?></div>
                    <span class="ptp-admin-stat-value"><?php echo $pending_count; ?></span>
                    <span class="ptp-admin-stat-label">Pending Payouts</span>
                </div>
                <div class="ptp-admin-stat">
                    <div class="ptp-admin-stat-icon yellow"><?php echo $this->icon('dollar'); ?></div>
                    <span class="ptp-admin-stat-value">$<?php echo number_format($pending_total, 0); ?></span>
                    <span class="ptp-admin-stat-label">Pending Amount</span>
                </div>
                <div class="ptp-admin-stat">
                    <div class="ptp-admin-stat-icon green"><?php echo $this->icon('check-circle'); ?></div>
                    <span class="ptp-admin-stat-value"><?php echo $completed_count; ?></span>
                    <span class="ptp-admin-stat-label">Completed</span>
                </div>
            </div>
            
            <!-- Status Tabs -->
            <div class="ptp-admin-tabs">
                <a href="<?php echo admin_url('admin.php?page=ptp-training-payouts&status=pending'); ?>" 
                   class="ptp-admin-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $pending_count; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=ptp-training-payouts&status=processing'); ?>" 
                   class="ptp-admin-tab <?php echo $status === 'processing' ? 'active' : ''; ?>">
                    Processing (<?php echo $processing_count; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=ptp-training-payouts&status=completed'); ?>" 
                   class="ptp-admin-tab <?php echo $status === 'completed' ? 'active' : ''; ?>">
                    Completed (<?php echo $completed_count; ?>)
                </a>
            </div>
            
            <!-- Payouts Table -->
            <div class="ptp-admin-card">
                <div class="ptp-admin-card-body" style="padding: 0;">
                    <?php if ($payouts): ?>
                    <table class="ptp-admin-table">
                        <thead>
                            <tr>
                                <th>Trainer</th>
                                <th>Amount</th>
                                <th>Sessions</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payouts as $payout): ?>
                            <tr>
                                <td>
                                    <div class="ptp-user-row">
                                        <?php if ($payout->trainer_photo): ?>
                                            <img src="<?php echo esc_url($payout->trainer_photo); ?>" class="ptp-user-avatar" alt="">
                                        <?php else: ?>
                                            <div class="ptp-user-avatar-placeholder"><?php echo strtoupper(substr($payout->trainer_name, 0, 1)); ?></div>
                                        <?php endif; ?>
                                        <div class="ptp-user-info">
                                            <div class="ptp-user-name"><?php echo esc_html($payout->trainer_name); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><strong>$<?php echo number_format($payout->amount, 2); ?></strong></td>
                                <td><?php echo $payout->session_count; ?></td>
                                <td>
                                    <?php echo date('M j', strtotime($payout->period_start)); ?> - 
                                    <?php echo date('M j, Y', strtotime($payout->period_end)); ?>
                                </td>
                                <td>
                                    <span class="ptp-badge ptp-badge-<?php echo $payout->status; ?>">
                                        <?php echo ucfirst($payout->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payout->status === 'pending'): ?>
                                    <button type="button" class="ptp-btn ptp-btn-sm ptp-btn-success" onclick="processPayout(<?php echo $payout->id; ?>)">
                                        Process
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="ptp-empty-state">
                        <?php echo $this->icon('dollar'); ?>
                        <h3>No payouts found</h3>
                        <p>Payouts will appear here when trainers complete sessions.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        function processPayout(payoutId) {
            if (!confirm('Process this payout? This will transfer funds to the trainer\'s Stripe account.')) return;
            
            jQuery.post(ajaxurl, {
                action: 'ptp_process_payout',
                id: payoutId,
                nonce: '<?php echo wp_create_nonce('ptp_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Payout processed successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }

    /* ======================================
       SETTINGS PAGE
       ====================================== */
    public function render_settings() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap ptp-admin">
            <div class="ptp-admin-header">
                <div class="ptp-admin-header-left">
                    <h1>Settings</h1>
                    <p class="ptp-admin-subtitle">Configure your PTP Private Training platform</p>
                </div>
            </div>
            
            <!-- Settings Tabs -->
            <div class="ptp-admin-tabs">
                <a href="<?php echo admin_url('admin.php?page=ptp-training-settings&tab=general'); ?>" 
                   class="ptp-admin-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                    General
                </a>
                <a href="<?php echo admin_url('admin.php?page=ptp-training-settings&tab=stripe'); ?>" 
                   class="ptp-admin-tab <?php echo $active_tab === 'stripe' ? 'active' : ''; ?>">
                    Stripe Payments
                </a>
                <a href="<?php echo admin_url('admin.php?page=ptp-training-settings&tab=google'); ?>" 
                   class="ptp-admin-tab <?php echo $active_tab === 'google' ? 'active' : ''; ?>">
                    Google Integration
                </a>
                <a href="<?php echo admin_url('admin.php?page=ptp-training-settings&tab=sms'); ?>" 
                   class="ptp-admin-tab <?php echo $active_tab === 'sms' ? 'active' : ''; ?>">
                    SMS Notifications
                </a>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('ptp_training_settings'); ?>
                
                <?php if ($active_tab === 'general'): ?>
                <!-- General Settings -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2><?php echo $this->icon('settings'); ?> Platform Settings</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <div class="ptp-form-row">
                            <div class="ptp-form-group">
                                <label>Platform Fee (%)<span class="required">*</span></label>
                                <input type="number" name="ptp_platform_fee_percent" 
                                       value="<?php echo esc_attr(get_option('ptp_platform_fee_percent', 25)); ?>" 
                                       min="0" max="50" step="1">
                                <div class="ptp-form-hint">Percentage of each transaction kept as platform fee (trainers receive the remainder)</div>
                            </div>
                            <div class="ptp-form-group">
                                <label>Trainer Referral Commission (%)</label>
                                <input type="number" name="ptp_trainer_referral_commission" 
                                       value="<?php echo esc_attr(get_option('ptp_trainer_referral_commission', 10)); ?>" 
                                       min="0" max="25" step="1">
                                <div class="ptp-form-hint">Commission trainers earn for referring new trainers</div>
                            </div>
                        </div>
                        <div class="ptp-form-row full">
                            <div class="ptp-form-group">
                                <label>Admin Notification Email</label>
                                <input type="email" name="ptp_admin_email" 
                                       value="<?php echo esc_attr(get_option('ptp_admin_email', get_option('admin_email'))); ?>" 
                                       class="regular-text">
                                <div class="ptp-form-hint">Email address for admin notifications (new applications, issues, etc.)</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($active_tab === 'stripe'): ?>
                <!-- Stripe Settings -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2><?php echo $this->icon('dollar'); ?> Stripe Connect Settings</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <div class="ptp-alert ptp-alert-info ptp-mb-24">
                            <div class="ptp-alert-icon"><?php echo $this->icon('alert-circle'); ?></div>
                            <div class="ptp-alert-content">
                                <strong>Stripe Connect Required</strong>
                                <p>PTP uses Stripe Connect Express for trainer payouts. Get your API keys from your <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a>.</p>
                            </div>
                        </div>
                        
                        <div class="ptp-form-row">
                            <div class="ptp-form-group">
                                <label>Publishable Key<span class="required">*</span></label>
                                <input type="text" name="ptp_stripe_publishable_key" 
                                       value="<?php echo esc_attr(get_option('ptp_stripe_publishable_key')); ?>" 
                                       placeholder="pk_live_..." class="regular-text">
                            </div>
                            <div class="ptp-form-group">
                                <label>Secret Key<span class="required">*</span></label>
                                <input type="password" name="ptp_stripe_secret_key" 
                                       value="<?php echo esc_attr(get_option('ptp_stripe_secret_key')); ?>" 
                                       placeholder="sk_live_..." class="regular-text">
                            </div>
                        </div>
                        <div class="ptp-form-row full">
                            <div class="ptp-form-group">
                                <label>Webhook Secret</label>
                                <input type="password" name="ptp_stripe_webhook_secret" 
                                       value="<?php echo esc_attr(get_option('ptp_stripe_webhook_secret')); ?>" 
                                       placeholder="whsec_..." class="regular-text">
                                <div class="ptp-form-hint">Webhook endpoint: <code><?php echo home_url('/wp-json/ptp-training/v1/stripe-webhook'); ?></code></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($active_tab === 'google'): ?>
                <!-- Google Settings -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2><?php echo $this->icon('map-pin'); ?> Google Integration</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <div class="ptp-form-row full">
                            <div class="ptp-form-group">
                                <label>Google Maps API Key<span class="required">*</span></label>
                                <input type="text" name="ptp_google_maps_key" 
                                       value="<?php echo esc_attr(get_option('ptp_google_maps_key')); ?>" 
                                       placeholder="AIza..." class="regular-text">
                                <div class="ptp-form-hint">Required for location-based trainer search and map display</div>
                            </div>
                        </div>
                        
                        <h3 style="margin-top: 32px;">Google Calendar Sync (Optional)</h3>
                        <div class="ptp-form-row">
                            <div class="ptp-form-group">
                                <label>Google Client ID</label>
                                <input type="text" name="ptp_google_client_id" 
                                       value="<?php echo esc_attr(get_option('ptp_google_client_id')); ?>" 
                                       class="regular-text">
                            </div>
                            <div class="ptp-form-group">
                                <label>Google Client Secret</label>
                                <input type="password" name="ptp_google_client_secret" 
                                       value="<?php echo esc_attr(get_option('ptp_google_client_secret')); ?>" 
                                       class="regular-text">
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($active_tab === 'sms'): ?>
                <!-- SMS Settings -->
                <div class="ptp-admin-card">
                    <div class="ptp-admin-card-header">
                        <h2><?php echo $this->icon('phone'); ?> Twilio SMS Settings</h2>
                    </div>
                    <div class="ptp-admin-card-body">
                        <div class="ptp-form-row full">
                            <div class="ptp-form-group">
                                <label>
                                    <input type="checkbox" name="ptp_sms_enabled" value="1" 
                                           <?php checked(get_option('ptp_sms_enabled'), 1); ?>>
                                    Enable SMS Notifications
                                </label>
                            </div>
                        </div>
                        
                        <div class="ptp-form-row">
                            <div class="ptp-form-group">
                                <label>Twilio Account SID</label>
                                <input type="text" name="ptp_twilio_sid" 
                                       value="<?php echo esc_attr(get_option('ptp_twilio_sid')); ?>" 
                                       placeholder="AC..." class="regular-text">
                            </div>
                            <div class="ptp-form-group">
                                <label>Twilio Auth Token</label>
                                <input type="password" name="ptp_twilio_token" 
                                       value="<?php echo esc_attr(get_option('ptp_twilio_token')); ?>" 
                                       class="regular-text">
                            </div>
                        </div>
                        <div class="ptp-form-row full">
                            <div class="ptp-form-group">
                                <label>Twilio Phone Number</label>
                                <input type="tel" name="ptp_twilio_phone" 
                                       value="<?php echo esc_attr(get_option('ptp_twilio_phone')); ?>" 
                                       placeholder="+1234567890" class="regular-text">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="ptp-form-actions">
                    <?php submit_button('Save Settings', 'ptp-btn ptp-btn-primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /* ======================================
       AJAX HANDLERS
       ====================================== */
    
    /**
     * Approve application
     */
    public function approve_application() {
        check_ajax_referer('ptp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $app_id = intval($_POST['id']);
        
        global $wpdb;
        $app = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_applications WHERE id = %d",
            $app_id
        ));
        
        if (!$app) {
            wp_send_json_error('Application not found');
        }
        
        // Create WordPress user
        $username = sanitize_user(strtolower($app->first_name . '.' . $app->last_name));
        $password = wp_generate_password(12);
        
        $user_id = wp_create_user($username, $password, $app->email);
        
        if (is_wp_error($user_id)) {
            $user = get_user_by('email', $app->email);
            if ($user) {
                $user_id = $user->ID;
            } else {
                wp_send_json_error($user_id->get_error_message());
            }
        }
        
        // Add trainer role
        $user = new WP_User($user_id);
        $user->add_role('ptp_trainer');
        
        // Create trainer profile
        $slug = sanitize_title($app->first_name . '-' . $app->last_name);
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_trainers WHERE slug = %s",
            $slug
        ));
        if ($existing) {
            $slug .= '-' . $user_id;
        }
        
        $wpdb->insert("{$wpdb->prefix}ptp_trainers", array(
            'user_id' => $user_id,
            'status' => 'approved',
            'display_name' => $app->first_name . ' ' . $app->last_name,
            'slug' => $slug,
            'bio' => $app->experience_summary,
            'credentials' => $app->playing_background,
            'primary_location_city' => $app->location_city,
            'primary_location_state' => $app->location_state,
            'primary_location_zip' => $app->location_zip,
            'intro_video_url' => $app->intro_video_url,
            'hourly_rate' => 75
        ));
        
        // Update application status
        $wpdb->update(
            "{$wpdb->prefix}ptp_applications",
            array(
                'status' => 'approved',
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time('mysql')
            ),
            array('id' => $app_id)
        );
        
        // Send welcome email
        $subject = 'Welcome to PTP Training!';
        $message = "Hi {$app->first_name},\n\n";
        $message .= "Great news! Your trainer application has been approved.\n\n";
        $message .= "Your login credentials:\n";
        $message .= "Username: {$username}\n";
        $message .= "Password: {$password}\n";
        $message .= "Dashboard: " . home_url('/trainer-dashboard/') . "\n\n";
        $message .= "Next steps:\n";
        $message .= "1. Log in and complete your profile\n";
        $message .= "2. Connect your Stripe account for payments\n";
        $message .= "3. Set your availability\n";
        $message .= "4. Start accepting bookings!\n\n";
        $message .= "Welcome to the team!\nThe PTP Team";
        
        wp_mail($app->email, $subject, $message);
        
        wp_send_json_success();
    }
    
    /**
     * Reject application
     */
    public function reject_application() {
        check_ajax_referer('ptp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $app_id = intval($_POST['id']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        global $wpdb;
        $app = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_applications WHERE id = %d",
            $app_id
        ));
        
        if (!$app) {
            wp_send_json_error('Application not found');
        }
        
        $wpdb->update(
            "{$wpdb->prefix}ptp_applications",
            array(
                'status' => 'rejected',
                'admin_notes' => $reason,
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time('mysql')
            ),
            array('id' => $app_id)
        );
        
        // Send rejection email
        $subject = 'PTP Training Application Update';
        $message = "Hi {$app->first_name},\n\n";
        $message .= "Thank you for your interest in becoming a PTP trainer.\n\n";
        $message .= "After reviewing your application, we've decided not to move forward at this time.\n\n";
        if ($reason) {
            $message .= "Feedback: {$reason}\n\n";
        }
        $message .= "You're welcome to apply again in the future.\n\n";
        $message .= "Best,\nThe PTP Team";
        
        wp_mail($app->email, $subject, $message);
        
        wp_send_json_success();
    }
    
    /**
     * Update trainer status
     */
    public function update_trainer_status() {
        check_ajax_referer('ptp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $trainer_id = intval($_POST['id']);
        $status = sanitize_text_field($_POST['status']);
        
        if (!in_array($status, array('approved', 'pending', 'inactive'))) {
            wp_send_json_error('Invalid status');
        }
        
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}ptp_trainers",
            array('status' => $status),
            array('id' => $trainer_id)
        );
        
        wp_send_json_success();
    }
    
    /**
     * Process payout
     */
    public function process_payout() {
        check_ajax_referer('ptp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $payout_id = intval($_POST['id']);
        
        // Check if PTP_Stripe class exists
        if (class_exists('PTP_Stripe')) {
            $result = PTP_Stripe::process_payout($payout_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
        } else {
            // Mark as processing without Stripe for now
            global $wpdb;
            $wpdb->update(
                "{$wpdb->prefix}ptp_payouts",
                array('status' => 'processing'),
                array('id' => $payout_id)
            );
        }
        
        wp_send_json_success();
    }
}

new PTP_Admin();
