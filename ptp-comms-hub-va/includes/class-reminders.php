<?php
/**
 * Reminders Management
 * v4.0.0 - VA task management with notifications
 */
class PTP_Comms_Hub_Reminders {
    
    /**
     * Reminder types
     */
    public static function get_reminder_types() {
        return array(
            'follow_up' => array('label' => 'Follow Up', 'icon' => '📞'),
            'callback' => array('label' => 'Callback Request', 'icon' => '📱'),
            'note_follow_up' => array('label' => 'Note Follow-up', 'icon' => '📝'),
            'birthday' => array('label' => 'Birthday', 'icon' => '🎂'),
            'anniversary' => array('label' => 'Anniversary', 'icon' => '🎉'),
            'event_prep' => array('label' => 'Event Preparation', 'icon' => '📅'),
            'payment' => array('label' => 'Payment Reminder', 'icon' => '💳'),
            'registration' => array('label' => 'Registration Follow-up', 'icon' => '📋'),
            'feedback' => array('label' => 'Request Feedback', 'icon' => '⭐'),
            'custom' => array('label' => 'Custom Reminder', 'icon' => '🔔')
        );
    }
    
    /**
     * Priority levels
     */
    public static function get_priorities() {
        return array(
            'urgent' => array('label' => 'Urgent', 'color' => '#ef4444'),
            'high' => array('label' => 'High', 'color' => '#f97316'),
            'normal' => array('label' => 'Normal', 'color' => '#3b82f6'),
            'low' => array('label' => 'Low', 'color' => '#6b7280')
        );
    }
    
    /**
     * Recurring options
     */
    public static function get_recurring_options() {
        return array(
            'none' => 'Does not repeat',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'biweekly' => 'Every 2 weeks',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'yearly' => 'Yearly'
        );
    }
    
    /**
     * Create a reminder
     */
    public static function create($data) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        $insert_data = array(
            'contact_id' => isset($data['contact_id']) ? intval($data['contact_id']) : null,
            'user_id' => $user_id,
            'assigned_to' => isset($data['assigned_to']) ? intval($data['assigned_to']) : $user_id,
            'reminder_type' => isset($data['reminder_type']) ? sanitize_text_field($data['reminder_type']) : 'follow_up',
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : '',
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
            'priority' => isset($data['priority']) ? sanitize_text_field($data['priority']) : 'normal',
            'due_date' => isset($data['due_date']) ? sanitize_text_field($data['due_date']) : current_time('mysql'),
            'status' => 'pending',
            'notification_method' => isset($data['notification_method']) ? sanitize_text_field($data['notification_method']) : 'email',
            'recurring' => isset($data['recurring']) && $data['recurring'] !== 'none' ? sanitize_text_field($data['recurring']) : null,
            'recurring_end_date' => isset($data['recurring_end_date']) && !empty($data['recurring_end_date']) ? sanitize_text_field($data['recurring_end_date']) : null,
            'related_order_id' => isset($data['related_order_id']) ? intval($data['related_order_id']) : null,
            'related_note_id' => isset($data['related_note_id']) ? intval($data['related_note_id']) : null,
            'action_url' => isset($data['action_url']) ? esc_url_raw($data['action_url']) : null,
            'meta_data' => isset($data['meta_data']) ? json_encode($data['meta_data']) : null,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($wpdb->prefix . 'ptp_reminders', $insert_data);
        
        if ($result) {
            $reminder_id = $wpdb->insert_id;
            
            // Create notification for assigned user
            if ($insert_data['assigned_to'] != $user_id) {
                self::notify_assignee($reminder_id, $insert_data);
            }
            
            // Log activity if contact-related
            if ($insert_data['contact_id']) {
                self::log_activity($insert_data['contact_id'], 'reminder_created', $insert_data['title']);
            }
            
            return $reminder_id;
        }
        
        return false;
    }
    
    /**
     * Update a reminder
     */
    public static function update($reminder_id, $data) {
        global $wpdb;
        
        $update_data = array('updated_at' => current_time('mysql'));
        
        $allowed_fields = array('title', 'description', 'priority', 'due_date', 'status', 
                                'assigned_to', 'notification_method', 'recurring', 'action_url');
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_reminders',
            $update_data,
            array('id' => $reminder_id)
        );
    }
    
    /**
     * Complete a reminder
     */
    public static function complete($reminder_id) {
        global $wpdb;
        
        $reminder = self::get($reminder_id);
        if (!$reminder) {
            return false;
        }
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_reminders',
            array(
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ),
            array('id' => $reminder_id)
        );
        
        // Create next occurrence if recurring
        if ($reminder->recurring) {
            self::create_next_occurrence($reminder);
        }
        
        // Log activity
        if ($reminder->contact_id) {
            self::log_activity($reminder->contact_id, 'reminder_completed', $reminder->title);
        }
        
        return true;
    }
    
    /**
     * Snooze a reminder
     */
    public static function snooze($reminder_id, $snooze_until) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_reminders',
            array(
                'snoozed_until' => sanitize_text_field($snooze_until),
                'status' => 'snoozed'
            ),
            array('id' => $reminder_id)
        );
    }
    
    /**
     * Delete a reminder
     */
    public static function delete($reminder_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'ptp_reminders',
            array('id' => $reminder_id)
        );
    }
    
    /**
     * Get a reminder
     */
    public static function get($reminder_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, 
                    c.parent_first_name, c.parent_last_name, c.parent_phone, c.parent_email,
                    u.display_name as assigned_to_name,
                    creator.display_name as created_by_name
             FROM {$wpdb->prefix}ptp_reminders r
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
             LEFT JOIN {$wpdb->users} u ON r.assigned_to = u.ID
             LEFT JOIN {$wpdb->users} creator ON r.user_id = creator.ID
             WHERE r.id = %d",
            $reminder_id
        ));
    }
    
    /**
     * Get reminders with filters
     */
    public static function get_reminders($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => null,
            'assigned_to' => null,
            'contact_id' => null,
            'status' => null,
            'type' => null,
            'priority' => null,
            'due_from' => null,
            'due_to' => null,
            'overdue_only' => false,
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'due_date',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("1=1");
        $params = array();
        
        if ($args['user_id']) {
            $where[] = "r.user_id = %d";
            $params[] = $args['user_id'];
        }
        
        if ($args['assigned_to']) {
            $where[] = "r.assigned_to = %d";
            $params[] = $args['assigned_to'];
        }
        
        if ($args['contact_id']) {
            $where[] = "r.contact_id = %d";
            $params[] = $args['contact_id'];
        }
        
        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "r.status IN ({$placeholders})";
                $params = array_merge($params, $args['status']);
            } else {
                $where[] = "r.status = %s";
                $params[] = $args['status'];
            }
        }
        
        if ($args['type']) {
            $where[] = "r.reminder_type = %s";
            $params[] = $args['type'];
        }
        
        if ($args['priority']) {
            $where[] = "r.priority = %s";
            $params[] = $args['priority'];
        }
        
        if ($args['due_from']) {
            $where[] = "r.due_date >= %s";
            $params[] = $args['due_from'];
        }
        
        if ($args['due_to']) {
            $where[] = "r.due_date <= %s";
            $params[] = $args['due_to'];
        }
        
        if ($args['overdue_only']) {
            $where[] = "r.due_date < NOW() AND r.status = 'pending'";
        }
        
        // Handle snoozed reminders
        $where[] = "(r.snoozed_until IS NULL OR r.snoozed_until <= NOW())";
        
        $where_sql = implode(' AND ', $where);
        $order_by = in_array($args['order_by'], array('due_date', 'created_at', 'priority')) ? $args['order_by'] : 'due_date';
        $order = $args['order'] === 'DESC' ? 'DESC' : 'ASC';
        
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, 
                    c.parent_first_name, c.parent_last_name, c.parent_phone,
                    u.display_name as assigned_to_name
             FROM {$wpdb->prefix}ptp_reminders r
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
             LEFT JOIN {$wpdb->users} u ON r.assigned_to = u.ID
             WHERE {$where_sql}
             ORDER BY 
                CASE r.priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'normal' THEN 3 
                    WHEN 'low' THEN 4 
                END,
                r.{$order_by} {$order}
             LIMIT %d OFFSET %d",
            $params
        ));
    }
    
    /**
     * Get today's reminders for a user
     */
    public static function get_todays_reminders($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return self::get_reminders(array(
            'assigned_to' => $user_id,
            'status' => 'pending',
            'due_from' => date('Y-m-d 00:00:00'),
            'due_to' => date('Y-m-d 23:59:59'),
            'limit' => 100
        ));
    }
    
    /**
     * Get overdue reminders
     */
    public static function get_overdue($user_id = null) {
        $args = array(
            'status' => 'pending',
            'overdue_only' => true,
            'limit' => 100
        );
        
        if ($user_id) {
            $args['assigned_to'] = $user_id;
        }
        
        return self::get_reminders($args);
    }
    
    /**
     * Get upcoming reminders (next 7 days)
     */
    public static function get_upcoming($user_id = null, $days = 7) {
        $args = array(
            'status' => 'pending',
            'due_from' => date('Y-m-d H:i:s'),
            'due_to' => date('Y-m-d 23:59:59', strtotime("+{$days} days")),
            'limit' => 100
        );
        
        if ($user_id) {
            $args['assigned_to'] = $user_id;
        }
        
        return self::get_reminders($args);
    }
    
    /**
     * Get reminder counts for dashboard
     */
    public static function get_counts($user_id = null) {
        global $wpdb;
        
        $user_where = $user_id ? $wpdb->prepare("AND assigned_to = %d", $user_id) : "";
        
        return array(
            'overdue' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_reminders WHERE status = 'pending' AND due_date < NOW() {$user_where}"),
            'today' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_reminders WHERE status = 'pending' AND DATE(due_date) = CURDATE() {$user_where}")),
            'upcoming' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_reminders WHERE status = 'pending' AND due_date > NOW() AND due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) {$user_where}")),
            'completed_this_week' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_reminders WHERE status = 'completed' AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) {$user_where}"))
        );
    }
    
    /**
     * Process due reminders (cron job)
     */
    public static function process_due_reminders() {
        global $wpdb;
        
        // Get reminders due in the next 5 minutes that haven't been notified
        $due_reminders = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, c.parent_first_name, c.parent_last_name
            FROM {$wpdb->prefix}ptp_reminders r
            LEFT JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
            WHERE r.status = 'pending'
            AND r.notification_sent = 0
            AND r.due_date <= DATE_ADD(NOW(), INTERVAL 5 MINUTE)
            AND (r.snoozed_until IS NULL OR r.snoozed_until <= NOW())
            LIMIT 50
        "));
        
        foreach ($due_reminders as $reminder) {
            self::send_reminder_notification($reminder);
            
            $wpdb->update(
                $wpdb->prefix . 'ptp_reminders',
                array('notification_sent' => 1),
                array('id' => $reminder->id)
            );
        }
        
        // Unsnooze snoozed reminders whose snooze time has passed
        $wpdb->query("
            UPDATE {$wpdb->prefix}ptp_reminders 
            SET status = 'pending', snoozed_until = NULL 
            WHERE status = 'snoozed' AND snoozed_until <= NOW()
        ");
        
        return count($due_reminders);
    }
    
    /**
     * Send reminder notification
     */
    private static function send_reminder_notification($reminder) {
        $user = get_user_by('id', $reminder->assigned_to);
        if (!$user) {
            return false;
        }
        
        $contact_name = trim($reminder->parent_first_name . ' ' . $reminder->parent_last_name);
        
        // Create in-app notification
        if (class_exists('PTP_Comms_Hub_Notifications')) {
            PTP_Comms_Hub_Notifications::create(array(
                'user_id' => $reminder->assigned_to,
                'contact_id' => $reminder->contact_id,
                'notification_type' => 'reminder_due',
                'title' => 'Reminder: ' . $reminder->title,
                'message' => $contact_name ? "Contact: {$contact_name}" : $reminder->description,
                'action_url' => admin_url('admin.php?page=ptp-comms-reminders&action=view&id=' . $reminder->id),
                'action_text' => 'View Reminder',
                'priority' => $reminder->priority
            ));
        }
        
        // Send email notification
        if ($reminder->notification_method === 'email' || $reminder->notification_method === 'both') {
            $subject = "[PTP] Reminder: " . $reminder->title;
            $message = "You have a reminder:\n\n";
            $message .= "Title: " . $reminder->title . "\n";
            if ($contact_name) {
                $message .= "Contact: " . $contact_name . "\n";
            }
            if ($reminder->description) {
                $message .= "Details: " . $reminder->description . "\n";
            }
            $message .= "\nDue: " . date('M j, Y g:i A', strtotime($reminder->due_date));
            $message .= "\n\nView in admin: " . admin_url('admin.php?page=ptp-comms-reminders&action=view&id=' . $reminder->id);
            
            wp_mail($user->user_email, $subject, $message);
        }
        
        // Send Teams notification if configured
        if (($reminder->notification_method === 'teams' || $reminder->notification_method === 'both') && class_exists('PTP_Comms_Hub_Teams_Integration')) {
            $teams_message = "🔔 **Reminder Due**\n\n";
            $teams_message .= "**{$reminder->title}**\n";
            if ($contact_name) {
                $teams_message .= "Contact: {$contact_name}\n";
            }
            $teams_message .= "Due: " . date('M j, Y g:i A', strtotime($reminder->due_date));
            
            PTP_Comms_Hub_Teams_Integration::send_message($teams_message);
        }
        
        return true;
    }
    
    /**
     * Create next occurrence for recurring reminder
     */
    private static function create_next_occurrence($reminder) {
        $next_date = self::calculate_next_date($reminder->due_date, $reminder->recurring);
        
        // Check if we've passed the end date
        if ($reminder->recurring_end_date && $next_date > $reminder->recurring_end_date) {
            return false;
        }
        
        return self::create(array(
            'contact_id' => $reminder->contact_id,
            'assigned_to' => $reminder->assigned_to,
            'reminder_type' => $reminder->reminder_type,
            'title' => $reminder->title,
            'description' => $reminder->description,
            'priority' => $reminder->priority,
            'due_date' => $next_date,
            'notification_method' => $reminder->notification_method,
            'recurring' => $reminder->recurring,
            'recurring_end_date' => $reminder->recurring_end_date,
            'parent_reminder_id' => $reminder->parent_reminder_id ?: $reminder->id
        ));
    }
    
    /**
     * Calculate next occurrence date
     */
    private static function calculate_next_date($current_date, $recurring) {
        $date = new DateTime($current_date);
        
        switch ($recurring) {
            case 'daily':
                $date->modify('+1 day');
                break;
            case 'weekly':
                $date->modify('+1 week');
                break;
            case 'biweekly':
                $date->modify('+2 weeks');
                break;
            case 'monthly':
                $date->modify('+1 month');
                break;
            case 'quarterly':
                $date->modify('+3 months');
                break;
            case 'yearly':
                $date->modify('+1 year');
                break;
        }
        
        return $date->format('Y-m-d H:i:s');
    }
    
    /**
     * Notify assignee when reminder is assigned to them
     */
    private static function notify_assignee($reminder_id, $data) {
        if (class_exists('PTP_Comms_Hub_Notifications')) {
            PTP_Comms_Hub_Notifications::create(array(
                'user_id' => $data['assigned_to'],
                'contact_id' => $data['contact_id'],
                'notification_type' => 'reminder_assigned',
                'title' => 'New Reminder Assigned',
                'message' => $data['title'],
                'action_url' => admin_url('admin.php?page=ptp-comms-reminders&action=view&id=' . $reminder_id),
                'action_text' => 'View Reminder'
            ));
        }
    }
    
    /**
     * Log activity
     */
    private static function log_activity($contact_id, $type, $title) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ptp_activity_log',
            array(
                'contact_id' => $contact_id,
                'user_id' => get_current_user_id(),
                'activity_type' => 'reminder',
                'activity_subtype' => $type,
                'title' => $title,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Quick reminder creation
     */
    public static function quick_reminder($contact_id, $title, $due_date, $priority = 'normal') {
        return self::create(array(
            'contact_id' => $contact_id,
            'title' => $title,
            'due_date' => $due_date,
            'priority' => $priority,
            'reminder_type' => 'follow_up'
        ));
    }
}
