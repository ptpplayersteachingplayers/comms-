<?php
/**
 * PTP Comms Hub - VA Dashboard Admin Page
 * v4.0.0 - Central hub for VAs to manage relationships
 */
class PTP_Comms_Hub_Admin_Page_VA_Dashboard {
    
    /**
     * Main render function
     */
    public static function render() {
        global $wpdb;
        $current_user_id = get_current_user_id();
        
        // Get today's reminders
        $today_reminders = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, c.parent_first_name, c.parent_last_name, c.parent_phone 
             FROM {$wpdb->prefix}ptp_reminders r
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
             WHERE r.assigned_to = %d AND r.status = 'pending' 
             AND DATE(r.due_date) = CURDATE()
             ORDER BY r.due_date ASC
             LIMIT 10",
            $current_user_id
        ));
        
        // Get overdue reminders
        $overdue_reminders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_reminders 
             WHERE assigned_to = %d AND status = 'pending' AND due_date < NOW()",
            $current_user_id
        ));
        
        // Get unread notifications
        $unread_notifications = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_notifications 
             WHERE user_id = %d AND is_read = 0",
            $current_user_id
        ));
        
        // Get contacts needing attention (low relationship score, no recent interaction)
        $needs_attention = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts 
             WHERE opted_in = 1 AND relationship_score < 30 
             AND (last_interaction_at IS NULL OR last_interaction_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
             ORDER BY relationship_score ASC
             LIMIT 10"
        );
        
        // Get recent activity
        $recent_activity = $wpdb->get_results(
            "SELECT a.*, c.parent_first_name, c.parent_last_name 
             FROM {$wpdb->prefix}ptp_activity_log a
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON a.contact_id = c.id
             ORDER BY a.created_at DESC
             LIMIT 15"
        );
        
        // Get stats
        $stats = array(
            'total_contacts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1"),
            'vip_contacts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE vip_status = 1"),
            'avg_relationship_score' => $wpdb->get_var("SELECT AVG(relationship_score) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1"),
            'notes_this_week' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contact_notes WHERE user_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
                $current_user_id
            )),
            'interactions_today' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_activity_log WHERE DATE(created_at) = CURDATE()"
            )
        );
        
        // HubSpot sync stats
        $hubspot_stats = array();
        if (class_exists('PTP_Comms_Hub_HubSpot_Sync')) {
            $hubspot_stats = PTP_Comms_Hub_HubSpot_Sync::get_queue_stats();
        }
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin ptp-va-dashboard">
            <div class="ptp-va-header">
                <div>
                    <h1>👋 VA Dashboard</h1>
                    <p class="subtitle">Build stronger relationships with every interaction</p>
                </div>
                <div class="ptp-va-header-actions">
                    <a href="?page=ptp-comms-contacts&action=new" class="ptp-comms-button">
                        <span class="dashicons dashicons-plus-alt2"></span> Add Contact
                    </a>
                    <a href="?page=ptp-comms-reminders&action=new" class="ptp-comms-button secondary">
                        <span class="dashicons dashicons-bell"></span> New Reminder
                    </a>
                </div>
            </div>
            
            <!-- Alert Banners -->
            <?php if ($overdue_reminders > 0): ?>
            <div class="ptp-comms-alert warning" style="margin-bottom: 20px;">
                <strong>⚠️ You have <?php echo $overdue_reminders; ?> overdue reminder<?php echo $overdue_reminders > 1 ? 's' : ''; ?>!</strong>
                <a href="?page=ptp-comms-reminders&filter=overdue" class="button button-small" style="margin-left: 15px;">View Overdue</a>
            </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="ptp-comms-stats ptp-va-stats">
                <div class="ptp-comms-stat-box">
                    <h2><?php echo number_format($stats['total_contacts']); ?></h2>
                    <p>Active Contacts</p>
                </div>
                <div class="ptp-comms-stat-box gold">
                    <h2><?php echo number_format($stats['vip_contacts']); ?></h2>
                    <p>VIP Families</p>
                </div>
                <div class="ptp-comms-stat-box <?php echo $stats['avg_relationship_score'] >= 60 ? 'green' : ($stats['avg_relationship_score'] >= 40 ? 'yellow' : 'red'); ?>">
                    <h2><?php echo round($stats['avg_relationship_score'] ?? 0); ?></h2>
                    <p>Avg. Relationship Score</p>
                </div>
                <div class="ptp-comms-stat-box blue">
                    <h2><?php echo number_format($stats['notes_this_week']); ?></h2>
                    <p>Your Notes This Week</p>
                </div>
                <div class="ptp-comms-stat-box purple">
                    <h2><?php echo number_format($stats['interactions_today']); ?></h2>
                    <p>Interactions Today</p>
                </div>
            </div>
            
            <div class="ptp-va-grid">
                <!-- Today's Tasks Column -->
                <div class="ptp-va-column">
                    <div class="ptp-comms-card">
                        <div class="ptp-card-header">
                            <h3>📋 Today's Tasks</h3>
                            <a href="?page=ptp-comms-reminders" class="ptp-link">View All →</a>
                        </div>
                        
                        <?php if (empty($today_reminders)): ?>
                        <div class="ptp-empty-state small">
                            <span class="dashicons dashicons-yes-alt" style="font-size: 40px; color: #46b450;"></span>
                            <p>All caught up for today! 🎉</p>
                        </div>
                        <?php else: ?>
                        <div class="ptp-task-list">
                            <?php foreach ($today_reminders as $reminder): ?>
                            <div class="ptp-task-item priority-<?php echo esc_attr($reminder->priority); ?>">
                                <div class="ptp-task-checkbox">
                                    <input type="checkbox" class="complete-reminder" data-id="<?php echo $reminder->id; ?>">
                                </div>
                                <div class="ptp-task-content">
                                    <strong><?php echo esc_html($reminder->title); ?></strong>
                                    <?php if ($reminder->contact_id): ?>
                                    <span class="ptp-task-contact">
                                        <a href="?page=ptp-comms-contacts&action=view&id=<?php echo $reminder->contact_id; ?>">
                                            <?php echo esc_html($reminder->parent_first_name . ' ' . $reminder->parent_last_name); ?>
                                        </a>
                                    </span>
                                    <?php endif; ?>
                                    <span class="ptp-task-time"><?php echo date('g:i A', strtotime($reminder->due_date)); ?></span>
                                </div>
                                <div class="ptp-task-actions">
                                    <?php if ($reminder->contact_id && $reminder->parent_phone): ?>
                                    <a href="?page=ptp-comms-inbox&contact=<?php echo $reminder->contact_id; ?>" 
                                       class="ptp-icon-btn" title="Send Message">
                                        <span class="dashicons dashicons-format-chat"></span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Needs Attention -->
                    <div class="ptp-comms-card">
                        <div class="ptp-card-header">
                            <h3>⚡ Needs Attention</h3>
                            <a href="?page=ptp-comms-contacts&segment=needs_attention" class="ptp-link">View All →</a>
                        </div>
                        
                        <?php if (empty($needs_attention)): ?>
                        <div class="ptp-empty-state small">
                            <p>All contacts are healthy! 💪</p>
                        </div>
                        <?php else: ?>
                        <div class="ptp-attention-list">
                            <?php foreach ($needs_attention as $contact): ?>
                            <div class="ptp-attention-item">
                                <div class="ptp-relationship-indicator score-<?php echo $contact->relationship_score < 20 ? 'low' : 'medium'; ?>">
                                    <?php echo $contact->relationship_score; ?>
                                </div>
                                <div class="ptp-attention-content">
                                    <a href="?page=ptp-comms-contacts&action=view&id=<?php echo $contact->id; ?>">
                                        <strong><?php echo esc_html($contact->parent_first_name . ' ' . $contact->parent_last_name); ?></strong>
                                    </a>
                                    <span class="ptp-last-contact">
                                        Last contact: <?php 
                                        echo $contact->last_interaction_at 
                                            ? human_time_diff(strtotime($contact->last_interaction_at)) . ' ago'
                                            : 'Never'; 
                                        ?>
                                    </span>
                                </div>
                                <div class="ptp-attention-actions">
                                    <a href="?page=ptp-comms-inbox&contact=<?php echo $contact->id; ?>" 
                                       class="ptp-comms-button small">Reach Out</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Activity Feed Column -->
                <div class="ptp-va-column">
                    <div class="ptp-comms-card">
                        <div class="ptp-card-header">
                            <h3>📊 Recent Activity</h3>
                        </div>
                        
                        <?php if (empty($recent_activity)): ?>
                        <div class="ptp-empty-state small">
                            <p>No recent activity</p>
                        </div>
                        <?php else: ?>
                        <div class="ptp-activity-feed">
                            <?php foreach ($recent_activity as $activity): ?>
                            <div class="ptp-activity-item">
                                <div class="ptp-activity-icon <?php echo esc_attr($activity->activity_type); ?>">
                                    <?php echo self::get_activity_icon($activity->activity_type); ?>
                                </div>
                                <div class="ptp-activity-content">
                                    <span class="ptp-activity-title"><?php echo esc_html($activity->title); ?></span>
                                    <?php if ($activity->contact_id): ?>
                                    <a href="?page=ptp-comms-contacts&action=view&id=<?php echo $activity->contact_id; ?>" class="ptp-activity-contact">
                                        <?php echo esc_html($activity->parent_first_name . ' ' . $activity->parent_last_name); ?>
                                    </a>
                                    <?php endif; ?>
                                    <span class="ptp-activity-time"><?php echo human_time_diff(strtotime($activity->created_at)); ?> ago</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="ptp-comms-card">
                        <div class="ptp-card-header">
                            <h3>🚀 Quick Actions</h3>
                        </div>
                        <div class="ptp-quick-actions">
                            <a href="?page=ptp-comms-campaigns&action=new" class="ptp-quick-action">
                                <span class="dashicons dashicons-megaphone"></span>
                                <span>New Campaign</span>
                            </a>
                            <a href="?page=ptp-comms-contacts&action=import" class="ptp-quick-action">
                                <span class="dashicons dashicons-upload"></span>
                                <span>Import Contacts</span>
                            </a>
                            <a href="?page=ptp-comms-segments" class="ptp-quick-action">
                                <span class="dashicons dashicons-groups"></span>
                                <span>Manage Segments</span>
                            </a>
                            <a href="?page=ptp-comms-automations" class="ptp-quick-action">
                                <span class="dashicons dashicons-controls-repeat"></span>
                                <span>Automations</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- HubSpot Sync Status -->
                    <?php if (!empty($hubspot_stats)): ?>
                    <div class="ptp-comms-card">
                        <div class="ptp-card-header">
                            <h3>🔄 HubSpot Sync</h3>
                            <a href="?page=ptp-comms-settings&tab=hubspot" class="ptp-link">Settings →</a>
                        </div>
                        <div class="ptp-sync-status">
                            <div class="ptp-sync-item">
                                <span class="label">Pending</span>
                                <span class="value"><?php echo number_format($hubspot_stats['pending']); ?></span>
                            </div>
                            <div class="ptp-sync-item">
                                <span class="label">Completed (24h)</span>
                                <span class="value success"><?php echo number_format($hubspot_stats['completed']); ?></span>
                            </div>
                            <?php if ($hubspot_stats['failed'] > 0): ?>
                            <div class="ptp-sync-item">
                                <span class="label">Failed</span>
                                <span class="value error"><?php echo number_format($hubspot_stats['failed']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Complete reminder
            $('.complete-reminder').on('change', function() {
                var checkbox = $(this);
                var reminderId = checkbox.data('id');
                
                if (checkbox.is(':checked')) {
                    $.post(ajaxurl, {
                        action: 'ptp_complete_reminder',
                        reminder_id: reminderId,
                        nonce: '<?php echo wp_create_nonce('ptp_reminder_action'); ?>'
                    }, function(response) {
                        if (response.success) {
                            checkbox.closest('.ptp-task-item').fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get activity icon by type
     */
    private static function get_activity_icon($type) {
        $icons = array(
            'sms_sent' => '💬',
            'sms_received' => '📨',
            'call_made' => '📞',
            'call_received' => '📲',
            'note_added' => '📝',
            'order_placed' => '🛒',
            'registration' => '✅',
            'reminder_completed' => '☑️',
            'contact_created' => '👤',
            'segment_added' => '🏷️',
            'vip_upgrade' => '⭐'
        );
        
        return isset($icons[$type]) ? $icons[$type] : '📌';
    }
}
