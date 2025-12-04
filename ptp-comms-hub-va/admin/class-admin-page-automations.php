<?php
/**
 * PTP Communications Hub - Automations Admin Page
 * v3.4.0 - Comprehensive redesign with visual workflow builder
 */
class PTP_Comms_Hub_Admin_Page_Automations {
    
    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['ptp_comms_automation_nonce'])) {
                check_admin_referer('ptp_comms_automation', 'ptp_comms_automation_nonce');
                self::handle_save();
            } elseif (isset($_POST['test_automation'])) {
                check_admin_referer('ptp_test_automation', 'ptp_test_nonce');
                self::handle_test();
            }
        }
        
        switch ($action) {
            case 'new':
                self::render_form();
                break;
            case 'edit':
                self::render_form(isset($_GET['id']) ? intval($_GET['id']) : 0);
                break;
            case 'toggle':
                self::handle_toggle();
                break;
            case 'delete':
                self::handle_delete();
                break;
            default:
                self::render_list($message);
        }
    }
    
    private static function render_list($message = '') {
        global $wpdb;
        
        $automations = PTP_Comms_Hub_Automations::get_all_automations();
        
        // Get stats
        $total_automations = count($automations);
        $active_count = count(array_filter($automations, function($a) { return $a->is_active; }));
        $total_executions = array_sum(array_column($automations, 'execution_count'));
        
        // Get executions today
        $today = current_time('Y-m-d');
        $executions_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs 
             WHERE DATE(created_at) = %s 
             AND message_content LIKE '%automation%'",
            $today
        )) ?: 0;
        
        // Group automations by trigger category
        $trigger_groups = array(
            'order' => array('label' => 'Order & Registration', 'triggers' => array('order_placed', 'new_contact'), 'automations' => array()),
            'reminders' => array('label' => 'Event Reminders', 'triggers' => array('event_approaching_7d', 'event_approaching_3d', 'event_approaching_1d'), 'automations' => array()),
            'post_event' => array('label' => 'Post-Event', 'triggers' => array('event_completed', 'event_followup_7d'), 'automations' => array()),
            'marketing' => array('label' => 'Upsell & Marketing', 'triggers' => array('clinic_no_camp_purchase', 'promo_window'), 'automations' => array())
        );
        
        foreach ($automations as $automation) {
            foreach ($trigger_groups as $key => &$group) {
                if (in_array($automation->trigger_type, $group['triggers'])) {
                    $group['automations'][] = $automation;
                    break;
                }
            }
        }
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <span class="dashicons dashicons-controls-repeat" style="color: #FCB900; font-size: 28px;"></span>
                        Automations
                    </h1>
                    <p style="margin: 5px 0 0; color: #666;">Automatically send messages based on triggers and conditions</p>
                </div>
                <a href="?page=ptp-comms-automations&action=new" class="ptp-comms-button">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                    Create Automation
                </a>
            </div>
            
            <?php if ($message === 'saved'): ?>
            <div class="notice notice-success is-dismissible"><p>Automation saved successfully!</p></div>
            <?php elseif ($message === 'deleted'): ?>
            <div class="notice notice-success is-dismissible"><p>Automation deleted.</p></div>
            <?php elseif ($message === 'tested'): ?>
            <div class="notice notice-success is-dismissible"><p>Test message sent! Check your phone.</p></div>
            <?php elseif ($message === 'test_failed'): ?>
            <div class="notice notice-error is-dismissible"><p>Test failed. Check Twilio configuration.</p></div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="ptp-comms-stats">
                <div class="ptp-comms-stat-box">
                    <h2><?php echo number_format($total_automations); ?></h2>
                    <p>Total Automations</p>
                </div>
                <div class="ptp-comms-stat-box green">
                    <h2><?php echo number_format($active_count); ?></h2>
                    <p>Active</p>
                </div>
                <div class="ptp-comms-stat-box blue">
                    <h2><?php echo number_format($total_executions); ?></h2>
                    <p>Total Executions</p>
                </div>
                <div class="ptp-comms-stat-box yellow">
                    <h2><?php echo number_format($executions_today); ?></h2>
                    <p>Sent Today</p>
                </div>
            </div>
            
            <?php if (empty($automations)): ?>
            <!-- Empty State -->
            <div class="ptp-comms-card">
                <div style="text-align: center; padding: 60px 20px;">
                    <span class="dashicons dashicons-controls-repeat" style="font-size: 80px; opacity: 0.15; color: #FCB900;"></span>
                    <h2>No Automations Yet</h2>
                    <p style="color: #666; max-width: 500px; margin: 10px auto 20px;">
                        Automations let you automatically send SMS messages when certain events happen, 
                        like order confirmations, event reminders, and follow-up messages.
                    </p>
                    <a href="?page=ptp-comms-automations&action=new" class="ptp-comms-button">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Create Your First Automation
                    </a>
                    
                    <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #e2e4e7;">
                        <h3 style="margin-bottom: 20px;">Quick Start Templates</h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; max-width: 800px; margin: 0 auto;">
                            <?php self::render_quick_start_card('Order Confirmation', 'Send immediate confirmation when order is placed', 'order_placed', 'cart'); ?>
                            <?php self::render_quick_start_card('7-Day Reminder', 'Remind parents one week before camp starts', 'event_approaching_7d', 'calendar-alt'); ?>
                            <?php self::render_quick_start_card('Post-Camp Follow-up', 'Thank parents and upsell next camp', 'event_followup_7d', 'star-filled'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            
            <!-- Automation Groups -->
            <?php foreach ($trigger_groups as $group_key => $group): ?>
            <?php if (!empty($group['automations'])): ?>
            <div class="ptp-comms-card" style="margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f1; margin-bottom: 15px;">
                    <span class="dashicons dashicons-<?php echo self::get_group_icon($group_key); ?>" style="color: #FCB900; font-size: 20px;"></span>
                    <h3 style="margin: 0;"><?php echo esc_html($group['label']); ?></h3>
                    <span class="ptp-comms-badge secondary"><?php echo count($group['automations']); ?></span>
                </div>
                
                <div class="ptp-automation-list">
                    <?php foreach ($group['automations'] as $automation): ?>
                    <div class="ptp-automation-item <?php echo $automation->is_active ? 'active' : 'inactive'; ?>">
                        <div class="ptp-automation-status">
                            <?php if ($automation->is_active): ?>
                            <span class="ptp-status-dot active" title="Active"></span>
                            <?php else: ?>
                            <span class="ptp-status-dot inactive" title="Inactive"></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ptp-automation-main">
                            <div class="ptp-automation-name">
                                <strong><?php echo esc_html($automation->name); ?></strong>
                            </div>
                            <div class="ptp-automation-meta">
                                <span class="ptp-automation-trigger">
                                    <span class="dashicons dashicons-flag" style="font-size: 14px;"></span>
                                    <?php echo esc_html(self::format_trigger_type($automation->trigger_type)); ?>
                                </span>
                                <?php if ($automation->template_name): ?>
                                <span class="ptp-automation-template">
                                    <span class="dashicons dashicons-media-text" style="font-size: 14px;"></span>
                                    <?php echo esc_html($automation->template_name); ?>
                                </span>
                                <?php endif; ?>
                                <span class="ptp-automation-delay">
                                    <span class="dashicons dashicons-clock" style="font-size: 14px;"></span>
                                    <?php echo esc_html(self::format_delay($automation->delay_minutes)); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="ptp-automation-stats">
                            <div class="ptp-stat-item">
                                <span class="ptp-stat-value"><?php echo number_format($automation->execution_count); ?></span>
                                <span class="ptp-stat-label">Sent</span>
                            </div>
                        </div>
                        
                        <div class="ptp-automation-actions">
                            <a href="?page=ptp-comms-automations&action=edit&id=<?php echo $automation->id; ?>" class="button button-small" title="Edit">
                                <span class="dashicons dashicons-edit" style="font-size: 14px; vertical-align: middle;"></span>
                            </a>
                            <a href="?page=ptp-comms-automations&action=toggle&id=<?php echo $automation->id; ?>&_wpnonce=<?php echo wp_create_nonce('toggle_automation_' . $automation->id); ?>" class="button button-small" title="<?php echo $automation->is_active ? 'Pause' : 'Activate'; ?>">
                                <span class="dashicons dashicons-<?php echo $automation->is_active ? 'controls-pause' : 'controls-play'; ?>" style="font-size: 14px; vertical-align: middle;"></span>
                            </a>
                            <a href="?page=ptp-comms-automations&action=delete&id=<?php echo $automation->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_automation_' . $automation->id); ?>" class="button button-small" onclick="return confirm('Delete this automation?');" title="Delete" style="color: #dc3232;">
                                <span class="dashicons dashicons-trash" style="font-size: 14px; vertical-align: middle;"></span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
            
            <?php endif; ?>
            
            <!-- How Automations Work -->
            <div class="ptp-comms-card" style="background: #f8f9fa;">
                <h3 style="margin-top: 0;"><span class="dashicons dashicons-info" style="color: #FCB900;"></span> How Automations Work</h3>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 15px;">
                    <div style="text-align: center;">
                        <div style="background: #FCB900; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                            <span class="dashicons dashicons-flag" style="color: #000; font-size: 24px;"></span>
                        </div>
                        <strong>1. Trigger</strong>
                        <p style="font-size: 13px; color: #666; margin: 5px 0 0;">Event occurs (order, reminder)</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="background: #FCB900; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                            <span class="dashicons dashicons-filter" style="color: #000; font-size: 24px;"></span>
                        </div>
                        <strong>2. Conditions</strong>
                        <p style="font-size: 13px; color: #666; margin: 5px 0 0;">Check criteria (market, type)</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="background: #FCB900; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                            <span class="dashicons dashicons-clock" style="color: #000; font-size: 24px;"></span>
                        </div>
                        <strong>3. Delay</strong>
                        <p style="font-size: 13px; color: #666; margin: 5px 0 0;">Wait if specified</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="background: #FCB900; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                            <span class="dashicons dashicons-email-alt" style="color: #000; font-size: 24px;"></span>
                        </div>
                        <strong>4. Send</strong>
                        <p style="font-size: 13px; color: #666; margin: 5px 0 0;">Message delivered</p>
                    </div>
                </div>
            </div>
        </div>
        <?php self::render_list_styles(); ?>
        <?php
    }
    
    private static function render_quick_start_card($name, $description, $trigger, $icon) {
        ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
            <span class="dashicons dashicons-<?php echo $icon; ?>" style="font-size: 32px; color: #FCB900; margin-bottom: 10px;"></span>
            <h4 style="margin: 10px 0 5px;"><?php echo esc_html($name); ?></h4>
            <p style="font-size: 13px; color: #666; margin: 0 0 15px;"><?php echo esc_html($description); ?></p>
            <a href="?page=ptp-comms-automations&action=new&trigger=<?php echo $trigger; ?>&name=<?php echo urlencode($name); ?>" class="button button-small">Create</a>
        </div>
        <?php
    }
    
    private static function render_form($automation_id = 0) {
        global $wpdb;
        
        $automation = null;
        $is_edit = false;
        
        if ($automation_id > 0) {
            $automation = PTP_Comms_Hub_Automations::get_automation($automation_id);
            $is_edit = true;
        }
        
        $prefill_trigger = isset($_GET['trigger']) ? sanitize_text_field($_GET['trigger']) : '';
        $prefill_name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';
        
        $templates = PTP_Comms_Hub_Templates::get_all_templates();
        
        $conditions = array();
        if ($automation && $automation->conditions) {
            $conditions = maybe_unserialize($automation->conditions);
        }
        
        $markets = array('PA' => 'Pennsylvania', 'NJ' => 'New Jersey', 'DE' => 'Delaware', 'MD' => 'Maryland', 'NY' => 'New York');
        $program_types = array('half_day' => 'Half Day Camp', 'full_day' => 'Full Day Camp', 'week_camp' => 'Week Camp', 'clinic' => 'Clinic');
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-automations" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> Back to Automations
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <!-- Main Form -->
                <div class="ptp-comms-card">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span class="dashicons dashicons-<?php echo $is_edit ? 'edit' : 'plus-alt'; ?>" style="color: #FCB900;"></span>
                        <?php echo $is_edit ? 'Edit Automation' : 'Create New Automation'; ?>
                    </h2>
                    
                    <form method="post" id="automation-form">
                        <?php wp_nonce_field('ptp_comms_automation', 'ptp_comms_automation_nonce'); ?>
                        <?php if ($is_edit): ?>
                        <input type="hidden" name="automation_id" value="<?php echo $automation_id; ?>">
                        <?php endif; ?>
                        
                        <!-- Basic Info -->
                        <div class="ptp-form-section">
                            <h3><span class="ptp-section-number">1</span> Basic Information</h3>
                            <div class="ptp-form-row">
                                <label for="name">Automation Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo esc_attr($automation->name ?? $prefill_name); ?>" class="regular-text" placeholder="e.g., 7-Day Camp Reminder" required>
                                <p class="description">A descriptive name to identify this automation</p>
                            </div>
                        </div>
                        
                        <!-- Trigger -->
                        <div class="ptp-form-section">
                            <h3><span class="ptp-section-number">2</span> Trigger</h3>
                            <div class="ptp-form-row">
                                <label for="trigger_type">When should this run? *</label>
                                <select id="trigger_type" name="trigger_type" class="regular-text" required>
                                    <option value="">-- Select Trigger --</option>
                                    <optgroup label="📦 Order & Registration">
                                        <option value="order_placed" <?php selected(($automation->trigger_type ?? $prefill_trigger), 'order_placed'); ?>>Order Placed (Confirmation)</option>
                                        <option value="new_contact" <?php selected(($automation->trigger_type ?? $prefill_trigger), 'new_contact'); ?>>New Contact Created</option>
                                    </optgroup>
                                    <optgroup label="📅 Event Reminders">
                                        <option value="event_approaching_7d" <?php selected(($automation->trigger_type ?? $prefill_trigger), 'event_approaching_7d'); ?>>7 Days Before Event</option>
                                        <option value="event_approaching_3d" <?php selected(($automation->trigger_type ?? $prefill_trigger), 'event_approaching_3d'); ?>>3 Days Before Event</option>
                                        <option value="event_approaching_1d" <?php selected(($automation->trigger_type ?? $prefill_trigger), 'event_approaching_1d'); ?>>1 Day Before Event</option>
                                    </optgroup>
                                    <optgroup label="✅ Post-Event">
                                        <option value="event_completed" <?php selected(($automation->trigger_type ?? $prefill_trigger), 'event_completed'); ?>>Day After Event (Thank You)</option>
                                        <option value="event_followup_7d" <?php selected(($automation->trigger_type ?? $prefill_trigger), 'event_followup_7d'); ?>>7 Days After Event (Upsell)</option>
                                    </optgroup>
                                    <optgroup label="📢 Marketing & Upsell">
                                        <option value="clinic_no_camp_purchase" <?php selected(($automation->trigger_type ?? $prefill_trigger), 'clinic_no_camp_purchase'); ?>>Clinic Attendee - No Camp Yet</option>
                                        <option value="promo_window" <?php selected(($automation->trigger_type ?? $prefill_trigger), 'promo_window'); ?>>Promotional Date Window</option>
                                    </optgroup>
                                </select>
                                <p class="description" id="trigger-description">Select the event that triggers this automation</p>
                            </div>
                            
                            <div id="date-window-fields" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px;">
                                <h4 style="margin-top: 0;">Date Window Settings</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <label for="window_start_date">Start Date</label>
                                        <input type="date" id="window_start_date" name="conditions[start_date]" value="<?php echo esc_attr($conditions['start_date'] ?? ''); ?>" class="regular-text">
                                    </div>
                                    <div>
                                        <label for="window_end_date">End Date</label>
                                        <input type="date" id="window_end_date" name="conditions[end_date]" value="<?php echo esc_attr($conditions['end_date'] ?? ''); ?>" class="regular-text">
                                    </div>
                                </div>
                                <p class="description">Messages will only be sent during this date range.</p>
                            </div>
                        </div>
                        
                        <!-- Conditions -->
                        <div class="ptp-form-section">
                            <h3><span class="ptp-section-number">3</span> Conditions <span style="font-weight: normal; color: #666; font-size: 14px;">(Optional)</span></h3>
                            <p style="color: #666; margin-bottom: 15px;">Only send to contacts that match these criteria:</p>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="ptp-form-row">
                                    <label for="condition_market">Market / Region</label>
                                    <select id="condition_market" name="conditions[market]">
                                        <option value="">Any Market</option>
                                        <?php foreach ($markets as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php selected($conditions['market'] ?? '', $code); ?>><?php echo esc_html($name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ptp-form-row">
                                    <label for="condition_program">Program Type</label>
                                    <select id="condition_program" name="conditions[program_type]">
                                        <option value="">Any Program</option>
                                        <?php foreach ($program_types as $type => $name): ?>
                                        <option value="<?php echo $type; ?>" <?php selected($conditions['program_type'] ?? '', $type); ?>><?php echo esc_html($name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Message -->
                        <div class="ptp-form-section">
                            <h3><span class="ptp-section-number">4</span> Message</h3>
                            <div class="ptp-form-row">
                                <label for="template_id">Message Template *</label>
                                <select id="template_id" name="template_id" class="regular-text" required>
                                    <option value="">-- Select Template --</option>
                                    <?php 
                                    $template_categories = array();
                                    foreach ($templates as $template) {
                                        $cat = ucfirst($template->category ?: 'general');
                                        $template_categories[$cat][] = $template;
                                    }
                                    foreach ($template_categories as $cat => $cat_templates): ?>
                                    <optgroup label="<?php echo esc_attr($cat); ?>">
                                        <?php foreach ($cat_templates as $template): ?>
                                        <option value="<?php echo $template->id; ?>" data-content="<?php echo esc_attr($template->content); ?>" <?php selected($automation->template_id ?? '', $template->id); ?>>
                                            <?php echo esc_html($template->name); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><a href="?page=ptp-comms-templates&action=new" target="_blank">Create new template</a></p>
                            </div>
                            <div id="template-preview" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px;">
                                <strong>Preview:</strong>
                                <div id="preview-content" style="margin-top: 10px; font-family: monospace; white-space: pre-wrap; background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></div>
                            </div>
                        </div>
                        
                        <!-- Timing -->
                        <div class="ptp-form-section">
                            <h3><span class="ptp-section-number">5</span> Timing</h3>
                            <div class="ptp-form-row">
                                <label for="delay_minutes">Delay After Trigger</label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="number" id="delay_minutes" name="delay_minutes" value="<?php echo esc_attr($automation->delay_minutes ?? 0); ?>" min="0" max="10080" class="small-text">
                                    <span>minutes</span>
                                    <span style="color: #666;">(0 = immediate)</span>
                                </div>
                                <p class="description">Common delays: 5 min, 60 min (1 hour), 1440 min (1 day)</p>
                            </div>
                            <div class="ptp-form-row" style="margin-top: 15px;">
                                <label>
                                    <input type="checkbox" name="is_active" value="1" <?php checked($automation->is_active ?? 1, 1); ?>>
                                    <strong>Active</strong> - Automation will run when triggered
                                </label>
                            </div>
                        </div>
                        
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f0f0f1; display: flex; gap: 10px;">
                            <button type="submit" name="save_automation" class="ptp-comms-button">
                                <span class="dashicons dashicons-saved" style="vertical-align: middle;"></span>
                                <?php echo $is_edit ? 'Update Automation' : 'Create Automation'; ?>
                            </button>
                            <a href="?page=ptp-comms-automations" class="ptp-comms-button secondary">Cancel</a>
                        </div>
                    </form>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <?php if ($is_edit && $automation): ?>
                    <!-- Test Automation -->
                    <div class="ptp-comms-card" style="margin-bottom: 20px;">
                        <h3 style="margin-top: 0;"><span class="dashicons dashicons-visibility" style="color: #FCB900;"></span> Test Automation</h3>
                        <p style="color: #666; font-size: 13px;">Send a test message to verify this works.</p>
                        <form method="post">
                            <?php wp_nonce_field('ptp_test_automation', 'ptp_test_nonce'); ?>
                            <input type="hidden" name="automation_id" value="<?php echo $automation_id; ?>">
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Phone Number</label>
                                <input type="tel" name="test_phone" placeholder="+1234567890" class="regular-text" style="width: 100%;">
                            </div>
                            <button type="submit" name="test_automation" class="button" style="width: 100%;">
                                <span class="dashicons dashicons-smartphone" style="vertical-align: middle;"></span> Send Test
                            </button>
                        </form>
                    </div>
                    
                    <!-- Stats -->
                    <div class="ptp-comms-card" style="margin-bottom: 20px;">
                        <h3 style="margin-top: 0;"><span class="dashicons dashicons-chart-bar" style="color: #FCB900;"></span> Statistics</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: center;">
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                                <div style="font-size: 24px; font-weight: 600;"><?php echo number_format($automation->execution_count); ?></div>
                                <div style="font-size: 12px; color: #666;">Total Sent</div>
                            </div>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                                <div style="font-size: 24px; font-weight: 600; color: <?php echo $automation->is_active ? '#46b450' : '#dc3232'; ?>;">
                                    <?php echo $automation->is_active ? 'ON' : 'OFF'; ?>
                                </div>
                                <div style="font-size: 12px; color: #666;">Status</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Variables Reference -->
                    <div class="ptp-comms-card">
                        <h3 style="margin-top: 0;"><span class="dashicons dashicons-code-standards" style="color: #FCB900;"></span> Template Variables</h3>
                        <p style="color: #666; font-size: 13px; margin-bottom: 10px;">Use these in your templates:</p>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; font-size: 12px; font-family: monospace;">
                            <div style="margin-bottom: 5px;"><strong>Contact:</strong></div>
                            {parent_name}, {child_name}, {child_age}
                            <div style="margin: 10px 0 5px;"><strong>Event:</strong></div>
                            {event_name}, {event_date}, {event_location}, {camp_time}
                            <div style="margin: 10px 0 5px;"><strong>Details:</strong></div>
                            {what_to_bring}, {head_coach}, {maps_link}
                        </div>
                        <p style="margin-top: 10px;"><a href="?page=ptp-comms-templates" target="_blank" style="font-size: 13px;">View all variables →</a></p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php self::render_form_styles(); ?>
        
        <script>
        jQuery(function($) {
            $('#trigger_type').on('change', function() {
                var trigger = $(this).val();
                if (trigger === 'promo_window') {
                    $('#date-window-fields').slideDown();
                } else {
                    $('#date-window-fields').slideUp();
                }
                var descriptions = {
                    'order_placed': 'Sends immediately when a WooCommerce order is marked complete',
                    'new_contact': 'Sends when a new contact is created in the system',
                    'event_approaching_7d': 'Sends 7 days before the registered event date',
                    'event_approaching_3d': 'Sends 3 days before the registered event date',
                    'event_approaching_1d': 'Sends 1 day before the registered event date',
                    'event_completed': 'Sends the day after the event ends',
                    'event_followup_7d': 'Sends 7 days after event - great for upselling',
                    'clinic_no_camp_purchase': 'Sends to clinic attendees without camp registration',
                    'promo_window': 'Sends during a specific date range'
                };
                $('#trigger-description').text(descriptions[trigger] || 'Select the event that triggers this automation');
            }).trigger('change');
            
            $('#template_id').on('change', function() {
                var content = $(this).find(':selected').data('content');
                if (content) {
                    $('#preview-content').text(content);
                    $('#template-preview').slideDown();
                } else {
                    $('#template-preview').slideUp();
                }
            }).trigger('change');
        });
        </script>
        <?php
    }
    
    private static function handle_save() {
        $automation_id = isset($_POST['automation_id']) ? intval($_POST['automation_id']) : 0;
        
        $conditions = array();
        if (!empty($_POST['conditions'])) {
            foreach ($_POST['conditions'] as $key => $value) {
                $value = sanitize_text_field($value);
                if (!empty($value)) {
                    $conditions[$key] = $value;
                }
            }
        }
        
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'trigger_type' => sanitize_text_field($_POST['trigger_type']),
            'template_id' => intval($_POST['template_id']),
            'delay_minutes' => intval($_POST['delay_minutes']),
            'conditions' => !empty($conditions) ? maybe_serialize($conditions) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        if ($automation_id > 0) {
            PTP_Comms_Hub_Automations::update_automation($automation_id, $data);
        } else {
            PTP_Comms_Hub_Automations::create_automation($data);
        }
        
        wp_safe_redirect(admin_url('admin.php?page=ptp-comms-automations&message=saved'));
        exit;
    }
    
    private static function handle_test() {
        $automation_id = isset($_POST['automation_id']) ? intval($_POST['automation_id']) : 0;
        $test_phone = isset($_POST['test_phone']) ? sanitize_text_field($_POST['test_phone']) : '';
        
        if (!$automation_id || !$test_phone) {
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-automations&action=edit&id=' . $automation_id . '&message=test_failed'));
            exit;
        }
        
        global $wpdb;
        $automation = PTP_Comms_Hub_Automations::get_automation($automation_id);
        if (!$automation || !$automation->template_id) {
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-automations&action=edit&id=' . $automation_id . '&message=test_failed'));
            exit;
        }
        
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ptp_templates WHERE id = %d", $automation->template_id));
        if (!$template) {
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-automations&action=edit&id=' . $automation_id . '&message=test_failed'));
            exit;
        }
        
        $sample_contact = array('parent_first_name' => 'Test', 'parent_last_name' => 'Parent', 'child_name' => 'Sample Camper', 'child_age' => '8');
        $sample_event = array('event_name' => 'Summer Soccer Camp', 'event_date' => date('Y-m-d', strtotime('+7 days')), 'event_location' => 'PTP Training Center', 'camp_time' => '9:00 AM - 12:00 PM', 'what_to_bring' => 'Cleats, shin guards, water bottle', 'head_coach' => 'Coach Mike');
        
        $message = ptp_comms_replace_variables($template->content, $sample_contact, $sample_event);
        $message = "[TEST] " . $message;
        
        $sms_service = new PTP_Comms_Hub_SMS_Service();
        $result = $sms_service->send_sms($test_phone, $message);
        
        if ($result && !empty($result['success'])) {
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-automations&action=edit&id=' . $automation_id . '&message=tested'));
        } else {
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-automations&action=edit&id=' . $automation_id . '&message=test_failed'));
        }
        exit;
    }
    
    private static function handle_toggle() {
        $automation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!wp_verify_nonce($_GET['_wpnonce'], 'toggle_automation_' . $automation_id)) {
            wp_die('Invalid nonce');
        }
        PTP_Comms_Hub_Automations::toggle_automation($automation_id);
        wp_safe_redirect(admin_url('admin.php?page=ptp-comms-automations'));
        exit;
    }
    
    private static function handle_delete() {
        $automation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_automation_' . $automation_id)) {
            wp_die('Invalid nonce');
        }
        PTP_Comms_Hub_Automations::delete_automation($automation_id);
        wp_safe_redirect(admin_url('admin.php?page=ptp-comms-automations&message=deleted'));
        exit;
    }
    
    private static function format_trigger_type($type) {
        $types = PTP_Comms_Hub_Automations::get_trigger_types();
        return $types[$type] ?? $type;
    }
    
    private static function format_delay($minutes) {
        if ($minutes == 0) return 'Immediate';
        if ($minutes < 60) return $minutes . ' min';
        if ($minutes < 1440) return round($minutes / 60, 1) . ' hrs';
        return round($minutes / 1440, 1) . ' days';
    }
    
    private static function get_group_icon($group) {
        $icons = array('order' => 'cart', 'reminders' => 'calendar-alt', 'post_event' => 'yes-alt', 'marketing' => 'megaphone');
        return $icons[$group] ?? 'admin-generic';
    }
    
    private static function render_list_styles() {
        ?>
        <style>
        .ptp-automation-list { display: flex; flex-direction: column; gap: 10px; }
        .ptp-automation-item { display: flex; align-items: center; gap: 15px; padding: 15px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; transition: all 0.2s ease; }
        .ptp-automation-item:hover { border-color: #FCB900; box-shadow: 0 2px 8px rgba(252, 185, 0, 0.1); }
        .ptp-automation-item.inactive { opacity: 0.6; background: #f8f9fa; }
        .ptp-automation-status { flex-shrink: 0; }
        .ptp-status-dot { display: block; width: 12px; height: 12px; border-radius: 50%; }
        .ptp-status-dot.active { background: #46b450; box-shadow: 0 0 0 3px rgba(70, 180, 80, 0.2); }
        .ptp-status-dot.inactive { background: #dc3232; }
        .ptp-automation-main { flex-grow: 1; }
        .ptp-automation-name { margin-bottom: 5px; }
        .ptp-automation-meta { display: flex; gap: 15px; flex-wrap: wrap; }
        .ptp-automation-meta > span { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; color: #666; }
        .ptp-automation-stats { display: flex; gap: 20px; }
        .ptp-stat-item { text-align: center; padding: 0 15px; }
        .ptp-stat-value { display: block; font-size: 20px; font-weight: 600; color: #1d2327; }
        .ptp-stat-label { font-size: 11px; color: #666; text-transform: uppercase; }
        .ptp-automation-actions { display: flex; gap: 5px; }
        .ptp-automation-actions .button { padding: 4px 8px; }
        .ptp-comms-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 500; }
        .ptp-comms-badge.secondary { background: #e2e3e5; color: #383d41; }
        </style>
        <?php
    }
    
    private static function render_form_styles() {
        ?>
        <style>
        .ptp-form-section { margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #f0f0f1; }
        .ptp-form-section:last-of-type { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .ptp-form-section h3 { display: flex; align-items: center; gap: 10px; margin: 0 0 20px; font-size: 16px; }
        .ptp-section-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #FCB900; color: #000; border-radius: 50%; font-size: 14px; font-weight: 600; }
        .ptp-form-row { margin-bottom: 15px; }
        .ptp-form-row label { display: block; font-weight: 600; margin-bottom: 5px; }
        .ptp-form-row input[type="text"], .ptp-form-row input[type="number"], .ptp-form-row input[type="tel"], .ptp-form-row input[type="date"], .ptp-form-row select { width: 100%; max-width: 400px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; }
        .ptp-form-row input:focus, .ptp-form-row select:focus { border-color: #FCB900; outline: none; box-shadow: 0 0 0 2px rgba(252, 185, 0, 0.2); }
        .ptp-form-row .description { margin-top: 5px; color: #666; font-size: 13px; }
        </style>
        <?php
    }
}
