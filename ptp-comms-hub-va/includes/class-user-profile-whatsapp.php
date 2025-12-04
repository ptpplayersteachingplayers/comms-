<?php
/**
 * User Profile WhatsApp Notification Settings
 * Allows users to configure their WhatsApp notification preferences
 * Version: 1.0.0
 */

class PTP_Comms_Hub_User_Profile_WhatsApp {

    public static function init() {
        add_action('show_user_profile', array(__CLASS__, 'render_whatsapp_fields'));
        add_action('edit_user_profile', array(__CLASS__, 'render_whatsapp_fields'));
        add_action('personal_options_update', array(__CLASS__, 'save_whatsapp_fields'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_whatsapp_fields'));
    }

    /**
     * Render WhatsApp notification fields on user profile
     */
    public static function render_whatsapp_fields($user) {
        // Only show for users who can edit posts (admins/editors)
        if (!user_can($user->ID, 'edit_posts')) {
            return;
        }

        $whatsapp_enabled = get_user_meta($user->ID, 'ptp_whatsapp_notifications', true);
        $whatsapp_phone = get_user_meta($user->ID, 'ptp_whatsapp_phone', true);
        $whatsapp_shared_inbox = get_user_meta($user->ID, 'ptp_whatsapp_shared_inbox', true);
        $notification_types = get_user_meta($user->ID, 'ptp_whatsapp_notification_types', true);

        if (!is_array($notification_types)) {
            $notification_types = array('new_message', 'contact_replied', 'reminder_due', 'voicemail');
        }

        $available_types = array(
            'new_message' => 'New Messages',
            'contact_replied' => 'Contact Replies',
            'reminder_due' => 'Reminder Alerts',
            'voicemail' => 'New Voicemails',
            'missed_call' => 'Missed Calls',
            'new_order' => 'New Orders',
            'new_registration' => 'New Registrations',
            'birthday_alert' => 'Birthday Alerts'
        );
        ?>
        <h2 style="display: flex; align-items: center; gap: 10px; margin-top: 40px; padding-top: 30px; border-top: 2px solid #e5e7eb;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#25D366" style="vertical-align: middle;">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            WhatsApp Notification Preferences
        </h2>

        <p class="description" style="margin-bottom: 20px;">
            Receive PTP Comms alerts directly to your WhatsApp. Perfect for staying informed on-the-go!
        </p>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="ptp_whatsapp_notifications">Enable WhatsApp Notifications</label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="ptp_whatsapp_notifications" id="ptp_whatsapp_notifications" value="yes" <?php checked($whatsapp_enabled, 'yes'); ?>>
                        Send me notifications via WhatsApp
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ptp_whatsapp_phone">Your WhatsApp Phone Number</label>
                </th>
                <td>
                    <input type="tel" name="ptp_whatsapp_phone" id="ptp_whatsapp_phone"
                           value="<?php echo esc_attr($whatsapp_phone); ?>"
                           placeholder="+12025551234" class="regular-text"
                           style="max-width: 250px;">
                    <p class="description">Enter your phone number in E.164 format (e.g., +12025551234)</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ptp_whatsapp_shared_inbox">Shared Inbox Alerts</label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="ptp_whatsapp_shared_inbox" id="ptp_whatsapp_shared_inbox" value="yes" <?php checked($whatsapp_shared_inbox, 'yes'); ?>>
                        Receive alerts for new messages in the Shared Inbox
                    </label>
                    <p class="description">Get notified when new SMS, WhatsApp, or Voice messages arrive in the shared inbox.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Notification Types</th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">Select which notifications to receive via WhatsApp</legend>
                        <?php foreach ($available_types as $type => $label): ?>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" name="ptp_whatsapp_notification_types[]" value="<?php echo esc_attr($type); ?>"
                                   <?php checked(in_array($type, $notification_types)); ?>>
                            <?php echo esc_html($label); ?>
                        </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="description" style="margin-top: 10px;">Select which types of notifications you want to receive via WhatsApp.</p>
                </td>
            </tr>
        </table>

        <?php if (!function_exists('ptp_comms_is_whatsapp_configured') || !ptp_comms_is_whatsapp_configured()): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin: 20px 0;">
            <p style="margin: 0; color: #856404;">
                <strong>Note:</strong> WhatsApp integration is not yet configured by your administrator.
                Once configured, you'll be able to receive notifications via WhatsApp.
            </p>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Save WhatsApp notification fields
     */
    public static function save_whatsapp_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        $whatsapp_enabled = isset($_POST['ptp_whatsapp_notifications']) ? 'yes' : 'no';
        update_user_meta($user_id, 'ptp_whatsapp_notifications', $whatsapp_enabled);

        $whatsapp_phone = isset($_POST['ptp_whatsapp_phone']) ? sanitize_text_field($_POST['ptp_whatsapp_phone']) : '';
        // Normalize phone number
        if (!empty($whatsapp_phone) && function_exists('ptp_comms_normalize_phone')) {
            $whatsapp_phone = ptp_comms_normalize_phone($whatsapp_phone);
        }
        update_user_meta($user_id, 'ptp_whatsapp_phone', $whatsapp_phone);

        $whatsapp_shared_inbox = isset($_POST['ptp_whatsapp_shared_inbox']) ? 'yes' : 'no';
        update_user_meta($user_id, 'ptp_whatsapp_shared_inbox', $whatsapp_shared_inbox);

        $notification_types = isset($_POST['ptp_whatsapp_notification_types'])
            ? array_map('sanitize_text_field', $_POST['ptp_whatsapp_notification_types'])
            : array();
        update_user_meta($user_id, 'ptp_whatsapp_notification_types', $notification_types);
    }
}

// Initialize the user profile WhatsApp settings
add_action('init', array('PTP_Comms_Hub_User_Profile_WhatsApp', 'init'));
