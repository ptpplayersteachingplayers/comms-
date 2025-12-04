<?php
/**
 * Campaigns management class
 * v3.4.0 - Queue-based sending for scalability, progress tracking, pause/resume
 */
class PTP_Comms_Hub_Campaigns {
    
    /**
     * Initialize campaign hooks
     */
    public static function init() {
        // Cron job for processing campaign queue
        add_action('ptp_comms_process_campaign_queue', array(__CLASS__, 'process_queue'));
        
        // Schedule queue processor if not already scheduled
        if (!wp_next_scheduled('ptp_comms_process_campaign_queue')) {
            wp_schedule_event(time(), 'every_minute', 'ptp_comms_process_campaign_queue');
        }
        
        // Add custom cron schedule
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedules'));
    }
    
    /**
     * Add custom cron schedules
     */
    public static function add_cron_schedules($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'ptp-comms-hub')
        );
        return $schedules;
    }
    
    /**
     * Create new campaign
     */
    public static function create_campaign($data) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'draft',
            'message_type' => 'sms',
            'target_segment' => 'all',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptp_campaigns',
            $data
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get campaign by ID
     */
    public static function get_campaign($campaign_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_campaigns WHERE id = %d",
            $campaign_id
        ));
    }
    
    /**
     * Start sending campaign - queues all recipients
     */
    public static function send_campaign($campaign_id) {
        global $wpdb;
        
        // Rate limiting check
        $recent_campaigns = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_campaigns 
            WHERE status IN ('sending', 'completed') 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND created_by = %d",
            get_current_user_id()
        ));
        
        if ($recent_campaigns >= 10) {
            return array(
                'success' => false,
                'error' => 'Rate limit exceeded. Maximum 10 campaigns per hour.'
            );
        }
        
        $campaign = self::get_campaign($campaign_id);
        
        if (!$campaign || !in_array($campaign->status, array('draft', 'paused'))) {
            return array('success' => false, 'error' => 'Invalid campaign or status');
        }
        
        // Get recipients based on segment
        $recipients = self::get_campaign_recipients($campaign->target_segment);
        
        if (empty($recipients)) {
            return array(
                'success' => false,
                'error' => 'No recipients found for this segment.'
            );
        }
        
        // Queue all recipients
        $queued = 0;
        foreach ($recipients as $contact) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'ptp_campaign_queue',
                array(
                    'campaign_id' => $campaign_id,
                    'contact_id' => $contact->id,
                    'phone_number' => $contact->parent_phone,
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                )
            );
            if ($result) $queued++;
        }
        
        // Update campaign status
        $wpdb->update(
            $wpdb->prefix . 'ptp_campaigns',
            array(
                'status' => 'sending',
                'total_recipients' => $queued,
                'started_at' => current_time('mysql')
            ),
            array('id' => $campaign_id)
        );
        
        // Fire action hook
        do_action('ptp_comms_campaign_started', $campaign_id, $queued);
        
        return array(
            'success' => true,
            'queued' => $queued,
            'message' => sprintf('Campaign started! %d messages queued for sending.', $queued)
        );
    }
    
    /**
     * Process campaign queue - runs via cron every minute
     */
    public static function process_queue() {
        global $wpdb;
        
        // Get active campaigns
        $campaigns = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ptp_campaigns WHERE status = 'sending'"
        );
        
        if (empty($campaigns)) {
            return;
        }
        
        $settings = class_exists('PTP_Comms_Hub_Settings') ? PTP_Comms_Hub_Settings::get_all() : array();
        $batch_size = isset($settings['campaign_batch_size']) ? intval($settings['campaign_batch_size']) : 50;
        
        foreach ($campaigns as $campaign) {
            // Get pending queue items for this campaign
            $queue_items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_campaign_queue 
                 WHERE campaign_id = %d AND status = 'pending' 
                 ORDER BY id ASC LIMIT %d",
                $campaign->id,
                $batch_size
            ));
            
            if (empty($queue_items)) {
                // No more items - mark campaign as completed
                self::complete_campaign($campaign->id);
                continue;
            }
            
            // Check quiet hours
            if (class_exists('PTP_Comms_Hub_Automations') && 
                method_exists('PTP_Comms_Hub_Automations', 'is_quiet_hours')) {
                // Skip processing during quiet hours for non-urgent campaigns
                continue;
            }
            
            // Process batch
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $sent = 0;
            $failed = 0;
            
            foreach ($queue_items as $item) {
                // Get contact for variable replacement
                $contact = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
                    $item->contact_id
                ));
                
                if (!$contact) {
                    self::update_queue_status($item->id, 'failed', 'Contact not found');
                    $failed++;
                    continue;
                }
                
                // Replace variables in message
                $message = ptp_comms_replace_variables($campaign->message_content, (array) $contact);
                
                // Fire before send hook
                do_action('ptp_comms_before_send_message', $item->contact_id, $message, 'sms');
                
                // Send SMS
                $result = $sms_service->send_sms($item->phone_number, $message);
                
                if ($result && !empty($result['success'])) {
                    self::update_queue_status($item->id, 'sent', null, isset($result['sid']) ? $result['sid'] : '');
                    $sent++;
                    
                    // Log the message
                    ptp_comms_log_message(
                        $item->contact_id,
                        'sms',
                        'outbound',
                        $message,
                        array(
                            'campaign_id' => $campaign->id,
                            'twilio_sid' => isset($result['sid']) ? $result['sid'] : ''
                        )
                    );
                    
                    // Fire after send hook
                    do_action('ptp_comms_after_send_message', $item->contact_id, $message, 'sms', $result);
                } else {
                    $error = isset($result['error']) ? $result['error'] : 'Unknown error';
                    self::update_queue_status($item->id, 'failed', $error);
                    $failed++;
                    
                    // Log failure
                    ptp_comms_log_message(
                        $item->contact_id,
                        'sms',
                        'outbound',
                        $message,
                        array(
                            'campaign_id' => $campaign->id,
                            'status' => 'failed',
                            'error' => $error
                        )
                    );
                }
                
                // Small delay between messages to avoid rate limiting
                usleep(100000); // 0.1 second
            }
            
            // Update campaign stats
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ptp_campaigns 
                 SET sent_count = sent_count + %d, failed_count = failed_count + %d 
                 WHERE id = %d",
                $sent,
                $failed,
                $campaign->id
            ));
        }
    }
    
    /**
     * Update queue item status
     */
    private static function update_queue_status($queue_id, $status, $error = null, $twilio_sid = null) {
        global $wpdb;
        
        $data = array(
            'status' => $status,
            'processed_at' => current_time('mysql')
        );
        
        if ($error) {
            $data['error_message'] = $error;
        }
        
        if ($twilio_sid) {
            $data['twilio_sid'] = $twilio_sid;
        }
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_campaign_queue',
            $data,
            array('id' => $queue_id)
        );
    }
    
    /**
     * Complete campaign
     */
    private static function complete_campaign($campaign_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_campaigns',
            array(
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ),
            array('id' => $campaign_id)
        );
        
        // Get final stats
        $campaign = self::get_campaign($campaign_id);
        
        // Notify Teams
        if (function_exists('ptp_comms_is_teams_configured') && ptp_comms_is_teams_configured()) {
            if (class_exists('PTP_Comms_Hub_Teams_Integration')) {
                PTP_Comms_Hub_Teams_Integration::notify_campaign_complete(
                    $campaign,
                    $campaign->sent_count,
                    $campaign->failed_count
                );
            }
        }
        
        // Fire completion hook
        do_action('ptp_comms_campaign_completed', $campaign_id, $campaign->sent_count, $campaign->failed_count);
    }
    
    /**
     * Pause campaign
     */
    public static function pause_campaign($campaign_id) {
        global $wpdb;
        
        $campaign = self::get_campaign($campaign_id);
        
        if (!$campaign || $campaign->status !== 'sending') {
            return false;
        }
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_campaigns',
            array('status' => 'paused'),
            array('id' => $campaign_id)
        );
        
        return true;
    }
    
    /**
     * Resume paused campaign
     */
    public static function resume_campaign($campaign_id) {
        global $wpdb;
        
        $campaign = self::get_campaign($campaign_id);
        
        if (!$campaign || $campaign->status !== 'paused') {
            return false;
        }
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_campaigns',
            array('status' => 'sending'),
            array('id' => $campaign_id)
        );
        
        return true;
    }
    
    /**
     * Get campaign progress
     */
    public static function get_campaign_progress($campaign_id) {
        global $wpdb;
        
        $campaign = self::get_campaign($campaign_id);
        
        if (!$campaign) {
            return null;
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
             FROM {$wpdb->prefix}ptp_campaign_queue 
             WHERE campaign_id = %d",
            $campaign_id
        ));
        
        $total = intval($stats->total);
        $processed = intval($stats->sent) + intval($stats->failed);
        
        return array(
            'campaign_id' => $campaign_id,
            'status' => $campaign->status,
            'total' => $total,
            'sent' => intval($stats->sent),
            'failed' => intval($stats->failed),
            'pending' => intval($stats->pending),
            'progress_percent' => $total > 0 ? round(($processed / $total) * 100, 1) : 0
        );
    }
    
    /**
     * Get campaign recipients based on segment
     */
    private static function get_campaign_recipients($segment) {
        global $wpdb;
        
        $where_conditions = array(
            'opted_in = 1',
            'opted_out = 0',
            "parent_phone IS NOT NULL",
            "parent_phone != ''"
        );
        $where_params = array();
        
        // Parse segment - could be market, program type, or custom segment
        if ($segment !== 'all' && !empty($segment)) {
            // Check if it's a market code
            $markets = array('PA', 'NJ', 'DE', 'MD', 'NY');
            if (in_array(strtoupper($segment), $markets)) {
                $where_conditions[] = 'state = %s';
                $where_params[] = strtoupper($segment);
            }
            // Check if it's a JSON segment query
            elseif (strpos($segment, 'segment:') === 0) {
                $segment_name = substr($segment, 8);
                $where_conditions[] = "segments LIKE %s";
                $where_params[] = '%' . $wpdb->esc_like($segment_name) . '%';
            }
            // Assume it's a market/region otherwise
            else {
                $where_conditions[] = '(state = %s OR segments LIKE %s)';
                $where_params[] = $segment;
                $where_params[] = '%' . $wpdb->esc_like($segment) . '%';
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $sql = "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE {$where_clause}";
        
        if (!empty($where_params)) {
            $sql = $wpdb->prepare($sql, ...$where_params);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get all campaigns
     */
    public static function get_all_campaigns($limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as created_by_name
             FROM {$wpdb->prefix}ptp_campaigns c
             LEFT JOIN {$wpdb->users} u ON c.created_by = u.ID
             ORDER BY c.created_at DESC
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Delete campaign
     */
    public static function delete_campaign($campaign_id) {
        global $wpdb;
        
        // Delete queue items first
        $wpdb->delete(
            $wpdb->prefix . 'ptp_campaign_queue',
            array('campaign_id' => $campaign_id)
        );
        
        // Delete campaign
        return $wpdb->delete(
            $wpdb->prefix . 'ptp_campaigns',
            array('id' => $campaign_id)
        );
    }
    
    /**
     * Export campaign results to CSV
     */
    public static function export_campaign_csv($campaign_id) {
        global $wpdb;
        
        $campaign = self::get_campaign($campaign_id);
        if (!$campaign) {
            return false;
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT q.*, c.parent_first_name, c.parent_last_name, c.parent_phone, c.parent_email
             FROM {$wpdb->prefix}ptp_campaign_queue q
             LEFT JOIN {$wpdb->prefix}ptp_contacts c ON q.contact_id = c.id
             WHERE q.campaign_id = %d
             ORDER BY q.id ASC",
            $campaign_id
        ));
        
        $csv_data = array();
        $csv_data[] = array('Contact ID', 'Name', 'Phone', 'Email', 'Status', 'Processed At', 'Error');
        
        foreach ($results as $row) {
            $csv_data[] = array(
                $row->contact_id,
                trim($row->parent_first_name . ' ' . $row->parent_last_name),
                $row->parent_phone,
                $row->parent_email,
                $row->status,
                $row->processed_at,
                $row->error_message
            );
        }
        
        return $csv_data;
    }
}

// Initialize campaigns
add_action('init', array('PTP_Comms_Hub_Campaigns', 'init'));
