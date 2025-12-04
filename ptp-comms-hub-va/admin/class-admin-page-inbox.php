<?php
/**
 * PTP Communications Hub - Inbox Admin Page
 * Enhanced with professional PTP styling, WhatsApp integration, and responsive design
 * Version: 4.0
 */

class PTP_Comms_Hub_Admin_Page_Inbox {

    /**
     * Supported message channels
     */
    private static $channels = array(
        'all' => array('label' => 'All Channels', 'icon' => 'dashicons-email-alt', 'color' => '#6b7280'),
        'sms' => array('label' => 'SMS', 'icon' => 'dashicons-smartphone', 'color' => '#3b82f6'),
        'whatsapp' => array('label' => 'WhatsApp', 'icon' => 'dashicons-whatsapp', 'color' => '#25D366'),
        'voice' => array('label' => 'Voice', 'icon' => 'dashicons-phone', 'color' => '#8b5cf6'),
        'teams' => array('label' => 'Teams', 'icon' => 'dashicons-groups', 'color' => '#6264a7')
    );

    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $conversation_id = isset($_GET['conversation']) ? intval($_GET['conversation']) : 0;

        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('ptp_comms_inbox_action');

            if (isset($_POST['send_message'])) {
                self::handle_send_message();
            } elseif (isset($_POST['mark_read'])) {
                self::handle_mark_read();
            } elseif (isset($_POST['bulk_action'])) {
                self::handle_bulk_action();
            } elseif (isset($_POST['archive_conversation'])) {
                self::handle_archive();
            }
        }

        // Enqueue responsive styles
        wp_enqueue_style('ptp-comms-responsive', plugin_dir_url(__FILE__) . 'css/ptp-comms-responsive.css', array(), '1.0.0');

        if ($action === 'view' && $conversation_id > 0) {
            self::render_conversation($conversation_id);
        } else {
            self::render_list();
        }
    }
    
    private static function render_list() {
        global $wpdb;

        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        $channel = isset($_GET['channel']) ? sanitize_text_field($_GET['channel']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        // Build query based on filter
        $where = "conv.status = 'active'";
        if ($filter === 'unread') {
            $where .= " AND conv.unread_count > 0";
        } elseif ($filter === 'archived') {
            $where = "conv.status = 'archived'";
        }

        // Add channel filter
        if ($channel !== 'all' && in_array($channel, array('sms', 'whatsapp', 'voice', 'teams'))) {
            $where .= $wpdb->prepare(" AND conv.channel = %s", $channel);
        }

        // Add search condition
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                " AND (c.parent_first_name LIKE %s OR c.parent_last_name LIKE %s OR c.parent_phone LIKE %s OR conv.last_message LIKE %s)",
                $search_like, $search_like, $search_like, $search_like
            );
        }
        
        // Get total count for pagination
        $total_items = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}ptp_conversations conv
            JOIN {$wpdb->prefix}ptp_contacts c ON conv.contact_id = c.id
            WHERE {$where}
        ");
        
        $total_pages = ceil($total_items / $per_page);
        
        $conversations = $wpdb->get_results("
            SELECT conv.*, c.parent_first_name, c.parent_last_name, c.parent_phone, c.parent_email, c.child_name, c.relationship_score
            FROM {$wpdb->prefix}ptp_conversations conv
            JOIN {$wpdb->prefix}ptp_contacts c ON conv.contact_id = c.id
            WHERE {$where}
            ORDER BY conv.last_message_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");

        $total_unread = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE unread_count > 0 AND status = 'active'");
        $total_active = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE status = 'active'");
        $total_archived = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE status = 'archived'");

        // Channel counts
        $channel_counts = array(
            'sms' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE channel = 'sms' AND status = 'active'"),
            'whatsapp' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE channel = 'whatsapp' AND status = 'active'"),
            'voice' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE channel = 'voice' AND status = 'active'"),
            'teams' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE channel = 'teams' AND status = 'active'")
        );

        // Check WhatsApp configuration
        $whatsapp_configured = function_exists('ptp_comms_is_whatsapp_configured') && ptp_comms_is_whatsapp_configured();
        
        // Success/error messages
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        
        ?>
        <div class="wrap ptp-comms-admin ptp-comms-wrap ptp-inbox-container">
            <!-- Header Section -->
            <div class="ptp-page-header">
                <div class="ptp-flex ptp-items-center ptp-gap-3">
                    <h1 style="margin: 0;">
                        <span class="dashicons dashicons-email-alt" style="color: var(--ptp-primary); vertical-align: middle;"></span>
                        <?php _e('Shared Inbox', 'ptp-comms-hub'); ?>
                    </h1>
                    <?php if ($total_unread > 0): ?>
                    <span class="ptp-comms-badge error ptp-pulse" style="font-size: 14px;">
                        <?php echo $total_unread; ?> new
                    </span>
                    <?php endif; ?>
                </div>

                <div class="ptp-page-header-actions">
                    <button onclick="location.reload()" class="ptp-comms-button secondary small">
                        <span class="dashicons dashicons-update"></span>
                        <span class="ptp-hide-mobile">Refresh</span>
                    </button>
                    <button type="button" onclick="ptpOpenNewConversation()" class="ptp-comms-button small" style="background: var(--ptp-success);">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <span class="ptp-hide-mobile">New</span> Conversation
                    </button>
                    <a href="?page=ptp-comms-campaigns&action=new" class="ptp-comms-button small">
                        <span class="dashicons dashicons-megaphone"></span>
                        <span class="ptp-hide-mobile">New</span> Campaign
                    </a>
                </div>
            </div>

            <!-- Channel Selector -->
            <div class="ptp-comms-card compact" style="margin-bottom: 16px;">
                <div class="ptp-channel-selector">
                    <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?>&channel=all<?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>"
                       class="ptp-channel-selector-btn <?php echo $channel === 'all' ? 'active' : ''; ?>">
                        <span class="dashicons dashicons-email-alt"></span>
                        <span>All</span>
                        <span class="ptp-comms-badge secondary" style="margin-left: 4px; padding: 2px 6px; font-size: 10px;">
                            <?php echo number_format($total_active); ?>
                        </span>
                    </a>
                    <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?>&channel=sms<?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>"
                       class="ptp-channel-selector-btn sms <?php echo $channel === 'sms' ? 'active' : ''; ?>">
                        <span class="dashicons dashicons-smartphone"></span>
                        <span class="ptp-hide-mobile">SMS</span>
                        <?php if ($channel_counts['sms'] > 0): ?>
                        <span class="ptp-comms-badge secondary" style="margin-left: 4px; padding: 2px 6px; font-size: 10px;">
                            <?php echo number_format($channel_counts['sms']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?>&channel=whatsapp<?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>"
                       class="ptp-channel-selector-btn whatsapp <?php echo $channel === 'whatsapp' ? 'active' : ''; ?>"
                       <?php if (!$whatsapp_configured): ?>title="WhatsApp not configured"<?php endif; ?>>
                        <svg class="channel-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        <span class="ptp-hide-mobile">WhatsApp</span>
                        <?php if ($channel_counts['whatsapp'] > 0): ?>
                        <span class="ptp-comms-badge secondary" style="margin-left: 4px; padding: 2px 6px; font-size: 10px;">
                            <?php echo number_format($channel_counts['whatsapp']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?>&channel=voice<?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>"
                       class="ptp-channel-selector-btn <?php echo $channel === 'voice' ? 'active' : ''; ?>">
                        <span class="dashicons dashicons-phone"></span>
                        <span class="ptp-hide-mobile">Voice</span>
                        <?php if ($channel_counts['voice'] > 0): ?>
                        <span class="ptp-comms-badge secondary" style="margin-left: 4px; padding: 2px 6px; font-size: 10px;">
                            <?php echo number_format($channel_counts['voice']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php if ($channel_counts['teams'] > 0): ?>
                    <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?>&channel=teams<?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>"
                       class="ptp-channel-selector-btn <?php echo $channel === 'teams' ? 'active' : ''; ?>">
                        <span class="dashicons dashicons-groups"></span>
                        <span class="ptp-hide-mobile">Teams</span>
                        <span class="ptp-comms-badge secondary" style="margin-left: 4px; padding: 2px 6px; font-size: 10px;">
                            <?php echo number_format($channel_counts['teams']); ?>
                        </span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Messages -->
            <?php if ($message === 'sent'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Message sent successfully!</strong> Your message has been delivered.
            </div>
            <?php elseif ($message === 'marked_read'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Marked as read.</strong> The conversation has been updated.
            </div>
            <?php elseif ($message === 'archived'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Conversation archived.</strong> You can find it in the archived filter.
            </div>
            <?php elseif ($message === 'bulk_success'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Bulk action completed!</strong> Selected conversations have been updated.
            </div>
            <?php elseif ($message === 'error'): ?>
            <div class="ptp-comms-alert danger">
                <span class="dashicons dashicons-warning"></span>
                <strong>Error!</strong> Something went wrong. Please try again.
            </div>
            <?php endif; ?>

            <!-- Filters & Search Bar -->
            <div class="ptp-comms-card compact" style="margin-bottom: 20px;">
                <div class="ptp-flex ptp-justify-between ptp-items-center" style="flex-wrap: wrap; gap: 15px;">
                    <!-- Filter Tabs -->
                    <div class="ptp-comms-tabs">
                        <a href="?page=ptp-comms-inbox&filter=all&channel=<?php echo $channel; ?><?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>"
                           class="ptp-comms-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-email"></span>
                            All
                            <span class="ptp-comms-badge secondary"><?php echo number_format($total_active); ?></span>
                        </a>
                        <a href="?page=ptp-comms-inbox&filter=unread&channel=<?php echo $channel; ?><?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>"
                           class="ptp-comms-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-warning"></span>
                            Unread
                            <?php if ($total_unread > 0): ?>
                            <span class="ptp-comms-badge error"><?php echo number_format($total_unread); ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?page=ptp-comms-inbox&filter=archived&channel=<?php echo $channel; ?><?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>"
                           class="ptp-comms-tab <?php echo $filter === 'archived' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-archive"></span>
                            Archived
                            <span class="ptp-comms-badge secondary"><?php echo number_format($total_archived); ?></span>
                        </a>
                    </div>

                    <!-- Search Form -->
                    <form method="get" action="" class="ptp-search-bar">
                        <input type="hidden" name="page" value="ptp-comms-inbox">
                        <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
                        <input type="hidden" name="channel" value="<?php echo esc_attr($channel); ?>">
                        <div class="ptp-search-input-wrapper">
                            <span class="dashicons dashicons-search"></span>
                            <input type="text"
                                   name="s"
                                   value="<?php echo esc_attr($search); ?>"
                                   placeholder="Search conversations..."
                                   class="ptp-search-input">
                            <?php if (!empty($search)): ?>
                            <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?>&channel=<?php echo $channel; ?>" class="ptp-search-clear">
                                <span class="dashicons dashicons-no-alt"></span>
                            </a>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="ptp-comms-button small">Search</button>
                    </form>
                </div>
            </div>
            
            <?php if (empty($conversations)): ?>
            <div class="ptp-comms-card">
                <div class="ptp-comms-empty-state">
                    <span class="dashicons dashicons-email-alt" style="font-size: 80px; opacity: 0.2; color: var(--ptp-primary);"></span>
                    <h3>
                        <?php if (!empty($search)): ?>
                            No conversations match your search
                        <?php elseif ($filter === 'unread'): ?>
                            No unread messages
                        <?php elseif ($filter === 'archived'): ?>
                            No archived conversations
                        <?php else: ?>
                            No conversations yet
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?php if (!empty($search)): ?>
                            Try adjusting your search terms or <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?>">clear the search</a>.
                        <?php else: ?>
                            Once parents reply to your SMS campaigns or text your PTP number, their conversations will appear here.
                        <?php endif; ?>
                    </p>
                    <?php if (empty($search) && $filter === 'all'): ?>
                    <div style="margin-top: 25px;">
                        <a href="?page=ptp-comms-campaigns&action=new" class="ptp-comms-button">
                            <span class="dashicons dashicons-email-alt"></span>
                            Send Your First Campaign
                        </a>
                        <a href="?page=ptp-comms-settings&tab=twilio#webhook-setup" class="ptp-comms-button secondary" style="margin-left: 10px;">
                            <span class="dashicons dashicons-admin-plugins"></span>
                            Configure Two-Way SMS
                        </a>
                    </div>
                    <div style="margin-top: 20px; padding: 15px; background: #f0f7ff; border-radius: 8px; border-left: 3px solid #0073aa; text-align: left; max-width: 600px; margin-left: auto; margin-right: auto;">
                        <p style="margin: 0; font-size: 13px; line-height: 1.6;">
                            <strong style="color: #0073aa;">💡 Enable Parent-Initiated Messages:</strong><br>
                            Configure your Twilio webhook so parents can text you anytime (not just reply to campaigns).
                            <a href="?page=ptp-comms-settings&tab=twilio#webhook-setup" style="font-weight: 600;">Setup takes 2 minutes →</a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Bulk Actions Form -->
            <form method="post" id="ptp-inbox-form">
                <?php wp_nonce_field('ptp_comms_inbox_action'); ?>
                
                <div class="ptp-comms-card no-padding">
                    <!-- Bulk Actions Bar -->
                    <div style="padding: 15px 20px; border-bottom: 2px solid var(--ptp-gray-100); background: var(--ptp-gray-50);">
                        <div class="ptp-flex ptp-items-center ptp-gap-3">
                            <label style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer;">
                                <input type="checkbox" id="select-all-conversations" style="margin: 0;">
                                <span style="font-weight: 600; color: var(--ptp-gray-700);">Select All</span>
                            </label>
                            
                            <div class="ptp-flex ptp-items-center ptp-gap-2" id="bulk-actions-bar" style="display: none;">
                                <span style="color: var(--ptp-gray-600); font-size: 14px;" id="selected-count">0 selected</span>
                                <button type="submit" name="bulk_action" value="mark_read" class="ptp-comms-button small secondary">
                                    <span class="dashicons dashicons-yes"></span>
                                    Mark as Read
                                </button>
                                <button type="submit" name="bulk_action" value="archive" class="ptp-comms-button small secondary">
                                    <span class="dashicons dashicons-archive"></span>
                                    Archive
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conversations Table -->
                    <div class="ptp-comms-table-container">
                        <table class="ptp-comms-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Contact</th>
                                    <th class="ptp-hide-mobile">Last Message</th>
                                    <th style="width: 80px; text-align: center;">Channel</th>
                                    <th class="ptp-hide-mobile" style="width: 80px; text-align: center;">Status</th>
                                    <th style="width: 60px; text-align: center;">Unread</th>
                                    <th class="ptp-hide-mobile" style="width: 100px;">Activity</th>
                                    <th style="width: 80px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conversations as $conv):
                                    $conv_channel = isset($conv->channel) && $conv->channel ? $conv->channel : 'sms';
                                    $channel_colors = array(
                                        'sms' => '#3b82f6',
                                        'whatsapp' => '#25D366',
                                        'voice' => '#8b5cf6',
                                        'teams' => '#6264a7'
                                    );
                                    $channel_color = isset($channel_colors[$conv_channel]) ? $channel_colors[$conv_channel] : '#6b7280';
                                ?>
                                <tr class="<?php echo $conv->unread_count > 0 ? 'ptp-conversation-unread' : ''; ?>"
                                    style="<?php echo $conv->unread_count > 0 ? 'background: #fff9e6;' : ''; ?>" data-conversation-id="<?php echo $conv->id; ?>">
                                    <td style="text-align: center;">
                                        <input type="checkbox" name="conversation_ids[]" value="<?php echo $conv->id; ?>" class="conversation-checkbox">
                                    </td>
                                    <td>
                                        <div class="ptp-flex ptp-items-center ptp-gap-3">
                                            <div class="ptp-avatar" style="border: 2px solid <?php echo $channel_color; ?>;">
                                                <?php echo strtoupper(substr($conv->parent_first_name, 0, 1) . substr($conv->parent_last_name, 0, 1)); ?>
                                            </div>
                                            <div style="min-width: 0; flex: 1;">
                                                <strong style="display: block; font-weight: 600; color: var(--ptp-black); font-size: 14px;">
                                                    <?php echo esc_html($conv->parent_first_name . ' ' . $conv->parent_last_name); ?>
                                                </strong>
                                                <small style="color: var(--ptp-gray-600); display: block; margin-top: 2px; font-size: 12px;">
                                                    <?php echo esc_html(ptp_comms_format_phone($conv->parent_phone)); ?>
                                                    <?php if (!empty($conv->child_name)): ?>
                                                    · <?php echo esc_html($conv->child_name); ?>
                                                    <?php endif; ?>
                                                </small>
                                                <!-- Mobile: Show last message preview -->
                                                <div class="ptp-show-mobile" style="margin-top: 4px; font-size: 12px; color: var(--ptp-gray-600); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                                                    <?php echo esc_html(substr($conv->last_message ?: 'No messages yet', 0, 50)); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="ptp-hide-mobile">
                                        <div class="ptp-truncate" style="max-width: 300px; font-size: 13px;">
                                            <?php echo esc_html($conv->last_message ?: 'No messages yet'); ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="ptp-channel-badge <?php echo $conv_channel; ?>" style="background: <?php echo $channel_color; ?>20; color: <?php echo $channel_color; ?>; border: 1px solid <?php echo $channel_color; ?>40;">
                                            <?php
                                            switch ($conv_channel) {
                                                case 'whatsapp':
                                                    echo '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>';
                                                    break;
                                                case 'voice':
                                                    echo '<span class="dashicons dashicons-phone" style="font-size: 12px; width: 12px; height: 12px;"></span>';
                                                    break;
                                                case 'teams':
                                                    echo '<span class="dashicons dashicons-groups" style="font-size: 12px; width: 12px; height: 12px;"></span>';
                                                    break;
                                                default:
                                                    echo '<span class="dashicons dashicons-smartphone" style="font-size: 12px; width: 12px; height: 12px;"></span>';
                                            }
                                            ?>
                                            <span class="ptp-hide-mobile"><?php echo ucfirst($conv_channel); ?></span>
                                        </span>
                                    </td>
                                    <td class="ptp-hide-mobile" style="text-align: center;">
                                        <?php if ($conv->last_message_direction): ?>
                                        <span class="ptp-comms-badge <?php echo $conv->last_message_direction === 'inbound' ? 'success' : 'info'; ?>" style="font-size: 10px; padding: 2px 6px;">
                                            <span class="dashicons dashicons-arrow-<?php echo $conv->last_message_direction === 'inbound' ? 'down' : 'up'; ?>-alt"
                                                  style="font-size: 12px; vertical-align: middle; width: 12px; height: 12px;">
                                            </span>
                                            <?php echo ucfirst($conv->last_message_direction); ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($conv->unread_count > 0): ?>
                                        <span class="ptp-comms-badge error ptp-pulse" style="font-size: 11px; padding: 2px 8px;">
                                            <?php echo $conv->unread_count; ?>
                                        </span>
                                        <?php else: ?>
                                        <span style="color: var(--ptp-gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ptp-hide-mobile">
                                        <span style="color: var(--ptp-gray-600); font-size: 12px;">
                                            <?php
                                            if ($conv->last_message_at) {
                                                echo human_time_diff(strtotime($conv->last_message_at), current_time('timestamp')) . ' ago';
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="?page=ptp-comms-inbox&action=view&conversation=<?php echo $conv->id; ?>"
                                           class="ptp-comms-button small" style="padding: 6px 10px;">
                                            <span class="dashicons dashicons-visibility"></span>
                                            <span class="ptp-hide-mobile">View</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="ptp-pagination">
                <?php if ($paged > 1): ?>
                <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>&paged=<?php echo ($paged - 1); ?>" 
                   class="ptp-pagination-item">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </a>
                <?php else: ?>
                <span class="ptp-pagination-item disabled">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $paged - 2); $i <= min($total_pages, $paged + 2); $i++): ?>
                <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>&paged=<?php echo $i; ?>" 
                   class="ptp-pagination-item <?php echo $i === $paged ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($paged < $total_pages): ?>
                <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>&paged=<?php echo ($paged + 1); ?>" 
                   class="ptp-pagination-item">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </a>
                <?php else: ?>
                <span class="ptp-pagination-item disabled">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
        
        <?php self::render_inbox_scripts($filter, $total_unread); ?>
        <?php self::render_inbox_styles(); ?>
        <?php
    }
    
    private static function render_conversation($conversation_id) {
        global $wpdb;
        
        $conversation = $wpdb->get_row($wpdb->prepare("
            SELECT conv.*, c.*
            FROM {$wpdb->prefix}ptp_conversations conv
            JOIN {$wpdb->prefix}ptp_contacts c ON conv.contact_id = c.id
            WHERE conv.id = %d
        ", $conversation_id));
        
        if (!$conversation) {
            echo '<div class="wrap ptp-comms-wrap"><div class="ptp-comms-card"><p>Conversation not found.</p></div></div>';
            return;
        }
        
        // Mark as read
        PTP_Comms_Hub_Conversations::mark_as_read($conversation_id);
        
        // Get messages
        $messages = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ptp_messages
            WHERE conversation_id = %d
            ORDER BY created_at ASC
        ", $conversation_id));
        
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        $twilio_configured = ptp_comms_is_twilio_configured();
        
        ?>
        <div class="wrap ptp-comms-wrap">
            <!-- Header with Breadcrumb -->
            <div class="ptp-flex ptp-items-center ptp-gap-3" style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-inbox" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    Back to Inbox
                </a>
                <span style="color: var(--ptp-gray-400);">/</span>
                <h1 style="margin: 0; font-size: 24px;">
                    Conversation with <?php echo esc_html($conversation->parent_first_name . ' ' . $conversation->parent_last_name); ?>
                </h1>
            </div>
            
            <!-- Status Messages -->
            <?php if ($message === 'sent'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Message sent successfully!</strong> Your message has been delivered.
            </div>
            <?php elseif ($message === 'error'): ?>
            <div class="ptp-comms-alert danger">
                <span class="dashicons dashicons-warning"></span>
                <strong>Error!</strong> Failed to send message. Please check your Twilio configuration.
            </div>
            <?php endif; ?>
            
            <!-- Two Column Layout -->
            <div style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
                <!-- Main Conversation Area -->
                <div>
                    <!-- Conversation Thread -->
                    <div class="ptp-comms-card" style="padding: 0;">
                        <div style="padding: 20px; border-bottom: 2px solid var(--ptp-gray-100); background: var(--ptp-gray-50);">
                            <div class="ptp-flex ptp-justify-between ptp-items-center">
                                <div class="ptp-flex ptp-items-center ptp-gap-3">
                                    <div class="ptp-avatar" style="width: 48px; height: 48px; font-size: 18px;">
                                        <?php echo strtoupper(substr($conversation->parent_first_name, 0, 1) . substr($conversation->parent_last_name, 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong style="display: block; font-size: 18px; color: var(--ptp-black);">
                                            <?php echo esc_html($conversation->parent_first_name . ' ' . $conversation->parent_last_name); ?>
                                        </strong>
                                        <div style="color: var(--ptp-gray-600); font-size: 14px; margin-top: 2px;">
                                            <?php echo esc_html(ptp_comms_format_phone($conversation->parent_phone)); ?>
                                            <?php if ($conversation->parent_email): ?>
                                            · <?php echo esc_html($conversation->parent_email); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ptp-flex ptp-gap-2">
                                    <a href="tel:<?php echo esc_attr($conversation->parent_phone); ?>" 
                                       class="ptp-comms-button success small"
                                       title="Click to call <?php echo esc_attr(ptp_comms_format_phone($conversation->parent_phone)); ?>">
                                        <span class="dashicons dashicons-phone"></span>
                                        Call
                                    </a>
                                    
                                    <form method="post" style="margin: 0;" onsubmit="return confirm('Are you sure you want to archive this conversation?');">
                                        <?php wp_nonce_field('ptp_comms_inbox_action'); ?>
                                        <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                                        <button type="submit" name="archive_conversation" class="ptp-comms-button secondary small">
                                            <span class="dashicons dashicons-archive"></span>
                                            Archive
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Messages Thread -->
                        <div class="ptp-conversation-thread" id="conversation-thread">
                            <?php if (empty($messages)): ?>
                            <div class="ptp-comms-empty-state" style="padding: 40px 20px;">
                                <span class="dashicons dashicons-email-alt" style="font-size: 60px; opacity: 0.15; color: var(--ptp-primary);"></span>
                                <p style="margin: 10px 0 0; color: var(--ptp-gray-500);">No messages yet</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                            <div class="ptp-message <?php echo $msg->direction; ?>" data-message-id="<?php echo $msg->id; ?>">
                                <div class="ptp-message-bubble">
                                    <div class="ptp-message-content">
                                        <?php echo nl2br(esc_html($msg->message_body)); ?>
                                    </div>
                                    <div class="ptp-message-meta">
                                        <?php 
                                        echo ucfirst($msg->message_type);
                                        if ($msg->status) {
                                            echo ' · ' . ucfirst($msg->status);
                                        }
                                        echo ' · ' . date('M j, Y g:i A', strtotime($msg->created_at));
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Message Form -->
                        <div style="padding: 20px; border-top: 2px solid var(--ptp-gray-100); background: var(--ptp-gray-50);">
                            <?php if ($twilio_configured): ?>
                            <?php
                            // Get canned replies for dropdown
                            $canned_replies = array();
                            if (class_exists('PTP_Comms_Hub_Canned_Replies')) {
                                $canned_replies = PTP_Comms_Hub_Canned_Replies::get_grouped_for_dropdown();
                            }
                            ?>
                            <form id="send-message-form">
                                <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                                <input type="hidden" name="contact_id" value="<?php echo $conversation->contact_id; ?>">
                                
                                <?php if (!empty($canned_replies)): ?>
                                <div style="margin-bottom: 10px;">
                                    <label style="display: block; font-size: 12px; font-weight: 600; color: var(--ptp-gray-700); margin-bottom: 5px;">
                                        Quick Replies
                                    </label>
                                    <select id="canned-reply-selector" class="ptp-comms-form-control" style="height: 40px;">
                                        <option value="">Select a quick reply...</option>
                                        <?php foreach ($canned_replies as $category => $group): ?>
                                        <optgroup label="<?php echo esc_attr($group['label']); ?>">
                                            <?php foreach ($group['replies'] as $reply): ?>
                                            <option value="<?php echo esc_attr($reply->content); ?>"
                                                    data-shortcut="<?php echo esc_attr($reply->shortcut); ?>">
                                                <?php echo esc_html($reply->name); ?>
                                                <?php if ($reply->shortcut): ?>
                                                (<?php echo esc_html($reply->shortcut); ?>)
                                                <?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <div class="ptp-message-compose-form">
                                    <div class="ptp-message-compose-row" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: end;">
                                        <div style="min-width: 140px;">
                                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--ptp-gray-700); margin-bottom: 5px;">
                                                Send via
                                            </label>
                                            <select name="message_type" id="message-type-select" class="ptp-comms-form-control" style="height: 44px;">
                                                <option value="sms" data-icon="smartphone" style="color: #3b82f6;">
                                                    SMS
                                                </option>
                                                <?php if (function_exists('ptp_comms_is_whatsapp_configured') && ptp_comms_is_whatsapp_configured()): ?>
                                                <option value="whatsapp" data-icon="whatsapp" style="color: #25D366;">
                                                    WhatsApp
                                                </option>
                                                <?php endif; ?>
                                                <option value="voice" data-icon="phone" style="color: #8b5cf6;">
                                                    Voice
                                                </option>
                                            </select>
                                        </div>

                                        <div style="flex: 1; min-width: 200px;">
                                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--ptp-gray-700); margin-bottom: 5px;">
                                                Your Message
                                                <span style="font-weight: 400; color: var(--ptp-gray-500);">(Type /shortcut for quick insert)</span>
                                            </label>
                                            <textarea name="message"
                                                      id="message-textarea"
                                                      rows="2"
                                                      placeholder="Type your message..."
                                                      class="ptp-comms-form-control"
                                                      style="resize: vertical; min-height: 44px;"
                                                      required></textarea>
                                            <div id="char-counter" style="font-size: 11px; color: var(--ptp-gray-500); margin-top: 4px;">
                                                <span id="char-count">0</span> / 160 characters
                                                <span id="segment-count" style="margin-left: 8px;">(1 segment)</span>
                                            </div>
                                        </div>

                                        <div class="ptp-message-compose-actions" style="display: flex; gap: 8px;">
                                            <button type="submit" class="ptp-comms-button" style="height: 44px; padding: 0 20px;" id="send-btn">
                                                <span class="dashicons dashicons-email-alt" id="send-icon"></span>
                                                <span id="send-text">Send</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <script>
                            // Character counter and segment calculator
                            jQuery(function($) {
                                var $textarea = $('#message-textarea');
                                var $charCount = $('#char-count');
                                var $segmentCount = $('#segment-count');
                                var $charCounter = $('#char-counter');

                                $textarea.on('input', function() {
                                    var len = $(this).val().length;
                                    $charCount.text(len);

                                    // Calculate SMS segments
                                    var segments = 1;
                                    if (len > 160) {
                                        segments = Math.ceil(len / 153); // Multi-part messages use 153 chars per segment
                                    }
                                    $segmentCount.text('(' + segments + ' segment' + (segments > 1 ? 's' : '') + ')');

                                    // Color coding
                                    if (len > 320) {
                                        $charCounter.css('color', 'var(--ptp-danger)');
                                    } else if (len > 160) {
                                        $charCounter.css('color', 'var(--ptp-warning)');
                                    } else {
                                        $charCounter.css('color', 'var(--ptp-gray-500)');
                                    }
                                });

                                // Update send button based on channel
                                $('#message-type-select').on('change', function() {
                                    var type = $(this).val();
                                    var $icon = $('#send-icon');
                                    var $text = $('#send-text');
                                    var $btn = $('#send-btn');

                                    switch(type) {
                                        case 'whatsapp':
                                            $icon.removeClass().addClass('dashicons').html('');
                                            $icon.css('background', 'url("data:image/svg+xml,%3Csvg viewBox=\'0 0 24 24\' fill=\'%23000\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z\'/%3E%3C/svg%3E") no-repeat center center').css({'width': '16px', 'height': '16px', 'background-size': 'contain'});
                                            $text.text('WhatsApp');
                                            $btn.css('background', 'linear-gradient(135deg, #25D366 0%, #128C7E 100%)').css('color', '#fff');
                                            break;
                                        case 'voice':
                                            $icon.removeClass().addClass('dashicons dashicons-phone');
                                            $icon.css('background', 'none');
                                            $text.text('Call');
                                            $btn.css('background', 'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)').css('color', '#fff');
                                            break;
                                        default:
                                            $icon.removeClass().addClass('dashicons dashicons-email-alt');
                                            $icon.css('background', 'none');
                                            $text.text('Send');
                                            $btn.css('background', '').css('color', '');
                                    }
                                });
                            });
                            </script>
                            
                            <script>
                            jQuery(function($) {
                                // Canned reply dropdown
                                $('#canned-reply-selector').on('change', function() {
                                    var content = $(this).val();
                                    if (content) {
                                        $('#message-textarea').val(content).focus();
                                        $(this).val('');
                                    }
                                });
                                
                                // Shortcut expansion (e.g., /thanks)
                                $('#message-textarea').on('input', function() {
                                    var val = $(this).val();
                                    var match = val.match(/^\/(\w+)$/);
                                    if (match) {
                                        var shortcut = match[1];
                                        var option = $('#canned-reply-selector option[data-shortcut="' + shortcut + '"]');
                                        if (option.length) {
                                            $(this).val(option.val());
                                        }
                                    }
                                });
                            });
                            </script>
                            <?php else: ?>
                            <div class="ptp-comms-info-box danger">
                                <strong>Twilio not configured.</strong> 
                                <a href="?page=ptp-comms-settings">Configure Twilio in settings</a> to send messages.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Info Sidebar -->
                <div>
                    <div class="ptp-comms-card">
                        <h3 style="margin-top: 0; padding-bottom: 12px; border-bottom: 2px solid var(--ptp-gray-100);">
                            Contact Details
                        </h3>
                        
                        <div class="ptp-contact-info-grid">
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Status</div>
                                <?php if ($conversation->opted_in && !$conversation->opted_out): ?>
                                <span class="ptp-comms-badge success">
                                    <span class="dashicons dashicons-yes"></span>
                                    Opted In
                                </span>
                                <?php else: ?>
                                <span class="ptp-comms-badge error">
                                    <span class="dashicons dashicons-warning"></span>
                                    Opted Out
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($conversation->child_name): ?>
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Child Name</div>
                                <div class="ptp-info-value"><?php echo esc_html($conversation->child_name); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($conversation->child_age): ?>
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Child Age</div>
                                <div class="ptp-info-value"><?php echo esc_html($conversation->child_age); ?> years</div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($conversation->zip_code): ?>
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Zip Code</div>
                                <div class="ptp-info-value"><?php echo esc_html($conversation->zip_code); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Total Messages</div>
                                <div class="ptp-info-value"><?php echo count($messages); ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--ptp-gray-100); display: flex; flex-direction: column; gap: 10px;">
                            <a href="tel:<?php echo esc_attr($conversation->parent_phone); ?>" 
                               class="ptp-comms-button success small ptp-w-full ptp-text-center">
                                <span class="dashicons dashicons-phone"></span>
                                Call <?php echo esc_html(ptp_comms_format_phone($conversation->parent_phone)); ?>
                            </a>
                            
                            <a href="?page=ptp-comms-contacts&action=edit&id=<?php echo $conversation->contact_id; ?>" 
                               class="ptp-comms-button secondary small ptp-w-full ptp-text-center">
                                <span class="dashicons dashicons-edit"></span>
                                Edit Contact
                            </a>
                        </div>
                    </div>
                    
                    <!-- Quick Info Card -->
                    <div class="ptp-comms-card" style="margin-top: 20px; background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%);">
                        <h4 style="margin: 0 0 10px; color: var(--ptp-black); font-size: 14px; font-weight: 600;">
                            <span class="dashicons dashicons-info" style="color: var(--ptp-primary);"></span>
                            Tips
                        </h4>
                        <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: var(--ptp-gray-700); line-height: 1.6;">
                            <li>Keep messages clear and concise</li>
                            <li>Respect opt-out preferences</li>
                            <li>Response time matters for engagement</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php self::render_conversation_scripts(); ?>
        <?php self::render_conversation_styles(); ?>
        <?php
    }
    
    // Handler functions
    private static function handle_send_message() {
        global $wpdb;

        if (!isset($_POST['conversation_id'], $_POST['contact_id'], $_POST['message'], $_POST['message_type'])) {
            return;
        }

        $conversation_id = intval($_POST['conversation_id']);
        $contact_id = intval($_POST['contact_id']);
        $message = sanitize_textarea_field($_POST['message']);
        $message_type = sanitize_text_field($_POST['message_type']);

        // Get contact to retrieve phone number
        $contact = PTP_Comms_Hub_Contacts::get_contact($contact_id);
        if (!$contact || empty($contact->parent_phone)) {
            wp_redirect(add_query_arg([
                'page' => 'ptp-comms-inbox',
                'action' => 'view',
                'conversation' => $conversation_id,
                'message' => 'error'
            ], admin_url('admin.php')));
            exit;
        }

        $result = array('success' => false, 'error' => 'Unknown channel');

        // Send based on message type/channel
        switch ($message_type) {
            case 'whatsapp':
                // Send via WhatsApp
                if (function_exists('ptp_comms_send_whatsapp')) {
                    $result = ptp_comms_send_whatsapp($contact->parent_phone, $message);
                } else {
                    $result = array('success' => false, 'error' => 'WhatsApp not configured');
                }
                break;

            case 'voice':
                // Send via Voice
                if (class_exists('PTP_Comms_Hub_Voice_Service')) {
                    $voice_service = new PTP_Comms_Hub_Voice_Service();
                    $result = $voice_service->make_call($contact->parent_phone, $message);
                }
                break;

            case 'sms':
            default:
                // Send via SMS (default)
                $sms_service = new PTP_Comms_Hub_SMS_Service();
                $result = $sms_service->send_sms($contact->parent_phone, $message);
                $message_type = 'sms';
                break;
        }

        if ($result['success']) {
            // Log the message in the messages table
            $wpdb->insert(
                $wpdb->prefix . 'ptp_messages',
                array(
                    'conversation_id' => $conversation_id,
                    'message_type' => $message_type,
                    'message_body' => $message,
                    'direction' => 'outbound',
                    'status' => isset($result['status']) ? $result['status'] : 'sent',
                    'twilio_sid' => isset($result['sid']) ? $result['sid'] : null,
                    'created_at' => current_time('mysql')
                )
            );

            // Update conversation with channel
            PTP_Comms_Hub_Conversations::update_conversation($conversation_id, $message, 'outbound', $message_type);

            // Update contact last interaction
            $wpdb->update(
                $wpdb->prefix . 'ptp_contacts',
                array(
                    'last_interaction_at' => current_time('mysql')
                ),
                array('id' => $contact_id)
            );

            wp_redirect(add_query_arg([
                'page' => 'ptp-comms-inbox',
                'action' => 'view',
                'conversation' => $conversation_id,
                'message' => 'sent'
            ], admin_url('admin.php')));
            exit;
        } else {
            wp_redirect(add_query_arg([
                'page' => 'ptp-comms-inbox',
                'action' => 'view',
                'conversation' => $conversation_id,
                'message' => 'error'
            ], admin_url('admin.php')));
            exit;
        }
    }
    
    private static function handle_mark_read() {
        if (!isset($_POST['conversation_id'])) {
            return;
        }
        
        $conversation_id = intval($_POST['conversation_id']);
        PTP_Comms_Hub_Conversations::mark_as_read($conversation_id);
        
        wp_redirect(add_query_arg([
            'page' => 'ptp-comms-inbox',
            'message' => 'marked_read'
        ], admin_url('admin.php')));
        exit;
    }
    
    private static function handle_bulk_action() {
        if (!isset($_POST['bulk_action']) || !isset($_POST['conversation_ids'])) {
            return;
        }
        
        global $wpdb;
        $action = sanitize_text_field($_POST['bulk_action']);
        $conversation_ids = array_map('intval', $_POST['conversation_ids']);
        
        if (empty($conversation_ids)) {
            return;
        }
        
        $placeholders = implode(',', array_fill(0, count($conversation_ids), '%d'));
        
        if ($action === 'mark_read') {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}ptp_conversations 
                SET unread_count = 0 
                WHERE id IN ($placeholders)
            ", $conversation_ids));
        } elseif ($action === 'archive') {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}ptp_conversations 
                SET status = 'archived' 
                WHERE id IN ($placeholders)
            ", $conversation_ids));
        }
        
        wp_redirect(add_query_arg([
            'page' => 'ptp-comms-inbox',
            'message' => 'bulk_success'
        ], admin_url('admin.php')));
        exit;
    }
    
    private static function handle_archive() {
        if (!isset($_POST['conversation_id'])) {
            return;
        }
        
        global $wpdb;
        $conversation_id = intval($_POST['conversation_id']);
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_conversations',
            ['status' => 'archived'],
            ['id' => $conversation_id],
            ['%s'],
            ['%d']
        );
        
        wp_redirect(add_query_arg([
            'page' => 'ptp-comms-inbox',
            'message' => 'archived'
        ], admin_url('admin.php')));
        exit;
    }
    
    // Script rendering functions
    private static function render_inbox_scripts($filter, $total_unread) {
        global $wpdb;
        
        // Get opted-in contacts for the new conversation modal
        $opted_in_contacts = $wpdb->get_results("
            SELECT id, parent_first_name, parent_last_name, parent_phone, parent_email 
            FROM {$wpdb->prefix}ptp_contacts 
            WHERE opted_in = 1 AND parent_phone IS NOT NULL AND parent_phone != ''
            ORDER BY parent_last_name, parent_first_name
            LIMIT 500
        ");
        ?>
        
        <!-- New Conversation Modal -->
        <div id="new-conversation-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 999999; align-items: center; justify-content: center;">
            <div style="background: #fff; border-radius: 12px; width: 100%; max-width: 550px; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <!-- Modal Header -->
                <div style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #fff; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 18px;">
                        <span class="dashicons dashicons-plus-alt" style="margin-right: 8px; color: #FCD116;"></span>
                        Start New Conversation
                    </h3>
                    <button type="button" onclick="ptpCloseNewConversation()" style="background: none; border: none; color: #fff; cursor: pointer; font-size: 20px; line-height: 1;">×</button>
                </div>
                
                <!-- Modal Body -->
                <div style="padding: 24px;">
                    <form id="new-conversation-form">
                        <?php wp_nonce_field('ptp_comms_inbox_action'); ?>
                        
                        <!-- Contact Search -->
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1a1a1a;">
                                Select Parent/Guardian
                            </label>
                            <input type="text" id="contact-search" placeholder="Search by name or phone..." 
                                   style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; margin-bottom: 8px;"
                                   oninput="ptpFilterContacts(this.value)">
                            
                            <select id="contact-select" name="contact_id" required 
                                    style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; height: 200px;" 
                                    size="8">
                                <?php foreach ($opted_in_contacts as $contact): 
                                    $name = trim($contact->parent_first_name . ' ' . $contact->parent_last_name);
                                    $phone = ptp_comms_format_phone($contact->parent_phone);
                                ?>
                                <option value="<?php echo esc_attr($contact->id); ?>" 
                                        data-phone="<?php echo esc_attr($contact->parent_phone); ?>"
                                        data-search="<?php echo esc_attr(strtolower($name . ' ' . $contact->parent_phone)); ?>">
                                    <?php echo esc_html($name ?: 'Unknown'); ?> — <?php echo esc_html($phone); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="color: #6b7280; font-size: 12px; margin-top: 4px;">
                                <strong><?php echo count($opted_in_contacts); ?></strong> opted-in contacts available
                            </p>
                        </div>
                        
                        <!-- Message -->
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1a1a1a;">
                                Message
                            </label>
                            <textarea name="message" id="new-message" required rows="4" 
                                      placeholder="Type your message here..."
                                      style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
                            <p style="color: #6b7280; font-size: 12px; margin-top: 4px;">
                                Message will be sent via SMS
                            </p>
                        </div>
                        
                        <!-- Actions -->
                        <div style="display: flex; gap: 12px; justify-content: flex-end;">
                            <button type="button" onclick="ptpCloseNewConversation()" 
                                    style="padding: 12px 24px; border: 2px solid #e5e7eb; background: #fff; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                Cancel
                            </button>
                            <button type="submit" id="send-new-message-btn"
                                    style="padding: 12px 24px; background: #FCD116; color: #1a1a1a; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                <span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 4px;"></span>
                                Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        // New Conversation Modal Functions
        function ptpOpenNewConversation() {
            document.getElementById('new-conversation-modal').style.display = 'flex';
            document.getElementById('contact-search').focus();
        }
        
        function ptpCloseNewConversation() {
            document.getElementById('new-conversation-modal').style.display = 'none';
            document.getElementById('new-conversation-form').reset();
            document.getElementById('contact-search').value = '';
            ptpFilterContacts('');
        }
        
        function ptpFilterContacts(query) {
            var select = document.getElementById('contact-select');
            var options = select.options;
            var queryLower = query.toLowerCase();
            
            for (var i = 0; i < options.length; i++) {
                var searchText = options[i].getAttribute('data-search') || '';
                if (searchText.indexOf(queryLower) !== -1 || query === '') {
                    options[i].style.display = '';
                } else {
                    options[i].style.display = 'none';
                }
            }
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                ptpCloseNewConversation();
            }
        });
        
        // Close modal on backdrop click
        document.getElementById('new-conversation-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                ptpCloseNewConversation();
            }
        });
        
        // Handle form submission
        jQuery('#new-conversation-form').on('submit', function(e) {
            e.preventDefault();
            
            var btn = document.getElementById('send-new-message-btn');
            var originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> Sending...';
            
            var contactId = jQuery('#contact-select').val();
            var message = jQuery('#new-message').val();
            
            if (!contactId || !message) {
                alert('Please select a contact and enter a message.');
                btn.disabled = false;
                btn.innerHTML = originalText;
                return;
            }
            
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ptp_comms_start_conversation',
                    nonce: '<?php echo wp_create_nonce('ptp_comms_hub_nonce'); ?>',
                    contact_id: contactId,
                    message: message
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to the new conversation
                        if (response.data && response.data.conversation_id) {
                            window.location.href = '<?php echo admin_url('admin.php?page=ptp-comms-inbox&action=view&conversation='); ?>' + response.data.conversation_id + '&message=sent';
                        } else {
                            window.location.href = '<?php echo admin_url('admin.php?page=ptp-comms-inbox&message=sent'); ?>';
                        }
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : 'Failed to send message';
                        alert('Error: ' + errorMsg);
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
        });
        
        jQuery(document).ready(function($) {
            // Select all functionality
            $('#select-all-conversations').on('change', function() {
                $('.conversation-checkbox').prop('checked', $(this).is(':checked'));
                updateBulkActionsBar();
            });
            
            $('.conversation-checkbox').on('change', function() {
                updateBulkActionsBar();
                const total = $('.conversation-checkbox').length;
                const checked = $('.conversation-checkbox:checked').length;
                $('#select-all-conversations').prop('checked', total === checked);
            });
            
            function updateBulkActionsBar() {
                const checked = $('.conversation-checkbox:checked').length;
                if (checked > 0) {
                    $('#bulk-actions-bar').show();
                    $('#selected-count').text(checked + ' selected');
                } else {
                    $('#bulk-actions-bar').hide();
                }
            }
            
            $('button[name="bulk_action"]').on('click', function(e) {
                const checked = $('.conversation-checkbox:checked').length;
                if (checked === 0) {
                    e.preventDefault();
                    alert('Please select at least one conversation.');
                    return false;
                }
                
                const action = $(this).val();
                const actionText = action === 'mark_read' ? 'mark as read' : 'archive';
                
                if (!confirm('Are you sure you want to ' + actionText + ' ' + checked + ' conversation(s)?')) {
                    e.preventDefault();
                    return false;
                }
            });
            
            <?php if ($filter === 'unread' || $total_unread > 0): ?>
            setInterval(function() {
                $.get('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'ptp_comms_check_unread',
                    nonce: '<?php echo wp_create_nonce('ptp_comms_hub_nonce'); ?>'
                }, function(response) {
                    if (response.success && response.data.count > 0) {
                        const notice = $('<div class="ptp-comms-alert info" style="position: fixed; top: 32px; right: 20px; z-index: 999999; animation: slideIn 0.3s ease; max-width: 400px;">')
                            .html('<span class="dashicons dashicons-email-alt"></span><strong>New messages!</strong> <a href="#" onclick="location.reload(); return false;" style="text-decoration: underline;">Refresh to view</a>')
                            .appendTo('body');
                        
                        setTimeout(function() {
                            notice.fadeOut(300, function() { $(this).remove(); });
                        }, 5000);
                    }
                });
            }, 30000);
            <?php endif; ?>
        });
        
        // Spin animation for loading
        var style = document.createElement('style');
        style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
        document.head.appendChild(style);
        </script>
        <?php
    }
    
    private static function render_conversation_scripts() {
        global $wpdb;
        
        // Get the current conversation ID from the URL
        $conversation_id = isset($_GET['conversation']) ? intval($_GET['conversation']) : 0;
        
        // Get the last message ID for polling
        $last_message_id = 0;
        if ($conversation_id > 0) {
            $last_message_id = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(id) FROM {$wpdb->prefix}ptp_messages WHERE conversation_id = %d",
                $conversation_id
            ));
            $last_message_id = $last_message_id ? $last_message_id : 0;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            let lastMessageId = <?php echo $last_message_id; ?>;
            let pollingInterval;
            const conversationId = <?php echo $conversation_id; ?>;
            
            // Auto-scroll to bottom of conversation
            const thread = document.getElementById('conversation-thread');
            if (thread) {
                thread.scrollTop = thread.scrollHeight;
            }
            
            // Auto-expand textarea
            $('textarea[name="message"]').on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Start polling for new messages if we have a conversation ID
            if (conversationId > 0) {
                startPolling();
            }

            // Function to poll for new messages
            function startPolling() {
                pollingInterval = setInterval(function() {
                    checkForNewMessages();
                }, 8000); // Check every 8 seconds (optimized from 3s)
            }

            // Function to check for new messages
            function checkForNewMessages() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'GET',
                    data: {
                        action: 'ptp_comms_get_new_messages',
                        nonce: '<?php echo wp_create_nonce('ptp_comms_hub_nonce'); ?>',
                        conversation_id: conversationId,
                        after_id: lastMessageId
                    },
                    success: function(response) {
                        if (response.success && response.data.messages && response.data.messages.length > 0) {
                            response.data.messages.forEach(function(msg) {
                                appendMessage(msg);
                                lastMessageId = Math.max(lastMessageId, msg.id);

                                // Update badge if inbound message
                                if (msg.direction === 'inbound') {
                                    updateUnreadBadge();
                                }
                            });
                        }
                    },
                    error: function() {
                        // Silent fail - will retry on next poll
                    }
                });
            }
            
            // Function to update unread badge (if any messages are inbound)
            function updateUnreadBadge() {
                // You can add logic here to update badge counts
            }
            
            // Stop polling when leaving the page
            $(window).on('beforeunload', function() {
                if (pollingInterval) {
                    clearInterval(pollingInterval);
                }
            });

            // Unbind any existing handlers to prevent duplicates
            $(document).off('submit', '#send-message-form');
            $('#send-message-form').off('submit');

            // Track submission state to prevent double-sends
            let isSubmitting = false;

            // AJAX form submission (single handler)
            $('#send-message-form').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                // Prevent double submission
                if (isSubmitting) {
                    return false;
                }

                const $form = $(this);
                const $submitBtn = $form.find('button[type="submit"]');
                const $textarea = $form.find('textarea[name="message"]');
                const message = $textarea.val().trim();

                if (!message) {
                    alert('Please enter a message.');
                    return false;
                }

                // Lock submission
                isSubmitting = true;

                // Disable form during submission
                $submitBtn.prop('disabled', true);
                $textarea.prop('disabled', true);

                // Update button text
                const originalHtml = $submitBtn.html();
                $submitBtn.html('<span class="dashicons dashicons-update"></span> Sending...');

                // Prepare data
                const formData = {
                    conversation_id: $form.find('input[name="conversation_id"]').val(),
                    contact_id: $form.find('input[name="contact_id"]').val(),
                    message: message,
                    message_type: $form.find('select[name="message_type"]').val()
                };

                // Send AJAX request
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ptp_comms_send_message',
                        nonce: '<?php echo wp_create_nonce('ptp_comms_hub_nonce'); ?>',
                        conversation_id: formData.conversation_id,
                        contact_id: formData.contact_id,
                        message: formData.message,
                        message_type: formData.message_type
                    },
                    success: function(response) {
                        if (response.success && response.data.message) {
                            // Add message to thread
                            appendMessage(response.data.message);
                            lastMessageId = Math.max(lastMessageId, response.data.message.id);

                            // Clear textarea
                            $textarea.val('').css('height', 'auto');

                            // Show success feedback
                            showNotification('Message sent successfully!', 'success');
                        }
                    },
                    error: function(xhr) {
                        const error = xhr.responseJSON?.data?.message || 'Failed to send message. Please try again.';
                        showNotification(error, 'error');
                    },
                    complete: function() {
                        // Re-enable form
                        $submitBtn.prop('disabled', false).html(originalHtml);
                        $textarea.prop('disabled', false).focus();

                        // Unlock submission after delay
                        setTimeout(function() {
                            isSubmitting = false;
                        }, 1000);
                    }
                });

                return false;
            });
            
            // Function to append message to thread
            function appendMessage(msg) {
                const thread = document.getElementById('conversation-thread');
                const emptyState = thread.querySelector('.ptp-comms-empty-state');
                
                // Check if message already exists (prevent duplicates)
                if (document.querySelector(`[data-message-id="${msg.id}"]`)) {
                    return;
                }
                
                // Remove empty state if it exists
                if (emptyState) {
                    emptyState.remove();
                }
                
                const timeAgo = getTimeAgo(msg.created_at);
                
                const messageHtml = `
                    <div class="ptp-message ${msg.direction}" data-message-id="${msg.id}">
                        <div class="ptp-message-bubble">
                            <div class="ptp-message-content">${escapeHtml(msg.message_body)}</div>
                            <div class="ptp-message-meta">
                                ${msg.message_type.charAt(0).toUpperCase() + msg.message_type.slice(1)} · 
                                ${msg.status || 'sent'} · 
                                ${timeAgo}
                            </div>
                        </div>
                    </div>
                `;
                
                thread.insertAdjacentHTML('beforeend', messageHtml);
                thread.scrollTop = thread.scrollHeight;
            }
            
            // Function to show notification
            function showNotification(message, type) {
                const alertClass = type === 'success' ? 'success' : 'danger';
                const icon = type === 'success' ? 'yes' : 'warning';
                
                const notification = $(`
                    <div class="ptp-comms-alert ${alertClass}" style="margin-bottom: 20px;">
                        <span class="dashicons dashicons-${icon}"></span>
                        <strong>${message}</strong>
                    </div>
                `);
                
                // Insert after header
                $('.ptp-comms-wrap > div:first').after(notification);
                
                // Auto-remove after 3 seconds
                setTimeout(() => {
                    notification.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 3000);
            }
            
            // Helper to escape HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML.replace(/\n/g, '<br>');
            }
            
            // Helper to calculate time ago
            function getTimeAgo(datetime) {
                const now = new Date();
                const created = new Date(datetime);
                const seconds = Math.floor((now - created) / 1000);
                
                if (seconds < 60) return 'Just now';
                if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
                if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
                return Math.floor(seconds / 86400) + 'd ago';
            }
        });
        </script>
        <?php
    }
    
    private static function render_inbox_styles() {
        ?>
        <style>
        .ptp-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ptp-primary) 0%, var(--ptp-primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ptp-black);
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(252, 185, 0, 0.3);
        }
        
        .ptp-conversation-unread {
            border-left: 4px solid var(--ptp-primary) !important;
        }
        
        .ptp-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .ptp-search-input-wrapper {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }
        
        .ptp-search-input-wrapper .dashicons {
            position: absolute;
            left: 12px;
            color: var(--ptp-gray-400);
            pointer-events: none;
        }
        
        .ptp-search-input {
            width: 100%;
            padding: 10px 40px 10px 40px;
            border: 2px solid var(--ptp-gray-300);
            border-radius: var(--ptp-radius);
            font-size: 14px;
            transition: var(--ptp-transition);
        }
        
        .ptp-search-input:focus {
            border-color: var(--ptp-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(252, 185, 0, 0.1);
        }
        
        .ptp-search-clear {
            position: absolute;
            right: 8px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ptp-gray-400);
            transition: var(--ptp-transition);
            border-radius: var(--ptp-radius-sm);
        }
        
        .ptp-search-clear:hover {
            color: var(--ptp-danger);
            background: var(--ptp-gray-100);
        }
        
        .ptp-comms-alert {
            padding: 15px 20px;
            border-radius: var(--ptp-radius);
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
            margin-bottom: 20px;
        }
        
        .ptp-comms-alert .dashicons {
            flex-shrink: 0;
        }
        
        .ptp-comms-alert.success {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 100%);
            border-color: var(--ptp-success);
            color: #1b5e20;
        }
        
        .ptp-comms-alert.danger {
            background: linear-gradient(135deg, #ffebee 0%, #fff5f5 100%);
            border-color: var(--ptp-danger);
            color: #b71c1c;
        }
        
        .ptp-comms-alert.info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f0f7fc 100%);
            border-color: var(--ptp-info);
            color: #01579b;
        }
        
        @media (max-width: 768px) {
            .ptp-search-bar {
                flex-direction: column;
                width: 100%;
            }
        }
        </style>
        <?php
    }
    
    private static function render_conversation_styles() {
        ?>
        <style>
        .ptp-conversation-thread {
            max-height: 600px;
            overflow-y: auto;
            padding: 20px;
            background: var(--ptp-gray-50);
            border-radius: var(--ptp-radius);
        }
        
        .ptp-message {
            margin-bottom: 20px;
            display: flex;
            animation: slideIn 0.3s ease;
        }
        
        .ptp-message.outbound {
            justify-content: flex-end;
        }
        
        .ptp-message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: var(--ptp-radius-lg);
            box-shadow: var(--ptp-shadow-sm);
        }
        
        .ptp-message.inbound .ptp-message-bubble {
            background: var(--ptp-white);
            border: 1px solid var(--ptp-gray-200);
        }
        
        .ptp-message.outbound .ptp-message-bubble {
            background: linear-gradient(135deg, var(--ptp-primary) 0%, var(--ptp-primary-dark) 100%);
            color: var(--ptp-black);
        }
        
        .ptp-message-content {
            margin-bottom: 8px;
            line-height: 1.5;
            font-size: 14px;
        }
        
        .ptp-message-meta {
            font-size: 11px;
            color: var(--ptp-gray-500);
        }
        
        .ptp-message.outbound .ptp-message-meta {
            color: var(--ptp-black);
            opacity: 0.7;
        }
        
        .ptp-comms-form-control {
            padding: 10px 12px;
            border: 2px solid var(--ptp-gray-300);
            border-radius: var(--ptp-radius);
            font-size: 14px;
            font-family: inherit;
            width: 100%;
            transition: var(--ptp-transition);
        }
        
        .ptp-comms-form-control:focus {
            outline: none;
            border-color: var(--ptp-primary);
            box-shadow: 0 0 0 3px rgba(252, 185, 0, 0.1);
        }
        
        .ptp-contact-info-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }
        
        .ptp-info-item {
            padding: 12px 15px;
            background: var(--ptp-gray-50);
            border-radius: var(--ptp-radius);
            border-left: 3px solid var(--ptp-primary);
        }
        
        .ptp-info-label {
            font-size: 11px;
            color: var(--ptp-gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .ptp-info-value {
            font-size: 14px;
            color: var(--ptp-black);
            font-weight: 500;
        }
        
        /* Loading state for disabled button */
        .ptp-comms-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .ptp-comms-button:disabled .dashicons-update {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* New message animation */
        .ptp-message {
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 1024px) {
            .wrap.ptp-comms-wrap > div:first-of-type {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
        <?php
    }
}
