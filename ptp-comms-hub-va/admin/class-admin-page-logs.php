<?php
class PTP_Comms_Hub_Admin_Page_Logs {
    public static function render() {
        global $wpdb;
        
        // Get filters
        $filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $filter_direction = isset($_GET['direction']) ? sanitize_text_field($_GET['direction']) : '';
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
        
        // Build query
        $where = array("DATE(l.created_at) BETWEEN %s AND %s");
        $params = array($start_date, $end_date);
        
        if ($filter_type) {
            $where[] = "l.message_type = %s";
            $params[] = $filter_type;
        }
        if ($filter_direction) {
            $where[] = "l.direction = %s";
            $params[] = $filter_direction;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, c.parent_first_name, c.parent_last_name, c.parent_phone
            FROM {$wpdb->prefix}ptp_communication_logs l
            LEFT JOIN {$wpdb->prefix}ptp_contacts c ON l.contact_id = c.id
            WHERE {$where_sql}
            ORDER BY l.created_at DESC
            LIMIT 500
        ", ...$params));
        
        // Stats
        $total_logs = count($logs);
        $outbound = count(array_filter($logs, function($l) { return $l->direction === 'outbound'; }));
        $inbound = count(array_filter($logs, function($l) { return $l->direction === 'inbound'; }));
        
        ?>
        <div class="wrap ptp-comms-admin ptp-comms-wrap">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin: 0;">
                    <span class="dashicons dashicons-list-view" style="color: #FCB900; vertical-align: middle;"></span>
                    Communication Logs
                </h1>
                <div style="display: flex; gap: 10px;">
                    <?php if (class_exists('PTP_Comms_Hub_CSV_Export')): ?>
                    <?php PTP_Comms_Hub_CSV_Export::render_export_button('logs', 'Export CSV', array(
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ), 'button'); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="ptp-comms-stats">
                <div class="ptp-comms-stat-box">
                    <h2><?php echo number_format($total_logs); ?></h2>
                    <p>Total Messages</p>
                </div>
                <div class="ptp-comms-stat-box blue">
                    <h2><?php echo number_format($outbound); ?></h2>
                    <p>Outbound</p>
                </div>
                <div class="ptp-comms-stat-box green">
                    <h2><?php echo number_format($inbound); ?></h2>
                    <p>Inbound</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="ptp-comms-card" style="margin-bottom: 20px;">
                <form method="get" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="ptp-comms-logs">
                    
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="regular-text">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">End Date</label>
                        <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="regular-text">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Type</label>
                        <select name="type">
                            <option value="">All Types</option>
                            <option value="sms" <?php selected($filter_type, 'sms'); ?>>SMS</option>
                            <option value="voice" <?php selected($filter_type, 'voice'); ?>>Voice</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Direction</label>
                        <select name="direction">
                            <option value="">All</option>
                            <option value="outbound" <?php selected($filter_direction, 'outbound'); ?>>Outbound</option>
                            <option value="inbound" <?php selected($filter_direction, 'inbound'); ?>>Inbound</option>
                        </select>
                    </div>
                    <button type="submit" class="button button-primary">Filter</button>
                    <a href="?page=ptp-comms-logs" class="button">Reset</a>
                </form>
            </div>
            
            <!-- Logs Table -->
            <div class="ptp-comms-card">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Date</th>
                            <th style="width: 180px;">Contact</th>
                            <th style="width: 70px; text-align: center;">Type</th>
                            <th style="width: 90px; text-align: center;">Direction</th>
                            <th>Message</th>
                            <th style="width: 100px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                No logs found for the selected filters.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('M j, Y g:ia', strtotime($log->created_at)); ?></td>
                            <td>
                                <?php if ($log->parent_first_name): ?>
                                <?php echo esc_html($log->parent_first_name . ' ' . $log->parent_last_name); ?>
                                <br><small style="color: #666;"><?php echo esc_html(ptp_comms_format_phone($log->parent_phone)); ?></small>
                                <?php else: ?>
                                <em style="color: #999;">Unknown</em>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="ptp-comms-badge <?php echo $log->message_type === 'sms' ? 'info' : 'success'; ?>">
                                    <?php echo strtoupper($log->message_type); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span style="color: <?php echo $log->direction === 'inbound' ? '#0073aa' : '#46b450'; ?>;">
                                    <?php echo $log->direction === 'inbound' ? '← In' : '→ Out'; ?>
                                </span>
                            </td>
                            <td style="max-width: 400px; word-wrap: break-word;">
                                <?php echo esc_html(wp_trim_words($log->message_content, 25, '...')); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php 
                                $status_class = '';
                                switch ($log->status) {
                                    case 'delivered':
                                    case 'sent':
                                        $status_class = 'success';
                                        break;
                                    case 'failed':
                                    case 'undelivered':
                                        $status_class = 'error';
                                        break;
                                    default:
                                        $status_class = 'warning';
                                }
                                ?>
                                <span class="ptp-comms-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($log->status ?: 'pending'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_logs >= 500): ?>
            <p style="color: #666; text-align: center; margin-top: 15px;">
                Showing most recent 500 logs. Use date filters to narrow results, or export for full data.
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
