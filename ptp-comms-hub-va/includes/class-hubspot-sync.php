<?php
/**
 * HubSpot integration and bi-directional synchronization
 * v4.0.0 - Robust sync with queue, notes, activities, and error handling
 */
class PTP_Comms_Hub_HubSpot_Sync {
    
    private static $api_base = 'https://api.hubapi.com';
    
    /**
     * Get API key from settings
     */
    private static function get_api_key() {
        return ptp_comms_get_setting('hubspot_api_key');
    }
    
    /**
     * Check if HubSpot is configured
     */
    public static function is_configured() {
        return !empty(self::get_api_key());
    }
    
    /**
     * Make API request to HubSpot
     */
    private static function api_request($endpoint, $method = 'GET', $data = null) {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            return array('success' => false, 'error' => 'HubSpot API key not configured');
        }
        
        $url = self::$api_base . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('[PTP HubSpot] API Error: ' . $response->get_error_message());
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code >= 200 && $code < 300) {
            return array('success' => true, 'data' => $body, 'code' => $code);
        }
        
        $error = isset($body['message']) ? $body['message'] : 'Unknown error';
        error_log("[PTP HubSpot] API Error ($code): $error");
        return array('success' => false, 'error' => $error, 'code' => $code, 'data' => $body);
    }
    
    /**
     * Sync contact to HubSpot (queue for processing)
     */
    public static function queue_contact_sync($contact_id, $priority = 5) {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_hubspot_sync_queue 
             WHERE contact_id = %d AND sync_type = 'contact' AND status = 'pending'",
            $contact_id
        ));
        
        if ($existing) {
            return true;
        }
        
        return $wpdb->insert(
            $wpdb->prefix . 'ptp_hubspot_sync_queue',
            array(
                'contact_id' => $contact_id,
                'sync_type' => 'contact',
                'sync_direction' => 'to_hubspot',
                'status' => 'pending',
                'priority' => $priority,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Sync note to HubSpot (queue for processing)
     */
    public static function queue_note_sync($contact_id, $note_id, $priority = 5) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT hubspot_contact_id FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact || !$contact->hubspot_contact_id) {
            self::queue_contact_sync($contact_id, 10);
        }
        
        return $wpdb->insert(
            $wpdb->prefix . 'ptp_hubspot_sync_queue',
            array(
                'contact_id' => $contact_id,
                'sync_type' => 'note',
                'sync_direction' => 'to_hubspot',
                'data_payload' => json_encode(array('note_id' => $note_id)),
                'status' => 'pending',
                'priority' => $priority,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Sync activity to HubSpot
     */
    public static function queue_activity_sync($contact_id, $activity_type, $activity_data, $priority = 5) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'ptp_hubspot_sync_queue',
            array(
                'contact_id' => $contact_id,
                'sync_type' => 'activity',
                'sync_direction' => 'to_hubspot',
                'data_payload' => json_encode(array(
                    'activity_type' => $activity_type,
                    'data' => $activity_data
                )),
                'status' => 'pending',
                'priority' => $priority,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Process sync queue (called by cron)
     */
    public static function process_sync_queue($limit = 50) {
        global $wpdb;
        
        if (!self::is_configured()) {
            return;
        }
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_hubspot_sync_queue 
             WHERE status = 'pending' AND (attempts < 3 OR last_attempt_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
             ORDER BY priority DESC, created_at ASC 
             LIMIT %d",
            $limit
        ));
        
        foreach ($items as $item) {
            self::process_queue_item($item);
        }
    }
    
    /**
     * Process a single queue item
     */
    private static function process_queue_item($item) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_hubspot_sync_queue',
            array(
                'attempts' => $item->attempts + 1,
                'last_attempt_at' => current_time('mysql'),
                'status' => 'processing'
            ),
            array('id' => $item->id)
        );
        
        $result = false;
        
        switch ($item->sync_type) {
            case 'contact':
                $result = self::sync_contact_now($item->contact_id);
                break;
            case 'note':
                $payload = json_decode($item->data_payload, true);
                $result = self::sync_note_now($item->contact_id, $payload['note_id']);
                break;
            case 'activity':
                $payload = json_decode($item->data_payload, true);
                $result = self::sync_activity_now($item->contact_id, $payload['activity_type'], $payload['data']);
                break;
        }
        
        if ($result) {
            $wpdb->update(
                $wpdb->prefix . 'ptp_hubspot_sync_queue',
                array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $item->id)
            );
        } else {
            $status = $item->attempts >= 2 ? 'failed' : 'pending';
            $wpdb->update(
                $wpdb->prefix . 'ptp_hubspot_sync_queue',
                array(
                    'status' => $status,
                    'error_message' => 'Sync failed after ' . ($item->attempts + 1) . ' attempts'
                ),
                array('id' => $item->id)
            );
        }
    }
    
    /**
     * Immediate contact sync to HubSpot
     */
    public static function sync_contact_now($contact_id) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            return false;
        }
        
        $properties = array(
            'email' => $contact->parent_email,
            'firstname' => $contact->parent_first_name,
            'lastname' => $contact->parent_last_name,
            'phone' => $contact->parent_phone,
            'city' => $contact->city,
            'state' => $contact->state,
            'zip' => $contact->zip_code,
            'address' => $contact->address,
            'ptp_child_name' => $contact->child_name,
            'ptp_child_age' => $contact->child_age,
            'ptp_relationship_score' => $contact->relationship_score,
            'ptp_lifetime_value' => $contact->lifetime_value,
            'ptp_total_orders' => $contact->total_orders,
            'ptp_vip_status' => $contact->vip_status ? 'Yes' : 'No',
            'ptp_source' => $contact->source,
            'ptp_preferred_contact_method' => $contact->preferred_contact_method
        );
        
        $properties = array_filter($properties, function($v) { return $v !== '' && $v !== null; });
        
        if ($contact->hubspot_contact_id) {
            $result = self::api_request(
                '/crm/v3/objects/contacts/' . $contact->hubspot_contact_id,
                'PATCH',
                array('properties' => $properties)
            );
        } else {
            $existing = self::find_contact_by_email($contact->parent_email);
            
            if ($existing) {
                $contact->hubspot_contact_id = $existing;
                $result = self::api_request(
                    '/crm/v3/objects/contacts/' . $existing,
                    'PATCH',
                    array('properties' => $properties)
                );
            } else {
                $result = self::api_request(
                    '/crm/v3/objects/contacts',
                    'POST',
                    array('properties' => $properties)
                );
            }
        }
        
        if ($result['success']) {
            $hubspot_id = isset($result['data']['id']) ? $result['data']['id'] : $contact->hubspot_contact_id;
            
            $wpdb->update(
                $wpdb->prefix . 'ptp_contacts',
                array(
                    'hubspot_contact_id' => $hubspot_id,
                    'hubspot_last_sync' => current_time('mysql')
                ),
                array('id' => $contact_id)
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Find HubSpot contact by email
     */
    private static function find_contact_by_email($email) {
        if (empty($email)) {
            return null;
        }
        
        $result = self::api_request(
            '/crm/v3/objects/contacts/search',
            'POST',
            array(
                'filterGroups' => array(
                    array(
                        'filters' => array(
                            array(
                                'propertyName' => 'email',
                                'operator' => 'EQ',
                                'value' => $email
                            )
                        )
                    )
                ),
                'limit' => 1
            )
        );
        
        if ($result['success'] && !empty($result['data']['results'])) {
            return $result['data']['results'][0]['id'];
        }
        
        return null;
    }
    
    /**
     * Sync note to HubSpot
     */
    public static function sync_note_now($contact_id, $note_id) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT hubspot_contact_id FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact || !$contact->hubspot_contact_id) {
            self::sync_contact_now($contact_id);
            $contact = $wpdb->get_row($wpdb->prepare(
                "SELECT hubspot_contact_id FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
                $contact_id
            ));
            
            if (!$contact || !$contact->hubspot_contact_id) {
                return false;
            }
        }
        
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contact_notes WHERE id = %d",
            $note_id
        ));
        
        if (!$note) {
            return false;
        }
        
        $note_body = $note->content;
        if ($note->title) {
            $note_body = "**{$note->title}**\n\n" . $note_body;
        }
        
        $note_body .= "\n\n---\nType: {$note->note_type} | Sentiment: {$note->sentiment} | Source: PTP Comms Hub";
        
        $result = self::api_request(
            '/crm/v3/objects/notes',
            'POST',
            array(
                'properties' => array(
                    'hs_timestamp' => strtotime($note->created_at) * 1000,
                    'hs_note_body' => $note_body
                ),
                'associations' => array(
                    array(
                        'to' => array('id' => $contact->hubspot_contact_id),
                        'types' => array(
                            array(
                                'associationCategory' => 'HUBSPOT_DEFINED',
                                'associationTypeId' => 202
                            )
                        )
                    )
                )
            )
        );
        
        if ($result['success']) {
            $wpdb->update(
                $wpdb->prefix . 'ptp_contact_notes',
                array(
                    'hubspot_synced' => 1,
                    'hubspot_note_id' => $result['data']['id']
                ),
                array('id' => $note_id)
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Sync activity to HubSpot
     */
    public static function sync_activity_now($contact_id, $activity_type, $activity_data) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT hubspot_contact_id FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact || !$contact->hubspot_contact_id) {
            return false;
        }
        
        $body = isset($activity_data['description']) ? $activity_data['description'] : '';
        if (isset($activity_data['title'])) {
            $body = "**{$activity_data['title']}**\n\n" . $body;
        }
        $body .= "\n\n[Synced from PTP Comms Hub]";
        
        $result = self::api_request(
            '/crm/v3/objects/notes',
            'POST',
            array(
                'properties' => array(
                    'hs_timestamp' => time() * 1000,
                    'hs_note_body' => $body
                ),
                'associations' => array(
                    array(
                        'to' => array('id' => $contact->hubspot_contact_id),
                        'types' => array(
                            array(
                                'associationCategory' => 'HUBSPOT_DEFINED',
                                'associationTypeId' => 202
                            )
                        )
                    )
                )
            )
        );
        
        return $result['success'];
    }
    
    /**
     * Create deal in HubSpot
     */
    public static function create_deal($contact_id, $order_id) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT hubspot_contact_id FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact || !$contact->hubspot_contact_id) {
            self::queue_contact_sync($contact_id, 10);
            return false;
        }
        
        if (!function_exists('wc_get_order')) {
            return false;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $deal_name = 'PTP Order #' . $order_id;
        $items = $order->get_items();
        if (!empty($items)) {
            $first_item = reset($items);
            $deal_name = $first_item->get_name() . ' - Order #' . $order_id;
        }
        
        $result = self::api_request(
            '/crm/v3/objects/deals',
            'POST',
            array(
                'properties' => array(
                    'dealname' => $deal_name,
                    'amount' => $order->get_total(),
                    'dealstage' => 'closedwon',
                    'pipeline' => 'default',
                    'closedate' => strtotime($order->get_date_created()) * 1000
                ),
                'associations' => array(
                    array(
                        'to' => array('id' => $contact->hubspot_contact_id),
                        'types' => array(
                            array(
                                'associationCategory' => 'HUBSPOT_DEFINED',
                                'associationTypeId' => 3
                            )
                        )
                    )
                )
            )
        );
        
        return $result['success'];
    }
    
    /**
     * Pull contacts from HubSpot
     */
    public static function pull_contacts_from_hubspot($limit = 100, $after = null) {
        global $wpdb;
        
        $params = array(
            'limit' => $limit,
            'properties' => array('email', 'firstname', 'lastname', 'phone', 'city', 'state', 'zip', 'lifecyclestage')
        );
        
        if ($after) {
            $params['after'] = $after;
        }
        
        $result = self::api_request('/crm/v3/objects/contacts?' . http_build_query($params));
        
        if (!$result['success']) {
            return array('success' => false);
        }
        
        $imported = 0;
        $updated = 0;
        
        foreach ($result['data']['results'] as $hs_contact) {
            $props = $hs_contact['properties'];
            
            $local = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE hubspot_contact_id = %s OR parent_email = %s",
                $hs_contact['id'],
                $props['email'] ?? ''
            ));
            
            $data = array(
                'parent_email' => $props['email'] ?? '',
                'parent_first_name' => $props['firstname'] ?? '',
                'parent_last_name' => $props['lastname'] ?? '',
                'parent_phone' => $props['phone'] ?? '',
                'city' => $props['city'] ?? '',
                'state' => $props['state'] ?? '',
                'zip_code' => $props['zip'] ?? '',
                'hubspot_contact_id' => $hs_contact['id'],
                'hubspot_lifecycle_stage' => $props['lifecyclestage'] ?? '',
                'hubspot_last_sync' => current_time('mysql'),
                'source' => 'hubspot_import',
                'updated_at' => current_time('mysql')
            );
            
            if ($local) {
                $wpdb->update($wpdb->prefix . 'ptp_contacts', $data, array('id' => $local->id));
                $updated++;
            } else {
                $data['created_at'] = current_time('mysql');
                $data['opted_in'] = 1;
                $wpdb->insert($wpdb->prefix . 'ptp_contacts', $data);
                $imported++;
            }
        }
        
        return array(
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'has_more' => !empty($result['data']['paging']['next']['after']),
            'next_after' => $result['data']['paging']['next']['after'] ?? null
        );
    }
    
    /**
     * Sync all contacts
     */
    public static function sync_all_contacts($batch_size = 50) {
        global $wpdb;
        
        $contacts = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_contacts 
             WHERE opted_in = 1 
             AND (hubspot_last_sync IS NULL OR hubspot_last_sync < DATE_SUB(NOW(), INTERVAL 24 HOUR))
             LIMIT %d",
            $batch_size
        ));
        
        $synced = 0;
        foreach ($contacts as $contact) {
            if (self::sync_contact_now($contact->id)) {
                $synced++;
            }
        }
        
        return $synced;
    }
    
    /**
     * Test HubSpot connection
     */
    public static function test_connection() {
        $result = self::api_request('/crm/v3/objects/contacts?limit=1');
        return $result['success'];
    }
    
    /**
     * Get sync queue stats
     */
    public static function get_queue_stats() {
        global $wpdb;
        
        return array(
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_hubspot_sync_queue WHERE status = 'pending'"),
            'processing' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_hubspot_sync_queue WHERE status = 'processing'"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_hubspot_sync_queue WHERE status = 'completed' AND completed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_hubspot_sync_queue WHERE status = 'failed'")
        );
    }
}

add_action('ptp_comms_sync_hubspot', array('PTP_Comms_Hub_HubSpot_Sync', 'process_sync_queue'));
