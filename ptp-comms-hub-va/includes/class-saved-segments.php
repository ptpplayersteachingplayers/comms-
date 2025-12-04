<?php
/**
 * Saved Segments Management
 * v4.0.0 - Advanced segmentation for targeted campaigns
 */
class PTP_Comms_Hub_Saved_Segments {
    
    /**
     * Segment types
     */
    public static function get_segment_types() {
        return array(
            'smart' => 'Smart Segment (Dynamic)',
            'static' => 'Static Segment (Manual)',
            'custom' => 'Custom Query',
            'hubspot' => 'HubSpot List Sync'
        );
    }
    
    /**
     * Available operators for conditions
     */
    public static function get_operators() {
        return array(
            '=' => 'Equals',
            '!=' => 'Does not equal',
            '>' => 'Greater than',
            '>=' => 'Greater than or equal',
            '<' => 'Less than',
            '<=' => 'Less than or equal',
            'contains' => 'Contains',
            'not_contains' => 'Does not contain',
            'starts_with' => 'Starts with',
            'ends_with' => 'Ends with',
            'is_empty' => 'Is empty',
            'is_not_empty' => 'Is not empty',
            'in' => 'Is one of',
            'not_in' => 'Is not one of',
            'within' => 'Within last X days',
            'older_than' => 'Older than X days'
        );
    }
    
    /**
     * Available fields for segmentation
     */
    public static function get_available_fields() {
        return array(
            'contact' => array(
                'label' => 'Contact Fields',
                'fields' => array(
                    'parent_first_name' => 'First Name',
                    'parent_last_name' => 'Last Name',
                    'parent_email' => 'Email',
                    'parent_phone' => 'Phone',
                    'child_name' => 'Child Name',
                    'child_age' => 'Child Age',
                    'state' => 'State',
                    'city' => 'City',
                    'zip_code' => 'ZIP Code',
                    'source' => 'Source',
                    'opted_in' => 'SMS Opted In',
                    'opted_out' => 'Opted Out',
                    'vip_status' => 'VIP Status',
                    'relationship_score' => 'Relationship Score',
                    'total_orders' => 'Total Orders',
                    'lifetime_value' => 'Lifetime Value',
                    'created_at' => 'Created Date',
                    'last_interaction_at' => 'Last Interaction'
                )
            ),
            'engagement' => array(
                'label' => 'Engagement',
                'fields' => array(
                    'total_interactions' => 'Total Interactions',
                    'notes_count' => 'Notes Count',
                    'last_message_at' => 'Last Message Date'
                )
            ),
            'hubspot' => array(
                'label' => 'HubSpot',
                'fields' => array(
                    'hubspot_lifecycle_stage' => 'Lifecycle Stage',
                    'hubspot_contact_id' => 'Has HubSpot ID'
                )
            ),
            'tags' => array(
                'label' => 'Tags & Segments',
                'fields' => array(
                    'segments' => 'Has Segment Tag',
                    'tags' => 'Has Tag'
                )
            )
        );
    }
    
    /**
     * Create a segment
     */
    public static function create($data) {
        global $wpdb;
        
        $insert_data = array(
            'name' => isset($data['name']) ? sanitize_text_field($data['name']) : '',
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
            'segment_type' => isset($data['segment_type']) ? sanitize_text_field($data['segment_type']) : 'smart',
            'criteria' => isset($data['criteria']) ? (is_array($data['criteria']) ? json_encode($data['criteria']) : $data['criteria']) : '{}',
            'is_dynamic' => isset($data['is_dynamic']) ? intval($data['is_dynamic']) : 1,
            'hubspot_list_id' => isset($data['hubspot_list_id']) ? sanitize_text_field($data['hubspot_list_id']) : null,
            'created_by' => get_current_user_id(),
            'is_active' => 1,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($wpdb->prefix . 'ptp_saved_segments', $insert_data);
        
        if ($result) {
            $segment_id = $wpdb->insert_id;
            
            // Cache initial count
            self::update_cached_count($segment_id);
            
            return $segment_id;
        }
        
        return false;
    }
    
    /**
     * Update a segment
     */
    public static function update($segment_id, $data) {
        global $wpdb;
        
        $update_data = array('updated_at' => current_time('mysql'));
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['criteria'])) {
            $update_data['criteria'] = is_array($data['criteria']) ? json_encode($data['criteria']) : $data['criteria'];
        }
        if (isset($data['is_active'])) {
            $update_data['is_active'] = intval($data['is_active']);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ptp_saved_segments',
            $update_data,
            array('id' => $segment_id)
        );
        
        if ($result !== false) {
            // Update cached count
            self::update_cached_count($segment_id);
        }
        
        return $result;
    }
    
    /**
     * Delete a segment
     */
    public static function delete($segment_id) {
        global $wpdb;
        
        // Delete segment members first
        $wpdb->delete($wpdb->prefix . 'ptp_segment_members', array('segment_id' => $segment_id));
        
        // Delete segment
        return $wpdb->delete($wpdb->prefix . 'ptp_saved_segments', array('id' => $segment_id));
    }
    
    /**
     * Get a segment
     */
    public static function get($segment_id) {
        global $wpdb;
        
        $segment = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, u.display_name as created_by_name
             FROM {$wpdb->prefix}ptp_saved_segments s
             LEFT JOIN {$wpdb->users} u ON s.created_by = u.ID
             WHERE s.id = %d",
            $segment_id
        ));
        
        if ($segment && $segment->criteria) {
            $segment->criteria = json_decode($segment->criteria, true);
        }
        
        return $segment;
    }
    
    /**
     * Get all segments
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'active_only' => true,
            'type' => null,
            'limit' => 100,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array("1=1");
        $params = array();
        
        if ($args['active_only']) {
            $where[] = "is_active = 1";
        }
        
        if ($args['type']) {
            $where[] = "segment_type = %s";
            $params[] = $args['type'];
        }
        
        $where_sql = implode(' AND ', $where);
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $segments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_saved_segments 
             WHERE {$where_sql}
             ORDER BY name ASC
             LIMIT %d OFFSET %d",
            $params
        ));
        
        foreach ($segments as &$segment) {
            if ($segment->criteria) {
                $segment->criteria = json_decode($segment->criteria, true);
            }
        }
        
        return $segments;
    }
    
    /**
     * Get contacts in a segment
     */
    public static function get_contacts($segment_id, $limit = null, $offset = 0) {
        global $wpdb;
        
        $segment = self::get($segment_id);
        if (!$segment) {
            return array();
        }
        
        // For static segments, get from members table
        if ($segment->segment_type === 'static') {
            $limit_sql = $limit ? $wpdb->prepare("LIMIT %d OFFSET %d", $limit, $offset) : "";
            
            return $wpdb->get_results("
                SELECT c.* FROM {$wpdb->prefix}ptp_contacts c
                INNER JOIN {$wpdb->prefix}ptp_segment_members sm ON c.id = sm.contact_id
                WHERE sm.segment_id = {$segment_id}
                ORDER BY c.parent_last_name, c.parent_first_name
                {$limit_sql}
            ");
        }
        
        // For dynamic segments, build query from criteria
        return self::query_by_criteria($segment->criteria, $limit, $offset);
    }
    
    /**
     * Get contact IDs in a segment (for campaigns)
     */
    public static function get_contact_ids($segment_id) {
        global $wpdb;
        
        $segment = self::get($segment_id);
        if (!$segment) {
            return array();
        }
        
        if ($segment->segment_type === 'static') {
            return $wpdb->get_col($wpdb->prepare(
                "SELECT contact_id FROM {$wpdb->prefix}ptp_segment_members WHERE segment_id = %d",
                $segment_id
            ));
        }
        
        $contacts = self::query_by_criteria($segment->criteria);
        return array_column($contacts, 'id');
    }
    
    /**
     * Query contacts by criteria
     */
    public static function query_by_criteria($criteria, $limit = null, $offset = 0) {
        global $wpdb;
        
        if (empty($criteria) || !isset($criteria['conditions'])) {
            return array();
        }
        
        $logic = isset($criteria['logic']) ? strtoupper($criteria['logic']) : 'AND';
        if (!in_array($logic, array('AND', 'OR'))) {
            $logic = 'AND';
        }
        
        $where_clauses = array();
        $params = array();
        
        foreach ($criteria['conditions'] as $condition) {
            $clause = self::build_condition_clause($condition, $params);
            if ($clause) {
                $where_clauses[] = $clause;
            }
        }
        
        if (empty($where_clauses)) {
            return array();
        }
        
        $where_sql = '(' . implode(" {$logic} ", $where_clauses) . ')';
        
        // Always filter for opted-in unless specifically querying opted-out
        $has_opt_condition = false;
        foreach ($criteria['conditions'] as $cond) {
            if (in_array($cond['field'], array('opted_in', 'opted_out'))) {
                $has_opt_condition = true;
                break;
            }
        }
        
        if (!$has_opt_condition) {
            $where_sql .= " AND opted_in = 1 AND opted_out = 0";
        }
        
        $limit_sql = "";
        if ($limit) {
            $limit_sql = $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        if (!empty($params)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE {$where_sql} ORDER BY parent_last_name, parent_first_name {$limit_sql}",
                $params
            );
        } else {
            $query = "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE {$where_sql} ORDER BY parent_last_name, parent_first_name {$limit_sql}";
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Build SQL clause from condition
     */
    private static function build_condition_clause($condition, &$params) {
        global $wpdb;
        
        $field = sanitize_key($condition['field']);
        $operator = isset($condition['operator']) ? $condition['operator'] : '=';
        $value = isset($condition['value']) ? $condition['value'] : '';
        
        // Validate field exists
        $valid_fields = array_merge(
            array_keys(self::get_available_fields()['contact']['fields']),
            array_keys(self::get_available_fields()['engagement']['fields']),
            array_keys(self::get_available_fields()['hubspot']['fields']),
            array_keys(self::get_available_fields()['tags']['fields'])
        );
        
        if (!in_array($field, $valid_fields)) {
            return null;
        }
        
        switch ($operator) {
            case '=':
                $params[] = $value;
                return "{$field} = %s";
                
            case '!=':
                $params[] = $value;
                return "{$field} != %s";
                
            case '>':
                $params[] = $value;
                return "{$field} > %s";
                
            case '>=':
                $params[] = $value;
                return "{$field} >= %s";
                
            case '<':
                $params[] = $value;
                return "{$field} < %s";
                
            case '<=':
                $params[] = $value;
                return "{$field} <= %s";
                
            case 'contains':
                $params[] = '%' . $wpdb->esc_like($value) . '%';
                return "{$field} LIKE %s";
                
            case 'not_contains':
                $params[] = '%' . $wpdb->esc_like($value) . '%';
                return "{$field} NOT LIKE %s";
                
            case 'starts_with':
                $params[] = $wpdb->esc_like($value) . '%';
                return "{$field} LIKE %s";
                
            case 'ends_with':
                $params[] = '%' . $wpdb->esc_like($value);
                return "{$field} LIKE %s";
                
            case 'is_empty':
                return "({$field} IS NULL OR {$field} = '')";
                
            case 'is_not_empty':
                return "({$field} IS NOT NULL AND {$field} != '')";
                
            case 'in':
                $values = is_array($value) ? $value : explode(',', $value);
                $values = array_map('trim', $values);
                $placeholders = implode(',', array_fill(0, count($values), '%s'));
                $params = array_merge($params, $values);
                return "{$field} IN ({$placeholders})";
                
            case 'not_in':
                $values = is_array($value) ? $value : explode(',', $value);
                $values = array_map('trim', $values);
                $placeholders = implode(',', array_fill(0, count($values), '%s'));
                $params = array_merge($params, $values);
                return "{$field} NOT IN ({$placeholders})";
                
            case 'within':
                // Value is number of days
                $days = intval($value);
                return "{$field} >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
                
            case 'older_than':
                // Value is number of days
                $days = intval($value);
                return "({$field} IS NULL OR {$field} < DATE_SUB(NOW(), INTERVAL {$days} DAY))";
                
            default:
                $params[] = $value;
                return "{$field} = %s";
        }
    }
    
    /**
     * Count contacts in segment
     */
    public static function count_contacts($segment_id) {
        global $wpdb;
        
        $segment = self::get($segment_id);
        if (!$segment) {
            return 0;
        }
        
        if ($segment->segment_type === 'static') {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_segment_members WHERE segment_id = %d",
                $segment_id
            ));
        }
        
        $contacts = self::query_by_criteria($segment->criteria);
        return count($contacts);
    }
    
    /**
     * Update cached count
     */
    public static function update_cached_count($segment_id) {
        global $wpdb;
        
        $count = self::count_contacts($segment_id);
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_saved_segments',
            array(
                'cached_count' => $count,
                'cache_updated_at' => current_time('mysql')
            ),
            array('id' => $segment_id)
        );
        
        return $count;
    }
    
    /**
     * Add contact to static segment
     */
    public static function add_member($segment_id, $contact_id) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_segment_members WHERE segment_id = %d AND contact_id = %d",
            $segment_id,
            $contact_id
        ));
        
        if ($exists) {
            return true;
        }
        
        return $wpdb->insert(
            $wpdb->prefix . 'ptp_segment_members',
            array(
                'segment_id' => $segment_id,
                'contact_id' => $contact_id,
                'added_by' => get_current_user_id(),
                'added_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Remove contact from static segment
     */
    public static function remove_member($segment_id, $contact_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'ptp_segment_members',
            array('segment_id' => $segment_id, 'contact_id' => $contact_id)
        );
    }
    
    /**
     * Bulk add members to segment
     */
    public static function bulk_add_members($segment_id, $contact_ids) {
        $added = 0;
        foreach ($contact_ids as $contact_id) {
            if (self::add_member($segment_id, intval($contact_id))) {
                $added++;
            }
        }
        
        self::update_cached_count($segment_id);
        return $added;
    }
    
    /**
     * Duplicate a segment
     */
    public static function duplicate($segment_id) {
        $segment = self::get($segment_id);
        if (!$segment) {
            return false;
        }
        
        return self::create(array(
            'name' => $segment->name . ' (Copy)',
            'description' => $segment->description,
            'segment_type' => $segment->segment_type,
            'criteria' => $segment->criteria,
            'is_dynamic' => $segment->is_dynamic
        ));
    }
    
    /**
     * Get segments for dropdown
     */
    public static function get_for_dropdown() {
        $segments = self::get_all(array('active_only' => true));
        
        $options = array();
        foreach ($segments as $segment) {
            $options[$segment->id] = $segment->name . ' (' . $segment->cached_count . ')';
        }
        
        return $options;
    }
    
    /**
     * Check if contact is in segment
     */
    public static function contact_in_segment($contact_id, $segment_id) {
        $contact_ids = self::get_contact_ids($segment_id);
        return in_array($contact_id, $contact_ids);
    }
    
    /**
     * Get segments a contact belongs to
     */
    public static function get_contact_segments($contact_id) {
        global $wpdb;
        
        $segments = self::get_all();
        $contact_segments = array();
        
        foreach ($segments as $segment) {
            if (self::contact_in_segment($contact_id, $segment->id)) {
                $contact_segments[] = $segment;
            }
        }
        
        return $contact_segments;
    }
}
