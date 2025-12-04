<?php
/**
 * Settings admin page - Complete with PTP Branding
 */
class PTP_Comms_Hub_Admin_Page_Settings {
    
    public static function render() {
        // Save settings if form submitted
        if (isset($_POST['ptp_comms_save_settings']) && check_admin_referer('ptp_comms_settings_nonce')) {
            self::save_settings();
            echo '<div class="ptp-comms-alert success" style="margin: 20px 0;">
                <span class="dashicons dashicons-yes-alt"></span>
                <div class="ptp-comms-alert-content">
                    <p style="margin: 0; font-weight: 600;">Settings saved successfully!</p>
                </div>
            </div>';
        }
        
        // Get current settings
        $settings = PTP_Comms_Hub_Settings::get_all();
        
        // Active tab
        if (isset($_POST['active_tab'])) {
            $active_tab = sanitize_text_field($_POST['active_tab']);
        } else {
            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'twilio';
        }
        
        ?>
        <div class="wrap ptp-comms-admin">
            <h1><?php _e('PTP Communications Hub Settings', 'ptp-comms-hub'); ?></h1>
            <p class="ptp-intro-text">
                <?php _e('Configure your integrations and automation settings for PTP Comms Hub.', 'ptp-comms-hub'); ?>
            </p>
            
            <nav class="ptp-comms-tabs nav-tab-wrapper" style="flex-wrap: wrap;">
                <a href="#twilio" class="nav-tab <?php echo $active_tab === 'twilio' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-smartphone"></span> Twilio (SMS & Voice)
                </a>
                <a href="#whatsapp" class="nav-tab <?php echo $active_tab === 'whatsapp' ? 'nav-tab-active' : ''; ?>" style="color: #25D366;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle;">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    WhatsApp
                </a>
                <a href="#voice-ivr" class="nav-tab <?php echo $active_tab === 'voice-ivr' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-phone"></span> Voice & IVR
                </a>
                <a href="#zoom" class="nav-tab <?php echo $active_tab === 'zoom' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-video-alt2"></span> Zoom Phone
                </a>
                <a href="#hubspot" class="nav-tab <?php echo $active_tab === 'hubspot' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-links"></span> HubSpot
                </a>
                <a href="#teams" class="nav-tab <?php echo $active_tab === 'teams' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-comments"></span> Microsoft Teams
                </a>
                <a href="#woocommerce" class="nav-tab <?php echo $active_tab === 'woocommerce' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-cart"></span> WooCommerce
                </a>
                <a href="#general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> General
                </a>
            </nav>
            
            <form method="post" action="" id="ptp-settings-form">
                <?php wp_nonce_field('ptp_comms_settings_nonce'); ?>
                <input type="hidden" name="active_tab" id="ptp_active_tab" value="<?php echo esc_attr($active_tab); ?>" />
                
                <div class="ptp-card" style="margin-top: 20px;">
                    <?php
                        // Render all tab panels
                        echo '<div id="twilio" class="ptp-comms-tab-content ' . ($active_tab === 'twilio' ? '' : 'ptp-hidden') . '">';
                        self::render_twilio_settings($settings);
                        echo '</div>';

                        echo '<div id="whatsapp" class="ptp-comms-tab-content ' . ($active_tab === 'whatsapp' ? '' : 'ptp-hidden') . '">';
                        self::render_whatsapp_settings($settings);
                        echo '</div>';

                        echo '<div id="voice-ivr" class="ptp-comms-tab-content ' . ($active_tab === 'voice-ivr' ? '' : 'ptp-hidden') . '">';
                        self::render_voice_ivr_settings($settings);
                        echo '</div>';

                        echo '<div id="zoom" class="ptp-comms-tab-content ' . ($active_tab === 'zoom' ? '' : 'ptp-hidden') . '">';
                        self::render_zoom_settings($settings);
                        echo '</div>';

                        echo '<div id="hubspot" class="ptp-comms-tab-content ' . ($active_tab === 'hubspot' ? '' : 'ptp-hidden') . '">';
                        self::render_hubspot_settings($settings);
                        echo '</div>';

                        echo '<div id="teams" class="ptp-comms-tab-content ' . ($active_tab === 'teams' ? '' : 'ptp-hidden') . '">';
                        self::render_teams_settings($settings);
                        echo '</div>';

                        echo '<div id="woocommerce" class="ptp-comms-tab-content ' . ($active_tab === 'woocommerce' ? '' : 'ptp-hidden') . '">';
                        self::render_woocommerce_settings($settings);
                        echo '</div>';

                        echo '<div id="general" class="ptp-comms-tab-content ' . ($active_tab === 'general' ? '' : 'ptp-hidden') . '">';
                        self::render_general_settings($settings);
                        echo '</div>';
                    ?>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--ptp-border);">
                        <button type="submit" name="ptp_comms_save_settings" class="button button-primary button-hero">
                            <span class="dashicons dashicons-saved" style="margin-top: 3px;"></span> 
                            <?php _e('Save All Settings', 'ptp-comms-hub'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <style>
        /* Settings page specific styles */
        .ptp-hidden { display: none !important; }
        
        .ptp-comms-tabs .nav-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            font-weight: 500;
        }
        
        .ptp-comms-tabs .nav-tab .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        
        .ptp-comms-form-group {
            margin-bottom: 25px;
        }
        
        .ptp-comms-form-group label {
            display: block;
            font-weight: 600;
            color: var(--ptp-ink);
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .ptp-comms-form-group input[type="text"],
        .ptp-comms-form-group input[type="password"],
        .ptp-comms-form-group input[type="tel"],
        .ptp-comms-form-group input[type="email"],
        .ptp-comms-form-group input[type="url"],
        .ptp-comms-form-group textarea,
        .ptp-comms-form-group select {
            width: 100%;
            max-width: 500px;
            padding: 10px 14px;
            border: 2px solid var(--ptp-border);
            border-radius: var(--ptp-radius);
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .ptp-comms-form-group input:focus,
        .ptp-comms-form-group textarea:focus,
        .ptp-comms-form-group select:focus {
            border-color: var(--ptp-yellow);
            box-shadow: 0 0 0 3px rgba(252, 185, 0, 0.1);
            outline: none;
        }
        
        .ptp-comms-form-help {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: var(--ptp-muted);
            font-style: italic;
        }
        
        .ptp-comms-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 20px;
            border-radius: var(--ptp-radius);
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .ptp-comms-alert.success {
            background: rgba(34, 197, 94, 0.1);
            border-left-color: var(--ptp-success);
            color: #166534;
        }
        
        .ptp-comms-alert.warning {
            background: rgba(245, 158, 11, 0.1);
            border-left-color: var(--ptp-warning);
            color: #92400e;
        }
        
        .ptp-comms-alert .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
            margin-top: 2px;
        }
        
        .ptp-comms-alert-content {
            flex: 1;
        }
        
        .ptp-info-box {
            background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%);
            border: 1px solid var(--ptp-yellow);
            border-left: 4px solid var(--ptp-yellow);
            padding: 20px;
            margin: 20px 0;
            border-radius: var(--ptp-radius);
        }
        
        .ptp-info-box h3 {
            margin-top: 0;
            color: var(--ptp-ink);
            font-size: 16px;
        }
        
        .ptp-info-box ol {
            margin-left: 20px;
            line-height: 1.8;
        }
        </style>
        <?php
    }
    
    private static function render_twilio_settings($settings) {
        $twilio_sid = isset($settings['twilio_account_sid']) ? $settings['twilio_account_sid'] : '';
        $twilio_token = isset($settings['twilio_auth_token']) ? $settings['twilio_auth_token'] : '';
        $twilio_phone = isset($settings['twilio_phone_number']) ? $settings['twilio_phone_number'] : '';
        $is_configured = ptp_comms_is_twilio_configured();
        
        ?>
        <h2><?php _e('Twilio Configuration', 'ptp-comms-hub'); ?></h2>
        
        <?php if ($is_configured): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes-alt"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('Twilio is connected.', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('SMS and voice messaging are enabled and ready to use.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="ptp-comms-alert warning">
                <span class="dashicons dashicons-warning"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('Twilio is not configured.', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('Complete the settings below to enable SMS and voice messaging.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="ptp-comms-form-group">
            <label for="twilio_account_sid"><?php _e('Account SID', 'ptp-comms-hub'); ?></label>
            <input type="text" id="twilio_account_sid" name="settings[twilio_account_sid]" value="<?php echo esc_attr($twilio_sid); ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
            <span class="ptp-comms-form-help">
                <?php _e('Find this in your', 'ptp-comms-hub'); ?> 
                <a href="https://console.twilio.com" target="_blank" rel="noopener"><?php _e('Twilio Console', 'ptp-comms-hub'); ?></a>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="twilio_auth_token"><?php _e('Auth Token', 'ptp-comms-hub'); ?></label>
            <input type="password" id="twilio_auth_token" name="settings[twilio_auth_token]" value="<?php echo esc_attr($twilio_token); ?>" placeholder="********************************">
            <span class="ptp-comms-form-help"><?php _e('Keep this secret! Find it in your Twilio Console.', 'ptp-comms-hub'); ?></span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="twilio_phone_number"><?php _e('Twilio Phone Number', 'ptp-comms-hub'); ?></label>
            <input type="tel" id="twilio_phone_number" name="settings[twilio_phone_number]" value="<?php echo esc_attr($twilio_phone); ?>" placeholder="+12025551234">
            <span class="ptp-comms-form-help"><?php _e('Your Twilio phone number in E.164 format (e.g., +12025551234)', 'ptp-comms-hub'); ?></span>
        </div>
        
        <!-- Test Connection Button -->
        <?php if ($twilio_sid && $twilio_token): ?>
        <div class="ptp-comms-form-group" style="padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e2e4e7;">
            <h4 style="margin: 0 0 15px 0;"><?php _e('🔌 Connection Test', 'ptp-comms-hub'); ?></h4>
            <p style="margin: 0 0 15px; color: #666;"><?php _e('Verify your Twilio credentials are working correctly.', 'ptp-comms-hub'); ?></p>
            <button type="button" id="test-twilio-btn" class="button button-secondary" onclick="ptpTestTwilioConnection()">
                <span class="dashicons dashicons-update-alt" style="margin-top: 3px;"></span>
                <?php _e('Test Twilio Connection', 'ptp-comms-hub'); ?>
            </button>
            <span id="twilio-test-result" style="margin-left: 15px;"></span>
        </div>
        
        <script>
        function ptpTestTwilioConnection() {
            var btn = document.getElementById('test-twilio-btn');
            var result = document.getElementById('twilio-test-result');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-update-alt" style="margin-top: 3px; animation: rotation 1s linear infinite;"></span> Testing...';
            result.innerHTML = '';
            
            // Get current form values
            var sid = document.getElementById('twilio_account_sid').value;
            var token = document.getElementById('twilio_auth_token').value;
            
            jQuery.post(ajaxurl, {
                action: 'ptp_test_twilio_connection',
                nonce: '<?php echo wp_create_nonce('ptp_test_twilio'); ?>',
                sid: sid,
                token: token
            }, function(response) {
                btn.disabled = false;
                btn.innerHTML = '<span class="dashicons dashicons-update-alt" style="margin-top: 3px;"></span> Test Twilio Connection';
                
                if (response.success) {
                    result.innerHTML = '<span style="color: #46b450;">✅ ' + response.data.message + '</span>';
                } else {
                    result.innerHTML = '<span style="color: #dc3232;">❌ ' + (response.data || 'Connection failed') + '</span>';
                }
            }).fail(function() {
                btn.disabled = false;
                btn.innerHTML = '<span class="dashicons dashicons-update-alt" style="margin-top: 3px;"></span> Test Twilio Connection';
                result.innerHTML = '<span style="color: #dc3232;">❌ Request failed</span>';
            });
        }
        </script>
        <style>
        @keyframes rotation {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        </style>
        <?php endif; ?>
        
        <div class="ptp-info-box" id="webhook-setup">
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php _e('Two-Way SMS Webhook Configuration (Required)', 'ptp-comms-hub'); ?>
            </h3>
            
            <p style="font-weight: 600; margin-bottom: 10px;">
                <strong><?php _e('Important:', 'ptp-comms-hub'); ?></strong> 
                <?php _e('To enable parent-initiated messages (not just campaign replies), configure your Twilio webhook:', 'ptp-comms-hub'); ?>
            </p>
            
            <div style="background: var(--ptp-bg); padding: 15px; border-radius: var(--ptp-radius); margin: 15px 0; border: 1px solid var(--ptp-border);">
                <p style="margin: 0 0 8px; font-weight: 600; font-size: 13px; color: var(--ptp-muted); text-transform: uppercase;">
                    <?php _e('SMS Webhook URL (copy this):', 'ptp-comms-hub'); ?>
                </p>
                <code style="display: block; padding: 12px; background: white; border: 2px solid var(--ptp-border); border-radius: var(--ptp-radius); font-size: 13px; word-break: break-all;"><?php echo home_url('/ptp-comms/sms-webhook'); ?></code>
                
                <p style="margin: 15px 0 8px; font-weight: 600; font-size: 13px; color: var(--ptp-muted); text-transform: uppercase;">
                    <?php _e('Voice Webhook URL (optional):', 'ptp-comms-hub'); ?>
                </p>
                <code style="display: block; padding: 12px; background: white; border: 2px solid var(--ptp-border); border-radius: var(--ptp-radius); font-size: 13px; word-break: break-all;"><?php echo home_url('/ptp-comms/voice-webhook'); ?></code>
            </div>
            
            <div style="background: rgba(0, 115, 170, 0.08); padding: 15px; border-radius: var(--ptp-radius); border-left: 3px solid #0073aa;">
                <h4 style="margin: 0 0 10px; color: #0073aa;">
                    <?php _e('Setup Steps:', 'ptp-comms-hub'); ?>
                </h4>
                <ol style="margin: 5px 0 0 20px; padding: 0; line-height: 1.8;">
                    <li><?php _e('Log in to', 'ptp-comms-hub'); ?> <a href="https://console.twilio.com" target="_blank"><?php _e('Twilio Console', 'ptp-comms-hub'); ?></a></li>
                    <li><?php _e('Go to Phone Numbers → Manage → Active numbers', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Click your PTP phone number', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Scroll to "Messaging Configuration"', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Set "A Message Comes In" webhook to your SMS URL (above)', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Set HTTP method to POST', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Save configuration', 'ptp-comms-hub'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }

    private static function render_whatsapp_settings($settings) {
        $whatsapp_enabled = isset($settings['whatsapp_enabled']) ? $settings['whatsapp_enabled'] : 'no';
        $whatsapp_phone = isset($settings['whatsapp_phone_number']) ? $settings['whatsapp_phone_number'] : '';
        $sandbox_mode = isset($settings['whatsapp_sandbox_mode']) ? $settings['whatsapp_sandbox_mode'] : 'yes';
        $business_name = isset($settings['whatsapp_business_name']) ? $settings['whatsapp_business_name'] : 'PTP Soccer Camps';
        $is_twilio_configured = ptp_comms_is_twilio_configured();
        $is_configured = function_exists('ptp_comms_is_whatsapp_configured') && ptp_comms_is_whatsapp_configured();

        ?>
        <h2 style="display: flex; align-items: center; gap: 10px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle;">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            <?php _e('WhatsApp Business Configuration', 'ptp-comms-hub'); ?>
        </h2>

        <?php if (!$is_twilio_configured): ?>
            <div class="ptp-comms-alert warning">
                <span class="dashicons dashicons-warning"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('Twilio must be configured first', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;">
                        <?php _e('WhatsApp Business uses Twilio as its provider. Please configure Twilio credentials in the Twilio tab first.', 'ptp-comms-hub'); ?>
                    </p>
                </div>
            </div>
        <?php elseif ($is_configured): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes-alt"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('WhatsApp is connected!', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('You can now send and receive WhatsApp messages through the Shared Inbox.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="ptp-comms-form-group">
            <label for="whatsapp_enabled">
                <input type="checkbox" id="whatsapp_enabled" name="settings[whatsapp_enabled]" value="yes" <?php checked($whatsapp_enabled, 'yes'); ?>>
                <?php _e('Enable WhatsApp Integration', 'ptp-comms-hub'); ?>
            </label>
            <span class="ptp-comms-form-help"><?php _e('Turn on WhatsApp messaging in the Shared Inbox.', 'ptp-comms-hub'); ?></span>
        </div>

        <div class="ptp-comms-form-group">
            <label for="whatsapp_phone_number"><?php _e('WhatsApp Business Phone Number', 'ptp-comms-hub'); ?></label>
            <input type="tel" id="whatsapp_phone_number" name="settings[whatsapp_phone_number]" value="<?php echo esc_attr($whatsapp_phone); ?>" placeholder="+12025551234">
            <span class="ptp-comms-form-help">
                <?php _e('Your WhatsApp-enabled Twilio phone number in E.164 format.', 'ptp-comms-hub'); ?>
                <a href="https://www.twilio.com/docs/whatsapp/tutorial/connect-number-business-profile" target="_blank" rel="noopener">Learn how to connect a number</a>
            </span>
        </div>

        <div class="ptp-comms-form-group">
            <label for="whatsapp_business_name"><?php _e('Business Display Name', 'ptp-comms-hub'); ?></label>
            <input type="text" id="whatsapp_business_name" name="settings[whatsapp_business_name]" value="<?php echo esc_attr($business_name); ?>" placeholder="PTP Soccer Camps">
            <span class="ptp-comms-form-help"><?php _e('Your business name as it appears in WhatsApp.', 'ptp-comms-hub'); ?></span>
        </div>

        <div class="ptp-comms-form-group">
            <label for="whatsapp_sandbox_mode">
                <input type="checkbox" id="whatsapp_sandbox_mode" name="settings[whatsapp_sandbox_mode]" value="yes" <?php checked($sandbox_mode, 'yes'); ?>>
                <?php _e('Use Twilio Sandbox (for testing)', 'ptp-comms-hub'); ?>
            </label>
            <span class="ptp-comms-form-help"><?php _e('Enable this for testing with the Twilio WhatsApp Sandbox before going live.', 'ptp-comms-hub'); ?></span>
        </div>

        <!-- Webhook Configuration -->
        <div class="ptp-info-box" id="whatsapp-webhook-setup" style="margin-top: 25px;">
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php _e('WhatsApp Webhook Configuration (Required)', 'ptp-comms-hub'); ?>
            </h3>

            <p style="font-weight: 600; margin-bottom: 10px;">
                <?php _e('Configure Twilio to forward WhatsApp messages to your site:', 'ptp-comms-hub'); ?>
            </p>

            <div style="background: var(--ptp-bg); padding: 15px; border-radius: var(--ptp-radius); margin: 15px 0; border: 1px solid var(--ptp-border);">
                <p style="margin: 0 0 8px; font-weight: 600; font-size: 13px; color: var(--ptp-muted); text-transform: uppercase;">
                    <?php _e('WhatsApp Webhook URL:', 'ptp-comms-hub'); ?>
                </p>
                <code style="display: block; padding: 12px; background: white; border: 2px solid var(--ptp-border); border-radius: var(--ptp-radius); font-size: 13px; word-break: break-all;"><?php echo home_url('/wp-json/ptp-comms/v1/whatsapp-incoming'); ?></code>

                <p style="margin: 15px 0 8px; font-weight: 600; font-size: 13px; color: var(--ptp-muted); text-transform: uppercase;">
                    <?php _e('Status Callback URL:', 'ptp-comms-hub'); ?>
                </p>
                <code style="display: block; padding: 12px; background: white; border: 2px solid var(--ptp-border); border-radius: var(--ptp-radius); font-size: 13px; word-break: break-all;"><?php echo home_url('/wp-json/ptp-comms/v1/whatsapp-status'); ?></code>
            </div>

            <div style="background: rgba(37, 211, 102, 0.1); padding: 15px; border-radius: var(--ptp-radius); border-left: 3px solid #25D366;">
                <h4 style="margin: 0 0 10px; color: #128C7E;">
                    <?php _e('Setup Steps:', 'ptp-comms-hub'); ?>
                </h4>
                <ol style="margin: 5px 0 0 20px; padding: 0; line-height: 1.8;">
                    <li><?php _e('Log in to', 'ptp-comms-hub'); ?> <a href="https://console.twilio.com/us1/develop/sms/senders/whatsapp-senders" target="_blank"><?php _e('Twilio WhatsApp Senders', 'ptp-comms-hub'); ?></a></li>
                    <li><?php _e('Click on your WhatsApp-enabled number (or set up a new one)', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('In the "Sandbox Configuration" or "WhatsApp Configuration" section:', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Set "When a message comes in" to the WhatsApp Webhook URL above', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Set "Status Callback URL" to the Status Callback URL above', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Set HTTP method to POST', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Save configuration', 'ptp-comms-hub'); ?></li>
                </ol>
            </div>
        </div>

        <!-- WhatsApp Notification Settings -->
        <div class="ptp-info-box" style="margin-top: 25px; background: #f0fdf4; border: 1px solid #86efac;">
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-bell"></span>
                <?php _e('WhatsApp Notifications for Team', 'ptp-comms-hub'); ?>
            </h3>
            <p style="margin-bottom: 15px;">
                <?php _e('Team members can receive notifications via WhatsApp when new messages arrive. Each user can configure this in their profile settings.', 'ptp-comms-hub'); ?>
            </p>
            <p style="margin: 0; font-size: 13px; color: #166534;">
                <strong><?php _e('To enable:', 'ptp-comms-hub'); ?></strong>
                <?php _e('Go to Users → Your Profile → scroll to "WhatsApp Notifications" section.', 'ptp-comms-hub'); ?>
            </p>
        </div>
        <?php
    }

    private static function render_voice_ivr_settings($settings) {
        $forwarding_numbers = isset($settings['ivr_forwarding_numbers']) ? $settings['ivr_forwarding_numbers'] : '';
        $greeting = isset($settings['ivr_greeting']) ? $settings['ivr_greeting'] : 'Thank you for calling PTP Soccer Camps.';
        $menu_prompt = isset($settings['ivr_menu_prompt']) ? $settings['ivr_menu_prompt'] : 'Press 1 to speak with a camp coordinator. Press 2 for registration information. Press 3 for camp locations and dates. Press 0 to repeat this menu.';
        $forward_message = isset($settings['ivr_forward_message']) ? $settings['ivr_forward_message'] : 'Please hold while we connect you to a camp coordinator.';
        $voicemail_message = isset($settings['ivr_voicemail_message']) ? $settings['ivr_voicemail_message'] : 'All of our coordinators are currently busy. Please leave a message after the beep, and we will return your call as soon as possible.';
        $dial_timeout = isset($settings['ivr_dial_timeout']) ? $settings['ivr_dial_timeout'] : '20';
        $auto_forward = isset($settings['ivr_auto_forward_business_hours']) ? $settings['ivr_auto_forward_business_hours'] : 'yes';
        $ring_staff_first = isset($settings['ivr_ring_staff_first']) ? $settings['ivr_ring_staff_first'] : 'yes';
        $no_answer_action = isset($settings['ivr_no_answer_action']) ? $settings['ivr_no_answer_action'] : 'voicemail';
        $missed_call_sms = isset($settings['missed_call_notify_sms']) ? $settings['missed_call_notify_sms'] : '';
        $ring_message = isset($settings['ivr_ring_message']) ? $settings['ivr_ring_message'] : 'Please hold while we connect your call.';
        
        // Convert array to comma-separated string if needed
        if (is_array($forwarding_numbers)) {
            $forwarding_numbers = implode(', ', $forwarding_numbers);
        }
        
        $is_twilio_configured = ptp_comms_is_twilio_configured();
        
        ?>
        <h2><?php _e('Voice & IVR Configuration', 'ptp-comms-hub'); ?></h2>
        
        <?php if (!$is_twilio_configured): ?>
            <div class="ptp-comms-alert warning">
                <span class="dashicons dashicons-warning"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('Twilio must be configured first', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;">
                        <?php _e('Go to the Twilio tab and enter your credentials before configuring voice settings.', 'ptp-comms-hub'); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- CALL ROUTING - RING STAFF FIRST -->
        <div class="ptp-card" style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #fff; margin-bottom: 24px;">
            <h3 style="margin-top: 0; color: #FCD116; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-phone"></span>
                <?php _e('📞 Call Routing - RING PERSON FIRST', 'ptp-comms-hub'); ?>
            </h3>
            <p style="color: #aaa; margin-bottom: 16px;">When enabled, incoming calls ring your staff phones FIRST. If no one answers, callers go to voicemail or IVR menu.</p>
            
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 12px; background: rgba(252,209,22,0.1); border-radius: 8px; border: 2px solid <?php echo $ring_staff_first === 'yes' ? '#FCD116' : 'transparent'; ?>;">
                    <input type="checkbox" name="settings[ivr_ring_staff_first]" value="yes" <?php checked($ring_staff_first, 'yes'); ?> style="width: 20px; height: 20px;">
                    <div>
                        <strong style="color: #FCD116;">✅ Ring Staff First (Recommended)</strong>
                        <div style="color: #aaa; font-size: 13px; margin-top: 4px;">Calls go directly to staff phones - no menu. If no answer, then IVR/voicemail.</div>
                    </div>
                </label>
                
                <div style="padding-left: 32px;">
                    <label style="display: block; margin-bottom: 8px; color: #fff; font-weight: 500;">If no one answers, then:</label>
                    <select name="settings[ivr_no_answer_action]" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #444; background: #333; color: #fff;">
                        <option value="voicemail" <?php selected($no_answer_action, 'voicemail'); ?>>📱 Go to Voicemail</option>
                        <option value="ivr" <?php selected($no_answer_action, 'ivr'); ?>>🔢 Show IVR Menu</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- MISSED CALL NOTIFICATIONS -->
        <div class="ptp-card" style="margin-bottom: 24px; background: #fff5f5; border: 1px solid #f5c2c2;">
            <h3 style="margin-top: 0; color: #c0392b; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-bell"></span>
                <?php _e('📵 Missed Call Notifications', 'ptp-comms-hub'); ?>
            </h3>
            <p style="color: #666; margin-bottom: 16px;">Get notified via SMS when a call is missed (in addition to Teams notifications).</p>
            
            <div class="ptp-comms-form-group" style="margin-bottom: 0;">
                <label for="missed_call_notify_sms"><?php _e('Send SMS Alert To:', 'ptp-comms-hub'); ?></label>
                <input type="text" 
                       id="missed_call_notify_sms" 
                       name="settings[missed_call_notify_sms]" 
                       value="<?php echo esc_attr($missed_call_sms); ?>" 
                       placeholder="+12154755801, +12155551234"
                       style="font-family: monospace;">
                <span class="ptp-comms-form-help">
                    <?php _e('Phone numbers to receive SMS when a call is missed. Separate multiple numbers with commas.', 'ptp-comms-hub'); ?>
                </span>
            </div>
        </div>
        
        <div class="ptp-info-box" style="background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%); border-left: 4px solid var(--ptp-primary);">
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px; color: var(--ptp-black);">
                <span class="dashicons dashicons-phone" style="color: var(--ptp-primary);"></span>
                <?php _e('How Call Routing Works', 'ptp-comms-hub'); ?>
            </h3>
            <ol style="margin: 10px 0 0 20px; padding: 0; line-height: 1.8;">
                <li><?php _e('Parent calls your Twilio phone number', 'ptp-comms-hub'); ?></li>
                <li><strong style="color: #27ae60;"><?php _e('If "Ring Staff First" is ON: ALL staff phones ring immediately', 'ptp-comms-hub'); ?></strong></li>
                <li><?php _e('First person to answer gets the call', 'ptp-comms-hub'); ?></li>
                <li><?php _e('If no answer after timeout → Voicemail OR IVR menu (your choice)', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Missed calls trigger SMS alerts + Teams notification', 'ptp-comms-hub'); ?></li>
            </ol>
        </div>
        
        <h3 style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--ptp-gray-100);">
            <span class="dashicons dashicons-groups" style="color: var(--ptp-primary);"></span>
            <?php _e('Staff Phone Numbers', 'ptp-comms-hub'); ?>
        </h3>
        
        <div class="ptp-comms-form-group">
            <label for="ivr_forwarding_numbers">
                <?php _e('Numbers to Ring', 'ptp-comms-hub'); ?>
                <span style="color: var(--ptp-danger);">*</span>
            </label>
            <input type="text" 
                   id="ivr_forwarding_numbers" 
                   name="settings[ivr_forwarding_numbers]" 
                   value="<?php echo esc_attr($forwarding_numbers); ?>" 
                   placeholder="+12154755801, +12155551234"
                   style="font-family: monospace;">
            <span class="ptp-comms-form-help">
                <?php _e('Enter phone numbers separated by commas. Must include country code (e.g., +1 for US). All numbers ring simultaneously.', 'ptp-comms-hub'); ?>
            </span>
            <div style="margin-top: 8px; padding: 10px; background: var(--ptp-gray-50); border-radius: 4px; font-size: 13px;">
                <strong><?php _e('Format Examples:', 'ptp-comms-hub'); ?></strong><br>
                ✅ <?php _e('Correct:', 'ptp-comms-hub'); ?> <code>+12154755801</code><br>
                ❌ <?php _e('Wrong:', 'ptp-comms-hub'); ?> <code>215-475-5801</code> or <code>2154755801</code>
            </div>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="ivr_dial_timeout"><?php _e('Ring Time Before Fallback', 'ptp-comms-hub'); ?></label>
            <input type="number" 
                   id="ivr_dial_timeout" 
                   name="settings[ivr_dial_timeout]" 
                   value="<?php echo esc_attr($dial_timeout); ?>" 
                   min="10" 
                   max="60"
                   style="width: 100px;">
            <span class="ptp-comms-form-help">
                <?php _e('How long (in seconds) phones will ring before going to voicemail/IVR. Default: 20 seconds.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="ivr_ring_message"><?php _e('Ring Message', 'ptp-comms-hub'); ?></label>
            <input type="text" 
                   id="ivr_ring_message" 
                   name="settings[ivr_ring_message]" 
                   value="<?php echo esc_attr($ring_message); ?>" 
                   placeholder="Please hold while we connect your call.">
            <span class="ptp-comms-form-help">
                <?php _e('Short message callers hear while phones are ringing.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[ivr_auto_forward_business_hours]" value="yes" <?php checked($auto_forward, 'yes'); ?>>
                <span><?php _e('Only ring staff during business hours', 'ptp-comms-hub'); ?></span>
            </label>
            <span class="ptp-comms-form-help" style="margin-left: 32px;">
                <?php _e('After hours, go directly to voicemail/IVR without ringing staff phones.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <h3 style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--ptp-gray-100);">
            <span class="dashicons dashicons-admin-comments" style="color: var(--ptp-primary);"></span>
            <?php _e('IVR Messages', 'ptp-comms-hub'); ?>
        </h3>
        
        <div class="ptp-comms-form-group">
            <label for="ivr_greeting"><?php _e('Greeting Message', 'ptp-comms-hub'); ?></label>
            <textarea id="ivr_greeting" 
                      name="settings[ivr_greeting]" 
                      rows="2"
                      placeholder="Thank you for calling PTP Soccer Camps."><?php echo esc_textarea($greeting); ?></textarea>
            <span class="ptp-comms-form-help">
                <?php _e('First message callers hear when they call your number (used for IVR menu).', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="ivr_menu_prompt"><?php _e('Menu Options', 'ptp-comms-hub'); ?></label>
            <textarea id="ivr_menu_prompt" 
                      name="settings[ivr_menu_prompt]" 
                      rows="4"><?php echo esc_textarea($menu_prompt); ?></textarea>
            <span class="ptp-comms-form-help">
                <?php _e('Menu options that callers hear. Include "Press 1 for..., Press 2 for...", etc.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="ivr_forward_message"><?php _e('Connecting Message', 'ptp-comms-hub'); ?></label>
            <textarea id="ivr_forward_message" 
                      name="settings[ivr_forward_message]" 
                      rows="2"><?php echo esc_textarea($forward_message); ?></textarea>
            <span class="ptp-comms-form-help">
                <?php _e('Message played while connecting caller to staff phones (for IVR press-1 option).', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="ivr_voicemail_message"><?php _e('Voicemail Greeting', 'ptp-comms-hub'); ?></label>
            <textarea id="ivr_voicemail_message" 
                      name="settings[ivr_voicemail_message]" 
                      rows="3"><?php echo esc_textarea($voicemail_message); ?></textarea>
            <span class="ptp-comms-form-help">
                <?php _e('Message played when no one answers. Caller will hear a beep after this message.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-info-box" style="margin-top: 20px;">
            <h3 style="margin-top: 0;">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Twilio Webhook Configuration', 'ptp-comms-hub'); ?>
            </h3>
            <p><?php _e('To enable IVR call handling, configure your Twilio number:', 'ptp-comms-hub'); ?></p>
            
            <div style="background: var(--ptp-gray-50); padding: 15px; border-radius: 4px; margin: 15px 0;">
                <p style="margin: 0 0 8px; font-weight: 600; font-size: 13px; color: var(--ptp-gray-700); text-transform: uppercase;">
                    <?php _e('Voice Webhook URL:', 'ptp-comms-hub'); ?>
                </p>
                <code style="display: block; padding: 12px; background: white; border: 2px solid var(--ptp-border); border-radius: 4px; font-size: 13px; word-break: break-all;">
                    <?php echo get_rest_url(null, 'ptp-comms/v1/ivr-menu'); ?>
                </code>
            </div>
            
            <div style="background: rgba(0, 115, 170, 0.08); padding: 15px; border-radius: 4px; border-left: 3px solid #0073aa;">
                <h4 style="margin: 0 0 10px; color: #0073aa;"><?php _e('Setup Steps:', 'ptp-comms-hub'); ?></h4>
                <ol style="margin: 5px 0 0 20px; padding: 0; line-height: 1.8;">
                    <li><?php _e('Log in to', 'ptp-comms-hub'); ?> <a href="https://console.twilio.com" target="_blank"><?php _e('Twilio Console', 'ptp-comms-hub'); ?></a></li>
                    <li><?php _e('Go to Phone Numbers → Manage → Active Numbers', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Click your phone number', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Under "Voice & Fax" section:', 'ptp-comms-hub'); ?></li>
                    <li style="padding-left: 20px;"><?php _e('Set "A CALL COMES IN" to: Webhook', 'ptp-comms-hub'); ?></li>
                    <li style="padding-left: 20px;"><?php _e('Paste the URL above', 'ptp-comms-hub'); ?></li>
                    <li style="padding-left: 20px;"><?php _e('Set HTTP Method to: POST', 'ptp-comms-hub'); ?></li>
                    <li><?php _e('Click Save', 'ptp-comms-hub'); ?></li>
                </ol>
            </div>
        </div>
        
        <div class="ptp-info-box" style="margin-top: 20px; background: linear-gradient(135deg, #e6f7ff 0%, #f0fbff 100%); border-left: 4px solid #0073aa;">
            <h3 style="margin-top: 0; color: #0073aa;">
                <span class="dashicons dashicons-lightbulb"></span>
                <?php _e('Testing Your IVR System', 'ptp-comms-hub'); ?>
            </h3>
            <ol style="margin: 10px 0 0 20px; padding: 0; line-height: 1.8;">
                <li><?php _e('Save these settings', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Call your Twilio number from your personal phone', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Listen to the greeting and menu', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Press 1 - Your staff phones should ring', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Don\'t answer - After timeout, should go to voicemail', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Leave a test voicemail', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Check Microsoft Teams for voicemail notification', 'ptp-comms-hub'); ?></li>
            </ol>
        </div>
        <?php
    }
    
    /**
     * Render Zoom Phone settings
     */
    private static function render_zoom_settings($settings) {
        $zoom_account_id = isset($settings['zoom_account_id']) ? $settings['zoom_account_id'] : '';
        $zoom_client_id = isset($settings['zoom_client_id']) ? $settings['zoom_client_id'] : '';
        $zoom_client_secret = isset($settings['zoom_client_secret']) ? $settings['zoom_client_secret'] : '';
        $zoom_webhook_secret = isset($settings['zoom_webhook_secret']) ? $settings['zoom_webhook_secret'] : '';
        $zoom_default_caller = isset($settings['zoom_default_caller_id']) ? $settings['zoom_default_caller_id'] : '';
        $zoom_sms_number = isset($settings['zoom_sms_number']) ? $settings['zoom_sms_number'] : '';
        
        $zoom_configured = !empty($zoom_account_id) && !empty($zoom_client_id) && !empty($zoom_client_secret);
        ?>
        
        <h2 style="display: flex; align-items: center; gap: 10px;">
            <span style="background: #2d8cff; color: white; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <span class="dashicons dashicons-video-alt2"></span>
            </span>
            <?php _e('Zoom Phone Integration', 'ptp-comms-hub'); ?>
        </h2>
        
        <div class="ptp-card" style="background: linear-gradient(135deg, #e6f4ff 0%, #f0f8ff 100%); border: 1px solid #91caff; margin-bottom: 24px;">
            <h3 style="margin-top: 0; color: #0958d9;">
                <span class="dashicons dashicons-info"></span>
                <?php _e('About Zoom Phone Integration', 'ptp-comms-hub'); ?>
            </h3>
            <p style="color: #333; line-height: 1.8;">
                Connect Zoom Phone to make and receive calls through Zoom Workplace. This integration provides:
            </p>
            <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                <li><strong>Click-to-call</strong> - Call contacts directly from Zoom</li>
                <li><strong>Inbound call logging</strong> - Track all incoming calls</li>
                <li><strong>Call recordings</strong> - Access Zoom call recordings</li>
                <li><strong>Voicemail integration</strong> - Get notified of Zoom voicemails</li>
                <li><strong>SMS via Zoom</strong> - Send/receive texts through Zoom Phone</li>
            </ul>
        </div>
        
        <?php if ($zoom_configured): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes-alt"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('Zoom Phone Connected', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('Your Zoom account is configured and ready to use.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="ptp-comms-alert warning">
                <span class="dashicons dashicons-warning"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('Zoom Phone Not Configured', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('Enter your Zoom credentials below to enable phone features.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <h3 style="margin-top: 30px;">
            <span class="dashicons dashicons-admin-network" style="color: #2d8cff;"></span>
            <?php _e('Server-to-Server OAuth Credentials', 'ptp-comms-hub'); ?>
        </h3>
        
        <div class="ptp-info-box" style="margin-bottom: 20px; font-size: 13px;">
            <strong>How to get these credentials:</strong>
            <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
                <li>Go to <a href="https://marketplace.zoom.us" target="_blank">Zoom App Marketplace</a></li>
                <li>Click "Develop" → "Build App"</li>
                <li>Select "Server-to-Server OAuth"</li>
                <li>Fill in app details and create</li>
                <li>Copy the Account ID, Client ID, and Client Secret</li>
                <li>Under "Scopes", add: <code>phone:read</code>, <code>phone:write</code>, <code>phone_sms:read</code>, <code>phone_sms:write</code></li>
            </ol>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="zoom_account_id"><?php _e('Account ID', 'ptp-comms-hub'); ?></label>
            <input type="text" 
                   id="zoom_account_id" 
                   name="settings[zoom_account_id]" 
                   value="<?php echo esc_attr($zoom_account_id); ?>"
                   style="font-family: monospace;">
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="zoom_client_id"><?php _e('Client ID', 'ptp-comms-hub'); ?></label>
            <input type="text" 
                   id="zoom_client_id" 
                   name="settings[zoom_client_id]" 
                   value="<?php echo esc_attr($zoom_client_id); ?>"
                   style="font-family: monospace;">
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="zoom_client_secret"><?php _e('Client Secret', 'ptp-comms-hub'); ?></label>
            <input type="password" 
                   id="zoom_client_secret" 
                   name="settings[zoom_client_secret]" 
                   value="<?php echo esc_attr($zoom_client_secret); ?>"
                   style="font-family: monospace;">
        </div>
        
        <h3 style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--ptp-gray-100);">
            <span class="dashicons dashicons-admin-settings" style="color: #2d8cff;"></span>
            <?php _e('Webhook Configuration', 'ptp-comms-hub'); ?>
        </h3>
        
        <div class="ptp-comms-form-group">
            <label for="zoom_webhook_secret"><?php _e('Webhook Secret Token', 'ptp-comms-hub'); ?></label>
            <input type="password" 
                   id="zoom_webhook_secret" 
                   name="settings[zoom_webhook_secret]" 
                   value="<?php echo esc_attr($zoom_webhook_secret); ?>"
                   style="font-family: monospace;">
            <span class="ptp-comms-form-help">
                <?php _e('Found in your Zoom app\'s "Feature" → "Event Subscriptions" settings.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div style="background: var(--ptp-gray-50); padding: 15px; border-radius: 4px; margin: 15px 0;">
            <p style="margin: 0 0 8px; font-weight: 600; font-size: 13px; color: var(--ptp-gray-700); text-transform: uppercase;">
                <?php _e('Zoom Webhook URL:', 'ptp-comms-hub'); ?>
            </p>
            <code style="display: block; padding: 12px; background: #1a1a1a; color: #2d8cff; border-radius: 4px; font-size: 13px; word-break: break-all;">
                <?php echo get_rest_url(null, 'ptp-comms/v1/zoom-webhook'); ?>
            </code>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                Add this URL to your Zoom app's Event Subscriptions. Enable events: <code>phone.call_ringing</code>, <code>phone.call_ended</code>, <code>phone.voicemail_received</code>, <code>phone.sms_received</code>
            </p>
        </div>
        
        <h3 style="margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--ptp-gray-100);">
            <span class="dashicons dashicons-phone" style="color: #2d8cff;"></span>
            <?php _e('Default Caller Settings', 'ptp-comms-hub'); ?>
        </h3>
        
        <div class="ptp-comms-form-group">
            <label for="zoom_default_caller_id"><?php _e('Default Caller User ID', 'ptp-comms-hub'); ?></label>
            <input type="text" 
                   id="zoom_default_caller_id" 
                   name="settings[zoom_default_caller_id]" 
                   value="<?php echo esc_attr($zoom_default_caller); ?>"
                   placeholder="user@yourdomain.com"
                   style="font-family: monospace;">
            <span class="ptp-comms-form-help">
                <?php _e('The Zoom user email whose phone number will be used for outbound calls.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="zoom_sms_number"><?php _e('SMS Phone Number', 'ptp-comms-hub'); ?></label>
            <input type="text" 
                   id="zoom_sms_number" 
                   name="settings[zoom_sms_number]" 
                   value="<?php echo esc_attr($zoom_sms_number); ?>"
                   placeholder="+12155551234"
                   style="font-family: monospace;">
            <span class="ptp-comms-form-help">
                <?php _e('Your Zoom Phone number for sending SMS messages.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        <?php
    }
    
    private static function render_hubspot_settings($settings) {
        $hubspot_key = isset($settings['hubspot_api_key']) ? $settings['hubspot_api_key'] : '';
        $auto_sync = isset($settings['hubspot_auto_sync']) ? $settings['hubspot_auto_sync'] : 'yes';
        $is_configured = ptp_comms_is_hubspot_configured();
        
        ?>
        <h2><?php _e('HubSpot Integration', 'ptp-comms-hub'); ?></h2>
        
        <?php if ($is_configured): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes-alt"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('HubSpot is connected.', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('Contact syncing is enabled and ready to use.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="ptp-comms-alert warning">
                <span class="dashicons dashicons-warning"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('HubSpot is not configured.', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('Complete the settings below to enable contact syncing.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="ptp-comms-form-group">
            <label for="hubspot_api_key"><?php _e('HubSpot API Key', 'ptp-comms-hub'); ?></label>
            <input type="password" id="hubspot_api_key" name="settings[hubspot_api_key]" value="<?php echo esc_attr($hubspot_key); ?>" placeholder="********************************">
            <span class="ptp-comms-form-help">
                <?php _e('Find this in', 'ptp-comms-hub'); ?> 
                <a href="https://app.hubspot.com/settings" target="_blank"><?php _e('HubSpot Settings', 'ptp-comms-hub'); ?></a> → 
                <?php _e('Integrations → API Key', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[hubspot_auto_sync]" value="yes" <?php checked($auto_sync, 'yes'); ?>>
                <span><?php _e('Automatically sync new contacts to HubSpot', 'ptp-comms-hub'); ?></span>
            </label>
            <span class="ptp-comms-form-help" style="margin-left: 32px;">
                <?php _e('When enabled, new contacts from WooCommerce orders will automatically sync to HubSpot.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-info-box">
            <h3><?php _e('HubSpot Sync Details', 'ptp-comms-hub'); ?></h3>
            <p><?php _e('When a contact is synced to HubSpot:', 'ptp-comms-hub'); ?></p>
            <ol>
                <li><?php _e('Contact properties (name, email, phone) are created/updated', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Custom PTP properties are populated (camp info, registration dates)', 'ptp-comms-hub'); ?></li>
                <li><?php _e('SMS opt-in status is tracked', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Engagement activities are logged as timeline events', 'ptp-comms-hub'); ?></li>
            </ol>
        </div>
        <?php
    }
    
    private static function render_teams_settings($settings) {
        $teams_webhook = isset($settings['teams_webhook_url']) ? $settings['teams_webhook_url'] : '';
        $bot_app_id = isset($settings['teams_bot_app_id']) ? $settings['teams_bot_app_id'] : '';
        $bot_password = isset($settings['teams_bot_app_password']) ? $settings['teams_bot_app_password'] : '';
        $tenant_id = isset($settings['teams_tenant_id']) ? $settings['teams_tenant_id'] : '';
        $service_url = isset($settings['teams_service_url']) ? $settings['teams_service_url'] : 'https://smba.trafficmanager.net/amer/';
        $notify_orders = isset($settings['teams_notify_orders']) ? $settings['teams_notify_orders'] : 'yes';
        $notify_messages = isset($settings['teams_notify_messages']) ? $settings['teams_notify_messages'] : 'yes';
        $notify_campaigns = isset($settings['teams_notify_campaigns']) ? $settings['teams_notify_campaigns'] : 'no';
        $is_configured = !empty($bot_app_id) && !empty($bot_password);
        
        ?>
        <h2><?php _e('Microsoft Teams Integration', 'ptp-comms-hub'); ?></h2>
        
        <p class="ptp-intro-text">
            <?php _e('Set up bidirectional SMS messaging through Microsoft Teams. When an SMS arrives, a new chat appears in Teams where you can reply directly.', 'ptp-comms-hub'); ?>
        </p>
        
        <?php if ($is_configured): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes-alt"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('Microsoft Teams Bot is configured.', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('Bidirectional messaging is enabled - you can reply to SMS directly from Teams chats.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="ptp-comms-alert warning">
                <span class="dashicons dashicons-warning"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('Microsoft Teams Bot is not configured.', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('Complete the bot setup to enable two-way SMS conversations in Teams.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <h3 style="margin-top: 30px;"><?php _e('Teams Bot Configuration (Recommended)', 'ptp-comms-hub'); ?></h3>
        <p style="color: #666; margin-bottom: 20px;">
            <?php _e('This enables true two-way conversations. Follow the setup guide to create your Azure bot.', 'ptp-comms-hub'); ?>
        </p>
        
        <div class="ptp-comms-form-group">
            <label for="teams_bot_app_id"><?php _e('Bot App ID (Microsoft App ID)', 'ptp-comms-hub'); ?></label>
            <input type="text" id="teams_bot_app_id" name="settings[teams_bot_app_id]" value="<?php echo esc_attr($bot_app_id); ?>" placeholder="12345678-1234-1234-1234-123456789012" style="font-family: monospace;">
            <span class="ptp-comms-form-help">
                <?php _e('From Azure Portal → Your Bot → Configuration → Microsoft App ID', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="teams_bot_app_password"><?php _e('Bot App Password (Client Secret)', 'ptp-comms-hub'); ?></label>
            <input type="password" id="teams_bot_app_password" name="settings[teams_bot_app_password]" value="<?php echo esc_attr($bot_password); ?>" placeholder="abc123~def456_ghi789" style="font-family: monospace;">
            <span class="ptp-comms-form-help">
                <?php _e('From Azure Portal → Your Bot → Manage → Certificates & secrets → New client secret', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="teams_tenant_id"><?php _e('Microsoft 365 Tenant ID', 'ptp-comms-hub'); ?></label>
            <input type="text" id="teams_tenant_id" name="settings[teams_tenant_id]" value="<?php echo esc_attr($tenant_id); ?>" placeholder="abcdefgh-1234-5678-90ab-cdef12345678" style="font-family: monospace;">
            <span class="ptp-comms-form-help">
                <?php _e('From Azure Portal → Azure Active Directory → Overview → Tenant ID', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="teams_service_url"><?php _e('Teams Service URL', 'ptp-comms-hub'); ?></label>
            <input type="url" id="teams_service_url" name="settings[teams_service_url]" value="<?php echo esc_attr($service_url); ?>" placeholder="https://smba.trafficmanager.net/amer/" style="font-family: monospace;">
            <span class="ptp-comms-form-help">
                <?php _e('Default works for most organizations. Change only if using Government or specialized cloud.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-info-box" style="background: #f0f7ff; border-left: 4px solid #0078d4;">
            <h3><?php _e('🤖 Bot Messaging Endpoint', 'ptp-comms-hub'); ?></h3>
            <p><?php _e('Set this as your bot\'s messaging endpoint in Azure:', 'ptp-comms-hub'); ?></p>
            <code style="display: block; background: white; padding: 12px; border-radius: 4px; margin: 10px 0; font-size: 13px; word-break: break-all;">
                <?php echo esc_html(get_rest_url(null, 'ptp-comms/v1/teams-bot')); ?>
            </code>
            <p style="margin-top: 10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ptp-comms-settings&download=teams-setup-guide')); ?>" class="button" style="margin-right: 10px;">
                    📖 Download Complete Setup Guide
                </a>
                <a href="https://portal.azure.com" target="_blank" class="button">
                    🚀 Open Azure Portal
                </a>
            </p>
        </div>
        
        <h3 style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #eee;">
            <?php _e('📱 Teams Shared Inbox (iOS Messages-Style)', 'ptp-comms-hub'); ?>
        </h3>
        <p style="color: #666; margin-bottom: 20px;">
            <?php _e('Create a shared SMS inbox in Teams where each contact gets their own chat thread - just like iOS Messages. Your whole team can see and reply to conversations.', 'ptp-comms-hub'); ?>
        </p>
        
        <?php
        $inbox_webhook = isset($settings['teams_inbox_webhook_url']) ? $settings['teams_inbox_webhook_url'] : '';
        $inbox_configured = !empty($inbox_webhook);
        ?>
        
        <?php if ($inbox_configured): ?>
            <div class="ptp-comms-alert success" style="margin-bottom: 20px;">
                <span class="dashicons dashicons-yes-alt"></span>
                <div class="ptp-comms-alert-content">
                    <strong><?php _e('Teams Shared Inbox is configured!', 'ptp-comms-hub'); ?></strong>
                    <p style="margin: 4px 0 0;"><?php _e('Inbound SMS will appear as adaptive cards in your Teams channel with reply buttons.', 'ptp-comms-hub'); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="ptp-comms-form-group">
            <label for="teams_inbox_webhook_url"><?php _e('Teams Channel Webhook URL (for Shared Inbox)', 'ptp-comms-hub'); ?></label>
            <input type="url" id="teams_inbox_webhook_url" name="settings[teams_inbox_webhook_url]" value="<?php echo esc_attr($inbox_webhook); ?>" placeholder="https://outlook.office.com/webhook/...">
            <span class="ptp-comms-form-help">
                <?php _e('Create an Incoming Webhook in a dedicated channel (e.g., "SMS Inbox") to receive messages.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-info-box" style="background: #e8f5e9;">
            <h3><?php _e('📋 Quick Setup Guide', 'ptp-comms-hub'); ?></h3>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li><?php _e('Create a Teams channel called "SMS Inbox" or similar', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Click the ••• menu next to the channel name → Connectors', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Search for "Incoming Webhook" and click Configure', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Give it a name (e.g., "PTP SMS") and optionally upload an icon', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Copy the webhook URL and paste it above', 'ptp-comms-hub'); ?></li>
            </ol>
            <p style="margin-top: 15px;"><strong><?php _e('How it works:', 'ptp-comms-hub'); ?></strong></p>
            <ul style="margin: 5px 0; padding-left: 20px;">
                <li><?php _e('Each inbound SMS creates an Adaptive Card in the channel', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Cards show contact info, message, and a reply button', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Click "Send SMS Reply" to respond directly from Teams', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Outbound messages are logged and shown in the channel', 'ptp-comms-hub'); ?></li>
            </ul>
        </div>
        
        <div class="ptp-info-box" style="margin-top: 15px; background: #fff3e0;">
            <h3><?php _e('🔧 Reply Webhook Endpoint', 'ptp-comms-hub'); ?></h3>
            <p><?php _e('For replies to work, your Teams workflow needs to POST to:', 'ptp-comms-hub'); ?></p>
            <code style="display: block; background: white; padding: 12px; border-radius: 4px; margin: 10px 0; font-size: 13px; word-break: break-all;">
                <?php echo esc_html(get_rest_url(null, 'ptp-comms/v1/teams-inbox-reply')); ?>
            </code>
            <p style="font-size: 13px; color: #666;"><?php _e('This endpoint accepts JSON with contact_id, phone, and reply_message fields.', 'ptp-comms-hub'); ?></p>
        </div>
        
        <h3 style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #eee;">
            <?php _e('Webhook Fallback (Optional)', 'ptp-comms-hub'); ?>
        </h3>
        <p style="color: #666; margin-bottom: 20px;">
            <?php _e('Incoming webhooks provide one-way notifications only (no replies). Configure bot above for full functionality.', 'ptp-comms-hub'); ?>
        </p>
        
        <div class="ptp-comms-form-group">
            <label for="teams_webhook_url"><?php _e('Teams Incoming Webhook URL', 'ptp-comms-hub'); ?></label>
            <input type="url" id="teams_webhook_url" name="settings[teams_webhook_url]" value="<?php echo esc_attr($teams_webhook); ?>" placeholder="https://outlook.office.com/webhook/...">
            <span class="ptp-comms-form-help">
                <?php _e('Create an Incoming Webhook connector in your Teams channel to get this URL.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <h3 style="margin-top: 30px;"><?php _e('Notification Settings', 'ptp-comms-hub'); ?></h3>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[teams_notify_orders]" value="yes" <?php checked($notify_orders, 'yes'); ?>>
                <span><?php _e('Notify on new WooCommerce orders', 'ptp-comms-hub'); ?></span>
            </label>
        </div>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[teams_notify_messages]" value="yes" <?php checked($notify_messages, 'yes'); ?>>
                <span><?php _e('Notify on inbound parent messages (Creates new chat for bot mode)', 'ptp-comms-hub'); ?></span>
            </label>
        </div>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[teams_notify_campaigns]" value="yes" <?php checked($notify_campaigns, 'yes'); ?>>
                <span><?php _e('Notify when campaigns are sent', 'ptp-comms-hub'); ?></span>
            </label>
        </div>
        <?php
    }
    
    private static function render_woocommerce_settings($settings) {
        $auto_create_contacts = isset($settings['woo_auto_create_contacts']) ? $settings['woo_auto_create_contacts'] : 'yes';
        $auto_opt_in = isset($settings['woo_auto_opt_in']) ? $settings['woo_auto_opt_in'] : 'no';
        $send_confirmation = isset($settings['woo_send_confirmation']) ? $settings['woo_send_confirmation'] : 'no';
        $confirmation_template = isset($settings['woo_confirmation_template']) ? $settings['woo_confirmation_template'] : '';
        $enable_reminders = isset($settings['woo_enable_reminders']) ? $settings['woo_enable_reminders'] : 'yes';
        $reminder_1day = isset($settings['woo_reminder_1day']) ? $settings['woo_reminder_1day'] : 'yes';
        $reminder_3day = isset($settings['woo_reminder_3day']) ? $settings['woo_reminder_3day'] : 'no';
        $reminder_7day = isset($settings['woo_reminder_7day']) ? $settings['woo_reminder_7day'] : 'yes';
        
        ?>
        <h2><?php _e('WooCommerce Integration', 'ptp-comms-hub'); ?></h2>
        <p class="description"><?php _e('Automatically sync WooCommerce orders with your contact database and send SMS notifications.', 'ptp-comms-hub'); ?></p>
        
        <?php if (!class_exists('WooCommerce')): ?>
        <div class="ptp-comms-alert warning" style="margin: 15px 0;">
            <span class="dashicons dashicons-warning"></span>
            <strong><?php _e('WooCommerce is not installed or activated.', 'ptp-comms-hub'); ?></strong>
            <p><?php _e('Install WooCommerce to enable order tracking and camp registration features.', 'ptp-comms-hub'); ?></p>
        </div>
        <?php else: ?>
        <div class="ptp-comms-alert success" style="margin: 15px 0;">
            <span class="dashicons dashicons-yes-alt"></span>
            <strong><?php _e('WooCommerce is active!', 'ptp-comms-hub'); ?></strong>
        </div>
        <?php endif; ?>
        
        <h3 style="margin-top: 30px;"><?php _e('Contact Creation', 'ptp-comms-hub'); ?></h3>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[woo_auto_create_contacts]" value="yes" <?php checked($auto_create_contacts, 'yes'); ?>>
                <span><?php _e('Automatically create contacts from orders', 'ptp-comms-hub'); ?></span>
            </label>
            <span class="ptp-comms-form-help" style="margin-left: 32px;">
                <?php _e('Creates a contact record when an order is placed with billing phone number.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[woo_auto_opt_in]" value="yes" <?php checked($auto_opt_in, 'yes'); ?>>
                <span><?php _e('Auto opt-in new contacts from orders', 'ptp-comms-hub'); ?></span>
            </label>
            <span class="ptp-comms-form-help" style="margin-left: 32px;">
                <?php _e('Automatically marks contacts as opted-in for SMS. Note: The checkout page also has an opt-in checkbox.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <h3 style="margin-top: 30px;"><?php _e('Order Confirmation SMS', 'ptp-comms-hub'); ?></h3>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[woo_send_confirmation]" value="yes" <?php checked($send_confirmation, 'yes'); ?>>
                <span><?php _e('Send SMS confirmation after order', 'ptp-comms-hub'); ?></span>
            </label>
            <span class="ptp-comms-form-help" style="margin-left: 32px;">
                <?php _e('Sends a confirmation SMS when order status changes to "processing" or "completed".', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="woo_confirmation_template"><?php _e('Confirmation SMS Template', 'ptp-comms-hub'); ?></label>
            <select id="woo_confirmation_template" name="settings[woo_confirmation_template]">
                <option value=""><?php _e('Use default message', 'ptp-comms-hub'); ?></option>
                <?php
                global $wpdb;
                $templates = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}ptp_templates WHERE category = 'confirmation' OR category = 'general'");
                foreach ($templates as $template) {
                    echo '<option value="' . $template->id . '" ' . selected($confirmation_template, $template->id, false) . '>' . esc_html($template->name) . '</option>';
                }
                ?>
            </select>
            <span class="ptp-comms-form-help"><?php _e('Template used for order confirmation messages. Available variables: {parent_first_name}, {child_name}, {event_name}, {event_date}, {event_time}, {event_location}, {order_number}', 'ptp-comms-hub'); ?></span>
        </div>
        
        <h3 style="margin-top: 30px;"><?php _e('Event Reminders', 'ptp-comms-hub'); ?></h3>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[woo_enable_reminders]" value="yes" <?php checked($enable_reminders, 'yes'); ?>>
                <span><?php _e('Enable automatic event reminders', 'ptp-comms-hub'); ?></span>
            </label>
            <span class="ptp-comms-form-help" style="margin-left: 32px;">
                <?php _e('Automatically send SMS reminders before camp/event dates.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group" style="margin-left: 32px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
            <p style="margin: 0 0 10px 0; font-weight: 600;"><?php _e('Send reminders:', 'ptp-comms-hub'); ?></p>
            
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 8px;">
                <input type="checkbox" name="settings[woo_reminder_7day]" value="yes" <?php checked($reminder_7day, 'yes'); ?>>
                <span><?php _e('7 days before event', 'ptp-comms-hub'); ?></span>
            </label>
            
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 8px;">
                <input type="checkbox" name="settings[woo_reminder_3day]" value="yes" <?php checked($reminder_3day, 'yes'); ?>>
                <span><?php _e('3 days before event', 'ptp-comms-hub'); ?></span>
            </label>
            
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[woo_reminder_1day]" value="yes" <?php checked($reminder_1day, 'yes'); ?>>
                <span><?php _e('1 day before event (recommended)', 'ptp-comms-hub'); ?></span>
            </label>
        </div>
        
        <h3 style="margin-top: 30px;"><?php _e('📊 Google Sheets Integration', 'ptp-comms-hub'); ?></h3>
        
        <?php
        $sheets_enabled = isset($settings['google_sheets_enabled']) ? $settings['google_sheets_enabled'] : 'no';
        $sheets_webhook = isset($settings['google_sheets_webhook_url']) ? $settings['google_sheets_webhook_url'] : '';
        ?>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[google_sheets_enabled]" value="yes" <?php checked($sheets_enabled, 'yes'); ?>>
                <span><?php _e('Enable Google Sheets auto-sync', 'ptp-comms-hub'); ?></span>
            </label>
            <span class="ptp-comms-form-help" style="margin-left: 32px;">
                <?php _e('Automatically sync new orders and registrations to a Google Sheet.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="google_sheets_webhook_url"><?php _e('Google Sheets Webhook URL', 'ptp-comms-hub'); ?></label>
            <input type="url" id="google_sheets_webhook_url" name="settings[google_sheets_webhook_url]" value="<?php echo esc_attr($sheets_webhook); ?>" placeholder="https://script.google.com/macros/s/...">
            <span class="ptp-comms-form-help"><?php _e('Your Google Apps Script Web App URL. See Orders & Camps > Google Sheets tab for setup instructions.', 'ptp-comms-hub'); ?></span>
        </div>
        
        <h3 style="margin-top: 30px;"><?php _e('🔄 Order Sync', 'ptp-comms-hub'); ?></h3>
        
        <div class="ptp-comms-form-group">
            <p style="margin-bottom: 15px; color: #666;"><?php _e('Sync all existing WooCommerce orders to the registrations table. Use this to populate registration data from past orders.', 'ptp-comms-hub'); ?></p>
            <button type="button" id="woo-sync-btn" class="button button-primary" onclick="ptpSyncAllOrdersFromSettings()">
                🔄 <?php _e('Sync All Orders Now', 'ptp-comms-hub'); ?>
            </button>
            <span id="woo-sync-status" style="margin-left: 15px;"></span>
        </div>
        
        <script>
        function ptpSyncAllOrdersFromSettings() {
            var btn = document.getElementById('woo-sync-btn');
            var status = document.getElementById('woo-sync-status');
            var nonce = (typeof ptpCommsData !== 'undefined' && ptpCommsData.nonce) ? ptpCommsData.nonce : '';
            var ajaxUrl = (typeof ptpCommsData !== 'undefined' && ptpCommsData.ajax_url) ? ptpCommsData.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
            
            btn.disabled = true;
            btn.innerHTML = '🔄 Syncing...';
            status.innerHTML = '<span style="color: #666;">Processing orders, please wait...</span>';
            
            jQuery.post(ajaxUrl, {
                action: 'ptp_sync_all_orders',
                nonce: nonce
            }, function(response) {
                btn.disabled = false;
                btn.innerHTML = '🔄 Sync All Orders Now';
                
                if (response.success) {
                    status.innerHTML = '<span style="color: green;">✅ Synced ' + response.data.synced + ' orders (' + response.data.errors + ' errors)</span>';
                } else {
                    status.innerHTML = '<span style="color: red;">❌ Sync failed: ' + (response.data || 'Unknown error') + '</span>';
                }
            }).fail(function(xhr, textStatus, error) {
                btn.disabled = false;
                btn.innerHTML = '🔄 Sync All Orders Now';
                status.innerHTML = '<span style="color: red;">❌ Request failed: ' + error + '</span>';
            });
        }
        </script>
        
        <div class="ptp-info-box" style="margin-top: 30px;">
            <h3><?php _e('📋 How It Works', 'ptp-comms-hub'); ?></h3>
            
            <h4><?php _e('When an order is placed:', 'ptp-comms-hub'); ?></h4>
            <ol>
                <li><?php _e('Contact is created/updated with billing & camper info', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Registration record created for each product with camp details', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Contact segments auto-updated (market, age group, year)', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Confirmation SMS sent (if enabled & opted-in)', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Teams notification sent (if configured)', 'ptp-comms-hub'); ?></li>
                <li><?php _e('HubSpot sync triggered (if configured)', 'ptp-comms-hub'); ?></li>
            </ol>
            
            <h4><?php _e('Product Setup (for camp details in SMS):', 'ptp-comms-hub'); ?></h4>
            <p><?php _e('Edit any WooCommerce product and scroll to the "PTP Camp Details" section to enter:', 'ptp-comms-hub'); ?></p>
            <ul>
                <li><?php _e('Camp date(s) and time', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Location name and address', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Program type, age range, market', 'ptp-comms-hub'); ?></li>
                <li><?php _e('What to bring, coach info', 'ptp-comms-hub'); ?></li>
            </ul>
            
            <h4><?php _e('Checkout Fields:', 'ptp-comms-hub'); ?></h4>
            <p><?php _e('The checkout page automatically includes fields for:', 'ptp-comms-hub'); ?></p>
            <ul>
                <li><?php _e('Camper name, age, birthdate', 'ptp-comms-hub'); ?></li>
                <li><?php _e('T-shirt size', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Medical/special needs', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Emergency contact info', 'ptp-comms-hub'); ?></li>
                <li><?php _e('SMS opt-in checkbox', 'ptp-comms-hub'); ?></li>
            </ul>
        </div>
        
        <?php
        // Show registration stats
        if (class_exists('WooCommerce')) {
            $stats = PTP_Comms_Hub_WooCommerce::get_registration_stats();
            ?>
            <div class="ptp-info-box" style="margin-top: 20px; background: #e8f5e9;">
                <h3><?php _e('📊 Registration Stats', 'ptp-comms-hub'); ?></h3>
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; text-align: center;">
                    <div>
                        <div style="font-size: 24px; font-weight: bold;"><?php echo number_format($stats['total']); ?></div>
                        <div style="font-size: 12px; color: #666;"><?php _e('Total', 'ptp-comms-hub'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #28a745;"><?php echo number_format($stats['confirmed']); ?></div>
                        <div style="font-size: 12px; color: #666;"><?php _e('Confirmed', 'ptp-comms-hub'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #ffc107;"><?php echo number_format($stats['pending']); ?></div>
                        <div style="font-size: 12px; color: #666;"><?php _e('Pending', 'ptp-comms-hub'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #dc3545;"><?php echo number_format($stats['cancelled']); ?></div>
                        <div style="font-size: 12px; color: #666;"><?php _e('Cancelled', 'ptp-comms-hub'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #17a2b8;"><?php echo number_format($stats['upcoming']); ?></div>
                        <div style="font-size: 12px; color: #666;"><?php _e('Upcoming', 'ptp-comms-hub'); ?></div>
                    </div>
                </div>
            </div>
            <?php
        }
    }
    
    private static function render_general_settings($settings) {
        $company_name = isset($settings['company_name']) ? $settings['company_name'] : 'PTP Soccer Camps';
        $timezone = isset($settings['timezone']) ? $settings['timezone'] : 'America/New_York';
        $date_format = isset($settings['date_format']) ? $settings['date_format'] : 'F j, Y';
        $enable_logging = isset($settings['enable_logging']) ? $settings['enable_logging'] : 'yes';
        
        // Quiet hours settings
        $quiet_hours_enabled = isset($settings['quiet_hours_enabled']) ? $settings['quiet_hours_enabled'] : 'yes';
        $quiet_hours_start = isset($settings['quiet_hours_start']) ? $settings['quiet_hours_start'] : '21';
        $quiet_hours_end = isset($settings['quiet_hours_end']) ? $settings['quiet_hours_end'] : '8';
        
        ?>
        <h2><?php _e('General Settings', 'ptp-comms-hub'); ?></h2>
        
        <div class="ptp-comms-form-group">
            <label for="company_name"><?php _e('Company Name', 'ptp-comms-hub'); ?></label>
            <input type="text" id="company_name" name="settings[company_name]" value="<?php echo esc_attr($company_name); ?>">
            <span class="ptp-comms-form-help"><?php _e('Used in messages and templates.', 'ptp-comms-hub'); ?></span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="timezone"><?php _e('Timezone', 'ptp-comms-hub'); ?></label>
            <select id="timezone" name="settings[timezone]">
                <option value="America/New_York" <?php selected($timezone, 'America/New_York'); ?>><?php _e('Eastern Time (ET)', 'ptp-comms-hub'); ?></option>
                <option value="America/Chicago" <?php selected($timezone, 'America/Chicago'); ?>><?php _e('Central Time (CT)', 'ptp-comms-hub'); ?></option>
                <option value="America/Denver" <?php selected($timezone, 'America/Denver'); ?>><?php _e('Mountain Time (MT)', 'ptp-comms-hub'); ?></option>
                <option value="America/Phoenix" <?php selected($timezone, 'America/Phoenix'); ?>><?php _e('Arizona Time (no DST)', 'ptp-comms-hub'); ?></option>
                <option value="America/Los_Angeles" <?php selected($timezone, 'America/Los_Angeles'); ?>><?php _e('Pacific Time (PT)', 'ptp-comms-hub'); ?></option>
            </select>
            <span class="ptp-comms-form-help"><?php _e('Timezone for scheduling campaigns and automations.', 'ptp-comms-hub'); ?></span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="date_format"><?php _e('Date Format', 'ptp-comms-hub'); ?></label>
            <select id="date_format" name="settings[date_format]">
                <option value="F j, Y" <?php selected($date_format, 'F j, Y'); ?>><?php _e('January 15, 2024', 'ptp-comms-hub'); ?></option>
                <option value="m/d/Y" <?php selected($date_format, 'm/d/Y'); ?>><?php _e('01/15/2024', 'ptp-comms-hub'); ?></option>
                <option value="d/m/Y" <?php selected($date_format, 'd/m/Y'); ?>><?php _e('15/01/2024', 'ptp-comms-hub'); ?></option>
                <option value="Y-m-d" <?php selected($date_format, 'Y-m-d'); ?>><?php _e('2024-01-15', 'ptp-comms-hub'); ?></option>
            </select>
            <span class="ptp-comms-form-help"><?php _e('How dates appear in messages.', 'ptp-comms-hub'); ?></span>
        </div>
        
        <!-- Quiet Hours / Compliance Section -->
        <h3 style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #eee;">
            <span class="dashicons dashicons-clock" style="color: #FCB900;"></span>
            <?php _e('Quiet Hours & Compliance', 'ptp-comms-hub'); ?>
        </h3>
        <p style="color: #666; margin-bottom: 20px;">
            <?php _e('Respect your recipients by not sending messages during late night/early morning hours.', 'ptp-comms-hub'); ?>
        </p>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[quiet_hours_enabled]" value="yes" <?php checked($quiet_hours_enabled, 'yes'); ?>>
                <span><?php _e('Enable Quiet Hours', 'ptp-comms-hub'); ?></span>
            </label>
            <span class="ptp-comms-form-help" style="margin-left: 32px;">
                <?php _e('Messages sent during quiet hours will be queued and sent at the next allowed time.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <div class="ptp-comms-form-group" style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div>
                <label for="quiet_hours_start"><?php _e('Quiet Hours Start', 'ptp-comms-hub'); ?></label>
                <select id="quiet_hours_start" name="settings[quiet_hours_start]" style="min-width: 120px;">
                    <?php for ($h = 17; $h <= 23; $h++): ?>
                        <option value="<?php echo $h; ?>" <?php selected($quiet_hours_start, (string)$h); ?>>
                            <?php echo ($h > 12 ? ($h - 12) . ':00 PM' : $h . ':00 PM'); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="quiet_hours_end"><?php _e('Quiet Hours End', 'ptp-comms-hub'); ?></label>
                <select id="quiet_hours_end" name="settings[quiet_hours_end]" style="min-width: 120px;">
                    <?php for ($h = 6; $h <= 11; $h++): ?>
                        <option value="<?php echo $h; ?>" <?php selected($quiet_hours_end, (string)$h); ?>>
                            <?php echo $h . ':00 AM'; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <span class="ptp-comms-form-help">
            <?php _e('Default: 9:00 PM - 8:00 AM. This applies to automated messages only - manual sends are not affected.', 'ptp-comms-hub'); ?>
        </span>
        
        <div class="ptp-info-box" style="margin-top: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
            <h4 style="margin-top: 0; color: #856404;">
                <span class="dashicons dashicons-shield"></span>
                <?php _e('SMS Compliance Best Practices', 'ptp-comms-hub'); ?>
            </h4>
            <ul style="margin: 10px 0 0 20px; color: #856404;">
                <li><?php _e('All outbound messages include opt-out instructions (Reply STOP)', 'ptp-comms-hub'); ?></li>
                <li><?php _e('STOP, START, and HELP keywords are automatically handled', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Quiet hours prevent late-night messaging', 'ptp-comms-hub'); ?></li>
                <li><?php _e('Contact consent is tracked for each registration', 'ptp-comms-hub'); ?></li>
            </ul>
        </div>
        
        <h3 style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #eee;">
            <span class="dashicons dashicons-admin-tools" style="color: #FCB900;"></span>
            <?php _e('Debugging & Maintenance', 'ptp-comms-hub'); ?>
        </h3>
        
        <div class="ptp-comms-form-group">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="settings[enable_logging]" value="yes" <?php checked($enable_logging, 'yes'); ?>>
                <span><?php _e('Enable detailed logging', 'ptp-comms-hub'); ?></span>
            </label>
            <span class="ptp-comms-form-help" style="margin-left: 32px;">
                <?php _e('Logs all API calls and webhook activity for debugging.', 'ptp-comms-hub'); ?>
            </span>
        </div>
        
        <!-- Webhook Health Check -->
        <div class="ptp-comms-form-group" style="padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e2e4e7; margin-top: 20px;">
            <h4 style="margin: 0 0 15px 0;"><?php _e('🔍 Webhook Health Check', 'ptp-comms-hub'); ?></h4>
            <p style="margin: 0 0 15px; color: #666;"><?php _e('Test that your webhooks are accessible from the internet.', 'ptp-comms-hub'); ?></p>
            <button type="button" id="test-webhook-btn" class="button button-secondary" onclick="ptpTestWebhooks()">
                <span class="dashicons dashicons-rest-api" style="margin-top: 3px;"></span>
                <?php _e('Test Webhooks', 'ptp-comms-hub'); ?>
            </button>
            <span id="webhook-test-result" style="margin-left: 15px;"></span>
        </div>
        
        <script>
        function ptpTestWebhooks() {
            var btn = document.getElementById('test-webhook-btn');
            var result = document.getElementById('webhook-test-result');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="dashicons dashicons-update-alt" style="margin-top: 3px; animation: rotation 1s linear infinite;"></span> Testing...';
            result.innerHTML = '';
            
            fetch('<?php echo home_url('/ptp-comms/test-webhook'); ?>')
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="dashicons dashicons-rest-api" style="margin-top: 3px;"></span> Test Webhooks';
                    
                    if (data.status === 'success') {
                        result.innerHTML = '<span style="color: #46b450;">✅ Webhooks are working! (' + data.timestamp + ')</span>';
                    } else {
                        result.innerHTML = '<span style="color: #dc3232;">❌ Webhook test failed</span>';
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="dashicons dashicons-rest-api" style="margin-top: 3px;"></span> Test Webhooks';
                    result.innerHTML = '<span style="color: #dc3232;">❌ Could not reach webhook endpoint</span>';
                });
        }
        </script>
        
        <div class="ptp-info-box">
            <h3><?php _e('System Information', 'ptp-comms-hub'); ?></h3>
            <p><strong><?php _e('Plugin Version:', 'ptp-comms-hub'); ?></strong> <?php echo defined('PTP_COMMS_HUB_VERSION') ? PTP_COMMS_HUB_VERSION : '3.4.0'; ?></p>
            <p><strong><?php _e('WordPress Version:', 'ptp-comms-hub'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
            <p><strong><?php _e('PHP Version:', 'ptp-comms-hub'); ?></strong> <?php echo PHP_VERSION; ?></p>
            <p><strong><?php _e('Database Prefix:', 'ptp-comms-hub'); ?></strong> <?php global $wpdb; echo $wpdb->prefix; ?></p>
            <p><strong><?php _e('SMS Webhook:', 'ptp-comms-hub'); ?></strong> <code style="font-size: 11px;"><?php echo home_url('/ptp-comms/sms-webhook'); ?></code></p>
            <p><strong><?php _e('SMS Status Webhook:', 'ptp-comms-hub'); ?></strong> <code style="font-size: 11px;"><?php echo home_url('/ptp-comms/sms-status'); ?></code></p>
        </div>
        <?php
    }
    
    private static function save_settings() {
        if (!isset($_POST['settings']) || !is_array($_POST['settings'])) {
            return;
        }
        
        $settings = $_POST['settings'];
        
        // Handle checkboxes (they're not sent if unchecked)
        $checkbox_fields = array(
            'hubspot_auto_sync',
            'teams_notify_orders',
            'teams_notify_messages',
            'teams_notify_campaigns',
            'woo_auto_create_contacts',
            'woo_auto_opt_in',
            'woo_send_confirmation',
            'woo_enable_reminders',
            'woo_reminder_7day',
            'woo_reminder_3day',
            'woo_reminder_1day',
            'google_sheets_enabled',
            'ivr_auto_forward_business_hours',
            'enable_logging',
            'quiet_hours_enabled'
        );
        
        foreach ($checkbox_fields as $field) {
            if (!isset($settings[$field])) {
                $settings[$field] = 'no';
            }
        }
        
        // Handle IVR forwarding numbers (convert comma-separated string to array)
        if (isset($settings['ivr_forwarding_numbers']) && !empty($settings['ivr_forwarding_numbers'])) {
            $numbers = $settings['ivr_forwarding_numbers'];
            // Split by comma, trim whitespace, remove empty entries
            $numbers_array = array_filter(array_map('trim', explode(',', $numbers)));
            $settings['ivr_forwarding_numbers'] = $numbers_array;
        }
        
        // Sanitize settings
        $sanitized = array();
        $textarea_fields = array('ivr_greeting', 'ivr_menu_prompt', 'ivr_forward_message', 'ivr_voicemail_message');
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $textarea_fields)) {
                $sanitized[$key] = sanitize_textarea_field($value);
            } elseif ($key === 'ivr_forwarding_numbers' && is_array($value)) {
                // Sanitize each phone number
                $sanitized[$key] = array_map('sanitize_text_field', $value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        // Save to database
        PTP_Comms_Hub_Settings::update_all($sanitized);
        
        // Flush rewrite rules if webhooks changed
        flush_rewrite_rules();
    }
}
