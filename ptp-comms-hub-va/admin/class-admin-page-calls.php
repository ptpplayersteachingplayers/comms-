<?php
/**
 * Calls admin page - Voice call management with recording playback
 * Redesigned interface with better UX
 */
class PTP_Comms_Hub_Admin_Page_Calls {
    
    public static function render() {
        global $wpdb;
        
        // Handle make call action
        if (isset($_POST['ptp_make_call']) && wp_verify_nonce($_POST['_wpnonce'], 'ptp_make_call')) {
            self::handle_make_call();
        }
        
        // Handle view single call details
        if (isset($_GET['view_call'])) {
            self::render_call_details(intval($_GET['view_call']));
            return;
        }
        
        // Get filter parameters
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_direction = isset($_GET['direction']) ? sanitize_text_field($_GET['direction']) : '';
        $filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $filter_date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Build query for calls
        $where_clauses = array("l.message_type IN ('voice', 'voicemail')");
        
        if ($filter_status) {
            $where_clauses[] = $wpdb->prepare("l.status = %s", $filter_status);
        }
        if ($filter_direction) {
            $where_clauses[] = $wpdb->prepare("l.direction = %s", $filter_direction);
        }
        if ($filter_date_from) {
            $where_clauses[] = $wpdb->prepare("DATE(l.created_at) >= %s", $filter_date_from);
        }
        if ($filter_date_to) {
            $where_clauses[] = $wpdb->prepare("DATE(l.created_at) <= %s", $filter_date_to);
        }
        if ($search) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = $wpdb->prepare(
                "(c.parent_first_name LIKE %s OR c.parent_last_name LIKE %s OR c.parent_phone LIKE %s)",
                $search_like, $search_like, $search_like
            );
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Get call logs
        $calls = $wpdb->get_results("
            SELECT l.*, 
                   c.parent_first_name, 
                   c.parent_last_name, 
                   c.parent_phone,
                   c.parent_email,
                   c.id as contact_id
            FROM {$wpdb->prefix}ptp_communication_logs l
            LEFT JOIN {$wpdb->prefix}ptp_contacts c ON l.contact_id = c.id
            WHERE {$where_sql}
            ORDER BY l.created_at DESC
            LIMIT 100
        ");
        
        // Get statistics
        $total_calls = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs WHERE message_type = 'voice'");
        $completed_calls = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs WHERE message_type = 'voice' AND status = 'completed'");
        $voicemails = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs WHERE message_type = 'voicemail'");
        $calls_today = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs WHERE message_type IN ('voice', 'voicemail') AND DATE(created_at) = CURDATE()");
        $recordings_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs WHERE message_type IN ('voice', 'voicemail') AND meta_data LIKE '%recording_url%'");
        
        // Check if voice is configured
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        $voice_configured = $voice_service->is_configured();
        
        ?>
        <div class="wrap ptp-comms-admin">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <div>
                    <h1 style="display: flex; align-items: center; gap: 12px; margin: 0;">
                        <span style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 12px; border-radius: 12px;">
                            <span class="dashicons dashicons-phone" style="font-size: 28px; width: 28px; height: 28px; color: #FCD116;"></span>
                        </span>
                        <?php _e('Voice Calls & Recordings', 'ptp-comms-hub'); ?>
                    </h1>
                    <p style="color: #666; margin: 8px 0 0 0;"><?php _e('Manage inbound/outbound calls, voicemails, and recordings', 'ptp-comms-hub'); ?></p>
                </div>
                
                <?php if ($voice_configured): ?>
                <button type="button" id="ptp-make-call-btn" class="button button-primary" 
                        style="background: linear-gradient(135deg, #FCD116 0%, #e5bc00 100%); border: none; color: #1a1a1a; padding: 12px 24px; font-size: 14px; font-weight: 600; border-radius: 8px; height: auto; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-phone" style="font-size: 18px;"></span>
                    <?php _e('Make a Call', 'ptp-comms-hub'); ?>
                </button>
                <?php endif; ?>
            </div>
            
            <?php if (!$voice_configured): ?>
            <div class="ptp-card" style="background: linear-gradient(135deg, #fff5f5 0%, #fff 100%); border-left: 4px solid #dc3232;">
                <h3 style="margin-top: 0; color: #dc3232;">⚠️ Voice Not Configured</h3>
                <p>To enable voice calling and IVR, you need to configure your Twilio credentials.</p>
                <a href="<?php echo admin_url('admin.php?page=ptp-comms-settings'); ?>" class="button button-primary">
                    Configure Twilio Settings
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px;">
                <div class="ptp-card" style="text-align: center; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #fff;">
                    <div style="font-size: 36px; font-weight: 700; color: #FCD116; margin-bottom: 4px;">
                        <?php echo number_format($total_calls ?: 0); ?>
                    </div>
                    <div style="color: #aaa; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                        <?php _e('Total Calls', 'ptp-comms-hub'); ?>
                    </div>
                </div>
                
                <div class="ptp-card" style="text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: #46b450; margin-bottom: 4px;">
                        <?php echo number_format($completed_calls ?: 0); ?>
                    </div>
                    <div style="color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                        <?php _e('Completed', 'ptp-comms-hub'); ?>
                    </div>
                </div>
                
                <div class="ptp-card" style="text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: #0073aa; margin-bottom: 4px;">
                        <?php echo number_format($voicemails ?: 0); ?>
                    </div>
                    <div style="color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                        <?php _e('Voicemails', 'ptp-comms-hub'); ?>
                    </div>
                </div>
                
                <div class="ptp-card" style="text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: #826eb4; margin-bottom: 4px;">
                        <?php echo number_format($recordings_count ?: 0); ?>
                    </div>
                    <div style="color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                        <?php _e('Recordings', 'ptp-comms-hub'); ?>
                    </div>
                </div>
                
                <div class="ptp-card" style="text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: #f56e28; margin-bottom: 4px;">
                        <?php echo number_format($calls_today ?: 0); ?>
                    </div>
                    <div style="color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                        <?php _e('Today', 'ptp-comms-hub'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="ptp-card" style="margin-bottom: 24px;">
                <form method="get" action="" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="ptp-comms-calls" />
                    
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px;">
                            <?php _e('Search', 'ptp-comms-hub'); ?>
                        </label>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" 
                               placeholder="Name or phone..." 
                               style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;" />
                    </div>
                    
                    <div style="min-width: 140px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px;">
                            <?php _e('Direction', 'ptp-comms-hub'); ?>
                        </label>
                        <select name="direction" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;">
                            <option value=""><?php _e('All', 'ptp-comms-hub'); ?></option>
                            <option value="inbound" <?php selected($filter_direction, 'inbound'); ?>>📥 Inbound</option>
                            <option value="outbound" <?php selected($filter_direction, 'outbound'); ?>>📤 Outbound</option>
                        </select>
                    </div>
                    
                    <div style="min-width: 140px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px;">
                            <?php _e('Status', 'ptp-comms-hub'); ?>
                        </label>
                        <select name="status" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;">
                            <option value=""><?php _e('All Statuses', 'ptp-comms-hub'); ?></option>
                            <option value="completed" <?php selected($filter_status, 'completed'); ?>>✓ Completed</option>
                            <option value="busy" <?php selected($filter_status, 'busy'); ?>>Busy</option>
                            <option value="no-answer" <?php selected($filter_status, 'no-answer'); ?>>No Answer</option>
                            <option value="failed" <?php selected($filter_status, 'failed'); ?>>Failed</option>
                            <option value="new" <?php selected($filter_status, 'new'); ?>>New</option>
                        </select>
                    </div>
                    
                    <div style="min-width: 140px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px;">
                            <?php _e('From Date', 'ptp-comms-hub'); ?>
                        </label>
                        <input type="date" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>" 
                               style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;" />
                    </div>
                    
                    <div style="min-width: 140px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px;">
                            <?php _e('To Date', 'ptp-comms-hub'); ?>
                        </label>
                        <input type="date" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>" 
                               style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;" />
                    </div>
                    
                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="button" style="padding: 10px 20px; height: auto; border-radius: 8px;">
                            <span class="dashicons dashicons-search" style="margin-top: 3px;"></span> Filter
                        </button>
                        <a href="?page=ptp-comms-calls" class="button" style="padding: 10px 16px; height: auto; border-radius: 8px;">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Call List -->
            <div class="ptp-card" style="padding: 0; overflow: hidden;">
                <?php if (empty($calls)): ?>
                    <div style="text-align: center; padding: 80px 20px; color: #666;">
                        <div style="background: #f5f5f5; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                            <span class="dashicons dashicons-phone" style="font-size: 40px; color: #ccc;"></span>
                        </div>
                        <h3 style="margin: 0 0 8px 0; color: #333;"><?php _e('No calls found', 'ptp-comms-hub'); ?></h3>
                        <p style="margin: 0; max-width: 400px; margin: 0 auto;"><?php _e('Calls will appear here once you make or receive voice calls through your Twilio number.', 'ptp-comms-hub'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="ptp-calls-list">
                        <?php foreach ($calls as $call): 
                            $call_data = json_decode($call->meta_data, true) ?: array();
                            $duration = isset($call_data['duration']) ? intval($call_data['duration']) : 0;
                            $recording_url = isset($call_data['recording_url']) ? $call_data['recording_url'] : '';
                            $call_sid = isset($call_data['call_sid']) ? $call_data['call_sid'] : '';
                            $is_voicemail = $call->message_type === 'voicemail';
                            $contact_name = trim($call->parent_first_name . ' ' . $call->parent_last_name);
                            if (empty($contact_name)) $contact_name = 'Unknown';
                        ?>
                            <div class="ptp-call-row" style="display: flex; align-items: center; padding: 16px 20px; border-bottom: 1px solid #eee; gap: 16px; transition: background 0.2s;" 
                                 onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                                
                                <!-- Icon -->
                                <div style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
                                    <?php if ($is_voicemail): ?>
                                        background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                                    <?php elseif ($call->status === 'completed'): ?>
                                        background: linear-gradient(135deg, #46b450 0%, #3a9142 100%);
                                    <?php elseif ($call->status === 'failed' || $call->status === 'no-answer'): ?>
                                        background: linear-gradient(135deg, #dc3232 0%, #b52727 100%);
                                    <?php else: ?>
                                        background: linear-gradient(135deg, #666 0%, #444 100%);
                                    <?php endif; ?>
                                ">
                                    <?php if ($is_voicemail): ?>
                                        <span class="dashicons dashicons-format-audio" style="color: #fff; font-size: 20px;"></span>
                                    <?php elseif ($call->direction === 'inbound'): ?>
                                        <span class="dashicons dashicons-arrow-down-alt" style="color: #fff; font-size: 20px;"></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-arrow-up-alt" style="color: #fff; font-size: 20px;"></span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Contact Info -->
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 15px; color: #1a1a1a; margin-bottom: 2px;">
                                        <?php echo esc_html($contact_name); ?>
                                        <?php if ($is_voicemail): ?>
                                            <span style="background: #0073aa; color: #fff; font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-weight: 500;">VOICEMAIL</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 13px; color: #666;">
                                        <?php echo esc_html(function_exists('ptp_comms_format_phone') ? ptp_comms_format_phone($call->parent_phone) : $call->parent_phone); ?>
                                        <span style="margin: 0 6px; color: #ddd;">•</span>
                                        <?php if ($call->direction === 'inbound'): ?>
                                            <span style="color: #46b450;">Inbound</span>
                                        <?php else: ?>
                                            <span style="color: #f56e28;">Outbound</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Duration & Status -->
                                <div style="text-align: center; min-width: 100px;">
                                    <?php if ($duration > 0): ?>
                                        <div style="font-size: 18px; font-weight: 600; color: #1a1a1a;">
                                            <?php echo gmdate('i:s', $duration); ?>
                                        </div>
                                        <div style="font-size: 11px; color: #999; text-transform: uppercase;">duration</div>
                                    <?php else: ?>
                                        <?php echo self::get_status_badge($call->status); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Recording Player -->
                                <div style="min-width: 200px;">
                                    <?php if ($recording_url): ?>
                                        <div class="ptp-inline-player" style="display: flex; align-items: center; gap: 8px; background: #f5f5f5; padding: 8px 12px; border-radius: 24px;">
                                            <button type="button" class="ptp-play-btn" data-url="<?php echo esc_attr($recording_url); ?>"
                                                    style="width: 32px; height: 32px; border-radius: 50%; border: none; background: #FCD116; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                                <span class="dashicons dashicons-controls-play" style="font-size: 16px; color: #1a1a1a; margin-left: 2px;"></span>
                                            </button>
                                            <div class="ptp-audio-progress" style="flex: 1; height: 4px; background: #ddd; border-radius: 2px; overflow: hidden;">
                                                <div class="ptp-audio-progress-bar" style="width: 0%; height: 100%; background: #FCD116; transition: width 0.1s;"></div>
                                            </div>
                                            <a href="<?php echo esc_attr($recording_url); ?>" download style="color: #666; text-decoration: none;" title="Download">
                                                <span class="dashicons dashicons-download" style="font-size: 16px;"></span>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #ccc; font-size: 12px;">No recording</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Time -->
                                <div style="text-align: right; min-width: 100px; color: #666; font-size: 13px;">
                                    <?php 
                                    $call_time = strtotime($call->created_at);
                                    $today = strtotime('today');
                                    $yesterday = strtotime('yesterday');
                                    
                                    if ($call_time >= $today) {
                                        echo 'Today ' . date('g:i A', $call_time);
                                    } elseif ($call_time >= $yesterday) {
                                        echo 'Yesterday ' . date('g:i A', $call_time);
                                    } else {
                                        echo date('M j', $call_time) . '<br><small>' . date('g:i A', $call_time) . '</small>';
                                    }
                                    ?>
                                </div>
                                
                                <!-- Actions -->
                                <div style="display: flex; gap: 8px;">
                                    <?php if ($call->parent_phone): ?>
                                    <a href="tel:<?php echo esc_attr($call->parent_phone); ?>" 
                                       class="button button-small" title="Call back"
                                       style="border-radius: 6px; padding: 4px 10px;">
                                        <span class="dashicons dashicons-phone" style="font-size: 14px; margin-top: 3px;"></span>
                                    </a>
                                    <?php endif; ?>
                                    <a href="?page=ptp-comms-calls&view_call=<?php echo $call->id; ?>" 
                                       class="button button-small" title="View details"
                                       style="border-radius: 6px; padding: 4px 10px;">
                                        <span class="dashicons dashicons-visibility" style="font-size: 14px; margin-top: 3px;"></span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Twilio Webhook Info -->
            <?php if ($voice_configured): ?>
            <div class="ptp-card" style="margin-top: 24px; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);">
                <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-admin-settings" style="color: #666;"></span>
                    Twilio Webhook Configuration
                </h3>
                <p style="color: #666; margin-bottom: 16px;">Configure these URLs in your Twilio phone number settings to enable inbound calls and IVR:</p>
                
                <div style="display: grid; gap: 12px;">
                    <div>
                        <label style="font-weight: 500; font-size: 13px; display: block; margin-bottom: 4px;">Voice Webhook URL (for incoming calls):</label>
                        <code style="display: block; padding: 12px; background: #1a1a1a; color: #FCD116; border-radius: 6px; font-size: 12px; word-break: break-all;">
                            <?php echo esc_url(get_rest_url(null, 'ptp-comms/v1/incoming-call')); ?>
                        </code>
                    </div>
                    <div>
                        <label style="font-weight: 500; font-size: 13px; display: block; margin-bottom: 4px;">Status Callback URL:</label>
                        <code style="display: block; padding: 12px; background: #1a1a1a; color: #FCD116; border-radius: 6px; font-size: 12px; word-break: break-all;">
                            <?php echo esc_url(get_rest_url(null, 'ptp-comms/v1/call-status')); ?>
                        </code>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Make Call Modal -->
        <div id="ptp-make-call-modal" class="ptp-modal" style="display: none;">
            <div class="ptp-modal-content" style="max-width: 450px; padding: 0; border-radius: 16px; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 24px; color: #fff;">
                    <h2 style="margin: 0; display: flex; align-items: center; gap: 12px;">
                        <span class="dashicons dashicons-phone" style="color: #FCD116;"></span>
                        Make a Call
                    </h2>
                </div>
                
                <form method="post" action="" style="padding: 24px;">
                    <?php wp_nonce_field('ptp_make_call'); ?>
                    <input type="hidden" name="ptp_make_call" value="1" />
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Phone Number</label>
                        <input type="tel" name="call_to" required placeholder="+1 (555) 123-4567"
                               style="width: 100%; padding: 14px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px;" />
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Message (Text-to-Speech)</label>
                        <textarea name="call_message" rows="3" placeholder="Hello, this is PTP Soccer Camps calling..."
                                  style="width: 100%; padding: 14px; border: 2px solid #ddd; border-radius: 10px; font-size: 14px; resize: vertical;"></textarea>
                        <small style="color: #666;">Leave blank for IVR call (caller hears menu)</small>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="record_call" value="1" checked style="width: 18px; height: 18px;" />
                            <span>Record this call</span>
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="button" class="button ptp-modal-close" style="flex: 1; padding: 14px; border-radius: 10px;">
                            Cancel
                        </button>
                        <button type="submit" class="button button-primary" style="flex: 1; padding: 14px; border-radius: 10px; background: #FCD116; border-color: #FCD116; color: #1a1a1a; font-weight: 600;">
                            <span class="dashicons dashicons-phone" style="margin-top: 3px;"></span> Call Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Hidden Audio Element -->
        <audio id="ptp-global-audio" style="display: none;"></audio>
        
        <style>
        .ptp-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ptp-modal-content {
            background: #fff;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .ptp-play-btn.playing .dashicons:before {
            content: "\f523"; /* pause icon */
        }
        .ptp-call-row:first-child {
            border-top: none;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var globalAudio = document.getElementById('ptp-global-audio');
            var currentPlayBtn = null;
            
            // Play button click
            $('.ptp-play-btn').on('click', function() {
                var $btn = $(this);
                var url = $btn.data('url');
                var $row = $btn.closest('.ptp-call-row');
                var $progressBar = $row.find('.ptp-audio-progress-bar');
                
                if ($btn.hasClass('playing')) {
                    globalAudio.pause();
                    $btn.removeClass('playing');
                } else {
                    if (currentPlayBtn) {
                        $(currentPlayBtn).removeClass('playing');
                    }
                    
                    globalAudio.src = url;
                    globalAudio.play();
                    $btn.addClass('playing');
                    currentPlayBtn = $btn[0];
                    
                    globalAudio.ontimeupdate = function() {
                        var progress = (globalAudio.currentTime / globalAudio.duration) * 100;
                        $progressBar.css('width', progress + '%');
                    };
                    
                    globalAudio.onended = function() {
                        $btn.removeClass('playing');
                        $progressBar.css('width', '0%');
                        currentPlayBtn = null;
                    };
                }
            });
            
            // Make call modal
            $('#ptp-make-call-btn').on('click', function() {
                $('#ptp-make-call-modal').fadeIn(200);
            });
            
            $('.ptp-modal-close').on('click', function() {
                $(this).closest('.ptp-modal').fadeOut(200);
            });
            
            $('.ptp-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).fadeOut(200);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle make call form submission
     */
    private static function handle_make_call() {
        $to = sanitize_text_field($_POST['call_to']);
        $message = sanitize_textarea_field($_POST['call_message'] ?? '');
        $record = isset($_POST['record_call']);
        
        $voice_service = new PTP_Comms_Hub_Voice_Service();
        
        if (!empty($message)) {
            $result = $voice_service->make_call($to, $message, $record);
        } else {
            $result = $voice_service->make_ivr_call($to);
        }
        
        if ($result['success']) {
            add_settings_error('ptp_comms', 'call_initiated', 'Call initiated successfully! Call SID: ' . $result['sid'], 'success');
        } else {
            add_settings_error('ptp_comms', 'call_failed', 'Call failed: ' . $result['error'], 'error');
        }
    }
    
    /**
     * Render single call details
     */
    private static function render_call_details($call_id) {
        global $wpdb;
        
        $call = $wpdb->get_row($wpdb->prepare("
            SELECT l.*, 
                   c.parent_first_name, 
                   c.parent_last_name, 
                   c.parent_phone,
                   c.parent_email,
                   c.id as contact_id
            FROM {$wpdb->prefix}ptp_communication_logs l
            LEFT JOIN {$wpdb->prefix}ptp_contacts c ON l.contact_id = c.id
            WHERE l.id = %d
        ", $call_id));
        
        if (!$call) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Call not found.</p></div></div>';
            return;
        }
        
        $call_data = json_decode($call->meta_data, true) ?: array();
        $duration = isset($call_data['duration']) ? intval($call_data['duration']) : 0;
        $recording_url = isset($call_data['recording_url']) ? $call_data['recording_url'] : '';
        $call_sid = isset($call_data['call_sid']) ? $call_data['call_sid'] : '';
        $contact_name = trim($call->parent_first_name . ' ' . $call->parent_last_name);
        if (empty($contact_name)) $contact_name = 'Unknown Caller';
        
        ?>
        <div class="wrap ptp-comms-admin">
            <div style="margin-bottom: 24px;">
                <a href="?page=ptp-comms-calls" class="button" style="margin-bottom: 16px;">
                    ← Back to Calls
                </a>
                <h1 style="display: flex; align-items: center; gap: 12px;">
                    <span class="dashicons dashicons-phone" style="font-size: 28px; color: #FCD116;"></span>
                    Call Details
                </h1>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 300px; gap: 24px;">
                <div>
                    <?php if ($recording_url): ?>
                    <div class="ptp-card" style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #fff;">
                        <h3 style="margin-top: 0; color: #FCD116; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-format-audio"></span>
                            Call Recording
                        </h3>
                        <audio controls style="width: 100%; margin-bottom: 16px;">
                            <source src="<?php echo esc_url($recording_url); ?>" type="audio/mpeg">
                        </audio>
                        <div style="display: flex; gap: 12px;">
                            <a href="<?php echo esc_url($recording_url); ?>" download class="button" style="background: #FCD116; border-color: #FCD116; color: #1a1a1a;">
                                <span class="dashicons dashicons-download" style="margin-top: 3px;"></span> Download Recording
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="ptp-card">
                        <h3 style="margin-top: 0;">Call Information</h3>
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th style="width: 150px;">Direction</th>
                                <td>
                                    <?php if ($call->direction === 'inbound'): ?>
                                        <span style="color: #46b450;">📥 Inbound</span>
                                    <?php else: ?>
                                        <span style="color: #f56e28;">📤 Outbound</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td><?php echo self::get_status_badge($call->status); ?></td>
                            </tr>
                            <tr>
                                <th>Duration</th>
                                <td><strong><?php echo $duration ? gmdate('i:s', $duration) . " ({$duration} seconds)" : 'N/A'; ?></strong></td>
                            </tr>
                            <tr>
                                <th>Date & Time</th>
                                <td><?php echo date('F j, Y \a\t g:i:s A', strtotime($call->created_at)); ?></td>
                            </tr>
                            <?php if ($call_sid): ?>
                            <tr>
                                <th>Call SID</th>
                                <td><code style="font-size: 11px;"><?php echo esc_html($call_sid); ?></code></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($call->message_type === 'voicemail' && !empty($call->message_content)): ?>
                            <tr>
                                <th>Transcription</th>
                                <td style="background: #f9f9f9; padding: 12px; border-radius: 6px;">
                                    <?php echo esc_html($call->message_content); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <div>
                    <div class="ptp-card">
                        <h3 style="margin-top: 0;">Contact</h3>
                        <div style="text-align: center; padding: 20px 0;">
                            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 32px; color: #FCD116; font-weight: 700;">
                                    <?php echo strtoupper(substr($contact_name, 0, 1)); ?>
                                </span>
                            </div>
                            <h3 style="margin: 0 0 4px 0;"><?php echo esc_html($contact_name); ?></h3>
                            <p style="margin: 0; color: #666;">
                                <a href="tel:<?php echo esc_attr($call->parent_phone); ?>" style="color: #0073aa; text-decoration: none;">
                                    <?php echo esc_html(function_exists('ptp_comms_format_phone') ? ptp_comms_format_phone($call->parent_phone) : $call->parent_phone); ?>
                                </a>
                            </p>
                        </div>
                        
                        <div style="display: flex; gap: 8px; margin-top: 16px;">
                            <a href="tel:<?php echo esc_attr($call->parent_phone); ?>" class="button" style="flex: 1; text-align: center;">
                                <span class="dashicons dashicons-phone" style="margin-top: 3px;"></span> Call
                            </a>
                            <?php if ($call->contact_id): ?>
                            <a href="?page=ptp-comms-contacts&action=edit&id=<?php echo $call->contact_id; ?>" class="button" style="flex: 1; text-align: center;">
                                View Contact
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private static function get_status_badge($status) {
        $badges = array(
            'completed' => '<span style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">✓ Completed</span>',
            'initiated' => '<span style="background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">⏳ Initiated</span>',
            'ringing' => '<span style="background: #cce5ff; color: #004085; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">🔔 Ringing</span>',
            'in-progress' => '<span style="background: #cce5ff; color: #004085; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">📞 In Progress</span>',
            'busy' => '<span style="background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">Busy</span>',
            'no-answer' => '<span style="background: #f8d7da; color: #721c24; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">No Answer</span>',
            'failed' => '<span style="background: #f8d7da; color: #721c24; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">✗ Failed</span>',
            'canceled' => '<span style="background: #e2e3e5; color: #383d41; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">Canceled</span>',
            'new' => '<span style="background: #d1ecf1; color: #0c5460; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">New</span>',
        );
        
        return isset($badges[$status]) ? $badges[$status] : '<span style="background: #e2e3e5; color: #383d41; padding: 4px 12px; border-radius: 12px; font-size: 12px;">' . ucfirst($status) . '</span>';
    }
}
