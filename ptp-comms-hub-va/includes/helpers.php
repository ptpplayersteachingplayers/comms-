<?php
/**
 * Helper functions for PTP Comms Hub
 */

/**
 * Replace template variables with actual contact/event data
 * v3.4.0 - Expanded variables to include all camp meta fields
 *
 * @param string $message The message template with variables
 * @param array $contact Contact data array
 * @param array $event Event data array (optional)
 * @return string Message with variables replaced
 */
function ptp_comms_replace_variables($message, $contact, $event = array()) {
    $replacements = array();
    
    // Contact variables
    $replacements['{parent_first_name}'] = !empty($contact['parent_first_name']) ? $contact['parent_first_name'] : '';
    $replacements['{parent_last_name}'] = !empty($contact['parent_last_name']) ? $contact['parent_last_name'] : '';
    $replacements['{parent_name}'] = trim((!empty($contact['parent_first_name']) ? $contact['parent_first_name'] : '') . ' ' . (!empty($contact['parent_last_name']) ? $contact['parent_last_name'] : ''));
    $replacements['{parent_phone}'] = !empty($contact['parent_phone']) ? ptp_comms_format_phone($contact['parent_phone']) : '';
    $replacements['{parent_email}'] = !empty($contact['parent_email']) ? $contact['parent_email'] : '';
    $replacements['{child_name}'] = !empty($contact['child_name']) ? $contact['child_name'] : '';
    $replacements['{child_age}'] = !empty($contact['child_age']) ? $contact['child_age'] : '';
    $replacements['{city}'] = !empty($contact['city']) ? $contact['city'] : '';
    $replacements['{state}'] = !empty($contact['state']) ? $contact['state'] : '';
    $replacements['{zip_code}'] = !empty($contact['zip_code']) ? $contact['zip_code'] : '';
    
    // Event/Camp variables - Core
    $replacements['{event_name}'] = !empty($event['event_name']) ? $event['event_name'] : (!empty($event['product_name']) ? $event['product_name'] : '');
    $replacements['{product_name}'] = !empty($event['product_name']) ? $event['product_name'] : (!empty($event['event_name']) ? $event['event_name'] : '');
    $replacements['{camp_name}'] = $replacements['{event_name}']; // Alias
    
    // Event date formatting
    $event_date_raw = !empty($event['event_date']) ? $event['event_date'] : '';
    $replacements['{event_date}'] = $event_date_raw ? date('F j, Y', strtotime($event_date_raw)) : '';
    $replacements['{event_date_short}'] = $event_date_raw ? date('M j', strtotime($event_date_raw)) : '';
    $replacements['{event_date_day}'] = $event_date_raw ? date('l', strtotime($event_date_raw)) : '';
    $replacements['{camp_date}'] = $replacements['{event_date}']; // Alias
    
    // End date
    $event_end_date_raw = !empty($event['event_end_date']) ? $event['event_end_date'] : '';
    $replacements['{event_end_date}'] = $event_end_date_raw ? date('F j, Y', strtotime($event_end_date_raw)) : '';
    $replacements['{camp_end_date}'] = $replacements['{event_end_date}']; // Alias
    
    // Date range (e.g., "June 15-19, 2025")
    if ($event_date_raw && $event_end_date_raw && $event_date_raw !== $event_end_date_raw) {
        $start = strtotime($event_date_raw);
        $end = strtotime($event_end_date_raw);
        if (date('Y-m', $start) === date('Y-m', $end)) {
            // Same month: "June 15-19, 2025"
            $replacements['{date_range}'] = date('F j', $start) . '-' . date('j, Y', $end);
        } else {
            // Different months: "June 28 - July 2, 2025"
            $replacements['{date_range}'] = date('F j', $start) . ' - ' . date('F j, Y', $end);
        }
    } else {
        $replacements['{date_range}'] = $replacements['{event_date}'];
    }
    
    // Time
    $replacements['{event_time}'] = !empty($event['event_time']) ? $event['event_time'] : (!empty($event['camp_time']) ? $event['camp_time'] : '');
    $replacements['{camp_time}'] = $replacements['{event_time}']; // Alias
    
    // Location
    $replacements['{event_location}'] = !empty($event['event_location']) ? $event['event_location'] : '';
    $replacements['{camp_location}'] = $replacements['{event_location}']; // Alias
    $replacements['{location}'] = $replacements['{event_location}']; // Alias
    $replacements['{event_address}'] = !empty($event['event_address']) ? $event['event_address'] : '';
    $replacements['{address}'] = $replacements['{event_address}']; // Alias
    $replacements['{maps_link}'] = !empty($event['maps_link']) ? $event['maps_link'] : '';
    $replacements['{google_maps_link}'] = $replacements['{maps_link}']; // Alias
    
    // Program info
    $replacements['{program_type}'] = !empty($event['program_type']) ? ptp_comms_format_program_type($event['program_type']) : '';
    $replacements['{program_type_raw}'] = !empty($event['program_type']) ? $event['program_type'] : '';
    
    // Market/Region
    $replacements['{market_slug}'] = !empty($event['market_slug']) ? $event['market_slug'] : (!empty($event['market']) ? $event['market'] : '');
    $replacements['{market}'] = $replacements['{market_slug}']; // Alias
    $replacements['{region}'] = ptp_comms_format_market($replacements['{market_slug}']);
    
    // Camp details
    $replacements['{what_to_bring}'] = !empty($event['what_to_bring']) ? $event['what_to_bring'] : 'Soccer cleats, shin guards, water bottle';
    $replacements['{head_coach}'] = !empty($event['head_coach']) ? $event['head_coach'] : '';
    $replacements['{coach}'] = $replacements['{head_coach}']; // Alias
    $replacements['{age_range}'] = !empty($event['age_range']) ? $event['age_range'] : '';
    
    // Child info from event (may override contact)
    if (!empty($event['child_name'])) {
        $replacements['{child_name}'] = $event['child_name'];
    }
    if (!empty($event['child_age'])) {
        $replacements['{child_age}'] = $event['child_age'];
    }
    
    // Order info
    $replacements['{order_id}'] = !empty($event['order_id']) ? $event['order_id'] : '';
    $replacements['{order_number}'] = $replacements['{order_id}']; // Alias
    
    // Registration status
    $replacements['{registration_status}'] = !empty($event['registration_status']) ? ucfirst($event['registration_status']) : '';
    
    // Contact ID (for internal use)
    $replacements['{contact_id}'] = !empty($contact['id']) ? $contact['id'] : (!empty($event['contact_id']) ? $event['contact_id'] : '');
    
    // Site info
    $replacements['{site_name}'] = get_bloginfo('name');
    $replacements['{site_url}'] = home_url();
    
    // Current date/time
    $replacements['{current_date}'] = date('F j, Y');
    $replacements['{current_year}'] = date('Y');
    
    // Apply custom filter for extensions
    $replacements = apply_filters('ptp_comms_template_variables', $replacements, $contact, $event);
    
    // Replace all variables
    $message = str_replace(array_keys($replacements), array_values($replacements), $message);
    
    // Clean up any remaining unreplaced variables
    $message = preg_replace('/{[^}]+}/', '', $message);
    
    return trim($message);
}

/**
 * Format program type for display
 */
function ptp_comms_format_program_type($type) {
    $types = array(
        'half_day' => 'Half Day Camp',
        'full_day' => 'Full Day Camp',
        'week_camp' => 'Week-Long Camp',
        'clinic' => 'Skills Clinic',
        'league' => 'League/Season',
        'private' => 'Private Training',
        'tournament' => 'Tournament'
    );
    
    return isset($types[$type]) ? $types[$type] : ucwords(str_replace('_', ' ', $type));
}

/**
 * Format market slug for display
 */
function ptp_comms_format_market($market) {
    $markets = array(
        'PA' => 'Pennsylvania',
        'NJ' => 'New Jersey',
        'DE' => 'Delaware',
        'MD' => 'Maryland',
        'NY' => 'New York'
    );
    
    return isset($markets[$market]) ? $markets[$market] : $market;
}

/**
 * Normalize phone number to E.164 format
 *
 * @param string $phone Phone number to normalize
 * @return string|false Normalized phone number or false on failure
 */
function ptp_comms_normalize_phone($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Handle US numbers
    if (strlen($phone) == 10) {
        return '+1' . $phone;
    } elseif (strlen($phone) == 11 && substr($phone, 0, 1) == '1') {
        return '+' . $phone;
    }
    
    // Already has country code
    if (strlen($phone) > 10 && substr($phone, 0, 1) != '1') {
        return '+' . $phone;
    }
    
    return false;
}

/**
 * Log communication activity
 *
 * @param int $contact_id Contact ID
 * @param string $message_type Type of message (sms, voice)
 * @param string $direction Direction (inbound, outbound)
 * @param string $content Message content
 * @param array $meta Additional metadata
 * @return int|false Log ID on success, false on failure
 */
function ptp_comms_log_message($contact_id, $message_type, $direction, $content, $meta = array()) {
    global $wpdb;
    
    error_log('[PTP Log Message] Contact ID: ' . $contact_id . ', Direction: ' . $direction . ', Type: ' . $message_type);
    
    // Get or create conversation
    $conversation_id = PTP_Comms_Hub_Conversations::get_or_create_conversation($contact_id);
    error_log('[PTP Log Message] Conversation ID: ' . $conversation_id);
    
    $current_time = current_time('mysql');
    
    // Log to messages table (for conversation view)
    $message_data = array(
        'conversation_id' => $conversation_id,
        'contact_id' => $contact_id,
        'message_type' => $message_type,
        'direction' => $direction,
        'message_body' => $content,
        'status' => ($direction === 'inbound') ? 'received' : (!empty($meta['status']) ? $meta['status'] : 'sent'),
        'twilio_sid' => !empty($meta['twilio_sid']) ? $meta['twilio_sid'] : '',
        'error_message' => !empty($meta['error']) ? $meta['error'] : null,
        'created_at' => $current_time,
        'updated_at' => $current_time
    );
    
    error_log('[PTP Log Message] Message data: ' . print_r($message_data, true));
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'ptp_messages',
        $message_data
    );
    
    $message_id = $result ? $wpdb->insert_id : false;
    error_log('[PTP Log Message] Inserted message ID: ' . $message_id);
    
    if (!$result) {
        error_log('[PTP Log Message] ERROR: ' . $wpdb->last_error);
    }
    
    // Also log to communication logs for reporting
    $log_data = array(
        'contact_id' => $contact_id,
        'message_type' => $message_type,
        'direction' => $direction,
        'message_content' => $content,
        'status' => ($direction === 'inbound') ? 'received' : (!empty($meta['status']) ? $meta['status'] : 'sent'),
        'twilio_sid' => !empty($meta['twilio_sid']) ? $meta['twilio_sid'] : '',
        'campaign_id' => !empty($meta['campaign_id']) ? $meta['campaign_id'] : null,
        'error_message' => !empty($meta['error']) ? $meta['error'] : null,
        'created_at' => $current_time
    );
    
    $wpdb->insert(
        $wpdb->prefix . 'ptp_communication_logs',
        $log_data
    );
    
    return $message_id;
}

/**
 * Get contact by phone number
 *
 * @param string $phone Phone number
 * @return object|null Contact object or null
 */
function ptp_comms_get_contact_by_phone($phone) {
    global $wpdb;
    
    $normalized = ptp_comms_normalize_phone($phone);
    if (!$normalized) {
        return null;
    }
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
        $normalized
    ));
}

/**
 * Get or create contact ID from phone number
 * If contact exists, update with new data (merging, not overwriting empty values)
 *
 * @param string $phone Phone number
 * @param array $data Additional contact data
 * @return int|false Contact ID or false on failure
 */
function ptp_comms_get_or_create_contact($phone, $data = array()) {
    global $wpdb;
    
    $normalized = ptp_comms_normalize_phone($phone);
    if (!$normalized) {
        error_log('[PTP Contact] Invalid phone number: ' . $phone);
        return false;
    }
    
    // Check if contact exists
    $contact = ptp_comms_get_contact_by_phone($normalized);
    
    if ($contact) {
        // Update existing contact with new data (don't overwrite with empty values)
        $update_data = array();
        
        // Fields that should be updated if provided and not empty
        $updateable_fields = array(
            'parent_first_name', 'parent_last_name', 'parent_email',
            'child_name', 'child_age', 'city', 'state', 'zip_code',
            'woo_order_id', 'source'
        );
        
        foreach ($updateable_fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                // Only update if current value is empty or we're setting a newer order
                if (empty($contact->$field) || $field === 'woo_order_id') {
                    $update_data[$field] = $data[$field];
                }
            }
        }
        
        // Always update opted_in if provided (for SMS opt-in on new orders)
        if (isset($data['opted_in']) && $data['opted_in'] == 1 && !$contact->opted_out) {
            $update_data['opted_in'] = 1;
        }
        
        // Update timestamp
        $update_data['updated_at'] = current_time('mysql');
        
        if (!empty($update_data)) {
            $wpdb->update(
                $wpdb->prefix . 'ptp_contacts',
                $update_data,
                array('id' => $contact->id)
            );
            error_log('[PTP Contact] Updated contact ' . $contact->id . ' with: ' . print_r($update_data, true));
        }
        
        return $contact->id;
    }
    
    // Create new contact
    $insert_data = array(
        'parent_phone' => $normalized,
        'opted_in' => isset($data['opted_in']) ? $data['opted_in'] : 1,
        'opted_out' => 0,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    );
    
    // Add provided data
    $allowed_fields = array(
        'parent_first_name', 'parent_last_name', 'parent_email',
        'child_name', 'child_age', 'city', 'state', 'zip_code',
        'woo_order_id', 'source', 'segments'
    );
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field]) && !empty($data[$field])) {
            $insert_data[$field] = $data[$field];
        }
    }
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'ptp_contacts',
        $insert_data
    );
    
    if ($result) {
        $contact_id = $wpdb->insert_id;
        error_log('[PTP Contact] Created new contact: ' . $contact_id);
        return $contact_id;
    }
    
    error_log('[PTP Contact] Failed to create contact: ' . $wpdb->last_error);
    return false;
}

/**
 * Check if contact has opted in
 *
 * @param int $contact_id Contact ID
 * @return bool True if opted in, false otherwise
 */
function ptp_comms_is_opted_in($contact_id) {
    global $wpdb;
    
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT opted_in FROM {$wpdb->prefix}ptp_contacts WHERE id = %d AND opted_out = 0",
        $contact_id
    ));
    
    return (bool) $result;
}

/**
 * Format phone number for display
 *
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function ptp_comms_format_phone($phone) {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($cleaned) == 11 && substr($cleaned, 0, 1) == '1') {
        $cleaned = substr($cleaned, 1);
    }
    
    if (strlen($cleaned) == 10) {
        return sprintf('(%s) %s-%s',
            substr($cleaned, 0, 3),
            substr($cleaned, 3, 3),
            substr($cleaned, 6)
        );
    }
    
    return $phone;
}

/**
 * Get setting value
 *
 * @param string $key Setting key
 * @param mixed $default Default value
 * @return mixed Setting value
 */
function ptp_comms_get_setting($key, $default = '') {
    return PTP_Comms_Hub_Settings::get($key, $default);
}

/**
 * Check if Twilio is configured
 *
 * @return bool True if configured, false otherwise
 */
function ptp_comms_is_twilio_configured() {
    $sid = ptp_comms_get_setting('twilio_account_sid');
    $token = ptp_comms_get_setting('twilio_auth_token');
    $from = ptp_comms_get_setting('twilio_phone_number');
    
    return !empty($sid) && !empty($token) && !empty($from);
}

/**
 * Check if HubSpot is configured
 *
 * @return bool True if configured, false otherwise
 */
function ptp_comms_is_hubspot_configured() {
    $api_key = ptp_comms_get_setting('hubspot_api_key');
    return !empty($api_key);
}

/**
 * Check if Microsoft Teams is configured
 *
 * @return bool True if configured, false otherwise
 */
function ptp_comms_is_teams_configured() {
    // Check for bot framework credentials (preferred)
    $bot_app_id = ptp_comms_get_setting('teams_bot_app_id');
    $bot_password = ptp_comms_get_setting('teams_bot_app_password');

    if (!empty($bot_app_id) && !empty($bot_password)) {
        return true;
    }

    // Fallback to webhook URL
    $webhook = ptp_comms_get_setting('teams_webhook_url');
    return !empty($webhook);
}

/**
 * Check if WhatsApp is configured
 *
 * @return bool True if configured, false otherwise
 */
function ptp_comms_is_whatsapp_configured() {
    // WhatsApp requires Twilio to be configured first
    if (!ptp_comms_is_twilio_configured()) {
        return false;
    }

    // Check if WhatsApp is enabled
    $enabled = ptp_comms_get_setting('whatsapp_enabled');
    if ($enabled !== 'yes') {
        return false;
    }

    // Check for WhatsApp phone number
    $whatsapp_phone = ptp_comms_get_setting('whatsapp_phone_number');
    return !empty($whatsapp_phone);
}
