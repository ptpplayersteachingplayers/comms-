<?php
/**
 * Contact Notes Management
 * v4.0.0 - VA relationship building with detailed note-taking
 */
class PTP_Comms_Hub_Contact_Notes {
    
    /**
     * Note types available
     */
    public static function get_note_types() {
        return array(
            'general' => array('label' => 'General Note', 'icon' => '📝'),
            'call' => array('label' => 'Call Summary', 'icon' => '📞'),
            'meeting' => array('label' => 'Meeting Notes', 'icon' => '👥'),
            'feedback' => array('label' => 'Customer Feedback', 'icon' => '💬'),
            'concern' => array('label' => 'Concern/Issue', 'icon' => '⚠️'),
            'preference' => array('label' => 'Preference/Interest', 'icon' => '⭐'),
            'family_info' => array('label' => 'Family Information', 'icon' => '👨‍👩‍👧'),
            'milestone' => array('label' => 'Milestone/Achievement', 'icon' => '🏆'),
            'follow_up' => array('label' => 'Follow-up Required', 'icon' => '📌'),
            'referral' => array('label' => 'Referral Info', 'icon' => '🤝'),
            'complaint' => array('label' => 'Complaint', 'icon' => '❗'),
            'resolution' => array('label' => 'Issue Resolution', 'icon' => '✅')
        );
    }
    
    /**
     * Sentiment options
     */
    public static function get_sentiments() {
        return array(
            'very_positive' => array('label' => 'Very Positive', 'icon' => '😄', 'color' => '#22c55e'),
            'positive' => array('label' => 'Positive', 'icon' => '🙂', 'color' => '#84cc16'),
            'neutral' => array('label' => 'Neutral', 'icon' => '😐', 'color' => '#6b7280'),
            'negative' => array('label' => 'Negative', 'icon' => '😕', 'color' => '#f59e0b'),
            'very_negative' => array('label' => 'Very Negative', 'icon' => '😞', 'color' => '#ef4444')
        );
    }
    
    /**
     * Add a note to a contact
     */
    public static function add_note($contact_id, $data) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        $insert_data = array(
            'contact_id' => intval($contact_id),
            'user_id' => $user_id,
            'note_type' => isset($data['note_type']) ? sanitize_text_field($data['note_type']) : 'general',
            'title' => isset($data['title']) ? sanitize_text_field($data['title']) : '',
            'content' => isset($data['content']) ? sanitize_textarea_field($data['content']) : '',
            'is_pinned' => isset($data['is_pinned']) ? intval($data['is_pinned']) : 0,
            'is_private' => isset($data['is_private']) ? intval($data['is_private']) : 0,
            'sentiment' => isset($data['sentiment']) ? sanitize_text_field($data['sentiment']) : 'neutral',
            'related_order_id' => isset($data['related_order_id']) ? intval($data['related_order_id']) : null,
            'related_interaction_id' => isset($data['related_interaction_id']) ? intval($data['related_interaction_id']) : null,
            'follow_up_date' => isset($data['follow_up_date']) && !empty($data['follow_up_date']) ? sanitize_text_field($data['follow_up_date']) : null,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($wpdb->prefix . 'ptp_contact_notes', $insert_data);
        
        if ($result) {
            $note_id = $wpdb->insert_id;
            
            // Update contact notes count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ptp_contacts SET notes_count = notes_count + 1, last_interaction_at = %s WHERE id = %d",
                current_time('mysql'),
                $contact_id
            ));
            
            // Log activity
            self::log_activity($contact_id, 'note_added', $insert_data['title'] ?: 'Note added');
            
            // Update relationship score based on note
            self::update_relationship_score($contact_id, $insert_data['sentiment']);
            
            // Create reminder if follow-up date set
            if (!empty($insert_data['follow_up_date'])) {
                self::create_follow_up_reminder($contact_id, $note_id, $insert_data);
            }
            
            // Queue HubSpot sync if enabled
            if (ptp_comms_get_setting('hubspot_sync_notes', true)) {
                PTP_Comms_Hub_HubSpot_Sync::queue_sync(
                    $contact_id,
                    'note',
                    'to_hubspot',
                    array(
                        'note_id' => $note_id,
                        'title' => $insert_data['title'],
                        'content' => $insert_data['content'],
                        'type' => $insert_data['note_type']
                    )
                );
            }
            
            return $note_id;
        }
        
        return false;
    }
    
    /**
     * Update a note
     */
    public static function update_note($note_id, $data) {
        global $wpdb;
        
        $update_data = array(
            'updated_at' => current_time('mysql')
        );
        
        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['content'])) {
            $update_data['content'] = sanitize_textarea_field($data['content']);
        }
        if (isset($data['note_type'])) {
            $update_data['note_type'] = sanitize_text_field($data['note_type']);
        }
        if (isset($data['is_pinned'])) {
            $update_data['is_pinned'] = intval($data['is_pinned']);
        }
        if (isset($data['sentiment'])) {
            $update_data['sentiment'] = sanitize_text_field($data['sentiment']);
        }
        if (isset($data['follow_up_date'])) {
            $update_data['follow_up_date'] = !empty($data['follow_up_date']) ? sanitize_text_field($data['follow_up_date']) : null;
        }
        if (isset($data['follow_up_completed'])) {
            $update_data['follow_up_completed'] = intval($data['follow_up_completed']);
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_contact_notes',
            $update_data,
            array('id' => $note_id)
        );
    }
    
    /**
     * Delete a note
     */
    public static function delete_note($note_id) {
        global $wpdb;
        
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT contact_id FROM {$wpdb->prefix}ptp_contact_notes WHERE id = %d",
            $note_id
        ));
        
        if ($note) {
            $result = $wpdb->delete(
                $wpdb->prefix . 'ptp_contact_notes',
                array('id' => $note_id)
            );
            
            if ($result) {
                // Update contact notes count
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ptp_contacts SET notes_count = GREATEST(notes_count - 1, 0) WHERE id = %d",
                    $note->contact_id
                ));
            }
            
            return $result;
        }
        
        return false;
    }
    
    /**
     * Get notes for a contact
     */
    public static function get_notes($contact_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'type' => null,
            'pinned_only' => false,
            'with_follow_up' => false,
            'include_private' => true,
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("contact_id = %d");
        $params = array($contact_id);
        
        if ($args['type']) {
            $where[] = "note_type = %s";
            $params[] = $args['type'];
        }
        
        if ($args['pinned_only']) {
            $where[] = "is_pinned = 1";
        }
        
        if ($args['with_follow_up']) {
            $where[] = "follow_up_date IS NOT NULL AND follow_up_completed = 0";
        }
        
        if (!$args['include_private'] && !current_user_can('manage_options')) {
            $where[] = "(is_private = 0 OR user_id = %d)";
            $params[] = get_current_user_id();
        }
        
        $where_sql = implode(' AND ', $where);
        $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';
        
        // Pinned notes first, then by date
        $sql = $wpdb->prepare(
            "SELECT n.*, u.display_name as author_name 
             FROM {$wpdb->prefix}ptp_contact_notes n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE {$where_sql}
             ORDER BY n.is_pinned DESC, n.created_at {$order}
             LIMIT %d OFFSET %d",
            array_merge($params, array($args['limit'], $args['offset']))
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get single note
     */
    public static function get_note($note_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT n.*, u.display_name as author_name 
             FROM {$wpdb->prefix}ptp_contact_notes n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE n.id = %d",
            $note_id
        ));
    }
    
    /**
     * Toggle pin status
     */
    public static function toggle_pin($note_id) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ptp_contact_notes SET is_pinned = 1 - is_pinned WHERE id = %d",
            $note_id
        ));
    }
    
    /**
     * Get notes requiring follow-up
     */
    public static function get_pending_follow_ups($user_id = null, $days_ahead = 7) {
        global $wpdb;
        
        $where = array(
            "follow_up_date IS NOT NULL",
            "follow_up_completed = 0",
            "follow_up_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)"
        );
        $params = array($days_ahead);
        
        if ($user_id) {
            $where[] = "user_id = %d";
            $params[] = $user_id;
        }
        
        $where_sql = implode(' AND ', $where);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, c.parent_first_name, c.parent_last_name, c.parent_phone
             FROM {$wpdb->prefix}ptp_contact_notes n
             JOIN {$wpdb->prefix}ptp_contacts c ON n.contact_id = c.id
             WHERE {$where_sql}
             ORDER BY n.follow_up_date ASC",
            $params
        ));
    }
    
    /**
     * Search notes
     */
    public static function search($query, $contact_id = null, $limit = 50) {
        global $wpdb;
        
        $search = '%' . $wpdb->esc_like($query) . '%';
        
        $where = "(n.title LIKE %s OR n.content LIKE %s)";
        $params = array($search, $search);
        
        if ($contact_id) {
            $where .= " AND n.contact_id = %d";
            $params[] = $contact_id;
        }
        
        $params[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, c.parent_first_name, c.parent_last_name, u.display_name as author_name
             FROM {$wpdb->prefix}ptp_contact_notes n
             JOIN {$wpdb->prefix}ptp_contacts c ON n.contact_id = c.id
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE {$where}
             ORDER BY n.created_at DESC
             LIMIT %d",
            $params
        ));
    }
    
    /**
     * Get note statistics for a contact
     */
    public static function get_contact_note_stats($contact_id) {
        global $wpdb;
        
        $stats = array(
            'total' => 0,
            'by_type' => array(),
            'by_sentiment' => array(),
            'pending_follow_ups' => 0,
            'last_note_date' => null
        );
        
        // Total count
        $stats['total'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contact_notes WHERE contact_id = %d",
            $contact_id
        ));
        
        // By type
        $type_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT note_type, COUNT(*) as count FROM {$wpdb->prefix}ptp_contact_notes WHERE contact_id = %d GROUP BY note_type",
            $contact_id
        ));
        foreach ($type_counts as $tc) {
            $stats['by_type'][$tc->note_type] = $tc->count;
        }
        
        // By sentiment
        $sentiment_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT sentiment, COUNT(*) as count FROM {$wpdb->prefix}ptp_contact_notes WHERE contact_id = %d GROUP BY sentiment",
            $contact_id
        ));
        foreach ($sentiment_counts as $sc) {
            $stats['by_sentiment'][$sc->sentiment] = $sc->count;
        }
        
        // Pending follow-ups
        $stats['pending_follow_ups'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contact_notes WHERE contact_id = %d AND follow_up_date IS NOT NULL AND follow_up_completed = 0",
            $contact_id
        ));
        
        // Last note date
        $stats['last_note_date'] = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(created_at) FROM {$wpdb->prefix}ptp_contact_notes WHERE contact_id = %d",
            $contact_id
        ));
        
        return $stats;
    }
    
    /**
     * Update relationship score based on sentiment
     */
    private static function update_relationship_score($contact_id, $sentiment) {
        global $wpdb;
        
        $adjustment = 0;
        switch ($sentiment) {
            case 'very_positive':
                $adjustment = 5;
                break;
            case 'positive':
                $adjustment = 2;
                break;
            case 'neutral':
                $adjustment = 1;
                break;
            case 'negative':
                $adjustment = -3;
                break;
            case 'very_negative':
                $adjustment = -5;
                break;
        }
        
        if ($adjustment !== 0) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ptp_contacts 
                 SET relationship_score = LEAST(GREATEST(relationship_score + %d, 0), 100),
                     total_interactions = total_interactions + 1
                 WHERE id = %d",
                $adjustment,
                $contact_id
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
                'activity_type' => 'note',
                'activity_subtype' => $type,
                'title' => $title,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Create follow-up reminder from note
     */
    private static function create_follow_up_reminder($contact_id, $note_id, $note_data) {
        if (!class_exists('PTP_Comms_Hub_Reminders')) {
            return false;
        }
        
        return PTP_Comms_Hub_Reminders::create(array(
            'contact_id' => $contact_id,
            'reminder_type' => 'note_follow_up',
            'title' => 'Follow up: ' . ($note_data['title'] ?: 'Note'),
            'description' => substr($note_data['content'], 0, 200),
            'due_date' => $note_data['follow_up_date'] . ' 09:00:00',
            'related_note_id' => $note_id
        ));
    }
    
    /**
     * Quick note - simplified note creation
     */
    public static function quick_note($contact_id, $content, $type = 'general') {
        return self::add_note($contact_id, array(
            'content' => $content,
            'note_type' => $type
        ));
    }
}
