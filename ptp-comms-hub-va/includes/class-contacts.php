<?php
/**
 * Contacts management class
 */
class PTP_Comms_Hub_Contacts {
    
    public static function get_contact($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $id
        ));
    }
    
    public static function create_contact($data) {
        global $wpdb;
        
        // Normalize phone if provided
        if (!empty($data['parent_phone'])) {
            $data['parent_phone'] = ptp_comms_normalize_phone($data['parent_phone']);
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptp_contacts',
            $data
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function update_contact($id, $data) {
        global $wpdb;
        
        if (!empty($data['parent_phone'])) {
            $data['parent_phone'] = ptp_comms_normalize_phone($data['parent_phone']);
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_contacts',
            $data,
            array('id' => $id)
        );
    }
    
    public static function delete_contact($id) {
        global $wpdb;
        return $wpdb->delete(
            $wpdb->prefix . 'ptp_contacts',
            array('id' => $id)
        );
    }
    
    public static function get_all_contacts($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'search' => '',
            'opted_in' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = "1=1";
        
        if ($args['opted_in'] !== null) {
            $where .= $wpdb->prepare(" AND opted_in = %d", $args['opted_in']);
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= $wpdb->prepare(
                " AND (parent_first_name LIKE %s OR parent_last_name LIKE %s OR parent_email LIKE %s OR parent_phone LIKE %s)",
                $search, $search, $search, $search
            );
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        ));
    }
    
    public static function opt_out($contact_id) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . 'ptp_contacts',
            array('opted_out' => 1, 'opted_in' => 0),
            array('id' => $contact_id)
        );
    }
    
    public static function opt_in($contact_id) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . 'ptp_contacts',
            array('opted_in' => 1, 'opted_out' => 0),
            array('id' => $contact_id)
        );
    }
}
