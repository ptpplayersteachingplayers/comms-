<?php
/**
 * PTP Comms Hub - Reminders Admin Page
 * v4.0.0 - VA Task Management
 */
class PTP_Comms_Hub_Admin_Page_Reminders {
    
    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        // Handle POST submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handle_post_request();
        }
        
        switch ($action) {
            case 'new':
            case 'edit':
                self::render_form($action === 'edit' ? intval($_GET['id'] ?? 0) : 0);
                break;
            case 'view':
                self::render_detail(intval($_GET['id'] ?? 0));
                break;
            default:
                self::render_list();
                break;
        }
    }
    
    private static function handle_post_request() {
        if (!isset($_POST['_wpnonce'])) return;
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ptp_comms_reminder_action')) {
            wp_die('Security check failed.');
        }
        
        if (isset($_POST['save_reminder'])) {
            self::handle_save_reminder();
        } elseif (isset($_POST['complete_reminder'])) {
            PTP_Comms_Hub_Reminders::complete(intval($_POST['reminder_id']));
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-reminders&message=completed'));
            exit;
        } elseif (isset($_POST['snooze_reminder'])) {
            $snooze_until = sanitize_text_field($_POST['snooze_until']);
            PTP_Comms_Hub_Reminders::snooze(intval($_POST['reminder_id']), $snooze_until);
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-reminders&message=snoozed'));
            exit;
        } elseif (isset($_POST['delete_reminder'])) {
            PTP_Comms_Hub_Reminders::delete(intval($_POST['reminder_id']));
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-reminders&message=deleted'));
            exit;
        }
    }
    
    private static function handle_save_reminder() {
        $reminder_id = intval($_POST['reminder_id'] ?? 0);
        
        $data = array(
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'reminder_type' => sanitize_text_field($_POST['reminder_type'] ?? 'follow_up'),
            'priority' => sanitize_text_field($_POST['priority'] ?? 'normal'),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? '') . ' ' . sanitize_text_field($_POST['due_time'] ?? '09:00') . ':00',
            'assigned_to' => intval($_POST['assigned_to'] ?? get_current_user_id()),
            'contact_id' => !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null,
            'notification_method' => sanitize_text_field($_POST['notification_method'] ?? 'email'),
            'recurring' => sanitize_text_field($_POST['recurring'] ?? 'none'),
            'recurring_end_date' => !empty($_POST['recurring_end_date']) ? sanitize_text_field($_POST['recurring_end_date']) : null
        );
        
        if ($reminder_id > 0) {
            PTP_Comms_Hub_Reminders::update($reminder_id, $data);
            $message = 'updated';
        } else {
            PTP_Comms_Hub_Reminders::create($data);
            $message = 'created';
        }
        
        wp_safe_redirect(admin_url('admin.php?page=ptp-comms-reminders&message=' . $message));
        exit;
    }
    
    private static function render_list() {
        $current_user_id = get_current_user_id();
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'my';
        
        $counts = PTP_Comms_Hub_Reminders::get_counts($filter === 'my' ? $current_user_id : null);
        
        // Get reminders based on filter
        $args = array('limit' => 100);
        
        switch ($filter) {
            case 'overdue':
                $args['status'] = 'pending';
                $args['overdue_only'] = true;
                break;
            case 'today':
                $args['status'] = 'pending';
                $args['due_from'] = date('Y-m-d 00:00:00');
                $args['due_to'] = date('Y-m-d 23:59:59');
                break;
            case 'upcoming':
                $args['status'] = 'pending';
                $args['due_from'] = date('Y-m-d H:i:s');
                $args['due_to'] = date('Y-m-d 23:59:59', strtotime('+7 days'));
                break;
            case 'completed':
                $args['status'] = 'completed';
                break;
            case 'all':
                // No additional filters
                break;
            case 'my':
            default:
                $args['assigned_to'] = $current_user_id;
                $args['status'] = 'pending';
                break;
        }
        
        $reminders = PTP_Comms_Hub_Reminders::get_reminders($args);
        $reminder_types = PTP_Comms_Hub_Reminders::get_reminder_types();
        $priorities = PTP_Comms_Hub_Reminders::get_priorities();
        
        // Get message
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1>Reminders & Tasks</h1>
                <a href="?page=ptp-comms-reminders&action=new" class="ptp-comms-button">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span> New Reminder
                </a>
            </div>
            
            <?php if ($message): ?>
            <div class="notice notice-success is-dismissible">
                <p>Reminder <?php echo esc_html($message); ?> successfully!</p>
            </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="ptp-comms-stats">
                <div class="ptp-comms-stat-box <?php echo $counts['overdue'] > 0 ? 'red' : ''; ?>">
                    <h2><?php echo number_format($counts['overdue']); ?></h2>
                    <p>Overdue</p>
                </div>
                <div class="ptp-comms-stat-box orange">
                    <h2><?php echo number_format($counts['today']); ?></h2>
                    <p>Due Today</p>
                </div>
                <div class="ptp-comms-stat-box blue">
                    <h2><?php echo number_format($counts['upcoming']); ?></h2>
                    <p>Upcoming (7 days)</p>
                </div>
                <div class="ptp-comms-stat-box green">
                    <h2><?php echo number_format($counts['completed_this_week']); ?></h2>
                    <p>Completed This Week</p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="ptp-comms-card" style="margin-bottom: 20px; padding: 15px;">
                <div class="ptp-comms-filters">
                    <a href="?page=ptp-comms-reminders&filter=my" class="ptp-comms-button small <?php echo $filter === 'my' ? '' : 'secondary'; ?>">My Reminders</a>
                    <a href="?page=ptp-comms-reminders&filter=overdue" class="ptp-comms-button small <?php echo $filter === 'overdue' ? '' : 'secondary'; ?>">Overdue</a>
                    <a href="?page=ptp-comms-reminders&filter=today" class="ptp-comms-button small <?php echo $filter === 'today' ? '' : 'secondary'; ?>">Today</a>
                    <a href="?page=ptp-comms-reminders&filter=upcoming" class="ptp-comms-button small <?php echo $filter === 'upcoming' ? '' : 'secondary'; ?>">Upcoming</a>
                    <a href="?page=ptp-comms-reminders&filter=completed" class="ptp-comms-button small <?php echo $filter === 'completed' ? '' : 'secondary'; ?>">Completed</a>
                    <a href="?page=ptp-comms-reminders&filter=all" class="ptp-comms-button small <?php echo $filter === 'all' ? '' : 'secondary'; ?>">All</a>
                </div>
            </div>
            
            <!-- Reminders List -->
            <div class="ptp-comms-card">
                <?php if (empty($reminders)): ?>
                <div class="ptp-comms-empty-state">
                    <span class="dashicons dashicons-bell" style="font-size: 60px; opacity: 0.3;"></span>
                    <h3>No reminders found</h3>
                    <p>Create a reminder to stay on top of your tasks.</p>
                    <a href="?page=ptp-comms-reminders&action=new" class="ptp-comms-button">Create Reminder</a>
                </div>
                <?php else: ?>
                <table class="ptp-comms-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Reminder</th>
                            <th style="width: 150px;">Contact</th>
                            <th style="width: 100px;">Type</th>
                            <th style="width: 80px;">Priority</th>
                            <th style="width: 150px;">Due Date</th>
                            <th style="width: 100px;">Assigned To</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reminders as $reminder): 
                            $is_overdue = $reminder->status === 'pending' && strtotime($reminder->due_date) < time();
                            $type_info = isset($reminder_types[$reminder->reminder_type]) ? $reminder_types[$reminder->reminder_type] : $reminder_types['custom'];
                            $priority_info = isset($priorities[$reminder->priority]) ? $priorities[$reminder->priority] : $priorities['normal'];
                        ?>
                        <tr class="<?php echo $is_overdue ? 'overdue-row' : ''; ?>">
                            <td style="text-align: center;">
                                <?php echo $type_info['icon']; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($reminder->title); ?></strong>
                                <?php if ($reminder->description): ?>
                                <br><small style="color: #666;"><?php echo esc_html(substr($reminder->description, 0, 80)); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($reminder->contact_id): ?>
                                <a href="?page=ptp-comms-contacts&action=edit&id=<?php echo $reminder->contact_id; ?>">
                                    <?php echo esc_html($reminder->parent_first_name . ' ' . $reminder->parent_last_name); ?>
                                </a>
                                <?php else: ?>
                                <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ptp-comms-badge info"><?php echo esc_html($type_info['label']); ?></span>
                            </td>
                            <td>
                                <span class="ptp-comms-badge" style="background-color: <?php echo esc_attr($priority_info['color']); ?>; color: #fff;">
                                    <?php echo esc_html($priority_info['label']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_overdue): ?>
                                <span style="color: #dc3232; font-weight: 600;">
                                    <?php echo date('M j, g:i A', strtotime($reminder->due_date)); ?>
                                </span>
                                <?php else: ?>
                                <?php echo date('M j, g:i A', strtotime($reminder->due_date)); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($reminder->assigned_to_name ?? 'Unassigned'); ?></td>
                            <td>
                                <?php if ($reminder->status === 'pending'): ?>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('ptp_comms_reminder_action'); ?>
                                    <input type="hidden" name="reminder_id" value="<?php echo $reminder->id; ?>">
                                    <button type="submit" name="complete_reminder" value="1" class="ptp-comms-button small success" title="Mark Complete">✓</button>
                                </form>
                                <?php endif; ?>
                                <a href="?page=ptp-comms-reminders&action=edit&id=<?php echo $reminder->id; ?>" class="ptp-comms-button small secondary">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .overdue-row { background-color: #fff5f5 !important; }
        .ptp-comms-filters { display: flex; gap: 10px; flex-wrap: wrap; }
        </style>
        <?php
    }
    
    private static function render_form($reminder_id = 0) {
        global $wpdb;
        
        $reminder = $reminder_id ? PTP_Comms_Hub_Reminders::get($reminder_id) : null;
        $reminder_types = PTP_Comms_Hub_Reminders::get_reminder_types();
        $priorities = PTP_Comms_Hub_Reminders::get_priorities();
        $recurring_options = PTP_Comms_Hub_Reminders::get_recurring_options();
        
        // Get all users for assignment
        $users = get_users(array('role__in' => array('administrator', 'editor', 'author')));
        
        // Get contacts for dropdown
        $contacts = $wpdb->get_results("SELECT id, parent_first_name, parent_last_name, parent_phone FROM {$wpdb->prefix}ptp_contacts ORDER BY parent_last_name, parent_first_name LIMIT 1000");
        
        // Pre-select contact if passed via URL
        $preselect_contact = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : ($reminder ? $reminder->contact_id : 0);
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-reminders" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> Back
                </a>
            </div>
            
            <div class="ptp-comms-card">
                <h2><?php echo $reminder_id ? 'Edit Reminder' : 'Create New Reminder'; ?></h2>
                
                <form method="post">
                    <?php wp_nonce_field('ptp_comms_reminder_action'); ?>
                    <input type="hidden" name="reminder_id" value="<?php echo $reminder_id; ?>">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="title">Title *</label></th>
                            <td>
                                <input type="text" name="title" id="title" class="regular-text" required
                                       value="<?php echo esc_attr($reminder->title ?? ''); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="description">Description</label></th>
                            <td>
                                <textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea($reminder->description ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="contact_id">Related Contact</label></th>
                            <td>
                                <select name="contact_id" id="contact_id" class="regular-text">
                                    <option value="">— No contact —</option>
                                    <?php foreach ($contacts as $c): ?>
                                    <option value="<?php echo $c->id; ?>" <?php selected($preselect_contact, $c->id); ?>>
                                        <?php echo esc_html($c->parent_first_name . ' ' . $c->parent_last_name . ' - ' . $c->parent_phone); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="reminder_type">Type</label></th>
                            <td>
                                <select name="reminder_type" id="reminder_type">
                                    <?php foreach ($reminder_types as $key => $type): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($reminder->reminder_type ?? 'follow_up', $key); ?>>
                                        <?php echo $type['icon'] . ' ' . esc_html($type['label']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="priority">Priority</label></th>
                            <td>
                                <select name="priority" id="priority">
                                    <?php foreach ($priorities as $key => $priority): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($reminder->priority ?? 'normal', $key); ?>>
                                        <?php echo esc_html($priority['label']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="due_date">Due Date & Time *</label></th>
                            <td>
                                <input type="date" name="due_date" id="due_date" required
                                       value="<?php echo esc_attr($reminder ? date('Y-m-d', strtotime($reminder->due_date)) : date('Y-m-d')); ?>">
                                <input type="time" name="due_time" id="due_time"
                                       value="<?php echo esc_attr($reminder ? date('H:i', strtotime($reminder->due_date)) : '09:00'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="assigned_to">Assign To</label></th>
                            <td>
                                <select name="assigned_to" id="assigned_to">
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user->ID; ?>" <?php selected($reminder->assigned_to ?? get_current_user_id(), $user->ID); ?>>
                                        <?php echo esc_html($user->display_name); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="notification_method">Notification</label></th>
                            <td>
                                <select name="notification_method" id="notification_method">
                                    <option value="email" <?php selected($reminder->notification_method ?? 'email', 'email'); ?>>Email</option>
                                    <option value="teams" <?php selected($reminder->notification_method ?? '', 'teams'); ?>>Teams</option>
                                    <option value="both" <?php selected($reminder->notification_method ?? '', 'both'); ?>>Both</option>
                                    <option value="none" <?php selected($reminder->notification_method ?? '', 'none'); ?>>None</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="recurring">Recurring</label></th>
                            <td>
                                <select name="recurring" id="recurring">
                                    <?php foreach ($recurring_options as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($reminder->recurring ?? 'none', $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <br><br>
                                <label>End recurring on: <input type="date" name="recurring_end_date" value="<?php echo esc_attr($reminder->recurring_end_date ?? ''); ?>"></label>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" name="save_reminder" value="1" class="ptp-comms-button">
                            <?php echo $reminder_id ? 'Update Reminder' : 'Create Reminder'; ?>
                        </button>
                        <?php if ($reminder_id): ?>
                        <button type="submit" name="delete_reminder" value="1" class="ptp-comms-button secondary" 
                                onclick="return confirm('Delete this reminder?');">Delete</button>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    private static function render_detail($reminder_id) {
        $reminder = PTP_Comms_Hub_Reminders::get($reminder_id);
        
        if (!$reminder) {
            echo '<div class="wrap"><p>Reminder not found.</p></div>';
            return;
        }
        
        $reminder_types = PTP_Comms_Hub_Reminders::get_reminder_types();
        $priorities = PTP_Comms_Hub_Reminders::get_priorities();
        
        $type_info = isset($reminder_types[$reminder->reminder_type]) ? $reminder_types[$reminder->reminder_type] : $reminder_types['custom'];
        $priority_info = isset($priorities[$reminder->priority]) ? $priorities[$reminder->priority] : $priorities['normal'];
        $is_overdue = $reminder->status === 'pending' && strtotime($reminder->due_date) < time();
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-reminders" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> Back
                </a>
            </div>
            
            <div class="ptp-comms-card">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h2 style="margin: 0 0 10px 0;">
                            <?php echo $type_info['icon']; ?> <?php echo esc_html($reminder->title); ?>
                        </h2>
                        <p style="margin: 0;">
                            <span class="ptp-comms-badge <?php echo $reminder->status === 'completed' ? 'success' : ($is_overdue ? 'error' : 'info'); ?>">
                                <?php echo ucfirst($reminder->status); ?>
                            </span>
                            <span class="ptp-comms-badge" style="background-color: <?php echo esc_attr($priority_info['color']); ?>; color: #fff;">
                                <?php echo esc_html($priority_info['label']); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <?php if ($reminder->status === 'pending'): ?>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('ptp_comms_reminder_action'); ?>
                            <input type="hidden" name="reminder_id" value="<?php echo $reminder->id; ?>">
                            <button type="submit" name="complete_reminder" value="1" class="ptp-comms-button success">✓ Mark Complete</button>
                        </form>
                        <?php endif; ?>
                        <a href="?page=ptp-comms-reminders&action=edit&id=<?php echo $reminder->id; ?>" class="ptp-comms-button secondary">Edit</a>
                    </div>
                </div>
                
                <hr style="margin: 20px 0;">
                
                <table class="form-table">
                    <tr>
                        <th>Due Date</th>
                        <td>
                            <?php if ($is_overdue): ?>
                            <span style="color: #dc3232; font-weight: 600;">
                                <?php echo date('F j, Y g:i A', strtotime($reminder->due_date)); ?> (Overdue)
                            </span>
                            <?php else: ?>
                            <?php echo date('F j, Y g:i A', strtotime($reminder->due_date)); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($reminder->description): ?>
                    <tr>
                        <th>Description</th>
                        <td><?php echo nl2br(esc_html($reminder->description)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($reminder->contact_id): ?>
                    <tr>
                        <th>Related Contact</th>
                        <td>
                            <a href="?page=ptp-comms-contacts&action=edit&id=<?php echo $reminder->contact_id; ?>">
                                <?php echo esc_html($reminder->parent_first_name . ' ' . $reminder->parent_last_name); ?>
                            </a>
                            <?php if ($reminder->parent_phone): ?>
                            <br><small><?php echo esc_html($reminder->parent_phone); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Assigned To</th>
                        <td><?php echo esc_html($reminder->assigned_to_name ?? 'Unassigned'); ?></td>
                    </tr>
                    <tr>
                        <th>Created By</th>
                        <td><?php echo esc_html($reminder->created_by_name); ?> on <?php echo date('M j, Y', strtotime($reminder->created_at)); ?></td>
                    </tr>
                    <?php if ($reminder->recurring): ?>
                    <tr>
                        <th>Recurring</th>
                        <td><?php echo ucfirst($reminder->recurring); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($reminder->completed_at): ?>
                    <tr>
                        <th>Completed</th>
                        <td><?php echo date('F j, Y g:i A', strtotime($reminder->completed_at)); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php if ($reminder->status === 'pending'): ?>
                <hr style="margin: 20px 0;">
                <h3>Snooze</h3>
                <form method="post">
                    <?php wp_nonce_field('ptp_comms_reminder_action'); ?>
                    <input type="hidden" name="reminder_id" value="<?php echo $reminder->id; ?>">
                    <p>
                        <input type="datetime-local" name="snooze_until" required 
                               min="<?php echo date('Y-m-d\TH:i'); ?>"
                               value="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>">
                        <button type="submit" name="snooze_reminder" value="1" class="ptp-comms-button secondary">Snooze Until</button>
                    </p>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
