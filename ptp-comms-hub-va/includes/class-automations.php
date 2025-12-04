<?php
/**
 * Automation processing class
 * v3.4.0 - Added new triggers, unified reminder logic, improved event data handling
 */
class PTP_Comms_Hub_Automations {
    
    /**
     * Get all active automations
     */
    public static function get_active_automations($trigger_type = null) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$wpdb->prefix}ptp_automations WHERE is_active = 1";
        
        if ($trigger_type) {
            $sql .= $wpdb->prepare(" AND trigger_type = %s", $trigger_type);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Execute automation for a contact
     */
    public static function execute_automation($automation_id, $contact_id, $event_data = array()) {
        global $wpdb;
        
        // Get automation details
        $automation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_automations WHERE id = %d AND is_active = 1",
            $automation_id
        ));
        
        if (!$automation) {
            return false;
        }
        
        // Get contact details
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact || !ptp_comms_is_opted_in($contact_id)) {
            return false;
        }
        
        // Check quiet hours before sending
        if (self::is_quiet_hours()) {
            // Queue for next allowed time
            $next_allowed = self::get_next_allowed_time();
            wp_schedule_single_event(
                $next_allowed,
                'ptp_comms_execute_delayed_automation',
                array($automation_id, $contact_id, $event_data)
            );
            error_log("[PTP Automations] Message queued for quiet hours, will send at " . date('Y-m-d H:i:s', $next_allowed));
            return true;
        }
        
        // Check if delay is needed
        if ($automation->delay_minutes > 0) {
            // Schedule for later execution
            wp_schedule_single_event(
                time() + ($automation->delay_minutes * 60),
                'ptp_comms_execute_delayed_automation',
                array($automation_id, $contact_id, $event_data)
            );
            return true;
        }
        
        // Get template
        if ($automation->template_id) {
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_templates WHERE id = %d",
                $automation->template_id
            ));
            
            if ($template) {
                // Replace variables in template
                $message = ptp_comms_replace_variables(
                    $template->content,
                    (array) $contact,
                    $event_data
                );
                
                // Send message
                if ($template->message_type === 'sms') {
                    $sms_service = new PTP_Comms_Hub_SMS_Service();
                    $result = $sms_service->send_sms($contact->parent_phone, $message);
                    
                    if ($result && !empty($result['success'])) {
                        // Log the message
                        ptp_comms_log_message(
                            $contact_id,
                            'sms',
                            'outbound',
                            $message,
                            array(
                                'twilio_sid' => isset($result['sid']) ? $result['sid'] : '',
                                'automation_id' => $automation_id
                            )
                        );
                        
                        // Update execution count
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}ptp_automations SET execution_count = execution_count + 1 WHERE id = %d",
                            $automation_id
                        ));
                        
                        // Fire action hook
                        do_action('ptp_comms_after_automation_run', $automation_id, $contact_id, $result);
                        
                        return true;
                    }
                } elseif ($template->message_type === 'voice') {
                    $voice_service = new PTP_Comms_Hub_Voice_Service();
                    $result = $voice_service->make_call($contact->parent_phone, $message);
                    
                    if ($result) {
                        ptp_comms_log_message(
                            $contact_id,
                            'voice',
                            'outbound',
                            $message,
                            array(
                                'twilio_sid' => isset($result['sid']) ? $result['sid'] : '',
                                'automation_id' => $automation_id
                            )
                        );
                        
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}ptp_automations SET execution_count = execution_count + 1 WHERE id = %d",
                            $automation_id
                        ));
                        
                        do_action('ptp_comms_after_automation_run', $automation_id, $contact_id, $result);
                        
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Trigger automations based on event
     */
    public static function trigger($trigger_type, $contact_id, $event_data = array()) {
        $automations = self::get_active_automations($trigger_type);
        
        foreach ($automations as $automation) {
            // Check conditions if any
            if ($automation->conditions) {
                $conditions = maybe_unserialize($automation->conditions);
                if (!self::check_conditions($conditions, $contact_id, $event_data)) {
                    continue;
                }
            }
            
            // Execute automation
            self::execute_automation($automation->id, $contact_id, $event_data);
        }
    }
    
    /**
     * Check if automation conditions are met
     */
    private static function check_conditions($conditions, $contact_id, $event_data) {
        if (empty($conditions)) {
            return true;
        }
        
        global $wpdb;
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        // Simple condition checking
        foreach ($conditions as $key => $value) {
            if (isset($contact->$key) && $contact->$key != $value) {
                return false;
            }
            if (isset($event_data[$key]) && $event_data[$key] != $value) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if currently in quiet hours
     */
    private static function is_quiet_hours() {
        $settings = class_exists('PTP_Comms_Hub_Settings') ? PTP_Comms_Hub_Settings::get_all() : array();
        
        if (empty($settings['quiet_hours_enabled']) || $settings['quiet_hours_enabled'] !== 'yes') {
            return false;
        }
        
        $start_hour = isset($settings['quiet_hours_start']) ? intval($settings['quiet_hours_start']) : 21; // 9 PM
        $end_hour = isset($settings['quiet_hours_end']) ? intval($settings['quiet_hours_end']) : 8; // 8 AM
        
        $current_hour = intval(current_time('G'));
        
        // Handle overnight quiet hours (e.g., 9 PM - 8 AM)
        if ($start_hour > $end_hour) {
            return ($current_hour >= $start_hour || $current_hour < $end_hour);
        }
        
        return ($current_hour >= $start_hour && $current_hour < $end_hour);
    }
    
    /**
     * Get next allowed send time (after quiet hours)
     */
    private static function get_next_allowed_time() {
        $settings = class_exists('PTP_Comms_Hub_Settings') ? PTP_Comms_Hub_Settings::get_all() : array();
        $end_hour = isset($settings['quiet_hours_end']) ? intval($settings['quiet_hours_end']) : 8;
        
        $tomorrow = strtotime('tomorrow ' . $end_hour . ':00:00');
        $today = strtotime('today ' . $end_hour . ':00:00');
        
        // If end hour hasn't passed today, use today
        if ($today > time()) {
            return $today;
        }
        
        return $tomorrow;
    }
    
    /**
     * Process pending automations (cron callback)
     * UNIFIED REMINDER LOGIC - This is the primary system for event reminders
     */
    public static function process_pending_automations() {
        global $wpdb;
        
        $settings = class_exists('PTP_Comms_Hub_Settings') ? PTP_Comms_Hub_Settings::get_all() : array();
        
        // Get registrations with confirmed status
        $registrations = $wpdb->get_results("
            SELECT r.*, c.parent_phone, c.parent_first_name, c.parent_last_name, c.parent_email, 
                   c.child_name as contact_child_name, c.child_age as contact_child_age,
                   c.opted_in, c.opted_out, c.id as contact_id_from_contacts
            FROM {$wpdb->prefix}ptp_registrations r
            JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
            WHERE r.registration_status IN ('confirmed', 'completed')
            AND r.event_date >= CURDATE()
            AND c.opted_in = 1 AND c.opted_out = 0
        ");
        
        $today = strtotime('today');
        
        foreach ($registrations as $reg) {
            if (empty($reg->event_date)) continue;
            
            $event_timestamp = strtotime($reg->event_date);
            $days_until = floor(($event_timestamp - $today) / 86400);
            
            // Build event data array with all necessary fields
            $event_data = array(
                'event_name' => $reg->product_name,
                'product_name' => $reg->product_name,
                'event_date' => $reg->event_date,
                'event_end_date' => $reg->event_end_date,
                'event_time' => $reg->event_time,
                'camp_time' => $reg->event_time,
                'event_location' => $reg->event_location,
                'event_address' => $reg->event_address,
                'program_type' => $reg->program_type,
                'market_slug' => $reg->market,
                'market' => $reg->market,
                'what_to_bring' => $reg->what_to_bring,
                'head_coach' => $reg->head_coach,
                'child_name' => !empty($reg->child_name) ? $reg->child_name : $reg->contact_child_name,
                'child_age' => !empty($reg->child_age) ? $reg->child_age : $reg->contact_child_age,
                'order_id' => $reg->order_id,
                'registration_status' => $reg->registration_status,
                'contact_id' => $reg->contact_id
            );
            
            // 7-day reminder
            if ($days_until == 7 && empty($reg->reminder_7day_sent)) {
                self::trigger('event_approaching_7d', $reg->contact_id, $event_data);
                
                // Mark reminder as sent
                $wpdb->update(
                    $wpdb->prefix . 'ptp_registrations',
                    array('reminder_7day_sent' => current_time('mysql')),
                    array('id' => $reg->id)
                );
            }
            
            // 3-day reminder
            if ($days_until == 3 && empty($reg->reminder_3day_sent)) {
                self::trigger('event_approaching_3d', $reg->contact_id, $event_data);
                
                $wpdb->update(
                    $wpdb->prefix . 'ptp_registrations',
                    array('reminder_3day_sent' => current_time('mysql')),
                    array('id' => $reg->id)
                );
            }
            
            // 1-day reminder
            if ($days_until == 1 && empty($reg->reminder_1day_sent)) {
                self::trigger('event_approaching_1d', $reg->contact_id, $event_data);
                
                $wpdb->update(
                    $wpdb->prefix . 'ptp_registrations',
                    array('reminder_1day_sent' => current_time('mysql')),
                    array('id' => $reg->id)
                );
            }
        }
        
        // Process completed events (day after)
        $completed_events = $wpdb->get_results("
            SELECT r.*, c.parent_phone, c.parent_first_name, c.parent_last_name,
                   c.child_name as contact_child_name, c.opted_in, c.opted_out
            FROM {$wpdb->prefix}ptp_registrations r
            JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
            WHERE r.event_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND r.registration_status IN ('confirmed', 'completed')
            AND c.opted_in = 1 AND c.opted_out = 0
        ");
        
        foreach ($completed_events as $event) {
            $event_data = self::build_event_data_from_registration($event);
            self::trigger('event_completed', $event->contact_id, $event_data);
        }
        
        // Process 7-day follow-up (for upsells)
        $followup_events = $wpdb->get_results("
            SELECT r.*, c.parent_phone, c.parent_first_name, c.parent_last_name,
                   c.child_name as contact_child_name, c.opted_in, c.opted_out
            FROM {$wpdb->prefix}ptp_registrations r
            JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
            WHERE r.event_date = DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND r.registration_status IN ('confirmed', 'completed')
            AND c.opted_in = 1 AND c.opted_out = 0
        ");
        
        foreach ($followup_events as $event) {
            $event_data = self::build_event_data_from_registration($event);
            self::trigger('event_followup_7d', $event->contact_id, $event_data);
        }
        
        // Process clinic-to-camp upsell (contacts who attended clinic but no camp registration)
        self::process_clinic_upsell_triggers();
        
        // Process date window triggers (e.g., Black Friday)
        self::process_date_window_triggers();
    }
    
    /**
     * Build event data array from registration record
     */
    private static function build_event_data_from_registration($reg) {
        return array(
            'event_name' => $reg->product_name,
            'product_name' => $reg->product_name,
            'event_date' => $reg->event_date,
            'event_end_date' => isset($reg->event_end_date) ? $reg->event_end_date : null,
            'event_time' => isset($reg->event_time) ? $reg->event_time : '',
            'camp_time' => isset($reg->event_time) ? $reg->event_time : '',
            'event_location' => isset($reg->event_location) ? $reg->event_location : '',
            'event_address' => isset($reg->event_address) ? $reg->event_address : '',
            'program_type' => isset($reg->program_type) ? $reg->program_type : '',
            'market_slug' => isset($reg->market) ? $reg->market : '',
            'market' => isset($reg->market) ? $reg->market : '',
            'what_to_bring' => isset($reg->what_to_bring) ? $reg->what_to_bring : '',
            'head_coach' => isset($reg->head_coach) ? $reg->head_coach : '',
            'child_name' => !empty($reg->child_name) ? $reg->child_name : (isset($reg->contact_child_name) ? $reg->contact_child_name : ''),
            'child_age' => !empty($reg->child_age) ? $reg->child_age : '',
            'order_id' => isset($reg->order_id) ? $reg->order_id : '',
            'registration_status' => isset($reg->registration_status) ? $reg->registration_status : 'confirmed',
            'contact_id' => $reg->contact_id
        );
    }
    
    /**
     * Process clinic-to-camp upsell triggers
     */
    private static function process_clinic_upsell_triggers() {
        global $wpdb;
        
        // Find contacts who attended a clinic in the past 14 days but have no upcoming camp registration
        $clinic_contacts = $wpdb->get_results("
            SELECT DISTINCT r.contact_id, c.parent_first_name, c.parent_phone, c.opted_in, c.opted_out,
                   r.child_name, r.product_name as clinic_name
            FROM {$wpdb->prefix}ptp_registrations r
            JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
            WHERE r.program_type = 'clinic'
            AND r.event_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND r.registration_status IN ('confirmed', 'completed')
            AND c.opted_in = 1 AND c.opted_out = 0
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->prefix}ptp_registrations r2
                WHERE r2.contact_id = r.contact_id
                AND r2.program_type IN ('half_day', 'full_day', 'week_camp')
                AND r2.event_date >= CURDATE()
                AND r2.registration_status IN ('confirmed', 'completed', 'pending')
            )
        ");
        
        foreach ($clinic_contacts as $contact) {
            $event_data = array(
                'clinic_name' => $contact->clinic_name,
                'child_name' => $contact->child_name
            );
            self::trigger('clinic_no_camp_purchase', $contact->contact_id, $event_data);
        }
    }
    
    /**
     * Process date window triggers (for promo campaigns)
     */
    private static function process_date_window_triggers() {
        global $wpdb;
        
        // Get all active date window automations
        $automations = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}ptp_automations 
            WHERE is_active = 1 
            AND trigger_type LIKE '%_window'
        ");
        
        $today = date('Y-m-d');
        
        foreach ($automations as $automation) {
            $conditions = maybe_unserialize($automation->conditions);
            
            if (empty($conditions['start_date']) || empty($conditions['end_date'])) {
                continue;
            }
            
            // Check if today is within the date window
            if ($today >= $conditions['start_date'] && $today <= $conditions['end_date']) {
                // Get all opted-in contacts that haven't received this automation yet today
                $contacts = $wpdb->get_results($wpdb->prepare("
                    SELECT c.* FROM {$wpdb->prefix}ptp_contacts c
                    WHERE c.opted_in = 1 AND c.opted_out = 0
                    AND NOT EXISTS (
                        SELECT 1 FROM {$wpdb->prefix}ptp_communication_logs l
                        WHERE l.contact_id = c.id
                        AND l.created_at >= %s
                        AND l.message_content LIKE %s
                    )
                ",
                    $today . ' 00:00:00',
                    '%' . $wpdb->esc_like($automation->name) . '%'
                ));
                
                foreach ($contacts as $contact) {
                    self::execute_automation($automation->id, $contact->id, array());
                }
            }
        }
    }
    
    /**
     * Create new automation
     */
    public static function create_automation($data) {
        global $wpdb;
        
        $defaults = array(
            'is_active' => 1,
            'delay_minutes' => 0,
            'execution_count' => 0,
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Serialize conditions if array
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = maybe_serialize($data['conditions']);
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptp_automations',
            $data
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update automation
     */
    public static function update_automation($id, $data) {
        global $wpdb;
        
        // Serialize conditions if array
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = maybe_serialize($data['conditions']);
        }
        
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_automations',
            $data,
            array('id' => $id)
        );
    }
    
    /**
     * Delete automation
     */
    public static function delete_automation($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'ptp_automations',
            array('id' => $id)
        );
    }
    
    /**
     * Toggle automation status
     */
    public static function toggle_automation($id) {
        global $wpdb;
        
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM {$wpdb->prefix}ptp_automations WHERE id = %d",
            $id
        ));
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_automations',
            array('is_active' => !$current),
            array('id' => $id)
        );
    }
    
    /**
     * Get automation by ID
     */
    public static function get_automation($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_automations WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get all automations
     */
    public static function get_all_automations() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT a.*, t.name as template_name
            FROM {$wpdb->prefix}ptp_automations a
            LEFT JOIN {$wpdb->prefix}ptp_templates t ON a.template_id = t.id
            ORDER BY a.created_at DESC
        ");
    }
    
    /**
     * Get available trigger types
     */
    public static function get_trigger_types() {
        return array(
            'order_placed' => 'Order Placed',
            'event_approaching_7d' => 'Event Approaching (7 days)',
            'event_approaching_3d' => 'Event Approaching (3 days)',
            'event_approaching_1d' => 'Event Approaching (1 day)',
            'event_completed' => 'Event Completed (Day After)',
            'event_followup_7d' => 'Event Follow-up (7 days after) - Upsell',
            'new_contact' => 'New Contact Created',
            'clinic_no_camp_purchase' => 'Clinic Attendee - No Camp Registration',
            'promo_window' => 'Promotional Date Window'
        );
    }
}
