<?php
/**
 * Canned Replies / Quick Response Snippets
 * v3.4.0 - Reusable message templates for fast responses
 */
class PTP_Comms_Hub_Canned_Replies {
    
    /**
     * Get all canned replies
     */
    public static function get_all($category = null) {
        global $wpdb;
        
        $where = "1=1";
        if ($category) {
            $where = $wpdb->prepare("category = %s", $category);
        }
        
        return $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}ptp_canned_replies 
            WHERE {$where}
            ORDER BY category ASC, sort_order ASC, name ASC
        ");
    }
    
    /**
     * Get single canned reply
     */
    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_canned_replies WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create new canned reply
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = array(
            'category' => 'general',
            'sort_order' => 0,
            'is_active' => 1,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptp_canned_replies',
            array(
                'name' => sanitize_text_field($data['name']),
                'shortcut' => sanitize_key($data['shortcut']),
                'content' => sanitize_textarea_field($data['content']),
                'category' => sanitize_key($data['category']),
                'sort_order' => intval($data['sort_order']),
                'is_active' => intval($data['is_active']),
                'created_by' => intval($data['created_by']),
                'created_at' => $data['created_at']
            )
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update canned reply
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['shortcut'])) {
            $update_data['shortcut'] = sanitize_key($data['shortcut']);
        }
        if (isset($data['content'])) {
            $update_data['content'] = sanitize_textarea_field($data['content']);
        }
        if (isset($data['category'])) {
            $update_data['category'] = sanitize_key($data['category']);
        }
        if (isset($data['sort_order'])) {
            $update_data['sort_order'] = intval($data['sort_order']);
        }
        if (isset($data['is_active'])) {
            $update_data['is_active'] = intval($data['is_active']);
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_canned_replies',
            $update_data,
            array('id' => $id)
        );
    }
    
    /**
     * Delete canned reply
     */
    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete(
            $wpdb->prefix . 'ptp_canned_replies',
            array('id' => $id)
        );
    }
    
    /**
     * Get categories
     */
    public static function get_categories() {
        return array(
            'general' => 'General',
            'greeting' => 'Greetings',
            'confirmation' => 'Confirmations',
            'reminder' => 'Reminders',
            'follow-up' => 'Follow-ups',
            'support' => 'Support',
            'weather' => 'Weather/Cancellations',
            'upsell' => 'Upsell/Promotions'
        );
    }
    
    /**
     * Get replies grouped by category for dropdown
     */
    public static function get_grouped_for_dropdown() {
        $replies = self::get_all();
        $categories = self::get_categories();
        $grouped = array();
        
        foreach ($categories as $key => $label) {
            $grouped[$key] = array(
                'label' => $label,
                'replies' => array()
            );
        }
        
        foreach ($replies as $reply) {
            if (!$reply->is_active) continue;
            
            $cat = isset($grouped[$reply->category]) ? $reply->category : 'general';
            $grouped[$cat]['replies'][] = $reply;
        }
        
        // Remove empty categories
        return array_filter($grouped, function($group) {
            return !empty($group['replies']);
        });
    }
    
    /**
     * Find reply by shortcut
     */
    public static function find_by_shortcut($shortcut) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_canned_replies WHERE shortcut = %s AND is_active = 1",
            sanitize_key($shortcut)
        ));
    }
    
    /**
     * Search replies
     */
    public static function search($query) {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($query) . '%';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_canned_replies 
             WHERE is_active = 1 AND (name LIKE %s OR content LIKE %s OR shortcut LIKE %s)
             ORDER BY name ASC LIMIT 10",
            $like, $like, $like
        ));
    }
    
    /**
     * Install default canned replies
     */
    public static function install_defaults() {
        $defaults = array(
            array(
                'name' => 'Quick Thanks',
                'shortcut' => 'thanks',
                'content' => "Thank you for reaching out! We'll get back to you shortly.",
                'category' => 'general'
            ),
            array(
                'name' => 'Registration Confirmed',
                'shortcut' => 'confirmed',
                'content' => "Great news! Your registration for {event_name} on {event_date} is confirmed. See you there!",
                'category' => 'confirmation'
            ),
            array(
                'name' => 'Camp Reminder',
                'shortcut' => 'reminder',
                'content' => "Hi {parent_name}! Just a friendly reminder that {child_name}'s camp is coming up on {event_date} at {event_location}. Don't forget to bring: {what_to_bring}",
                'category' => 'reminder'
            ),
            array(
                'name' => 'Weather Update',
                'shortcut' => 'weather',
                'content' => "Weather Update: Camp is still ON for today! Please dress appropriately and bring water. See you soon!",
                'category' => 'weather'
            ),
            array(
                'name' => 'Weather Delay',
                'shortcut' => 'delay',
                'content' => "Weather Update: Due to weather conditions, we're delaying camp by 1 hour. New start time: [TIME]. We'll update you if anything changes.",
                'category' => 'weather'
            ),
            array(
                'name' => 'Weather Cancellation',
                'shortcut' => 'cancel',
                'content' => "Weather Update: Unfortunately, today's camp has been CANCELLED due to weather. We'll contact you about make-up options. Stay safe!",
                'category' => 'weather'
            ),
            array(
                'name' => 'Follow-up Survey',
                'shortcut' => 'survey',
                'content' => "Hi {parent_name}! We hope {child_name} had a blast at camp! We'd love your feedback: [SURVEY_LINK]. Thanks for being part of the PTP family!",
                'category' => 'follow-up'
            ),
            array(
                'name' => 'Lock Your Week',
                'shortcut' => 'lyw',
                'content' => "Hey {parent_name}! Loved having {child_name} at the clinic! Ready for a full week of training? Lock Your Week camps are filling fast: ptpsoccercamps.com",
                'category' => 'upsell'
            ),
            array(
                'name' => 'Support Response',
                'shortcut' => 'support',
                'content' => "Thanks for contacting PTP! We're looking into this and will have an answer for you within 24 hours. Reply HELP anytime for assistance.",
                'category' => 'support'
            ),
            array(
                'name' => 'Greeting Morning',
                'shortcut' => 'morning',
                'content' => "Good morning! Thanks for reaching out to PTP Soccer Camps. How can we help you today?",
                'category' => 'greeting'
            )
        );
        
        foreach ($defaults as $reply) {
            // Check if already exists
            $existing = self::find_by_shortcut($reply['shortcut']);
            if (!$existing) {
                self::create($reply);
            }
        }
    }
}
