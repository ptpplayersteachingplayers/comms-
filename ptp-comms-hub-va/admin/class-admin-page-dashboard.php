<?php
class PTP_Comms_Hub_Admin_Page_Dashboard {
    public static function render() {
        global $wpdb;
        
        // Get statistics
        $total_contacts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts");
        $opted_in = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs");
        $active_automations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_automations WHERE is_active = 1");
        $unread_conversations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE unread_count > 0");
        
        // Get voice call statistics
        $total_calls = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs WHERE message_type = 'voice'");
        $completed_calls = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs WHERE message_type = 'voice' AND status = 'completed'");
        
        // Check system configuration
        $twilio_configured = !empty(get_option('ptp_comms_twilio_account_sid'));
        $hubspot_configured = !empty(get_option('ptp_comms_hubspot_api_key'));
        $teams_configured = !empty(get_option('ptp_comms_teams_webhook_url'));
        
        // Check webhook health (has inbound message in last 7 days?)
        $recent_inbound = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs 
            WHERE direction = 'inbound' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $webhook_healthy = ($recent_inbound > 0);
        
        // Get recent activity
        $recent_messages = $wpdb->get_results("
            SELECT cl.*, c.parent_first_name, c.parent_last_name, c.parent_phone
            FROM {$wpdb->prefix}ptp_communication_logs cl
            JOIN {$wpdb->prefix}ptp_contacts c ON cl.contact_id = c.id
            ORDER BY cl.created_at DESC
            LIMIT 5
        ");
        
        ?>
        <div class="wrap ptp-comms-admin">
            <h1><?php _e('PTP Communications Hub', 'ptp-comms-hub'); ?></h1>
            <p class="ptp-intro-text">
                <?php _e('Central command for PTP texting. See your contacts, campaigns, and system status at a glance.', 'ptp-comms-hub'); ?>
            </p>
            
            <!-- Quick Start Section -->
            <h2><?php _e('Quick Start', 'ptp-comms-hub'); ?></h2>
            <div class="ptp-quick-start-grid">
                <a href="?page=ptp-comms-contacts" class="ptp-quick-card">
                    <span class="dashicons dashicons-groups"></span>
                    <div class="ptp-card-title"><?php _e('Add Contacts', 'ptp-comms-hub'); ?></div>
                    <div class="ptp-card-muted"><?php _e('Import parents from CSV or add them one-by-one.', 'ptp-comms-hub'); ?></div>
                </a>
                
                <a href="?page=ptp-comms-settings&tab=twilio" class="ptp-quick-card">
                    <span class="dashicons dashicons-smartphone"></span>
                    <div class="ptp-card-title"><?php _e('Connect Twilio', 'ptp-comms-hub'); ?></div>
                    <div class="ptp-card-muted"><?php _e('Connect your Twilio number to send SMS & voice.', 'ptp-comms-hub'); ?></div>
                </a>
                
                <a href="?page=ptp-comms-settings&tab=hubspot" class="ptp-quick-card">
                    <span class="dashicons dashicons-admin-links"></span>
                    <div class="ptp-card-title"><?php _e('Connect HubSpot', 'ptp-comms-hub'); ?></div>
                    <div class="ptp-card-muted"><?php _e('Sync contacts and track engagement automatically.', 'ptp-comms-hub'); ?></div>
                </a>
                
                <a href="?page=ptp-comms-campaigns&action=new" class="ptp-quick-card">
                    <span class="dashicons dashicons-megaphone"></span>
                    <div class="ptp-card-title"><?php _e('Create First Campaign', 'ptp-comms-hub'); ?></div>
                    <div class="ptp-card-muted"><?php _e('Send your first text blast to parents.', 'ptp-comms-hub'); ?></div>
                </a>
            </div>
            
            <!-- Statistics Overview -->
            <h2><?php _e('Overview', 'ptp-comms-hub'); ?></h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="ptp-card" style="text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--ptp-ink); margin-bottom: 8px;">
                        <?php echo number_format($total_contacts); ?>
                    </div>
                    <div style="color: var(--ptp-muted); font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php _e('Total Contacts', 'ptp-comms-hub'); ?>
                    </div>
                    <div style="margin-top: 10px; font-size: 12px; color: #46b450;">
                        ✓ <?php echo number_format($opted_in); ?> <?php _e('Opted In', 'ptp-comms-hub'); ?>
                    </div>
                </div>
                
                <div class="ptp-card" style="text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--ptp-ink); margin-bottom: 8px;">
                        <?php echo number_format($total_messages); ?>
                    </div>
                    <div style="color: var(--ptp-muted); font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php _e('Messages Sent', 'ptp-comms-hub'); ?>
                    </div>
                    <div style="margin-top: 10px; font-size: 12px; color: var(--ptp-muted);">
                        <?php _e('All time', 'ptp-comms-hub'); ?>
                    </div>
                </div>
                
                <div class="ptp-card" style="text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--ptp-ink); margin-bottom: 8px;">
                        <?php echo number_format($unread_conversations); ?>
                    </div>
                    <div style="color: var(--ptp-muted); font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php _e('Unread Messages', 'ptp-comms-hub'); ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <a href="?page=ptp-comms-inbox" style="font-size: 12px;">
                            <?php _e('View Inbox', 'ptp-comms-hub'); ?> →
                        </a>
                    </div>
                </div>
                
                <div class="ptp-card" style="text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--ptp-ink); margin-bottom: 8px;">
                        <?php echo number_format($active_automations); ?>
                    </div>
                    <div style="color: var(--ptp-muted); font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php _e('Active Automations', 'ptp-comms-hub'); ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <a href="?page=ptp-comms-automations" style="font-size: 12px;">
                            <?php _e('Manage', 'ptp-comms-hub'); ?> →
                        </a>
                    </div>
                </div>
                
                <div class="ptp-card" style="text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--ptp-yellow); margin-bottom: 8px;">
                        <?php echo number_format($total_calls); ?>
                    </div>
                    <div style="color: var(--ptp-muted); font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php _e('Voice Calls', 'ptp-comms-hub'); ?>
                    </div>
                    <div style="margin-top: 10px; font-size: 12px; color: #46b450;">
                        ✓ <?php echo number_format($completed_calls); ?> <?php _e('Completed', 'ptp-comms-hub'); ?>
                    </div>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="ptp-card">
                <h2><?php _e('System Status', 'ptp-comms-hub'); ?></h2>
                <ul class="ptp-status-list">
                    <li>
                        <span><?php _e('Twilio SMS/Voice', 'ptp-comms-hub'); ?></span>
                        <?php if ($twilio_configured): ?>
                        <span class="ptp-status-badge ptp-status-ok"><?php _e('Configured', 'ptp-comms-hub'); ?></span>
                        <?php else: ?>
                        <span class="ptp-status-badge ptp-status-missing"><?php _e('Not Configured', 'ptp-comms-hub'); ?></span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <span><?php _e('Two-Way SMS Webhook', 'ptp-comms-hub'); ?></span>
                        <?php if ($webhook_healthy): ?>
                        <span class="ptp-status-badge ptp-status-ok">
                            <?php _e('Working', 'ptp-comms-hub'); ?>
                        </span>
                        <div style="margin-top: 8px; font-size: 12px; color: var(--ptp-muted);">
                            <?php printf(__('Last inbound message received %d days ago', 'ptp-comms-hub'), floor((time() - strtotime($wpdb->get_var("SELECT created_at FROM {$wpdb->prefix}ptp_communication_logs WHERE direction = 'inbound' ORDER BY created_at DESC LIMIT 1"))) / 86400)); ?>
                        </div>
                        <?php else: ?>
                        <span class="ptp-status-badge ptp-status-warning">
                            <?php _e('Setup Required', 'ptp-comms-hub'); ?>
                        </span>
                        <div style="margin-top: 8px; font-size: 12px; color: var(--ptp-muted);">
                            Webhook URL: <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;"><?php echo home_url('/ptp-comms/sms-webhook'); ?></code>
                            <a href="<?php echo admin_url('admin.php?page=ptp-comms-settings&tab=twilio#webhook-setup'); ?>" style="margin-left: 8px;">Setup Guide →</a>
                        </div>
                        <?php endif; ?>
                    </li>
                    <li>
                        <span><?php _e('HubSpot Integration', 'ptp-comms-hub'); ?></span>
                        <?php if ($hubspot_configured): ?>
                        <span class="ptp-status-badge ptp-status-ok"><?php _e('Configured', 'ptp-comms-hub'); ?></span>
                        <?php else: ?>
                        <span class="ptp-status-badge ptp-status-missing"><?php _e('Not Configured', 'ptp-comms-hub'); ?></span>
                        <?php endif; ?>
                    </li>
                    <li>
                        <span><?php _e('Microsoft Teams Notifications', 'ptp-comms-hub'); ?></span>
                        <?php if ($teams_configured): ?>
                        <span class="ptp-status-badge ptp-status-ok"><?php _e('Configured', 'ptp-comms-hub'); ?></span>
                        <?php else: ?>
                        <span class="ptp-status-badge ptp-status-missing"><?php _e('Not Configured', 'ptp-comms-hub'); ?></span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
            
            <!-- WooCommerce Automation Info -->
            <div class="ptp-card">
                <h2><?php _e('WooCommerce Automation', 'ptp-comms-hub'); ?></h2>
                <p><?php _e('When WooCommerce marks an order as "completed" or "processing", PTP Comms will:', 'ptp-comms-hub'); ?></p>
                <ol>
                    <li><?php _e('Create or update the contact record', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Register the purchase in their activity log', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Sync contact data to HubSpot (if enabled)', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Send configured automated messages', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Trigger Microsoft Teams notification (if configured)', 'ptp-comms-hub'); ?></li>
                </ol>
            </div>
            
            <!-- Recent Activity -->
            <?php if (!empty($recent_messages)): ?>
            <div class="ptp-card">
                <h2><?php _e('Recent Activity', 'ptp-comms-hub'); ?></h2>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php _e('Contact', 'ptp-comms-hub'); ?></th>
                            <th><?php _e('Type', 'ptp-comms-hub'); ?></th>
                            <th><?php _e('Status', 'ptp-comms-hub'); ?></th>
                            <th><?php _e('Date', 'ptp-comms-hub'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_messages as $message): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($message->parent_first_name . ' ' . $message->parent_last_name); ?></strong><br>
                                <small><?php echo esc_html($message->parent_phone); ?></small>
                            </td>
                            <td><?php echo esc_html(strtoupper($message->type)); ?></td>
                            <td>
                                <?php if ($message->status === 'sent'): ?>
                                <span class="ptp-status-badge ptp-status-ok"><?php _e('Sent', 'ptp-comms-hub'); ?></span>
                                <?php elseif ($message->status === 'failed'): ?>
                                <span class="ptp-status-badge ptp-status-missing"><?php _e('Failed', 'ptp-comms-hub'); ?></span>
                                <?php else: ?>
                                <span class="ptp-status-badge ptp-status-warning"><?php echo esc_html(ucfirst($message->status)); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y g:i a', strtotime($message->created_at)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
