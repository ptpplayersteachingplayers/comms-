<?php
/**
 * Templates management class
 */
class PTP_Comms_Hub_Templates {
    
    public static function get_template($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_templates WHERE id = %d",
            $id
        ));
    }
    
    public static function get_all_templates($category = null) {
        global $wpdb;
        
        $where = "1=1";
        if ($category) {
            $where .= $wpdb->prepare(" AND category = %s", $category);
        }
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ptp_templates WHERE $where ORDER BY name ASC"
        );
    }
    
    public static function create_template($data) {
        global $wpdb;
        
        $defaults = array(
            'message_type' => 'sms',
            'is_active' => 1,
            'usage_count' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptp_templates',
            $data
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function update_template($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_templates',
            $data,
            array('id' => $id)
        );
    }
    
    public static function delete_template($id) {
        global $wpdb;
        return $wpdb->delete(
            $wpdb->prefix . 'ptp_templates',
            array('id' => $id)
        );
    }
    
    public static function increment_usage($id) {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ptp_templates SET usage_count = usage_count + 1 WHERE id = %d",
            $id
        ));
    }
    
    public static function render_template($template, $contact, $event = array()) {
        if (is_numeric($template)) {
            $template = self::get_template($template);
        }
        
        if (!$template || !isset($template->content)) {
            return '';
        }
        
        $contact_array = is_array($contact) ? $contact : (array) $contact;
        $event_array = is_array($event) ? $event : (array) $event;
        
        return ptp_comms_replace_variables($template->content, $contact_array, $event_array);
    }
}
