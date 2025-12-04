<?php
/**
 * PTP Private Training Plugin Integration
 * Enables communication between Trainers, Parents, and VAs
 * Version: 1.0.0
 */

class PTP_Comms_Hub_Private_Training_Integration {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Only initialize if PTP Private Training plugin is active
        add_action('plugins_loaded', array($this, 'maybe_init'), 20);
    }

    /**
     * Initialize if Private Training plugin exists
     */
    public function maybe_init() {
        // Hook into Private Training events
        $this->register_hooks();

        // Add trainer communication features
        add_action('ptp_training_session_booked', array($this, 'notify_session_booked'), 10, 3);
        add_action('ptp_training_session_cancelled', array($this, 'notify_session_cancelled'), 10, 3);
        add_action('ptp_training_session_completed', array($this, 'notify_session_completed'), 10, 3);
        add_action('ptp_training_session_reminder', array($this, 'send_session_reminder'), 10, 2);
        add_action('ptp_training_feedback_submitted', array($this, 'notify_feedback_submitted'), 10, 3);

        // Register REST API endpoints for trainer communication
        add_action('rest_api_init', array($this, 'register_api_endpoints'));

        // Add shortcodes for parent portal
        add_shortcode('ptp_message_trainer', array($this, 'render_message_trainer_form'));
        add_shortcode('ptp_training_inbox', array($this, 'render_training_inbox'));
    }

    /**
     * Register integration hooks
     */
    private function register_hooks() {
        // WooCommerce booking integration
        add_action('woocommerce_booking_confirmed', array($this, 'handle_booking_confirmed'), 10, 1);
        add_action('woocommerce_booking_cancelled', array($this, 'handle_booking_cancelled'), 10, 1);

        // Sync trainer data with contacts
        add_action('ptp_trainer_assigned', array($this, 'sync_trainer_to_contact'), 10, 2);

        // Auto-create reminders for training sessions
        add_action('ptp_training_session_created', array($this, 'create_session_reminders'), 10, 2);
    }

    /**
     * Notify when training session is booked
     */
    public function notify_session_booked($session_id, $parent_id, $trainer_id) {
        $parent = $this->get_parent_contact($parent_id);
        $trainer = get_user_by('id', $trainer_id);
        $session = $this->get_session_details($session_id);

        if (!$parent || !$trainer || !$session) {
            return;
        }

        $child_name = $parent->child_name ?: 'your child';
        $session_date = date('l, F j', strtotime($session['date']));
        $session_time = date('g:i A', strtotime($session['time']));

        // Message to parent
        $parent_message = "Hi {$parent->parent_first_name}! Your private training session for {$child_name} has been confirmed:\n\n";
        $parent_message .= "Trainer: {$trainer->display_name}\n";
        $parent_message .= "Date: {$session_date}\n";
        $parent_message .= "Time: {$session_time}\n";
        $parent_message .= "Location: {$session['location']}\n\n";
        $parent_message .= "We'll send a reminder before your session. See you there!";

        // Send to parent via their preferred channel
        $this->send_to_parent($parent, $parent_message, 'session_confirmation');

        // Notify trainer
        $trainer_message = "New training session booked!\n\n";
        $trainer_message .= "Parent: {$parent->parent_first_name} {$parent->parent_last_name}\n";
        $trainer_message .= "Child: {$child_name}\n";
        $trainer_message .= "Date: {$session_date} at {$session_time}\n";
        $trainer_message .= "Location: {$session['location']}";

        $this->send_to_trainer($trainer_id, $trainer_message, 'session_booked');

        // Notify VA team
        $this->notify_va_team("New training session booked: {$parent->parent_first_name} with {$trainer->display_name} on {$session_date}", $parent->id);
    }

    /**
     * Notify when training session is cancelled
     */
    public function notify_session_cancelled($session_id, $parent_id, $trainer_id) {
        $parent = $this->get_parent_contact($parent_id);
        $trainer = get_user_by('id', $trainer_id);
        $session = $this->get_session_details($session_id);

        if (!$parent || !$session) {
            return;
        }

        $session_date = date('l, F j', strtotime($session['date']));

        // Message to parent
        $parent_message = "Hi {$parent->parent_first_name}, your training session scheduled for {$session_date} has been cancelled. ";
        $parent_message .= "Please contact us to reschedule. We apologize for any inconvenience!";

        $this->send_to_parent($parent, $parent_message, 'session_cancelled');

        // Notify trainer
        if ($trainer) {
            $trainer_message = "Session cancelled: {$parent->parent_first_name} {$parent->parent_last_name} on {$session_date}";
            $this->send_to_trainer($trainer_id, $trainer_message, 'session_cancelled');
        }

        // Notify VA team
        $this->notify_va_team("Training session cancelled: {$parent->parent_first_name} - {$session_date}", $parent->id);
    }

    /**
     * Notify when training session is completed
     */
    public function notify_session_completed($session_id, $parent_id, $trainer_id) {
        $parent = $this->get_parent_contact($parent_id);
        $trainer = get_user_by('id', $trainer_id);

        if (!$parent) {
            return;
        }

        $child_name = $parent->child_name ?: 'your child';
        $feedback_url = home_url('/training-feedback/?session=' . $session_id);

        // Message to parent requesting feedback
        $parent_message = "Hi {$parent->parent_first_name}! We hope {$child_name} had a great training session today!\n\n";
        $parent_message .= "We'd love to hear your feedback: {$feedback_url}\n\n";
        $parent_message .= "Thanks for training with PTP!";

        $this->send_to_parent($parent, $parent_message, 'feedback_request');

        // Create follow-up reminder for VA
        if (class_exists('PTP_Comms_Hub_Reminders')) {
            PTP_Comms_Hub_Reminders::create(array(
                'contact_id' => $parent->id,
                'title' => "Follow up on training session",
                'description' => "Check if {$parent->parent_first_name} wants to book another session",
                'due_date' => date('Y-m-d H:i:s', strtotime('+3 days')),
                'reminder_type' => 'follow_up',
                'priority' => 'normal'
            ));
        }
    }

    /**
     * Send session reminder
     */
    public function send_session_reminder($session_id, $hours_before = 24) {
        $session = $this->get_session_details($session_id);
        if (!$session) return;

        $parent = $this->get_parent_contact($session['parent_id']);
        $trainer = get_user_by('id', $session['trainer_id']);

        if (!$parent) return;

        $child_name = $parent->child_name ?: 'your child';
        $session_date = date('l, F j', strtotime($session['date']));
        $session_time = date('g:i A', strtotime($session['time']));

        $reminder_text = $hours_before >= 24 ? "tomorrow" : "today";

        $parent_message = "Reminder: {$child_name}'s training session is {$reminder_text}!\n\n";
        $parent_message .= "Trainer: " . ($trainer ? $trainer->display_name : 'TBD') . "\n";
        $parent_message .= "Date: {$session_date}\n";
        $parent_message .= "Time: {$session_time}\n";
        $parent_message .= "Location: {$session['location']}\n\n";
        $parent_message .= "See you there!";

        $this->send_to_parent($parent, $parent_message, 'session_reminder');

        // Also remind trainer
        if ($trainer) {
            $trainer_message = "Training session reminder:\n";
            $trainer_message .= "Child: {$child_name}\n";
            $trainer_message .= "Parent: {$parent->parent_first_name} {$parent->parent_last_name}\n";
            $trainer_message .= "Time: {$session_date} at {$session_time}\n";
            $trainer_message .= "Location: {$session['location']}";

            $this->send_to_trainer($session['trainer_id'], $trainer_message, 'session_reminder');
        }
    }

    /**
     * Notify when feedback is submitted
     */
    public function notify_feedback_submitted($session_id, $parent_id, $feedback) {
        $parent = $this->get_parent_contact($parent_id);
        $session = $this->get_session_details($session_id);

        if (!$parent || !$session) return;

        $rating = isset($feedback['rating']) ? $feedback['rating'] : 'N/A';
        $comments = isset($feedback['comments']) ? $feedback['comments'] : '';

        // Notify trainer
        $trainer_message = "New feedback received!\n\n";
        $trainer_message .= "From: {$parent->parent_first_name} {$parent->parent_last_name}\n";
        $trainer_message .= "Rating: {$rating}/5\n";
        if ($comments) {
            $trainer_message .= "Comments: {$comments}";
        }

        $this->send_to_trainer($session['trainer_id'], $trainer_message, 'feedback_received');

        // Notify VA team for follow-up if low rating
        if ($rating && $rating < 4) {
            $this->notify_va_team(
                "Low rating ({$rating}/5) from {$parent->parent_first_name} - needs follow-up",
                $parent->id,
                'high'
            );
        }

        // Thank parent
        $thank_you = "Thank you for your feedback, {$parent->parent_first_name}! We appreciate you taking the time to share your thoughts.";
        $this->send_to_parent($parent, $thank_you, 'feedback_thanks');
    }

    /**
     * Send message to parent via preferred channel
     */
    private function send_to_parent($contact, $message, $type = 'general') {
        if (!$contact || empty($contact->parent_phone)) {
            return false;
        }

        $results = array();

        // Determine preferred channel (default to SMS)
        $preferred_channel = get_user_meta($contact->user_id ?? 0, 'ptp_preferred_channel', true) ?: 'sms';

        // Send via SMS
        if ($preferred_channel === 'sms' || $preferred_channel === 'both') {
            if (class_exists('PTP_Comms_Hub_SMS_Service')) {
                $sms = new PTP_Comms_Hub_SMS_Service();
                $results['sms'] = $sms->send_sms($contact->parent_phone, $message);

                // Log the message
                if (function_exists('ptp_comms_log_message')) {
                    ptp_comms_log_message($contact->id, 'sms', 'outbound', $message, array(
                        'type' => $type,
                        'twilio_sid' => isset($results['sms']['sid']) ? $results['sms']['sid'] : ''
                    ));
                }
            }
        }

        // Send via WhatsApp
        if ($preferred_channel === 'whatsapp' || $preferred_channel === 'both') {
            if (function_exists('ptp_comms_send_whatsapp') && function_exists('ptp_comms_is_whatsapp_configured') && ptp_comms_is_whatsapp_configured()) {
                $results['whatsapp'] = ptp_comms_send_whatsapp($contact->parent_phone, $message);

                // Log the message
                if (function_exists('ptp_comms_log_message')) {
                    ptp_comms_log_message($contact->id, 'whatsapp', 'outbound', $message, array(
                        'type' => $type,
                        'twilio_sid' => isset($results['whatsapp']['sid']) ? $results['whatsapp']['sid'] : ''
                    ));
                }
            }
        }

        return $results;
    }

    /**
     * Send message to trainer
     */
    private function send_to_trainer($trainer_id, $message, $type = 'general') {
        $trainer = get_user_by('id', $trainer_id);
        if (!$trainer) return false;

        $results = array();

        // Create in-app notification
        if (class_exists('PTP_Comms_Hub_Notifications')) {
            $results['notification'] = PTP_Comms_Hub_Notifications::create(array(
                'user_id' => $trainer_id,
                'notification_type' => 'new_message',
                'title' => 'Training Update',
                'message' => $message,
                'priority' => 'normal'
            ));
        }

        // Send email
        $email_enabled = get_user_meta($trainer_id, 'ptp_email_notifications', true);
        if ($email_enabled !== 'disabled') {
            $results['email'] = wp_mail(
                $trainer->user_email,
                '[PTP Training] ' . ucfirst(str_replace('_', ' ', $type)),
                $message
            );
        }

        // Send WhatsApp if trainer has it enabled
        $whatsapp_enabled = get_user_meta($trainer_id, 'ptp_whatsapp_notifications', true);
        $whatsapp_phone = get_user_meta($trainer_id, 'ptp_whatsapp_phone', true);

        if ($whatsapp_enabled === 'yes' && $whatsapp_phone && function_exists('ptp_comms_send_whatsapp')) {
            $results['whatsapp'] = ptp_comms_send_whatsapp($whatsapp_phone, $message);
        }

        return $results;
    }

    /**
     * Notify VA team
     */
    private function notify_va_team($message, $contact_id = null, $priority = 'normal') {
        if (!class_exists('PTP_Comms_Hub_Notifications')) {
            return false;
        }

        return PTP_Comms_Hub_Notifications::notify_admins(array(
            'contact_id' => $contact_id,
            'notification_type' => 'system',
            'title' => 'Training Update',
            'message' => $message,
            'priority' => $priority,
            'action_url' => $contact_id ? admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact_id) : null
        ));
    }

    /**
     * Get parent contact from contact ID or user ID
     */
    private function get_parent_contact($parent_id) {
        global $wpdb;

        // Try contact ID first
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $parent_id
        ));

        // If not found, try user ID
        if (!$contact) {
            $contact = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE user_id = %d",
                $parent_id
            ));
        }

        return $contact;
    }

    /**
     * Get session details
     */
    private function get_session_details($session_id) {
        // This would integrate with the training plugin's session table
        // For now, return mock structure that should be adapted
        global $wpdb;

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_training_sessions WHERE id = %d",
            $session_id
        ));

        if ($session) {
            return array(
                'id' => $session->id,
                'parent_id' => $session->parent_id,
                'trainer_id' => $session->trainer_id,
                'date' => $session->session_date,
                'time' => $session->session_time,
                'location' => $session->location,
                'status' => $session->status
            );
        }

        return null;
    }

    /**
     * Register REST API endpoints for trainer communication
     */
    public function register_api_endpoints() {
        // Parent sends message to trainer
        register_rest_route('ptp-comms/v1', '/training/message-trainer', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_message_trainer'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        // Trainer sends message to parent
        register_rest_route('ptp-comms/v1', '/training/message-parent', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_message_parent'),
            'permission_callback' => function() {
                return current_user_can('edit_posts'); // Trainers should have this cap
            }
        ));

        // Get training conversation history
        register_rest_route('ptp-comms/v1', '/training/conversation/(?P<contact_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_conversation'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));
    }

    /**
     * API: Parent messages trainer
     */
    public function api_message_trainer($request) {
        $user_id = get_current_user_id();
        $message = sanitize_textarea_field($request->get_param('message'));
        $trainer_id = intval($request->get_param('trainer_id'));

        if (empty($message)) {
            return new WP_REST_Response(array('error' => 'Message is required'), 400);
        }

        $trainer = get_user_by('id', $trainer_id);
        if (!$trainer) {
            return new WP_REST_Response(array('error' => 'Trainer not found'), 404);
        }

        $user = wp_get_current_user();
        $trainer_message = "Message from {$user->display_name}:\n\n{$message}";

        $result = $this->send_to_trainer($trainer_id, $trainer_message, 'parent_message');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Message sent to trainer'
        ));
    }

    /**
     * API: Trainer messages parent
     */
    public function api_message_parent($request) {
        $contact_id = intval($request->get_param('contact_id'));
        $message = sanitize_textarea_field($request->get_param('message'));
        $channel = sanitize_text_field($request->get_param('channel')) ?: 'sms';

        if (empty($message)) {
            return new WP_REST_Response(array('error' => 'Message is required'), 400);
        }

        $contact = $this->get_parent_contact($contact_id);
        if (!$contact) {
            return new WP_REST_Response(array('error' => 'Contact not found'), 404);
        }

        $trainer = wp_get_current_user();
        $parent_message = "Message from your trainer {$trainer->display_name}:\n\n{$message}";

        $result = $this->send_to_parent($contact, $parent_message, 'trainer_message');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Message sent to parent'
        ));
    }

    /**
     * API: Get conversation history
     */
    public function api_get_conversation($request) {
        global $wpdb;

        $contact_id = intval($request->get_param('contact_id'));

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, c.parent_first_name, c.parent_last_name
             FROM {$wpdb->prefix}ptp_messages m
             JOIN {$wpdb->prefix}ptp_conversations conv ON m.conversation_id = conv.id
             JOIN {$wpdb->prefix}ptp_contacts c ON conv.contact_id = c.id
             WHERE conv.contact_id = %d
             ORDER BY m.created_at DESC
             LIMIT 50",
            $contact_id
        ));

        return new WP_REST_Response(array(
            'success' => true,
            'messages' => $messages
        ));
    }

    /**
     * Render message trainer form shortcode
     */
    public function render_message_trainer_form($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to message your trainer.</p>';
        }

        $atts = shortcode_atts(array(
            'trainer_id' => 0
        ), $atts);

        ob_start();
        ?>
        <div class="ptp-message-trainer-form">
            <h3>Message Your Trainer</h3>
            <form id="ptp-trainer-message-form">
                <?php wp_nonce_field('ptp_trainer_message', 'ptp_nonce'); ?>
                <input type="hidden" name="trainer_id" value="<?php echo esc_attr($atts['trainer_id']); ?>">

                <div class="form-group">
                    <label for="trainer_message">Your Message</label>
                    <textarea name="message" id="trainer_message" rows="4" required placeholder="Type your message here..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
            <div id="ptp-message-status"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#ptp-trainer-message-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $status = $('#ptp-message-status');

                $.ajax({
                    url: '<?php echo esc_url(rest_url('ptp-comms/v1/training/message-trainer')); ?>',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                    },
                    data: {
                        trainer_id: $form.find('[name="trainer_id"]').val(),
                        message: $form.find('[name="message"]').val()
                    },
                    success: function(response) {
                        $status.html('<div class="alert alert-success">Message sent successfully!</div>');
                        $form.find('[name="message"]').val('');
                    },
                    error: function() {
                        $status.html('<div class="alert alert-danger">Failed to send message. Please try again.</div>');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render training inbox shortcode
     */
    public function render_training_inbox($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your messages.</p>';
        }

        global $wpdb;
        $user_id = get_current_user_id();

        // Get contact associated with user
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE user_id = %d",
            $user_id
        ));

        if (!$contact) {
            return '<p>No messaging history found.</p>';
        }

        // Get messages
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.* FROM {$wpdb->prefix}ptp_messages m
             JOIN {$wpdb->prefix}ptp_conversations c ON m.conversation_id = c.id
             WHERE c.contact_id = %d
             ORDER BY m.created_at DESC
             LIMIT 20",
            $contact->id
        ));

        ob_start();
        ?>
        <div class="ptp-training-inbox">
            <h3>Your Messages</h3>
            <?php if (empty($messages)): ?>
                <p>No messages yet.</p>
            <?php else: ?>
                <div class="message-list">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message <?php echo $msg->direction === 'inbound' ? 'from-you' : 'from-trainer'; ?>">
                            <div class="message-content"><?php echo esc_html($msg->message_body); ?></div>
                            <div class="message-meta">
                                <span class="message-time"><?php echo date('M j, g:i A', strtotime($msg->created_at)); ?></span>
                                <span class="message-channel"><?php echo esc_html(ucfirst($msg->message_type)); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .ptp-training-inbox .message-list { max-height: 400px; overflow-y: auto; }
        .ptp-training-inbox .message { padding: 12px; margin: 8px 0; border-radius: 8px; max-width: 80%; }
        .ptp-training-inbox .from-trainer { background: #e3f2fd; margin-right: auto; }
        .ptp-training-inbox .from-you { background: #f5f5f5; margin-left: auto; text-align: right; }
        .ptp-training-inbox .message-meta { font-size: 11px; color: #666; margin-top: 4px; }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Sync trainer assignment to contact
     */
    public function sync_trainer_to_contact($contact_id, $trainer_id) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ptp_contacts',
            array('assigned_trainer' => $trainer_id),
            array('id' => $contact_id)
        );
    }

    /**
     * Create automatic reminders for training sessions
     */
    public function create_session_reminders($session_id, $session_data) {
        if (!class_exists('PTP_Comms_Hub_Reminders')) {
            return;
        }

        $session_date = strtotime($session_data['date'] . ' ' . $session_data['time']);

        // 24-hour reminder
        $reminder_24h = date('Y-m-d H:i:s', $session_date - (24 * 60 * 60));
        PTP_Comms_Hub_Reminders::create(array(
            'contact_id' => $session_data['parent_id'],
            'title' => '24h Training Session Reminder',
            'description' => 'Send reminder for tomorrow\'s training session',
            'due_date' => $reminder_24h,
            'reminder_type' => 'event_prep',
            'priority' => 'high',
            'meta_data' => array('session_id' => $session_id, 'reminder_type' => '24h')
        ));

        // 1-hour reminder
        $reminder_1h = date('Y-m-d H:i:s', $session_date - (60 * 60));
        PTP_Comms_Hub_Reminders::create(array(
            'contact_id' => $session_data['parent_id'],
            'title' => '1h Training Session Reminder',
            'description' => 'Send final reminder for training session',
            'due_date' => $reminder_1h,
            'reminder_type' => 'event_prep',
            'priority' => 'urgent',
            'meta_data' => array('session_id' => $session_id, 'reminder_type' => '1h')
        ));
    }

    /**
     * Handle WooCommerce booking confirmation
     */
    public function handle_booking_confirmed($booking_id) {
        // This integrates with WooCommerce Bookings if used
        if (!function_exists('get_wc_booking')) {
            return;
        }

        $booking = get_wc_booking($booking_id);
        if (!$booking) return;

        $order = $booking->get_order();
        if (!$order) return;

        // Get or create contact from order
        $contact_id = $this->get_or_create_contact_from_order($order);
        if (!$contact_id) return;

        // Trigger our notification
        do_action('ptp_training_session_booked', $booking_id, $contact_id, 0);
    }

    /**
     * Get or create contact from WooCommerce order
     */
    private function get_or_create_contact_from_order($order) {
        global $wpdb;

        $phone = $order->get_billing_phone();
        $email = $order->get_billing_email();

        // Try to find existing contact
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s OR parent_email = %s LIMIT 1",
            $phone, $email
        ));

        if ($contact) {
            return $contact->id;
        }

        // Create new contact
        $wpdb->insert(
            $wpdb->prefix . 'ptp_contacts',
            array(
                'parent_first_name' => $order->get_billing_first_name(),
                'parent_last_name' => $order->get_billing_last_name(),
                'parent_phone' => $phone,
                'parent_email' => $email,
                'source' => 'woocommerce_booking',
                'opted_in' => 1,
                'created_at' => current_time('mysql')
            )
        );

        return $wpdb->insert_id;
    }
}

// Initialize the integration
add_action('init', function() {
    PTP_Comms_Hub_Private_Training_Integration::get_instance();
});

/**
 * Helper functions for external use
 */

/**
 * Send training notification to parent
 */
function ptp_comms_training_notify_parent($contact_id, $message, $type = 'general') {
    $integration = PTP_Comms_Hub_Private_Training_Integration::get_instance();
    $contact = $integration->get_parent_contact($contact_id);
    if ($contact) {
        return $integration->send_to_parent($contact, $message, $type);
    }
    return false;
}

/**
 * Send training notification to trainer
 */
function ptp_comms_training_notify_trainer($trainer_id, $message, $type = 'general') {
    $integration = PTP_Comms_Hub_Private_Training_Integration::get_instance();
    return $integration->send_to_trainer($trainer_id, $message, $type);
}
