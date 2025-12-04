<?php
/**
 * Activity Log / Relationship Timeline
 * v4.0.0 - Track all contact interactions for VA relationship building
 */
class PTP_Comms_Hub_Activity_Log {
    
    /**
     * Activity types
     */
    public static function get_activity_types() {
        return array(
            'sms' => array('label' => 'SMS Message', 'icon' => '💬', 'color' => '#3b82f6'),
            'call' => array('label' => 'Phone Call', 'icon' => '📞', 'color' => '#22c55e'),
            'voicemail' => array('label' => 'Voicemail', 'icon' => '📱', 'color' => '#f59e0b'),
            'email' => array('label' => 'Email', 'icon' => '📧', 'color' => '#8b5cf6'),
            'note' => array('label' => 'Note', 'icon' => '📝', 'color' => '#6366f1'),
            'reminder' => array('label' => 'Reminder', 'icon' => '🔔', 'color' => '#ec4899'),
            'order' => array('label' => 'Order', 'icon' => '🛒', 'color' => '#10b981'),
            'registration' => array('label' => 'Registration', 'icon' => '✅', 'color' => '#14b8a6'),
            'campaign' => array('label' => 'Campaign', 'icon' => '📢', 'color' => '#f97316'),
            'hubspot_sync' => array('label' => 'HubSpot Sync', 'icon' => '🔄', 'color' => '#ff7a59'),
            'teams' => array('label' => 'Teams Message', 'icon' => '💼', 'color' => '#6264a7'),
            'segment' => array('label' => 'Segment Change', 'icon' => '🏷️', 'color' => '#84cc16'),
            'status_change' => array('label' => 'Status Change', 'icon' => '🔀', 'color' => '#a855f7'),
            'profile_update' => array('label' => 'Profile Update', 'icon' => '👤', 'color' => '#64748b'),
            'opt_in' => array('label' => 'Opt In', 'icon' => '✔️', 'color' => '#22c55e'),
            'opt_out' => array('label' => 'Opt Out', 'icon' => '❌', 'color' => '#ef4444'),
            'milestone' => array('label' => 'Milestone', 'icon' => '🏆', 'color' => '#eab308'),
            'system' => array('label' => 'System', 'icon' => '⚙️', 'color' => '#64748b')
        );
    }
    
    /**
     * Log an activity
     */
    public static function log($contact_id, $activity_type, $title, $data = array()) {
        global $wpdb;
        
        $insert_data = array(
            'contact_id' => intval($contact_id),
            'user_id' => isset($data['user_id']) ? intval($data['user_id']) : get_current_user_id(),
            'activity_type' => sanitize_text_field($activity_type),
            'activity_subtype' => isset($data['subtype']) ? sanitize_text_field($data['subtype']) : null,
            'title' => sanitize_text_field($title),
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
            'related_id' => isset($data['related_id']) ? intval($data['related_id']) : null,
            'related_type' => isset($data['related_type']) ? sanitize_text_field($data['related_type']) : null,
            'importance' => isset($data['importance']) ? intval($data['importance']) : 5,
            'meta_data' => isset($data['meta']) ? json_encode($data['meta']) : null,
            'created_at' => isset($data['created_at']) ? $data['created_at'] : current_time('mysql')
        );
        
        $result = $wpdb->insert($wpdb->prefix . 'ptp_activity_log', $insert_data);
        
        if ($result) {
            // Update contact last interaction
            $wpdb->update(
                $wpdb->prefix . 'ptp_contacts',
                array(
                    'last_interaction_at' => current_time('mysql'),
                    'total_interactions' => new \stdClass() // Will use raw SQL below
                ),
                array('id' => $contact_id)
            );
            
            // Increment total interactions
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ptp_contacts SET total_interactions = total_interactions + 1 WHERE id = %d",
                $contact_id
            ));
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get activity timeline for a contact
     */
    public static function get_timeline($contact_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'type' => null,
            'from_date' => null,
            'to_date' => null,
            'min_importance' => null,
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("contact_id = %d");
        $params = array($contact_id);
        
        if ($args['type']) {
            if (is_array($args['type'])) {
                $placeholders = implode(',', array_fill(0, count($args['type']), '%s'));
                $where[] = "activity_type IN ({$placeholders})";
                $params = array_merge($params, $args['type']);
            } else {
                $where[] = "activity_type = %s";
                $params[] = $args['type'];
            }
        }
        
        if ($args['from_date']) {
            $where[] = "created_at >= %s";
            $params[] = $args['from_date'];
        }
        
        if ($args['to_date']) {
            $where[] = "created_at <= %s";
            $params[] = $args['to_date'];
        }
        
        if ($args['min_importance']) {
            $where[] = "importance >= %d";
            $params[] = $args['min_importance'];
        }
        
        $where_sql = implode(' AND ', $where);
        $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';
        
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name as user_name
             FROM {$wpdb->prefix}ptp_activity_log a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE {$where_sql}
             ORDER BY a.created_at {$order}
             LIMIT %d OFFSET %d",
            $params
        ));
        
        // Decode meta data
        foreach ($activities as &$activity) {
            if ($activity->meta_data) {
                $activity->meta_data = json_decode($activity->meta_data, true);
            }
        }
        
        return $activities;
    }
    
    /**
     * Get activity timeline with communication logs merged
     */
    public static function get_full_timeline($contact_id, $limit = 100) {
        global $wpdb;
        
        // Get activities
        $activities = self::get_timeline($contact_id, array('limit' => $limit));
        
        // Get communication logs
        $comm_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contact_id, message_type as activity_type, direction as activity_subtype,
                    CASE 
                        WHEN direction = 'inbound' THEN 'Received message'
                        ELSE 'Sent message'
                    END as title,
                    message_content as description,
                    NULL as user_id, NULL as user_name,
                    NULL as related_id, NULL as related_type,
                    5 as importance, 0 as hubspot_synced,
                    meta_data, created_at
             FROM {$wpdb->prefix}ptp_communication_logs
             WHERE contact_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $contact_id,
            $limit
        ));
        
        // Merge and sort
        $timeline = array_merge($activities, $comm_logs);
        
        usort($timeline, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        // Limit combined results
        return array_slice($timeline, 0, $limit);
    }
    
    /**
     * Get recent activities across all contacts
     */
    public static function get_recent($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'type' => null,
            'user_id' => null,
            'hours' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("1=1");
        $params = array();
        
        if ($args['type']) {
            $where[] = "a.activity_type = %s";
            $params[] = $args['type'];
        }
        
        if ($args['user_id']) {
            $where[] = "a.user_id = %d";
            $params[] = $args['user_id'];
        }
        
        if ($args['hours']) {
            $where[] = "a.created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)";
            $params[] = $args['hours'];
        }
        
        $where_sql = implode(' AND ', $where);
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, c.parent_first_name, c.parent_last_name, u.display_name as user_name
             FROM {$wpdb->prefix}ptp_activity_log a
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON a.contact_id = c.id
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE {$where_sql}
             ORDER BY a.created_at DESC
             LIMIT %d OFFSET %d",
            $params
        ));
    }
    
    /**
     * Get activity statistics for a contact
     */
    public static function get_contact_stats($contact_id) {
        global $wpdb;
        
        $stats = array(
            'total' => 0,
            'by_type' => array(),
            'by_month' => array(),
            'first_activity' => null,
            'last_activity' => null
        );
        
        // Total
        $stats['total'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_activity_log WHERE contact_id = %d",
            $contact_id
        ));
        
        // By type
        $type_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT activity_type, COUNT(*) as count 
             FROM {$wpdb->prefix}ptp_activity_log 
             WHERE contact_id = %d 
             GROUP BY activity_type 
             ORDER BY count DESC",
            $contact_id
        ));
        foreach ($type_counts as $tc) {
            $stats['by_type'][$tc->activity_type] = $tc->count;
        }
        
        // By month (last 6 months)
        $monthly = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(created_at, '%%Y-%%m') as month, COUNT(*) as count
             FROM {$wpdb->prefix}ptp_activity_log
             WHERE contact_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY month
             ORDER BY month DESC",
            $contact_id
        ));
        foreach ($monthly as $m) {
            $stats['by_month'][$m->month] = $m->count;
        }
        
        // First and last
        $stats['first_activity'] = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(created_at) FROM {$wpdb->prefix}ptp_activity_log WHERE contact_id = %d",
            $contact_id
        ));
        
        $stats['last_activity'] = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(created_at) FROM {$wpdb->prefix}ptp_activity_log WHERE contact_id = %d",
            $contact_id
        ));
        
        return $stats;
    }
    
    /**
     * Get dashboard activity summary
     */
    public static function get_dashboard_summary($hours = 24) {
        global $wpdb;
        
        $types = $wpdb->get_results($wpdb->prepare(
            "SELECT activity_type, COUNT(*) as count
             FROM {$wpdb->prefix}ptp_activity_log
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
             GROUP BY activity_type
             ORDER BY count DESC",
            $hours
        ));
        
        $summary = array(
            'total' => 0,
            'types' => array()
        );
        
        foreach ($types as $type) {
            $summary['types'][$type->activity_type] = $type->count;
            $summary['total'] += $type->count;
        }
        
        return $summary;
    }
    
    /**
     * Delete old activities
     */
    public static function cleanup($days = 365) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ptp_activity_log 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
             AND importance < 8",
            $days
        ));
    }
    
    /**
     * Log SMS activity
     */
    public static function log_sms($contact_id, $direction, $message, $meta = array()) {
        return self::log($contact_id, 'sms', 
            $direction === 'inbound' ? 'SMS received' : 'SMS sent',
            array(
                'subtype' => $direction,
                'description' => substr($message, 0, 200),
                'meta' => $meta
            )
        );
    }
    
    /**
     * Log call activity
     */
    public static function log_call($contact_id, $direction, $duration = 0, $meta = array()) {
        $title = $direction === 'inbound' ? 'Incoming call' : 'Outgoing call';
        if ($duration > 0) {
            $title .= ' (' . gmdate('i:s', $duration) . ')';
        }
        
        return self::log($contact_id, 'call', $title, array(
            'subtype' => $direction,
            'meta' => array_merge($meta, array('duration' => $duration))
        ));
    }
    
    /**
     * Log order activity
     */
    public static function log_order($contact_id, $order_id, $total, $items = array()) {
        return self::log($contact_id, 'order', 'New order #' . $order_id, array(
            'description' => 'Order total: $' . number_format($total, 2),
            'related_id' => $order_id,
            'related_type' => 'order',
            'importance' => 8,
            'meta' => array('total' => $total, 'items' => $items)
        ));
    }
    
    /**
     * Log registration activity
     */
    public static function log_registration($contact_id, $event_name, $registration_id = null) {
        return self::log($contact_id, 'registration', 'Registered for ' . $event_name, array(
            'related_id' => $registration_id,
            'related_type' => 'registration',
            'importance' => 8
        ));
    }
    
    /**
     * Log opt-in/opt-out
     */
    public static function log_opt_change($contact_id, $opted_in) {
        $type = $opted_in ? 'opt_in' : 'opt_out';
        $title = $opted_in ? 'Opted in to SMS' : 'Opted out of SMS';
        
        return self::log($contact_id, $type, $title, array('importance' => 7));
    }
    
    /**
     * Log milestone
     */
    public static function log_milestone($contact_id, $milestone, $description = '') {
        return self::log($contact_id, 'milestone', $milestone, array(
            'description' => $description,
            'importance' => 9
        ));
    }
    
    /**
     * Search activities
     */
    public static function search($query, $contact_id = null, $limit = 50) {
        global $wpdb;
        
        $search = '%' . $wpdb->esc_like($query) . '%';
        
        $where = "(a.title LIKE %s OR a.description LIKE %s)";
        $params = array($search, $search);
        
        if ($contact_id) {
            $where .= " AND a.contact_id = %d";
            $params[] = $contact_id;
        }
        
        $params[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, c.parent_first_name, c.parent_last_name
             FROM {$wpdb->prefix}ptp_activity_log a
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON a.contact_id = c.id
             WHERE {$where}
             ORDER BY a.created_at DESC
             LIMIT %d",
            $params
        ));
    }
}
