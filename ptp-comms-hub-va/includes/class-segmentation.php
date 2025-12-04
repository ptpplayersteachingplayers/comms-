<?php
/**
 * Customer segmentation for personalized SMS campaigns
 * Integrates with HubSpot and WooCommerce for targeted messaging
 */
class PTP_Comms_Hub_Segmentation {
    
    /**
     * Get all available segments
     */
    public static function get_segments() {
        return array(
            'new_registrations' => array(
                'name' => 'New Registrations (Last 7 Days)',
                'description' => 'Parents who registered in the last 7 days',
                'source' => 'woocommerce'
            ),
            'upcoming_camps' => array(
                'name' => 'Upcoming Camp Attendees',
                'description' => 'Parents with camps starting in the next 14 days',
                'source' => 'woocommerce'
            ),
            'past_customers' => array(
                'name' => 'Past Customers',
                'description' => 'Parents who attended camps previously',
                'source' => 'woocommerce'
            ),
            'past_camp_attendees' => array(
                'name' => 'Past Camp Attendees',
                'description' => 'Everyone who attended a camp that has already happened',
                'source' => 'woocommerce'
            ),
            'high_value' => array(
                'name' => 'High Value Customers',
                'description' => 'Parents with total spending over $500',
                'source' => 'woocommerce'
            ),
            'abandoned_cart' => array(
                'name' => 'Abandoned Cart',
                'description' => 'Parents with abandoned carts in last 48 hours',
                'source' => 'woocommerce'
            ),
            'by_state' => array(
                'name' => 'By State/Region',
                'description' => 'Filter by state (PA, NJ, DE, MD, NY)',
                'source' => 'contact'
            ),
            'by_age_group' => array(
                'name' => 'By Child Age Group',
                'description' => 'Filter by child age ranges',
                'source' => 'contact'
            ),
            'by_camp_product' => array(
                'name' => 'By Camp/Product Type',
                'description' => 'Filter by specific camp products purchased',
                'source' => 'woocommerce'
            ),
            'by_event_date_range' => array(
                'name' => 'By Event Date Range',
                'description' => 'Filter by camp/event date range',
                'source' => 'woocommerce'
            ),
            'by_order_date_range' => array(
                'name' => 'By Order Date Range',
                'description' => 'Filter by order/purchase date range',
                'source' => 'woocommerce'
            ),
            'by_order_status' => array(
                'name' => 'By Order Status',
                'description' => 'Filter by WooCommerce order status',
                'source' => 'woocommerce'
            ),
            'repeat_customers' => array(
                'name' => 'Repeat Customers',
                'description' => 'Parents who have registered for multiple camps',
                'source' => 'woocommerce'
            ),
            'first_time_buyers' => array(
                'name' => 'First-Time Buyers',
                'description' => 'Parents who purchased for the first time',
                'source' => 'woocommerce'
            ),
            'needs_reminder' => array(
                'name' => 'Needs Reminder',
                'description' => 'Registrations that haven\'t received all reminders',
                'source' => 'woocommerce'
            ),
            'by_market' => array(
                'name' => 'By Market/Location',
                'description' => 'Filter by camp market/location',
                'source' => 'woocommerce'
            ),
            'by_total_spend' => array(
                'name' => 'By Total Spend',
                'description' => 'Filter by customer total spending range',
                'source' => 'woocommerce'
            ),
            'hubspot_list' => array(
                'name' => 'HubSpot List',
                'description' => 'Import from HubSpot contact lists',
                'source' => 'hubspot'
            ),
            'hubspot_lifecycle' => array(
                'name' => 'HubSpot Lifecycle Stage',
                'description' => 'Filter by HubSpot lifecycle stage (Lead, Customer, etc.)',
                'source' => 'hubspot'
            ),
            'hubspot_property' => array(
                'name' => 'HubSpot Custom Property',
                'description' => 'Filter by any HubSpot contact property',
                'source' => 'hubspot'
            ),
            'engaged_contacts' => array(
                'name' => 'Engaged Contacts',
                'description' => 'Contacts who replied to SMS in last 30 days',
                'source' => 'engagement'
            ),
            'unengaged_contacts' => array(
                'name' => 'Unengaged Contacts',
                'description' => 'Contacts who haven\'t replied in 90+ days',
                'source' => 'engagement'
            ),
            'birthday_month' => array(
                'name' => 'Birthday Month',
                'description' => 'Children with birthdays in a specific month',
                'source' => 'contact'
            ),
            'sms_opted_in' => array(
                'name' => 'SMS Opted In',
                'description' => 'Contacts who have opted in for SMS communications',
                'source' => 'contact'
            ),
            'custom_segment' => array(
                'name' => 'Custom Segment',
                'description' => 'Contacts tagged with a specific custom segment',
                'source' => 'contact'
            )
        );
    }
    
    /**
     * Get contacts for a specific segment
     */
    public static function get_segment_contacts($segment_type, $options = array()) {
        switch ($segment_type) {
            case 'new_registrations':
                return self::get_new_registrations($options);
                
            case 'upcoming_camps':
                return self::get_upcoming_camp_attendees($options);
                
            case 'past_customers':
                return self::get_past_customers($options);
                
            case 'high_value':
                return self::get_high_value_customers($options);
                
            case 'abandoned_cart':
                return self::get_abandoned_cart_contacts($options);
                
            case 'by_state':
                return self::get_contacts_by_state($options);
                
            case 'by_age_group':
                return self::get_contacts_by_age_group($options);
                
            case 'by_camp_product':
                return self::get_contacts_by_camp_product($options);
                
            case 'repeat_customers':
                return self::get_repeat_customers($options);
                
            case 'first_time_buyers':
                return self::get_first_time_buyers($options);
                
            case 'hubspot_list':
                return self::get_hubspot_list_contacts($options);
                
            case 'hubspot_lifecycle':
                return self::get_hubspot_lifecycle_contacts($options);
                
            case 'hubspot_property':
                return self::get_hubspot_property_contacts($options);
                
            case 'engaged_contacts':
                return self::get_engaged_contacts($options);
                
            case 'unengaged_contacts':
                return self::get_unengaged_contacts($options);
                
            case 'birthday_month':
                return self::get_birthday_month_contacts($options);
                
            case 'past_camp_attendees':
                return self::get_past_camp_attendees($options);
                
            case 'by_event_date_range':
                return self::get_contacts_by_event_date_range($options);
                
            case 'by_order_date_range':
                return self::get_contacts_by_order_date_range($options);
                
            case 'by_order_status':
                return self::get_contacts_by_order_status($options);
                
            case 'needs_reminder':
                return self::get_contacts_needs_reminder($options);
                
            case 'by_market':
                return self::get_contacts_by_market($options);
                
            case 'by_total_spend':
                return self::get_contacts_by_total_spend($options);
                
            case 'sms_opted_in':
                return self::get_sms_opted_in_contacts($options);
                
            case 'custom_segment':
                return self::get_custom_segment_contacts($options);
                
            default:
                return array();
        }
    }
    
    /**
     * Get new registrations (last 7 days)
     */
    private static function get_new_registrations($options) {
        global $wpdb;
        
        $days = isset($options['days']) ? intval($options['days']) : 7;
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get orders from last X days
        $orders = wc_get_orders(array(
            'limit' => -1,
            'date_created' => '>=' . $date_from,
            'status' => array('completed', 'processing')
        ));
        
        $contact_ids = array();
        foreach ($orders as $order) {
            $phone = $order->get_billing_phone();
            if ($phone) {
                $contact = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s AND opted_in = 1",
                    ptp_comms_normalize_phone($phone)
                ));
                if ($contact) {
                    $contact_ids[] = $contact->id;
                }
            }
        }
        
        return array_unique($contact_ids);
    }
    
    /**
     * Get contacts with upcoming camps
     */
    private static function get_upcoming_camp_attendees($options) {
        global $wpdb;
        
        $days_ahead = isset($options['days_ahead']) ? intval($options['days_ahead']) : 14;
        
        // Get orders with camp dates in the next X days
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d', strtotime("+{$days_ahead} days"));
        
        $query = $wpdb->prepare(
            "SELECT DISTINCT c.id 
            FROM {$wpdb->prefix}ptp_contacts c
            INNER JOIN {$wpdb->prefix}postmeta pm ON c.woo_order_id = pm.post_id
            WHERE pm.meta_key = '_camp_start_date' 
            AND pm.meta_value BETWEEN %s AND %s
            AND c.opted_in = 1",
            $date_from,
            $date_to
        );
        
        $results = $wpdb->get_results($query);
        return array_map(function($row) { return $row->id; }, $results);
    }
    
    /**
     * Get past customers
     */
    private static function get_past_customers($options) {
        global $wpdb;
        
        $exclude_recent_days = isset($options['exclude_recent_days']) ? intval($options['exclude_recent_days']) : 30;
        $date_before = date('Y-m-d H:i:s', strtotime("-{$exclude_recent_days} days"));
        
        // Get contacts with completed orders before the exclusion date
        $query = $wpdb->prepare(
            "SELECT DISTINCT c.id 
            FROM {$wpdb->prefix}ptp_contacts c
            WHERE c.woo_order_id IS NOT NULL
            AND c.created_at < %s
            AND c.opted_in = 1",
            $date_before
        );
        
        $results = $wpdb->get_results($query);
        return array_map(function($row) { return $row->id; }, $results);
    }
    
    /**
     * Get high value customers
     */
    private static function get_high_value_customers($options) {
        global $wpdb;
        
        $min_value = isset($options['min_value']) ? floatval($options['min_value']) : 500;
        
        // Get customers with total order value above threshold
        $contact_ids = array();
        
        $contacts = $wpdb->get_results(
            "SELECT DISTINCT parent_phone FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1"
        );
        
        foreach ($contacts as $contact) {
            $total = 0;
            $orders = wc_get_orders(array(
                'billing_phone' => $contact->parent_phone,
                'status' => array('completed', 'processing'),
                'limit' => -1
            ));
            
            foreach ($orders as $order) {
                $total += $order->get_total();
            }
            
            if ($total >= $min_value) {
                $contact_record = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s AND opted_in = 1",
                    $contact->parent_phone
                ));
                if ($contact_record) {
                    $contact_ids[] = $contact_record->id;
                }
            }
        }
        
        return $contact_ids;
    }
    
    /**
     * Get abandoned cart contacts
     */
    private static function get_abandoned_cart_contacts($options) {
        global $wpdb;
        
        $hours = isset($options['hours']) ? intval($options['hours']) : 48;
        $date_from = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        // Check if abandoned cart plugin is active
        if (!function_exists('wc_get_abandoned_carts')) {
            return array();
        }
        
        // Get abandoned carts
        $carts = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT user_id, session_id 
            FROM {$wpdb->prefix}cartbounty 
            WHERE cart_saved >= %s 
            AND mail_sent = 0",
            $date_from
        ));
        
        $contact_ids = array();
        foreach ($carts as $cart) {
            if ($cart->user_id) {
                $user = get_user_by('id', $cart->user_id);
                if ($user) {
                    $phone = get_user_meta($user->ID, 'billing_phone', true);
                    if ($phone) {
                        $contact = $wpdb->get_row($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s AND opted_in = 1",
                            ptp_comms_normalize_phone($phone)
                        ));
                        if ($contact) {
                            $contact_ids[] = $contact->id;
                        }
                    }
                }
            }
        }
        
        return $contact_ids;
    }
    
    /**
     * Get contacts by state
     */
    private static function get_contacts_by_state($options) {
        global $wpdb;
        
        $states = isset($options['states']) ? $options['states'] : array('PA', 'NJ', 'DE', 'MD', 'NY');
        if (!is_array($states)) {
            $states = array($states);
        }
        
        $placeholders = implode(',', array_fill(0, count($states), '%s'));
        $query = $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_contacts 
            WHERE state IN ($placeholders) 
            AND opted_in = 1",
            ...$states
        );
        
        $results = $wpdb->get_results($query);
        return array_map(function($row) { return $row->id; }, $results);
    }
    
    /**
     * Get contacts by child age group
     */
    private static function get_contacts_by_age_group($options) {
        global $wpdb;
        
        $min_age = isset($options['min_age']) ? intval($options['min_age']) : 0;
        $max_age = isset($options['max_age']) ? intval($options['max_age']) : 18;
        
        $query = $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_contacts 
            WHERE child_age BETWEEN %d AND %d 
            AND opted_in = 1",
            $min_age,
            $max_age
        );
        
        $results = $wpdb->get_results($query);
        return array_map(function($row) { return $row->id; }, $results);
    }
    
    /**
     * Get contacts from HubSpot list
     */
    private static function get_hubspot_list_contacts($options) {
        global $wpdb;
        
        $list_id = isset($options['list_id']) ? intval($options['list_id']) : 0;
        if (!$list_id) {
            return array();
        }
        
        $api_key = ptp_comms_get_setting('hubspot_api_key');
        if (empty($api_key)) {
            return array();
        }
        
        // Get contacts from HubSpot list
        $url = "https://api.hubapi.com/contacts/v1/lists/{$list_id}/contacts/all";
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $api_key),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['contacts'])) {
            return array();
        }
        
        // Match HubSpot contacts with local contacts
        $contact_ids = array();
        foreach ($body['contacts'] as $hubspot_contact) {
            $vid = $hubspot_contact['vid'];
            $contact = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE hubspot_contact_id = %s AND opted_in = 1",
                $vid
            ));
            if ($contact) {
                $contact_ids[] = $contact->id;
            }
        }
        
        return $contact_ids;
    }
    
    /**
     * Get engaged contacts
     */
    private static function get_engaged_contacts($options) {
        global $wpdb;
        
        $days = isset($options['days']) ? intval($options['days']) : 30;
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $query = $wpdb->prepare(
            "SELECT DISTINCT c.id 
            FROM {$wpdb->prefix}ptp_contacts c
            INNER JOIN {$wpdb->prefix}ptp_messages m ON c.id = m.contact_id
            WHERE m.direction = 'inbound' 
            AND m.created_at >= %s
            AND c.opted_in = 1",
            $date_from
        );
        
        $results = $wpdb->get_results($query);
        return array_map(function($row) { return $row->id; }, $results);
    }
    
    /**
     * Get unengaged contacts
     */
    private static function get_unengaged_contacts($options) {
        global $wpdb;
        
        $days = isset($options['days']) ? intval($options['days']) : 90;
        $date_before = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get contacts who haven't replied in X days
        $query = $wpdb->prepare(
            "SELECT c.id 
            FROM {$wpdb->prefix}ptp_contacts c
            WHERE c.opted_in = 1
            AND c.id NOT IN (
                SELECT DISTINCT contact_id 
                FROM {$wpdb->prefix}ptp_messages 
                WHERE direction = 'inbound' 
                AND created_at >= %s
            )",
            $date_before
        );
        
        $results = $wpdb->get_results($query);
        return array_map(function($row) { return $row->id; }, $results);
    }
    
    /**
     * Create custom segment with multiple filters
     */
    public static function create_custom_segment($filters) {
        $contact_ids = array();
        
        foreach ($filters as $filter) {
            $segment_contacts = self::get_segment_contacts($filter['type'], $filter['options']);
            
            if (empty($contact_ids)) {
                $contact_ids = $segment_contacts;
            } else {
                // Combine with AND logic (intersection)
                if ($filter['logic'] === 'AND') {
                    $contact_ids = array_intersect($contact_ids, $segment_contacts);
                }
                // Combine with OR logic (union)
                elseif ($filter['logic'] === 'OR') {
                    $contact_ids = array_unique(array_merge($contact_ids, $segment_contacts));
                }
            }
        }
        
        return $contact_ids;
    }
    
    /**
     * Get segment contact count
     */
    public static function get_segment_count($segment_type, $options = array()) {
        $contacts = self::get_segment_contacts($segment_type, $options);
        return count($contacts);
    }
    
    /**
     * Preview segment contacts
     */
    public static function preview_segment($segment_type, $options = array(), $limit = 10) {
        global $wpdb;
        
        $contact_ids = self::get_segment_contacts($segment_type, $options);
        if (empty($contact_ids)) {
            return array();
        }
        
        $ids_string = implode(',', array_map('intval', array_slice($contact_ids, 0, $limit)));
        $query = "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id IN ($ids_string)";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get personalization variables for contact
     */
    public static function get_personalization_vars($contact_id) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            return array();
        }
        
        // Get latest order
        $latest_order = null;
        if ($contact->woo_order_id) {
            $latest_order = wc_get_order($contact->woo_order_id);
        }
        
        // Build personalization variables
        $vars = array(
            '{{first_name}}' => $contact->parent_first_name,
            '{{last_name}}' => $contact->parent_last_name,
            '{{full_name}}' => "{$contact->parent_first_name} {$contact->parent_last_name}",
            '{{child_name}}' => $contact->child_name,
            '{{child_age}}' => $contact->child_age,
            '{{state}}' => $contact->state,
            '{{city}}' => $contact->city,
            '{{zip}}' => $contact->zip_code
        );
        
        if ($latest_order) {
            $vars['{{order_number}}'] = $latest_order->get_order_number();
            $vars['{{order_total}}'] = '$' . number_format($latest_order->get_total(), 2);
            $vars['{{order_date}}'] = $latest_order->get_date_created()->format('F j, Y');
            
            // Get camp names from order
            $items = $latest_order->get_items();
            $camp_names = array();
            foreach ($items as $item) {
                $camp_names[] = $item->get_name();
            }
            $vars['{{camp_names}}'] = implode(', ', $camp_names);
        }
        
        return $vars;
    }
    
    /**
     * Apply personalization to message
     */
    public static function personalize_message($message, $contact_id) {
        $vars = self::get_personalization_vars($contact_id);
        
        foreach ($vars as $placeholder => $value) {
            $message = str_replace($placeholder, $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Get contacts by specific camp/product
     */
    private static function get_contacts_by_camp_product($options) {
        global $wpdb;
        
        $product_id = isset($options['product_id']) ? intval($options['product_id']) : 0;
        $product_name = isset($options['product_name']) ? sanitize_text_field($options['product_name']) : '';
        
        if (!$product_id && !$product_name) {
            return array();
        }
        
        $contact_ids = array();
        
        // Query orders with specific products
        $args = array(
            'limit' => -1,
            'status' => array('completed', 'processing')
        );
        
        $orders = wc_get_orders($args);
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $match = false;
                
                if ($product_id && $item->get_product_id() == $product_id) {
                    $match = true;
                }
                
                if ($product_name && stripos($item->get_name(), $product_name) !== false) {
                    $match = true;
                }
                
                if ($match) {
                    $phone = $order->get_billing_phone();
                    if ($phone) {
                        $contact = $wpdb->get_row($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s AND opted_in = 1",
                            ptp_comms_normalize_phone($phone)
                        ));
                        if ($contact) {
                            $contact_ids[] = $contact->id;
                        }
                    }
                    break;
                }
            }
        }
        
        return array_unique($contact_ids);
    }
    
    /**
     * Get repeat customers
     */
    private static function get_repeat_customers($options) {
        global $wpdb;
        
        $min_orders = isset($options['min_orders']) ? intval($options['min_orders']) : 2;
        
        $contact_ids = array();
        
        $contacts = $wpdb->get_results(
            "SELECT DISTINCT parent_phone FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1"
        );
        
        foreach ($contacts as $contact) {
            $orders = wc_get_orders(array(
                'billing_phone' => $contact->parent_phone,
                'status' => array('completed', 'processing'),
                'limit' => -1
            ));
            
            if (count($orders) >= $min_orders) {
                $contact_record = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s AND opted_in = 1",
                    $contact->parent_phone
                ));
                if ($contact_record) {
                    $contact_ids[] = $contact_record->id;
                }
            }
        }
        
        return $contact_ids;
    }
    
    /**
     * Get first-time buyers
     */
    private static function get_first_time_buyers($options) {
        global $wpdb;
        
        $days = isset($options['days']) ? intval($options['days']) : 30;
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $contact_ids = array();
        
        $contacts = $wpdb->get_results(
            "SELECT DISTINCT parent_phone FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1"
        );
        
        foreach ($contacts as $contact) {
            $orders = wc_get_orders(array(
                'billing_phone' => $contact->parent_phone,
                'status' => array('completed', 'processing'),
                'limit' => -1
            ));
            
            if (count($orders) === 1) {
                $order = $orders[0];
                if ($order->get_date_created() >= new DateTime($date_from)) {
                    $contact_record = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s AND opted_in = 1",
                        $contact->parent_phone
                    ));
                    if ($contact_record) {
                        $contact_ids[] = $contact_record->id;
                    }
                }
            }
        }
        
        return $contact_ids;
    }
    
    /**
     * Get contacts by HubSpot lifecycle stage
     */
    private static function get_hubspot_lifecycle_contacts($options) {
        global $wpdb;
        
        $lifecycle_stage = isset($options['lifecycle_stage']) ? sanitize_text_field($options['lifecycle_stage']) : '';
        if (empty($lifecycle_stage)) {
            return array();
        }
        
        $api_key = ptp_comms_get_setting('hubspot_api_key');
        if (empty($api_key)) {
            return array();
        }
        
        // Get all contacts with the specific lifecycle stage from HubSpot
        $url = "https://api.hubapi.com/crm/v3/objects/contacts/search";
        $body = array(
            'filterGroups' => array(
                array(
                    'filters' => array(
                        array(
                            'propertyName' => 'lifecyclestage',
                            'operator' => 'EQ',
                            'value' => $lifecycle_stage
                        )
                    )
                )
            ),
            'limit' => 100
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($data['results'])) {
            return array();
        }
        
        $contact_ids = array();
        foreach ($data['results'] as $hubspot_contact) {
            $vid = $hubspot_contact['id'];
            $contact = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE hubspot_contact_id = %s AND opted_in = 1",
                $vid
            ));
            if ($contact) {
                $contact_ids[] = $contact->id;
            }
        }
        
        return $contact_ids;
    }
    
    /**
     * Get contacts by HubSpot custom property
     */
    private static function get_hubspot_property_contacts($options) {
        global $wpdb;
        
        $property_name = isset($options['property_name']) ? sanitize_text_field($options['property_name']) : '';
        $property_value = isset($options['property_value']) ? sanitize_text_field($options['property_value']) : '';
        $operator = isset($options['operator']) ? sanitize_text_field($options['operator']) : 'EQ';
        
        if (empty($property_name) || empty($property_value)) {
            return array();
        }
        
        $api_key = ptp_comms_get_setting('hubspot_api_key');
        if (empty($api_key)) {
            return array();
        }
        
        $url = "https://api.hubapi.com/crm/v3/objects/contacts/search";
        $body = array(
            'filterGroups' => array(
                array(
                    'filters' => array(
                        array(
                            'propertyName' => $property_name,
                            'operator' => $operator,
                            'value' => $property_value
                        )
                    )
                )
            ),
            'limit' => 100
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($data['results'])) {
            return array();
        }
        
        $contact_ids = array();
        foreach ($data['results'] as $hubspot_contact) {
            $vid = $hubspot_contact['id'];
            $contact = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE hubspot_contact_id = %s AND opted_in = 1",
                $vid
            ));
            if ($contact) {
                $contact_ids[] = $contact->id;
            }
        }
        
        return $contact_ids;
    }
    
    /**
     * Get contacts by birthday month
     */
    private static function get_birthday_month_contacts($options) {
        global $wpdb;
        
        $month = isset($options['month']) ? intval($options['month']) : date('n');
        
        $query = $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_contacts 
            WHERE MONTH(child_birthdate) = %d 
            AND opted_in = 1",
            $month
        );
        
        $results = $wpdb->get_results($query);
        return array_map(function($row) { return $row->id; }, $results);
    }
    
    /**
     * Get past camp attendees (camps that have already happened)
     */
    private static function get_past_camp_attendees($options) {
        global $wpdb;
        
        $results = $wpdb->get_col("
            SELECT DISTINCT contact_id FROM {$wpdb->prefix}ptp_registrations 
            WHERE registration_status = 'confirmed' 
            AND event_date < CURDATE()
            AND contact_id IS NOT NULL
        ");
        
        return array_map('intval', $results);
    }
    
    /**
     * Get contacts by event date range
     */
    private static function get_contacts_by_event_date_range($options) {
        global $wpdb;
        
        $date_from = isset($options['date_from']) ? sanitize_text_field($options['date_from']) : '';
        $date_to = isset($options['date_to']) ? sanitize_text_field($options['date_to']) : '';
        
        if (empty($date_from) && empty($date_to)) {
            return array();
        }
        
        $where = array("registration_status = 'confirmed'", "contact_id IS NOT NULL");
        $params = array();
        
        if ($date_from) {
            $where[] = 'event_date >= %s';
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $where[] = 'event_date <= %s';
            $params[] = $date_to;
        }
        
        $where_sql = implode(' AND ', $where);
        $query = "SELECT DISTINCT contact_id FROM {$wpdb->prefix}ptp_registrations WHERE {$where_sql}";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return array_map('intval', $wpdb->get_col($query));
    }
    
    /**
     * Get contacts by order date range
     */
    private static function get_contacts_by_order_date_range($options) {
        global $wpdb;
        
        $date_from = isset($options['date_from']) ? sanitize_text_field($options['date_from']) : '';
        $date_to = isset($options['date_to']) ? sanitize_text_field($options['date_to']) : '';
        
        if (empty($date_from) && empty($date_to)) {
            return array();
        }
        
        $where = array("contact_id IS NOT NULL");
        $params = array();
        
        if ($date_from) {
            $where[] = 'created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        
        if ($date_to) {
            $where[] = 'created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }
        
        $where_sql = implode(' AND ', $where);
        $query = "SELECT DISTINCT contact_id FROM {$wpdb->prefix}ptp_registrations WHERE {$where_sql}";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return array_map('intval', $wpdb->get_col($query));
    }
    
    /**
     * Get contacts by order status
     */
    private static function get_contacts_by_order_status($options) {
        global $wpdb;
        
        $status = isset($options['status']) ? sanitize_text_field($options['status']) : '';
        
        if (empty($status)) {
            return array();
        }
        
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT contact_id FROM {$wpdb->prefix}ptp_registrations 
            WHERE registration_status = %s 
            AND contact_id IS NOT NULL
        ", $status));
        
        return array_map('intval', $results);
    }
    
    /**
     * Get contacts that need reminders sent
     */
    private static function get_contacts_needs_reminder($options) {
        global $wpdb;
        
        $reminder_type = isset($options['reminder_type']) ? sanitize_text_field($options['reminder_type']) : '7_day';
        
        $today = date('Y-m-d');
        
        switch ($reminder_type) {
            case '1_day':
                $target_date = date('Y-m-d', strtotime('+1 day'));
                $field = 'reminder_1day_sent';
                break;
            case '3_day':
                $target_date = date('Y-m-d', strtotime('+3 days'));
                $field = 'reminder_3day_sent';
                break;
            case '7_day':
            default:
                $target_date = date('Y-m-d', strtotime('+7 days'));
                $field = 'reminder_7day_sent';
                break;
        }
        
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT contact_id FROM {$wpdb->prefix}ptp_registrations 
            WHERE registration_status = 'confirmed' 
            AND event_date = %s
            AND {$field} IS NULL
            AND contact_id IS NOT NULL
        ", $target_date));
        
        return array_map('intval', $results);
    }
    
    /**
     * Get contacts by market/location
     */
    private static function get_contacts_by_market($options) {
        global $wpdb;
        
        $market = isset($options['market']) ? sanitize_text_field($options['market']) : '';
        
        if (empty($market)) {
            return array();
        }
        
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT contact_id FROM {$wpdb->prefix}ptp_registrations 
            WHERE registration_status = 'confirmed' 
            AND market = %s
            AND contact_id IS NOT NULL
        ", $market));
        
        return array_map('intval', $results);
    }
    
    /**
     * Get contacts by total spend range
     */
    private static function get_contacts_by_total_spend($options) {
        global $wpdb;
        
        $min_spend = isset($options['min_spend']) ? floatval($options['min_spend']) : 0;
        $max_spend = isset($options['max_spend']) ? floatval($options['max_spend']) : PHP_FLOAT_MAX;
        
        $query = "
            SELECT contact_id FROM {$wpdb->prefix}ptp_registrations 
            WHERE contact_id IS NOT NULL
            GROUP BY contact_id 
            HAVING SUM(line_total) >= %f
        ";
        
        $params = array($min_spend);
        
        if ($max_spend < PHP_FLOAT_MAX) {
            $query .= " AND SUM(line_total) <= %f";
            $params[] = $max_spend;
        }
        
        $results = $wpdb->get_col($wpdb->prepare($query, $params));
        
        return array_map('intval', $results);
    }
    
    /**
     * Get SMS opted-in contacts
     */
    private static function get_sms_opted_in_contacts($options) {
        global $wpdb;
        
        $results = $wpdb->get_col("
            SELECT id FROM {$wpdb->prefix}ptp_contacts 
            WHERE opted_in = 1 AND opted_out = 0
        ");
        
        return array_map('intval', $results);
    }
    
    /**
     * Get contacts with a custom segment tag
     */
    private static function get_custom_segment_contacts($options) {
        global $wpdb;
        
        $segment_name = isset($options['segment_name']) ? sanitize_text_field($options['segment_name']) : '';
        
        if (empty($segment_name)) {
            return array();
        }
        
        // Get all contacts and filter by segment
        $contacts = $wpdb->get_results("
            SELECT id, segments FROM {$wpdb->prefix}ptp_contacts 
            WHERE segments IS NOT NULL AND segments != ''
        ");
        
        $contact_ids = array();
        foreach ($contacts as $contact) {
            $segments = array_map('trim', explode(',', $contact->segments));
            if (in_array($segment_name, $segments)) {
                $contact_ids[] = intval($contact->id);
            }
        }
        
        return $contact_ids;
    }
    
    /**
     * Get all custom segment names that exist
     */
    public static function get_existing_custom_segments() {
        global $wpdb;
        
        $contacts = $wpdb->get_col("
            SELECT DISTINCT segments FROM {$wpdb->prefix}ptp_contacts 
            WHERE segments IS NOT NULL AND segments != ''
        ");
        
        $all_segments = array();
        foreach ($contacts as $segments_string) {
            $segments = array_map('trim', explode(',', $segments_string));
            foreach ($segments as $seg) {
                if (!empty($seg) && !in_array($seg, $all_segments)) {
                    $all_segments[] = $seg;
                }
            }
        }
        
        sort($all_segments);
        return $all_segments;
    }
    
    /**
     * Get segment statistics for dashboard
     */
    public static function get_segment_stats() {
        global $wpdb;
        
        return array(
            'total_contacts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts"),
            'opted_in' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1 AND opted_out = 0"),
            'opted_out' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_out = 1"),
            'with_orders' => $wpdb->get_var("SELECT COUNT(DISTINCT contact_id) FROM {$wpdb->prefix}ptp_registrations"),
            'upcoming_events' => $wpdb->get_var("SELECT COUNT(DISTINCT contact_id) FROM {$wpdb->prefix}ptp_registrations WHERE event_date >= CURDATE() AND registration_status = 'confirmed'"),
            'past_events' => $wpdb->get_var("SELECT COUNT(DISTINCT contact_id) FROM {$wpdb->prefix}ptp_registrations WHERE event_date < CURDATE() AND registration_status = 'confirmed'"),
            'custom_segments' => count(self::get_existing_custom_segments()),
        );
    }
}
