<?php
class PTP_Comms_Hub_Admin_Page_Campaigns {
    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
            check_admin_referer('ptp_comms_campaign_create');
            self::handle_create_campaign();
        }
        
        if ($action === 'new') {
            self::render_create_form();
        } elseif ($action === 'view' && isset($_GET['id'])) {
            self::render_campaign_details(intval($_GET['id']));
        } else {
            self::render_list();
        }
    }
    
    private static function render_list() {
        global $wpdb;
        
        $campaigns = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}ptp_campaigns 
            ORDER BY created_at DESC
        ");
        
        // Get stats
        $total_campaigns = count($campaigns);
        $active_campaigns = count(array_filter($campaigns, function($c) { return $c->status === 'active' || $c->status === 'sending'; }));
        $total_sent = array_sum(array_column($campaigns, 'sent_count'));
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin: 0;">Campaigns</h1>
                <a href="?page=ptp-comms-campaigns&action=new" class="ptp-comms-button">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                    New Campaign
                </a>
            </div>
            
            <!-- Stats -->
            <div class="ptp-comms-stats">
                <div class="ptp-comms-stat-box">
                    <h2><?php echo number_format($total_campaigns); ?></h2>
                    <p>Total Campaigns</p>
                </div>
                <div class="ptp-comms-stat-box green">
                    <h2><?php echo number_format($active_campaigns); ?></h2>
                    <p>Active/Sending</p>
                </div>
                <div class="ptp-comms-stat-box blue">
                    <h2><?php echo number_format($total_sent); ?></h2>
                    <p>Messages Sent</p>
                </div>
                <div class="ptp-comms-stat-box purple">
                    <h2><?php 
                    $total_recipients = array_sum(array_column($campaigns, 'total_recipients'));
                    $rate = $total_recipients > 0 ? round(($total_sent / $total_recipients) * 100, 1) : 0;
                    echo $rate . '%'; 
                    ?></h2>
                    <p>Delivery Rate</p>
                </div>
            </div>
            
            <!-- Campaigns Table -->
            <?php if (empty($campaigns)): ?>
            <div class="ptp-comms-card">
                <div class="ptp-comms-empty-state">
                    <span class="dashicons dashicons-megaphone" style="font-size: 80px; opacity: 0.2;"></span>
                    <h3>No campaigns yet</h3>
                    <p>Create your first campaign to start reaching your contacts.</p>
                    <a href="?page=ptp-comms-campaigns&action=new" class="ptp-comms-button">Create Campaign</a>
                </div>
            </div>
            <?php else: ?>
            <div class="ptp-comms-card">
                <table class="ptp-comms-table">
                    <thead>
                        <tr>
                            <th>Campaign Name</th>
                            <th style="width: 100px; text-align: center;">Type</th>
                            <th style="width: 120px; text-align: center;">Status</th>
                            <th style="width: 100px; text-align: right;">Recipients</th>
                            <th style="width: 100px; text-align: right;">Sent</th>
                            <th style="width: 120px; text-align: right;">Delivery</th>
                            <th style="width: 150px;">Created</th>
                            <th style="width: 100px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($campaign->name); ?></strong>
                                <?php if ($campaign->message_preview): ?>
                                <br><small style="color: #666;"><?php echo esc_html(substr($campaign->message_preview, 0, 60)) . '...'; ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="ptp-comms-badge <?php echo $campaign->message_type === 'sms' ? 'info' : 'success'; ?>">
                                    <?php echo strtoupper($campaign->message_type); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php 
                                $status_class = 'warning';
                                if ($campaign->status === 'completed') $status_class = 'success';
                                elseif ($campaign->status === 'failed') $status_class = 'error';
                                elseif ($campaign->status === 'sending' || $campaign->status === 'active') $status_class = 'info';
                                ?>
                                <span class="ptp-comms-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($campaign->status); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <?php echo number_format($campaign->total_recipients); ?>
                            </td>
                            <td style="text-align: right;">
                                <strong><?php echo number_format($campaign->sent_count); ?></strong>
                            </td>
                            <td style="text-align: right;">
                                <?php 
                                $delivery_rate = $campaign->total_recipients > 0 
                                    ? round(($campaign->sent_count / $campaign->total_recipients) * 100, 1) 
                                    : 0;
                                $color = $delivery_rate >= 90 ? '#46b450' : ($delivery_rate >= 70 ? '#f39c12' : '#dc3232');
                                ?>
                                <span style="color: <?php echo $color; ?>; font-weight: 600;">
                                    <?php echo $delivery_rate; ?>%
                                </span>
                            </td>
                            <td>
                                <?php echo date('M j, Y g:i A', strtotime($campaign->created_at)); ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="?page=ptp-comms-campaigns&action=view&id=<?php echo $campaign->id; ?>" 
                                   class="ptp-comms-button small secondary">
                                    View
                                </a>
                                <?php if ($campaign->status === 'completed' && class_exists('PTP_Comms_Hub_CSV_Export')): ?>
                                <a href="<?php echo esc_url(PTP_Comms_Hub_CSV_Export::get_export_url('campaign', array('campaign_id' => $campaign->id))); ?>" 
                                   class="ptp-comms-button small secondary" 
                                   title="Export results to CSV"
                                   style="margin-left: 5px;">
                                    <span class="dashicons dashicons-download" style="font-size: 14px; vertical-align: middle;"></span>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private static function render_create_form() {
        global $wpdb;
        
        // Get templates
        $templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ptp_templates ORDER BY name ASC");
        
        // Get contact count
        $total_contacts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1 AND opted_out = 0");
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-campaigns" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> Back to Campaigns
                </a>
            </div>
            
            <div class="ptp-comms-card">
                <h1 style="margin-top: 0;">Create New Campaign</h1>
                
                <?php if (!ptp_comms_is_twilio_configured()): ?>
                <div class="ptp-comms-alert warning">
                    <strong>⚠ Twilio Not Configured</strong><br>
                    You need to configure Twilio before sending campaigns. 
                    <a href="?page=ptp-comms-settings">Configure Now</a>
                </div>
                <?php elseif ($total_contacts == 0): ?>
                <div class="ptp-comms-alert warning">
                    <strong>⚠ No Contacts Available</strong><br>
                    You don't have any opted-in contacts yet. 
                    <a href="?page=ptp-comms-contacts">Add Contacts</a>
                </div>
                <?php endif; ?>
                
                <form method="post" id="campaign-form">
                    <?php wp_nonce_field('ptp_comms_campaign_create'); ?>
                    
                    <div class="ptp-comms-grid">
                        <!-- Left Column - Campaign Details -->
                        <div style="grid-column: span 2;">
                            <div class="ptp-comms-form-group">
                                <label>Campaign Name *</label>
                                <input type="text" name="campaign_name" class="ptp-comms-form-control" required 
                                       placeholder="e.g., Spring Camp Registration Reminder">
                                <span class="ptp-comms-form-help">Internal name for this campaign</span>
                            </div>
                            
                            <div class="ptp-comms-form-group">
                                <label>Message Type *</label>
                                <div style="display: flex; gap: 20px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="message_type" value="sms" checked onchange="updateMessageType()">
                                        <span class="ptp-comms-badge info">SMS</span>
                                        <span style="color: #666; font-size: 13px;">Text message</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="message_type" value="voice" onchange="updateMessageType()">
                                        <span class="ptp-comms-badge success">VOICE</span>
                                        <span style="color: #666; font-size: 13px;">Phone call</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="ptp-comms-form-group">
                                <label>Use Template (Optional)</label>
                                <select name="template_id" class="ptp-comms-form-control" onchange="loadTemplate()">
                                    <option value="">— Select a template —</option>
                                    <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo $template->id; ?>" 
                                            data-content="<?php echo esc_attr($template->message); ?>"
                                            data-type="<?php echo esc_attr($template->message_type); ?>">
                                        <?php echo esc_html($template->name); ?> (<?php echo strtoupper($template->message_type); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="ptp-comms-form-group">
                                <label>Message Content *</label>
                                <textarea name="message_content" id="message-content" class="ptp-comms-form-control" 
                                          rows="6" required 
                                          placeholder="Hi {parent_first_name}, just a reminder that our spring soccer camp starts on {event_date}..."></textarea>
                                <span class="ptp-comms-form-help">
                                    Available variables: {parent_first_name}, {parent_last_name}, {child_name}, {child_age}, {event_name}, {event_date}
                                </span>
                                <div id="sms-counter" style="margin-top: 8px; font-size: 13px; color: #666;"></div>
                            </div>
                            
                            <div class="ptp-comms-form-group">
                                <label>Target Audience *</label>
                                <select name="audience" class="ptp-comms-form-control" required>
                                    <option value="all">All Opted-In Contacts (<?php echo number_format($total_contacts); ?>)</option>
                                    <option value="with_children">Only Contacts with Children</option>
                                    <option value="by_age">Filter by Child Age</option>
                                    <option value="by_zip">Filter by Zip Code</option>
                                </select>
                            </div>
                            
                            <div class="ptp-comms-form-group" id="age-filter" style="display: none;">
                                <label>Child Age Range</label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="number" name="age_min" class="ptp-comms-form-control" 
                                           placeholder="Min age" style="width: 100px;">
                                    <span>to</span>
                                    <input type="number" name="age_max" class="ptp-comms-form-control" 
                                           placeholder="Max age" style="width: 100px;">
                                </div>
                            </div>
                            
                            <div class="ptp-comms-form-group" id="zip-filter" style="display: none;">
                                <label>Zip Codes (comma separated)</label>
                                <input type="text" name="zip_codes" class="ptp-comms-form-control" 
                                       placeholder="19087, 19003, 19010">
                            </div>
                        </div>
                        
                        <!-- Right Column - Preview -->
                        <div>
                            <div class="ptp-comms-card" style="background: #f9f9f9; border: 1px solid #ddd;">
                                <h3 style="margin-top: 0;">Preview</h3>
                                <div class="ptp-message-preview-box">
                                    <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #ddd;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <span id="preview-type">SMS Message</span>
                                        </div>
                                        <div id="message-preview" style="line-height: 1.6; color: #0e0f11;">
                                            <em style="color: #999;">Type your message to see preview...</em>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 15px; padding: 15px; background: white; border-radius: 8px; border: 1px solid #ddd;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 8px;">Sample Variables:</div>
                                        <div style="font-size: 13px; color: #0e0f11; line-height: 1.8;">
                                            {parent_first_name} → <strong>Sarah</strong><br>
                                            {child_name} → <strong>Emma</strong><br>
                                            {event_date} → <strong>March 15, 2025</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f0f0f1; display: flex; gap: 15px;">
                        <button type="submit" name="create_campaign" class="ptp-comms-button" 
                                <?php echo !ptp_comms_is_twilio_configured() || $total_contacts == 0 ? 'disabled' : ''; ?>>
                            <span class="dashicons dashicons-yes" style="vertical-align: middle; margin-right: 5px;"></span>
                            Create & Send Campaign
                        </button>
                        <a href="?page=ptp-comms-campaigns" class="ptp-comms-button secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Message preview
            $('#message-content').on('input', function() {
                let message = $(this).val();
                if (message) {
                    // Replace variables with sample data
                    let preview = message
                        .replace(/{parent_first_name}/g, 'Sarah')
                        .replace(/{parent_last_name}/g, 'Johnson')
                        .replace(/{child_name}/g, 'Emma')
                        .replace(/{child_age}/g, '8')
                        .replace(/{event_name}/g, 'Spring Soccer Camp')
                        .replace(/{event_date}/g, 'March 15, 2025');
                    $('#message-preview').html(preview.replace(/\n/g, '<br>'));
                    
                    // SMS character count
                    let charCount = message.length;
                    let segmentCount = Math.ceil(charCount / 160);
                    $('#sms-counter').html(`<strong>${charCount}</strong> characters • <strong>${segmentCount}</strong> SMS segment${segmentCount !== 1 ? 's' : ''}`);
                } else {
                    $('#message-preview').html('<em style="color: #999;">Type your message to see preview...</em>');
                    $('#sms-counter').html('');
                }
            });
            
            // Audience filter
            $('select[name="audience"]').on('change', function() {
                $('#age-filter, #zip-filter').hide();
                if ($(this).val() === 'by_age') {
                    $('#age-filter').show();
                } else if ($(this).val() === 'by_zip') {
                    $('#zip-filter').show();
                }
            });
        });
        
        function updateMessageType() {
            const type = document.querySelector('input[name="message_type"]:checked').value;
            document.getElementById('preview-type').textContent = type.toUpperCase() + ' Message';
        }
        
        function loadTemplate() {
            const select = document.querySelector('select[name="template_id"]');
            const option = select.options[select.selectedIndex];
            if (option.value) {
                document.getElementById('message-content').value = option.dataset.content;
                document.querySelector('input[name="message_type"][value="' + option.dataset.type + '"]').checked = true;
                updateMessageType();
                jQuery('#message-content').trigger('input');
            }
        }
        </script>
        
        <style>
        .ptp-comms-form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        .ptp-comms-form-control:focus {
            outline: none;
            border-color: #FCB900;
            box-shadow: 0 0 0 1px #FCB900;
        }
        .ptp-comms-form-group {
            margin-bottom: 25px;
        }
        .ptp-comms-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0e0f11;
        }
        .ptp-comms-form-help {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: #666;
            font-style: italic;
        }
        </style>
        <?php
    }
    
    private static function render_campaign_details($campaign_id) {
        global $wpdb;
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign) {
            echo '<div class="wrap"><div class="ptp-comms-alert error">Campaign not found.</div></div>';
            return;
        }
        
        // Get campaign logs
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT cl.*, c.parent_first_name, c.parent_last_name, c.parent_phone
            FROM {$wpdb->prefix}ptp_communication_logs cl
            JOIN {$wpdb->prefix}ptp_contacts c ON cl.contact_id = c.id
            WHERE cl.campaign_id = %d
            ORDER BY cl.created_at DESC
            LIMIT 100
        ", $campaign_id));
        
        $success_count = count(array_filter($logs, function($l) { return $l->status === 'sent' || $l->status === 'delivered'; }));
        $failed_count = count(array_filter($logs, function($l) { return $l->status === 'failed'; }));
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-campaigns" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> Back to Campaigns
                </a>
            </div>
            
            <div class="ptp-comms-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 30px;">
                    <div>
                        <h1 style="margin: 0 0 10px 0; padding: 0; border: none;"><?php echo esc_html($campaign->name); ?></h1>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <span class="ptp-comms-badge <?php echo $campaign->message_type === 'sms' ? 'info' : 'success'; ?>">
                                <?php echo strtoupper($campaign->message_type); ?>
                            </span>
                            <?php 
                            $status_class = 'warning';
                            if ($campaign->status === 'completed') $status_class = 'success';
                            elseif ($campaign->status === 'failed') $status_class = 'error';
                            elseif ($campaign->status === 'sending') $status_class = 'info';
                            ?>
                            <span class="ptp-comms-badge <?php echo $status_class; ?>">
                                <?php echo ucfirst($campaign->status); ?>
                            </span>
                            <span style="color: #666; font-size: 14px;">
                                Created <?php echo date('M j, Y \a\t g:i A', strtotime($campaign->created_at)); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Stats -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: 700; color: #0e0f11;"><?php echo number_format($campaign->total_recipients); ?></div>
                        <div style="color: #666; font-size: 13px; margin-top: 5px;">Recipients</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #edf7ed; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: 700; color: #46b450;"><?php echo number_format($success_count); ?></div>
                        <div style="color: #666; font-size: 13px; margin-top: 5px;">Delivered</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fef2f2; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: 700; color: #dc3232;"><?php echo number_format($failed_count); ?></div>
                        <div style="color: #666; font-size: 13px; margin-top: 5px;">Failed</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                        <div style="font-size: 32px; font-weight: 700; color: #0e0f11;">
                            <?php echo $campaign->total_recipients > 0 ? round(($success_count / $campaign->total_recipients) * 100, 1) : 0; ?>%
                        </div>
                        <div style="color: #666; font-size: 13px; margin-top: 5px;">Success Rate</div>
                    </div>
                </div>
                
                <!-- Message Content -->
                <?php if ($campaign->message_preview): ?>
                <div style="margin-bottom: 30px;">
                    <h3>Message Content</h3>
                    <div class="ptp-comms-message-preview">
                        <?php echo nl2br(esc_html($campaign->message_preview)); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Delivery Log -->
                <h3>Delivery Log</h3>
                <?php if (empty($logs)): ?>
                <div class="ptp-comms-empty-state">
                    <p>No delivery logs available for this campaign.</p>
                </div>
                <?php else: ?>
                <table class="ptp-comms-table">
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Sent At</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($log->parent_first_name . ' ' . $log->parent_last_name); ?></strong><br>
                                <small style="color: #666;"><?php echo esc_html(ptp_comms_format_phone($log->parent_phone)); ?></small>
                            </td>
                            <td>
                                <?php 
                                $status_class = $log->status === 'sent' || $log->status === 'delivered' ? 'success' : 
                                              ($log->status === 'failed' ? 'error' : 'warning');
                                ?>
                                <span class="ptp-comms-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($log->status); ?>
                                </span>
                            </td>
                            <td>
                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo esc_html(substr($log->message_content, 0, 100)); ?>
                                </div>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($log->created_at)); ?></td>
                            <td>
                                <?php if ($log->error_message): ?>
                                <span style="color: #dc3232; font-size: 12px;">
                                    <?php echo esc_html($log->error_message); ?>
                                </span>
                                <?php else: ?>
                                <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($logs) >= 100): ?>
                <div style="text-align: center; margin-top: 15px; color: #666; font-size: 13px;">
                    Showing first 100 results. <a href="?page=ptp-comms-logs&campaign=<?php echo $campaign_id; ?>">View all logs</a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private static function handle_create_campaign() {
        global $wpdb;
        
        $campaign_name = sanitize_text_field($_POST['campaign_name']);
        $message_type = sanitize_text_field($_POST['message_type']);
        $message_content = sanitize_textarea_field($_POST['message_content']);
        $audience = sanitize_text_field($_POST['audience']);
        
        // Build audience query
        $where = "opted_in = 1 AND opted_out = 0";
        
        if ($audience === 'with_children') {
            $where .= " AND child_name IS NOT NULL AND child_name != ''";
        } elseif ($audience === 'by_age' && !empty($_POST['age_min']) && !empty($_POST['age_max'])) {
            $age_min = intval($_POST['age_min']);
            $age_max = intval($_POST['age_max']);
            $where .= $wpdb->prepare(" AND child_age BETWEEN %d AND %d", $age_min, $age_max);
        } elseif ($audience === 'by_zip' && !empty($_POST['zip_codes'])) {
            $zips = array_map('trim', explode(',', sanitize_text_field($_POST['zip_codes'])));
            $zip_placeholders = implode(',', array_fill(0, count($zips), '%s'));
            $where .= $wpdb->prepare(" AND zip_code IN ($zip_placeholders)", ...$zips);
        }
        
        // Get recipients
        $recipients = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE {$where}");
        
        if (empty($recipients)) {
            wp_redirect(add_query_arg(['page' => 'ptp-comms-campaigns', 'error' => 'no_recipients'], admin_url('admin.php')));
            exit;
        }
        
        // Create campaign
        $campaign_id = $wpdb->insert(
            $wpdb->prefix . 'ptp_campaigns',
            [
                'name' => $campaign_name,
                'message_type' => $message_type,
                'message_preview' => substr($message_content, 0, 200),
                'status' => 'sending',
                'total_recipients' => count($recipients),
                'sent_count' => 0
            ]
        );
        
        if (!$campaign_id) {
            wp_redirect(add_query_arg(['page' => 'ptp-comms-campaigns', 'error' => 'db_error'], admin_url('admin.php')));
            exit;
        }
        
        $campaign_id = $wpdb->insert_id;
        
        // Send messages
        $sent_count = 0;
        foreach ($recipients as $contact) {
            $personalized_message = ptp_comms_replace_variables($message_content, (array)$contact);
            
            if ($message_type === 'sms') {
                $result = PTP_Comms_Hub_SMS_Service::send_sms($contact->id, $personalized_message, ['campaign_id' => $campaign_id]);
            } else {
                $result = PTP_Comms_Hub_Voice_Service::make_call($contact->id, $personalized_message, ['campaign_id' => $campaign_id]);
            }
            
            if ($result['success']) {
                $sent_count++;
            }
        }
        
        // Update campaign
        $wpdb->update(
            $wpdb->prefix . 'ptp_campaigns',
            [
                'sent_count' => $sent_count,
                'status' => 'completed'
            ],
            ['id' => $campaign_id]
        );
        
        wp_redirect(add_query_arg(['page' => 'ptp-comms-campaigns', 'action' => 'view', 'id' => $campaign_id, 'success' => 1], admin_url('admin.php')));
        exit;
    }
}
