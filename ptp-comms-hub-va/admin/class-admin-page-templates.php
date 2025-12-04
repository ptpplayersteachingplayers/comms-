<?php
/**
 * Templates admin page - v3.4.0
 * Updated available variables, added test send functionality
 */
class PTP_Comms_Hub_Admin_Page_Templates {
    public static function render() {
        // Handle test send
        if (isset($_POST['ptp_test_template']) && check_admin_referer('ptp_test_template_nonce')) {
            self::handle_test_send();
        }
        
        $templates = PTP_Comms_Hub_Templates::get_all_templates();
        ?>
        <div class="wrap ptp-comms-admin">
            <h1>Message Templates</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th>Name</th><th>Category</th><th>Type</th><th>Content</th><th>Usage</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><strong><?php echo esc_html($template->name); ?></strong></td>
                            <td><?php echo ucfirst($template->category); ?></td>
                            <td><?php echo strtoupper($template->message_type); ?></td>
                            <td><small><?php echo esc_html(substr($template->content, 0, 100)) . '...'; ?></small></td>
                            <td><?php echo number_format($template->usage_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Test Send Section -->
            <div class="ptp-card" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px;">
                <h3 style="margin-top: 0;">🧪 Test Template</h3>
                <p style="color: #666;">Send a test message using sample data to verify your templates work correctly.</p>
                
                <form method="post" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                    <?php wp_nonce_field('ptp_test_template_nonce'); ?>
                    
                    <div>
                        <label for="test_template_id" style="display: block; margin-bottom: 5px; font-weight: 600;">Template</label>
                        <select name="test_template_id" id="test_template_id" style="min-width: 200px;">
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo $template->id; ?>"><?php echo esc_html($template->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="test_phone" style="display: block; margin-bottom: 5px; font-weight: 600;">Phone Number</label>
                        <input type="tel" name="test_phone" id="test_phone" placeholder="+1 (555) 123-4567" style="min-width: 180px;" required>
                    </div>
                    
                    <div>
                        <button type="submit" name="ptp_test_template" class="button button-secondary">
                            📤 Send Test
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Available Variables Reference -->
            <div style="margin-top: 30px; background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%); padding: 25px; border-left: 4px solid #FCB900; border-radius: 5px;">
                <h3 style="margin-top: 0;">📝 Available Template Variables</h3>
                <p>Use these variables in your templates. They will be automatically replaced with actual data:</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-top: 20px;">
                    <div>
                        <h4 style="margin: 0 0 10px 0; color: #333;">👤 Parent/Contact</h4>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li><code>{parent_first_name}</code> - First name</li>
                            <li><code>{parent_last_name}</code> - Last name</li>
                            <li><code>{parent_name}</code> - Full name</li>
                            <li><code>{parent_phone}</code> - Phone number</li>
                            <li><code>{parent_email}</code> - Email address</li>
                            <li><code>{city}</code>, <code>{state}</code>, <code>{zip_code}</code></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="margin: 0 0 10px 0; color: #333;">👶 Camper</h4>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li><code>{child_name}</code> - Camper's name</li>
                            <li><code>{child_age}</code> - Camper's age</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="margin: 0 0 10px 0; color: #333;">📅 Event/Camp Dates</h4>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li><code>{event_name}</code> / <code>{camp_name}</code> - Camp name</li>
                            <li><code>{event_date}</code> / <code>{camp_date}</code> - Full date (June 15, 2025)</li>
                            <li><code>{event_date_short}</code> - Short date (Jun 15)</li>
                            <li><code>{event_date_day}</code> - Day name (Monday)</li>
                            <li><code>{event_end_date}</code> / <code>{camp_end_date}</code></li>
                            <li><code>{date_range}</code> - Date range (June 15-19, 2025)</li>
                            <li><code>{event_time}</code> / <code>{camp_time}</code> - Time (9:00 AM - 12:00 PM)</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="margin: 0 0 10px 0; color: #333;">📍 Location</h4>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li><code>{event_location}</code> / <code>{location}</code> - Venue name</li>
                            <li><code>{event_address}</code> / <code>{address}</code> - Full address</li>
                            <li><code>{maps_link}</code> / <code>{google_maps_link}</code></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="margin: 0 0 10px 0; color: #333;">⚽ Camp Details</h4>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li><code>{program_type}</code> - Program type (Half Day Camp)</li>
                            <li><code>{market}</code> / <code>{region}</code> - Market/State</li>
                            <li><code>{age_range}</code> - Age range (6-10)</li>
                            <li><code>{head_coach}</code> / <code>{coach}</code></li>
                            <li><code>{what_to_bring}</code> - Items to bring</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="margin: 0 0 10px 0; color: #333;">🛒 Order Info</h4>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
                            <li><code>{order_id}</code> / <code>{order_number}</code></li>
                            <li><code>{registration_status}</code> - Status (Confirmed)</li>
                            <li><code>{site_name}</code> - Website name</li>
                            <li><code>{site_url}</code> - Website URL</li>
                            <li><code>{current_date}</code>, <code>{current_year}</code></li>
                        </ul>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.03); border-radius: 5px;">
                    <strong>💡 Pro Tip:</strong> Use <code>{what_to_bring}</code> in day-before reminders to help parents prepare. 
                    Include <code>{maps_link}</code> so they can easily navigate to the venue!
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle test template send
     */
    private static function handle_test_send() {
        $template_id = intval($_POST['test_template_id']);
        $phone = sanitize_text_field($_POST['test_phone']);
        
        if (empty($phone)) {
            echo '<div class="notice notice-error"><p>Please enter a phone number.</p></div>';
            return;
        }
        
        global $wpdb;
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_templates WHERE id = %d",
            $template_id
        ));
        
        if (!$template) {
            echo '<div class="notice notice-error"><p>Template not found.</p></div>';
            return;
        }
        
        // Sample data for testing
        $sample_contact = array(
            'parent_first_name' => 'Test',
            'parent_last_name' => 'Parent',
            'parent_phone' => $phone,
            'parent_email' => 'test@example.com',
            'child_name' => 'Sample Camper',
            'child_age' => '8',
            'city' => 'Philadelphia',
            'state' => 'PA',
            'zip_code' => '19103'
        );
        
        $sample_event = array(
            'event_name' => 'Summer Soccer Camp',
            'product_name' => 'Summer Soccer Camp',
            'event_date' => date('Y-m-d', strtotime('+7 days')),
            'event_end_date' => date('Y-m-d', strtotime('+11 days')),
            'event_time' => '9:00 AM - 12:00 PM',
            'event_location' => 'PTP Training Center',
            'event_address' => '123 Soccer Lane, Philadelphia, PA 19103',
            'maps_link' => 'https://maps.google.com',
            'program_type' => 'half_day',
            'market' => 'PA',
            'market_slug' => 'PA',
            'age_range' => '6-10',
            'head_coach' => 'Coach Mike',
            'what_to_bring' => 'Soccer cleats, shin guards, water bottle, snack',
            'order_id' => '12345',
            'registration_status' => 'confirmed'
        );
        
        $message = ptp_comms_replace_variables($template->content, $sample_contact, $sample_event);
        
        // Send test SMS
        if (class_exists('PTP_Comms_Hub_SMS_Service') && function_exists('ptp_comms_is_twilio_configured') && ptp_comms_is_twilio_configured()) {
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $result = $sms_service->send_sms($phone, $message);
            
            if ($result && !empty($result['success'])) {
                echo '<div class="notice notice-success"><p>✅ Test message sent successfully to ' . esc_html($phone) . '</p></div>';
            } else {
                $error = isset($result['error']) ? $result['error'] : 'Unknown error';
                echo '<div class="notice notice-error"><p>❌ Failed to send test: ' . esc_html($error) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-warning"><p>⚠️ Twilio not configured. Here\'s what the message would look like:</p><pre style="background:#f5f5f5;padding:15px;border-radius:5px;">' . esc_html($message) . '</pre></div>';
        }
    }
}
