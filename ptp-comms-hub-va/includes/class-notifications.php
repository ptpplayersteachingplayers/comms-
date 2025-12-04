<?php
/**
 * Notifications Management
 * v4.0.0 - VA alerts and notification system
 */
class PTP_Comms_Hub_Notifications {
    
    /**
     * Notification types
     */
    public static function get_notification_types() {
        return array(
            'reminder_due' => array('label' => 'Reminder Due', 'icon' => '🔔'),
            'reminder_assigned' => array('label' => 'Reminder Assigned', 'icon' => '📌'),
            'new_message' => array('label' => 'New Message', 'icon' => '💬'),
            'new_registration' => array('label' => 'New Registration', 'icon' => '✅'),
            'new_order' => array('label' => 'New Order', 'icon' => '🛒'),
            'contact_replied' => array('label' => 'Contact Replied', 'icon' => '📩'),
            'voicemail' => array('label' => 'New Voicemail', 'icon' => '📞'),
            'missed_call' => array('label' => 'Missed Call', 'icon' => '📵'),
            'campaign_complete' => array('label' => 'Campaign Complete', 'icon' => '📢'),
            'hubspot_sync' => array('label' => 'HubSpot Sync', 'icon' => '🔄'),
            'contact_milestone' => array('label' => 'Contact Milestone', 'icon' => '🏆'),
            'birthday_alert' => array('label' => 'Birthday Alert', 'icon' => '🎂'),
            'low_engagement' => array('label' => 'Low Engagement Alert', 'icon' => '⚠️'),
            'system' => array('label' => 'System Notification', 'icon' => '⚙️')
        );
    }
    
    /**
     * Create a notification
     */
    public static function create($data) {
        global $wpdb;
        
        $insert_data = array(
            'user_id' => isset($data['user_id']) ? intval($data['user_id']) : get_current_user_id(),
            'contact_id' => isset($data['contact_id']) ? intval($data['contact_id']) : null,
            'notification_type' => isset($data['notification_type']) ? sanitize_text_field($data['notification_type']) : 'system',
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : '',
            'message' => isset($data['message']) ? sanitize_textarea_field($data['message']) : '',
            'action_url' => isset($data['action_url']) ? esc_url_raw($data['action_url']) : null,
            'action_text' => isset($data['action_text']) ? sanitize_text_field($data['action_text']) : null,
            'priority' => isset($data['priority']) ? sanitize_text_field($data['priority']) : 'normal',
            'expires_at' => isset($data['expires_at']) ? sanitize_text_field($data['expires_at']) : null,
            'meta_data' => isset($data['meta_data']) ? json_encode($data['meta_data']) : null,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($wpdb->prefix . 'ptp_notifications', $insert_data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Create notification for multiple users
     */
    public static function create_for_users($user_ids, $data) {
        $notification_ids = array();
        
        foreach ($user_ids as $user_id) {
            $data['user_id'] = $user_id;
            $id = self::create($data);
            if ($id) {
                $notification_ids[] = $id;
            }
        }
        
        return $notification_ids;
    }
    
    /**
     * Create notification for all admins
     */
    public static function notify_admins($data) {
        $admin_users = get_users(array('role__in' => array('administrator', 'editor')));
        $user_ids = array_column($admin_users, 'ID');
        
        return self::create_for_users($user_ids, $data);
    }
    
    /**
     * Mark notification as read
     */
    public static function mark_read($notification_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_notifications',
            array('is_read' => 1, 'read_at' => current_time('mysql')),
            array('id' => $notification_id)
        );
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public static function mark_all_read($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_notifications',
            array('is_read' => 1, 'read_at' => current_time('mysql')),
            array('user_id' => $user_id, 'is_read' => 0)
        );
    }
    
    /**
     * Dismiss notification
     */
    public static function dismiss($notification_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_notifications',
            array('is_dismissed' => 1),
            array('id' => $notification_id)
        );
    }
    
    /**
     * Delete notification
     */
    public static function delete($notification_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'ptp_notifications',
            array('id' => $notification_id)
        );
    }
    
    /**
     * Get notifications for a user
     */
    public static function get_notifications($user_id = null, $args = array()) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $defaults = array(
            'unread_only' => false,
            'type' => null,
            'limit' => 50,
            'offset' => 0,
            'include_expired' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("user_id = %d", "is_dismissed = 0");
        $params = array($user_id);
        
        if ($args['unread_only']) {
            $where[] = "is_read = 0";
        }
        
        if ($args['type']) {
            $where[] = "notification_type = %s";
            $params[] = $args['type'];
        }
        
        if (!$args['include_expired']) {
            $where[] = "(expires_at IS NULL OR expires_at > NOW())";
        }
        
        $where_sql = implode(' AND ', $where);
        
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, c.parent_first_name, c.parent_last_name
             FROM {$wpdb->prefix}ptp_notifications n
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON n.contact_id = c.id
             WHERE {$where_sql}
             ORDER BY 
                CASE n.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 END,
                n.created_at DESC
             LIMIT %d OFFSET %d",
            $params
        ));
    }
    
    /**
     * Get unread count
     */
    public static function get_unread_count($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_notifications 
             WHERE user_id = %d AND is_read = 0 AND is_dismissed = 0 
             AND (expires_at IS NULL OR expires_at > NOW())",
            $user_id
        ));
    }
    
    /**
     * Get notification
     */
    public static function get($notification_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_notifications WHERE id = %d",
            $notification_id
        ));
    }
    
    /**
     * Send email digest of unread notifications
     */
    public static function send_digest() {
        global $wpdb;
        
        // Get users with pending digest notifications
        $users_with_notifications = $wpdb->get_col("
            SELECT DISTINCT user_id FROM {$wpdb->prefix}ptp_notifications 
            WHERE is_read = 0 AND is_dismissed = 0 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        
        foreach ($users_with_notifications as $user_id) {
            $notifications = self::get_notifications($user_id, array(
                'unread_only' => true,
                'limit' => 20
            ));
            
            if (empty($notifications)) {
                continue;
            }
            
            // Check user preference for digest
            $user = get_user_by('id', $user_id);
            $receive_digest = get_user_meta($user_id, 'ptp_notification_digest', true);
            
            if ($receive_digest === 'disabled') {
                continue;
            }
            
            // Build digest email
            $subject = '[PTP] You have ' . count($notifications) . ' unread notification(s)';
            
            $message = "Hi " . $user->display_name . ",\n\n";
            $message .= "You have the following unread notifications:\n\n";
            
            foreach ($notifications as $notif) {
                $types = self::get_notification_types();
                $icon = isset($types[$notif->notification_type]['icon']) ? $types[$notif->notification_type]['icon'] : '📌';
                
                $message .= $icon . " " . $notif->title . "\n";
                if ($notif->message) {
                    $message .= "   " . substr($notif->message, 0, 100) . "\n";
                }
                $message .= "   " . date('M j, g:i A', strtotime($notif->created_at)) . "\n\n";
            }
            
            $message .= "\nView all notifications: " . admin_url('admin.php?page=ptp-comms-dashboard');
            $message .= "\n\n---\nTo change notification preferences, visit your profile settings.";
            
            wp_mail($user->user_email, $subject, $message);
        }
    }
    
    /**
     * Create notification for new SMS reply
     */
    public static function notify_sms_reply($contact_id, $message_content) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            return false;
        }
        
        $contact_name = trim($contact->parent_first_name . ' ' . $contact->parent_last_name);
        if (empty($contact_name)) {
            $contact_name = function_exists('ptp_comms_format_phone') ? ptp_comms_format_phone($contact->parent_phone) : $contact->parent_phone;
        }
        
        // Notify assigned VA or all admins
        if ($contact->assigned_va) {
            return self::create(array(
                'user_id' => $contact->assigned_va,
                'contact_id' => $contact_id,
                'notification_type' => 'contact_replied',
                'title' => "Reply from {$contact_name}",
                'message' => substr($message_content, 0, 200),
                'action_url' => admin_url('admin.php?page=ptp-comms-inbox&contact=' . $contact_id),
                'action_text' => 'View Conversation',
                'priority' => 'high'
            ));
        } else {
            return self::notify_admins(array(
                'contact_id' => $contact_id,
                'notification_type' => 'contact_replied',
                'title' => "Reply from {$contact_name}",
                'message' => substr($message_content, 0, 200),
                'action_url' => admin_url('admin.php?page=ptp-comms-inbox&contact=' . $contact_id),
                'action_text' => 'View Conversation',
                'priority' => 'high'
            ));
        }
    }
    
    /**
     * Create notification for new registration
     */
    public static function notify_new_registration($contact_id, $order_id, $event_name) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            return false;
        }
        
        $contact_name = trim($contact->parent_first_name . ' ' . $contact->parent_last_name);
        
        return self::notify_admins(array(
            'contact_id' => $contact_id,
            'notification_type' => 'new_registration',
            'title' => "New Registration: {$event_name}",
            'message' => "Parent: {$contact_name}\nChild: {$contact->child_name}",
            'action_url' => admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact_id),
            'action_text' => 'View Contact',
            'meta_data' => array('order_id' => $order_id)
        ));
    }
    
    /**
     * Create notification for birthday
     */
    public static function notify_birthday($contact_id) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            return false;
        }
        
        $message = "{$contact->child_name}'s birthday is today!";
        if ($contact->child_age) {
            $message .= " They are turning " . ($contact->child_age + 1) . ".";
        }
        
        $notify_user = $contact->assigned_va ?: null;
        
        if ($notify_user) {
            return self::create(array(
                'user_id' => $notify_user,
                'contact_id' => $contact_id,
                'notification_type' => 'birthday_alert',
                'title' => "🎂 Birthday: {$contact->child_name}",
                'message' => $message,
                'action_url' => admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact_id),
                'action_text' => 'Send Birthday Message',
                'priority' => 'high'
            ));
        } else {
            return self::notify_admins(array(
                'contact_id' => $contact_id,
                'notification_type' => 'birthday_alert',
                'title' => "🎂 Birthday: {$contact->child_name}",
                'message' => $message,
                'action_url' => admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact_id),
                'action_text' => 'Send Birthday Message',
                'priority' => 'high'
            ));
        }
    }
    
    /**
     * Create notification for low engagement
     */
    public static function notify_low_engagement($contact_id) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            return false;
        }
        
        $contact_name = trim($contact->parent_first_name . ' ' . $contact->parent_last_name);
        $days_since = $contact->last_interaction_at 
            ? floor((time() - strtotime($contact->last_interaction_at)) / 86400)
            : 'Unknown';
        
        $notify_user = $contact->assigned_va ?: null;
        
        $data = array(
            'contact_id' => $contact_id,
            'notification_type' => 'low_engagement',
            'title' => "Low Engagement: {$contact_name}",
            'message' => "Relationship score: {$contact->relationship_score}. Last interaction: {$days_since} days ago.",
            'action_url' => admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact_id),
            'action_text' => 'Re-engage Contact',
            'priority' => 'normal'
        );
        
        if ($notify_user) {
            $data['user_id'] = $notify_user;
            return self::create($data);
        } else {
            return self::notify_admins($data);
        }
    }
    
    /**
     * Clean up old notifications
     */
    public static function cleanup_old($days = 30) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ptp_notifications 
             WHERE (is_read = 1 OR is_dismissed = 1) 
             AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * Get notifications as admin bar count
     */
    public static function admin_bar_count() {
        $count = self::get_unread_count();
        return $count > 0 ? $count : '';
    }

    /**
     * Send WhatsApp notification to user
     */
    public static function send_whatsapp_notification($user_id, $notification_data) {
        // Check if WhatsApp is configured
        if (!function_exists('ptp_comms_send_whatsapp') || !function_exists('ptp_comms_is_whatsapp_configured')) {
            return false;
        }

        if (!ptp_comms_is_whatsapp_configured()) {
            return false;
        }

        // Check user preference for WhatsApp notifications
        $whatsapp_enabled = get_user_meta($user_id, 'ptp_whatsapp_notifications', true);
        $whatsapp_phone = get_user_meta($user_id, 'ptp_whatsapp_phone', true);

        if ($whatsapp_enabled !== 'yes' || empty($whatsapp_phone)) {
            return false;
        }

        // Check if this notification type should trigger WhatsApp
        $allowed_types = get_user_meta($user_id, 'ptp_whatsapp_notification_types', true);
        if (empty($allowed_types)) {
            // Default types to send via WhatsApp
            $allowed_types = array('new_message', 'contact_replied', 'reminder_due', 'voicemail', 'missed_call');
        }

        $notification_type = isset($notification_data['notification_type']) ? $notification_data['notification_type'] : 'system';
        if (!in_array($notification_type, $allowed_types)) {
            return false;
        }

        // Build WhatsApp message
        $types = self::get_notification_types();
        $icon = isset($types[$notification_type]['icon']) ? $types[$notification_type]['icon'] : '📣';

        $message = "{$icon} *PTP Comms Alert*\n\n";
        $message .= "*" . ($notification_data['title'] ?? 'New Notification') . "*\n";
        if (!empty($notification_data['message'])) {
            $message .= "\n" . substr($notification_data['message'], 0, 500) . "\n";
        }
        if (!empty($notification_data['action_url'])) {
            $message .= "\n🔗 " . $notification_data['action_url'];
        }
        $message .= "\n\n_" . date('M j, g:i A') . "_";

        // Send via WhatsApp
        return ptp_comms_send_whatsapp($whatsapp_phone, $message);
    }

    /**
     * Send WhatsApp to all subscribed users
     */
    public static function broadcast_whatsapp_notification($notification_data) {
        // Get users with WhatsApp notifications enabled
        $users = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'ptp_whatsapp_notifications',
                    'value' => 'yes'
                ),
                array(
                    'key' => 'ptp_whatsapp_phone',
                    'value' => '',
                    'compare' => '!='
                )
            )
        ));

        $results = array();
        foreach ($users as $user) {
            $result = self::send_whatsapp_notification($user->ID, $notification_data);
            $results[$user->ID] = $result;
        }

        return $results;
    }

    /**
     * Create notification and optionally send via WhatsApp
     */
    public static function create_with_whatsapp($data) {
        // Create the notification
        $notification_id = self::create($data);

        if ($notification_id) {
            // Send WhatsApp notification
            $user_id = isset($data['user_id']) ? $data['user_id'] : get_current_user_id();
            self::send_whatsapp_notification($user_id, $data);

            // Fire action for WhatsApp integration
            do_action('ptp_comms_notification_created', $notification_id, $data);
        }

        return $notification_id;
    }

    /**
     * Notify via WhatsApp for shared inbox
     */
    public static function notify_shared_inbox_whatsapp($contact_id, $message_content, $channel = 'sms') {
        global $wpdb;

        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));

        if (!$contact) {
            return false;
        }

        $contact_name = trim($contact->parent_first_name . ' ' . $contact->parent_last_name);
        if (empty($contact_name)) {
            $contact_name = function_exists('ptp_comms_format_phone') ? ptp_comms_format_phone($contact->parent_phone) : $contact->parent_phone;
        }

        $channel_label = strtoupper($channel);
        $channel_icon = $channel === 'whatsapp' ? '💬' : ($channel === 'voice' ? '📞' : '📱');

        $notification_data = array(
            'notification_type' => 'contact_replied',
            'title' => "{$channel_icon} {$channel_label} from {$contact_name}",
            'message' => substr($message_content, 0, 300),
            'action_url' => admin_url('admin.php?page=ptp-comms-inbox&contact=' . $contact_id),
            'priority' => 'high'
        );

        // If assigned VA, notify them
        if (!empty($contact->assigned_va)) {
            $notification_data['user_id'] = $contact->assigned_va;
            return self::send_whatsapp_notification($contact->assigned_va, $notification_data);
        } else {
            // Notify all users subscribed to shared inbox
            return self::broadcast_whatsapp_notification($notification_data);
        }
    }
}

// Add admin bar notification count
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $count = PTP_Comms_Hub_Notifications::get_unread_count();
    
    if ($count > 0) {
        $wp_admin_bar->add_node(array(
            'id' => 'ptp-notifications',
            'title' => '<span class="ab-icon dashicons dashicons-bell"></span><span class="ptp-notif-count">' . $count . '</span>',
            'href' => admin_url('admin.php?page=ptp-comms-dashboard'),
            'meta' => array('title' => $count . ' unread notification(s)')
        ));
    }
}, 100);

// Add CSS for admin bar notification
add_action('admin_head', function() {
    echo '<style>
        #wp-admin-bar-ptp-notifications .ab-icon { margin-right: 5px !important; }
        #wp-admin-bar-ptp-notifications .ptp-notif-count { 
            background: #ca4a1f; 
            color: #fff; 
            padding: 0 6px; 
            border-radius: 10px; 
            font-size: 11px;
            vertical-align: middle;
        }
    </style>';
});
