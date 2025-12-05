<?php
/**
 * Conversations management class
 */
class PTP_Comms_Hub_Conversations {
    
    /**
     * Get or create conversation for a contact (unified - same conversation for all channels)
     * This ensures SMS and WhatsApp messages go to the same conversation thread
     */
    public static function get_or_create_conversation($contact_id, $channel = 'sms') {
        global $wpdb;

        // Look for any active conversation for this contact (unified messaging)
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_conversations
             WHERE contact_id = %d AND status = 'active'
             ORDER BY last_message_at DESC LIMIT 1",
            $contact_id
        ));

        if ($conversation) {
            // Reactivate if needed
            if ($conversation->status === 'archived') {
                $wpdb->update(
                    $wpdb->prefix . 'ptp_conversations',
                    array('status' => 'active', 'updated_at' => current_time('mysql')),
                    array('id' => $conversation->id)
                );
            }
            return $conversation->id;
        }

        // Also check for archived conversations and reactivate
        $archived = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_conversations
             WHERE contact_id = %d AND status = 'archived'
             ORDER BY last_message_at DESC LIMIT 1",
            $contact_id
        ));

        if ($archived) {
            $wpdb->update(
                $wpdb->prefix . 'ptp_conversations',
                array('status' => 'active', 'updated_at' => current_time('mysql')),
                array('id' => $archived->id)
            );
            return $archived->id;
        }

        $current_time = current_time('mysql');

        $wpdb->insert(
            $wpdb->prefix . 'ptp_conversations',
            array(
                'contact_id' => $contact_id,
                'status' => 'active',
                'channel' => $channel,
                'unread_count' => 0,
                'created_at' => $current_time,
                'updated_at' => $current_time
            )
        );

        return $wpdb->insert_id;
    }
    
    /**
     * Update conversation with new message (unified messaging support)
     * Updates channel to reflect the last message type (SMS/WhatsApp)
     */
    public static function update_conversation($conversation_id, $message, $direction, $channel = null) {
        global $wpdb;

        $current_time = current_time('mysql');

        $data = array(
            'last_message' => substr($message, 0, 500), // Limit message preview
            'last_message_direction' => $direction,
            'last_message_at' => $current_time,
            'updated_at' => $current_time,
            'status' => 'active' // Always reactivate on new message
        );

        // Add channel if provided (unified messaging - shows last channel used)
        if ($channel) {
            $data['channel'] = $channel;
        }

        // Update conversation data
        $wpdb->update(
            $wpdb->prefix . 'ptp_conversations',
            $data,
            array('id' => $conversation_id)
        );

        // Increment unread count for inbound messages
        if ($direction === 'inbound') {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ptp_conversations SET unread_count = unread_count + 1 WHERE id = %d",
                $conversation_id
            ));

            // Clear polling cache for this conversation (for real-time updates)
            delete_transient('ptp_conv_poll_' . $conversation_id);
        }

        // Update contact's last interaction timestamp
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT contact_id FROM {$wpdb->prefix}ptp_conversations WHERE id = %d",
            $conversation_id
        ));

        if ($conversation) {
            $contact_update = array('last_interaction_at' => $current_time);
            if ($channel) {
                $contact_update['preferred_contact_method'] = $channel;
            }
            $wpdb->update(
                $wpdb->prefix . 'ptp_contacts',
                $contact_update,
                array('id' => $conversation->contact_id)
            );
        }

        return true;
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
