<?php
/**
 * Conversations management class
 */
class PTP_Comms_Hub_Conversations {
    
    public static function get_or_create_conversation($contact_id) {
        global $wpdb;
        
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_conversations WHERE contact_id = %d",
            $contact_id
        ));
        
        if ($conversation) {
            return $conversation->id;
        }
        
        $current_time = current_time('mysql');
        
        $wpdb->insert(
            $wpdb->prefix . 'ptp_conversations',
            array(
                'contact_id' => $contact_id,
                'status' => 'active',
                'created_at' => $current_time,
                'updated_at' => $current_time
            )
        );
        
        return $wpdb->insert_id;
    }
    
    public static function update_conversation($conversation_id, $message, $direction, $channel = null) {
        global $wpdb;

        $current_time = current_time('mysql');

        $data = array(
            'last_message' => $message,
            'last_message_direction' => $direction,
            'last_message_at' => $current_time,
            'updated_at' => $current_time
        );

        // Add channel if provided
        if ($channel) {
            $data['channel'] = $channel;
        }

        if ($direction === 'inbound') {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ptp_conversations SET unread_count = unread_count + 1 WHERE id = %d",
                $conversation_id
            ));
        }

        return $wpdb->update(
            $wpdb->prefix . 'ptp_conversations',
            $data,
            array('id' => $conversation_id)
        );
    }
    
    public static function mark_as_read($conversation_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_conversations',
            array('unread_count' => 0),
            array('id' => $conversation_id)
        );
    }
    
    public static function get_conversations($status = 'active') {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT conv.*, c.parent_first_name, c.parent_last_name, c.parent_phone
            FROM {$wpdb->prefix}ptp_conversations conv
            JOIN {$wpdb->prefix}ptp_contacts c ON conv.contact_id = c.id
            WHERE conv.status = %s
            ORDER BY conv.last_message_at DESC",
            $status
        ));
    }
    
    public static function get_conversation_messages($contact_id, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_communication_logs
            WHERE contact_id = %d
            ORDER BY created_at DESC
            LIMIT %d",
            $contact_id,
            $limit
        ));
    }
}
