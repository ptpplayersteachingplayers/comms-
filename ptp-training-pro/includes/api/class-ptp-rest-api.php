<?php
/**
 * REST API Endpoints - v3.0
 * Full API for marketplace, booking, reviews, calendar
 */

if (!defined('ABSPATH')) exit;

class PTP_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        $namespace = 'ptp-training/v1';
        
        // Public - Trainers
        register_rest_route($namespace, '/trainers', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_trainers'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route($namespace, '/trainers/(?P<slug>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_trainer'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route($namespace, '/trainers/(?P<id>\d+)/reviews', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_trainer_reviews'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route($namespace, '/trainers/(?P<id>\d+)/availability', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_trainer_availability'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route($namespace, '/trainers/(?P<id>\d+)/slots', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_available_slots'),
            'permission_callback' => '__return_true'
        ));
        
        // Public - Filters
        register_rest_route($namespace, '/filters', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_filters'),
            'permission_callback' => '__return_true'
        ));
        
        // Public - Applications
        register_rest_route($namespace, '/applications', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_application'),
            'permission_callback' => '__return_true'
        ));
        
        // Authenticated - Booking
        register_rest_route($namespace, '/book', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_booking'),
            'permission_callback' => array($this, 'check_logged_in')
        ));
        
        register_rest_route($namespace, '/sessions/(?P<id>\d+)/schedule', array(
            'methods' => 'POST',
            'callback' => array($this, 'schedule_session'),
            'permission_callback' => array($this, 'check_logged_in')
        ));
        
        register_rest_route($namespace, '/sessions/(?P<id>\d+)/cancel', array(
            'methods' => 'POST',
            'callback' => array($this, 'cancel_session'),
            'permission_callback' => array($this, 'check_logged_in')
        ));
        
        // Authenticated - Reviews
        register_rest_route($namespace, '/reviews', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_review'),
            'permission_callback' => array($this, 'check_logged_in')
        ));
        
        // Authenticated - My Training
        register_rest_route($namespace, '/my/packs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_my_packs'),
            'permission_callback' => array($this, 'check_logged_in')
        ));
        
        register_rest_route($namespace, '/my/sessions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_my_sessions'),
            'permission_callback' => array($this, 'check_logged_in')
        ));
        
        // Trainer Dashboard
        register_rest_route($namespace, '/trainer/profile', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'trainer_profile'),
            'permission_callback' => array($this, 'check_is_trainer')
        ));
        
        register_rest_route($namespace, '/trainer/availability', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_availability'),
            'permission_callback' => array($this, 'check_is_trainer')
        ));
        
        register_rest_route($namespace, '/trainer/sessions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_trainer_sessions'),
            'permission_callback' => array($this, 'check_is_trainer')
        ));
        
        register_rest_route($namespace, '/trainer/sessions/(?P<id>\d+)/complete', array(
            'methods' => 'POST',
            'callback' => array($this, 'complete_session'),
            'permission_callback' => array($this, 'check_is_trainer')
        ));
        
        register_rest_route($namespace, '/trainer/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_trainer_stats'),
            'permission_callback' => array($this, 'check_is_trainer')
        ));
        
        register_rest_route($namespace, '/trainer/stripe-connect', array(
            'methods' => 'POST',
            'callback' => array($this, 'stripe_connect'),
            'permission_callback' => array($this, 'check_is_trainer')
        ));
        
        register_rest_route($namespace, '/trainer/calendar-connect', array(
            'methods' => 'POST',
            'callback' => array($this, 'calendar_connect'),
            'permission_callback' => array($this, 'check_is_trainer')
        ));
        
        register_rest_route($namespace, '/trainer/video-upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_intro_video'),
            'permission_callback' => array($this, 'check_is_trainer')
        ));
        
        // Messages
        register_rest_route($namespace, '/messages/(?P<conversation_id>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_messages'),
            'permission_callback' => array($this, 'check_logged_in')
        ));
        
        register_rest_route($namespace, '/messages', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_message'),
            'permission_callback' => array($this, 'check_logged_in')
        ));
        
        // Webhooks
        register_rest_route($namespace, '/stripe-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'stripe_webhook'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route($namespace, '/twilio-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'twilio_webhook'),
            'permission_callback' => '__return_true'
        ));
    }
    
    // Permission Callbacks
    public function check_logged_in() {
        return is_user_logged_in();
    }
    
    public function check_is_trainer() {
        if (!is_user_logged_in()) return false;
        $trainer = PTP_Database::get_trainer_by_user(get_current_user_id());
        return $trainer && $trainer->status === 'approved';
    }
    
    // Public Endpoints
    public function get_trainers($request) {
        $args = array(
            'status' => 'approved',
            'state' => $request->get_param('state'),
            'specialty' => $request->get_param('specialty'),
            'min_rating' => $request->get_param('min_rating'),
            'lat' => $request->get_param('lat'),
            'lng' => $request->get_param('lng'),
            'radius' => $request->get_param('radius') ?: 50,
            'sort' => $request->get_param('sort') ?: 'rating',
            'limit' => min($request->get_param('limit') ?: 24, 100),
            'offset' => $request->get_param('offset') ?: 0
        );
        
        $trainers = PTP_Database::get_trainers($args);
        $total = PTP_Database::count_trainers($args);
        
        $formatted = array();
        foreach ($trainers as $trainer) {
            $formatted[] = $this->format_trainer($trainer);
        }
        
        return rest_ensure_response(array(
            'trainers' => $formatted,
            'total' => $total,
            'has_more' => ($args['offset'] + count($trainers)) < $total
        ));
    }
    
    public function get_trainer($request) {
        $slug = $request->get_param('slug');
        $trainer = PTP_Database::get_trainer_by_slug($slug);
        
        if (!$trainer) {
            return new WP_Error('not_found', 'Trainer not found', array('status' => 404));
        }
        
        return rest_ensure_response($this->format_trainer($trainer, true));
    }
    
    private function format_trainer($trainer, $full = false) {
        $data = array(
            'id' => $trainer->id,
            'slug' => $trainer->slug,
            'display_name' => $trainer->display_name,
            'tagline' => $trainer->tagline,
            'profile_photo' => $trainer->profile_photo,
            'avg_rating' => floatval($trainer->avg_rating),
            'total_reviews' => intval($trainer->total_reviews),
            'total_sessions' => intval($trainer->total_sessions),
            'hourly_rate' => floatval($trainer->hourly_rate),
            'location' => array(
                'city' => $trainer->primary_location_city,
                'state' => $trainer->primary_location_state,
                'lat' => floatval($trainer->primary_location_lat),
                'lng' => floatval($trainer->primary_location_lng)
            ),
            'specialties' => $trainer->specialties ? json_decode($trainer->specialties, true) : array(),
            'is_featured' => (bool)$trainer->is_featured
        );
        
        if (isset($trainer->distance)) {
            $data['distance'] = round($trainer->distance, 1);
        }
        
        if ($full) {
            $data['bio'] = $trainer->bio;
            $data['intro_video_url'] = $trainer->intro_video_url;
            $data['intro_video_thumbnail'] = $trainer->intro_video_thumbnail;
            $data['experience_years'] = intval($trainer->experience_years);
            $data['credentials'] = $trainer->credentials;
            $data['certifications'] = $trainer->certifications ? json_decode($trainer->certifications, true) : array();
            $data['age_groups'] = $trainer->age_groups ? json_decode($trainer->age_groups, true) : array();
            $data['service_radius'] = intval($trainer->service_radius_miles);
            $data['response_time'] = intval($trainer->response_time_hours);
            
            // Pricing
            $data['pricing'] = array(
                'single' => floatval($trainer->hourly_rate),
                'pack_4' => array(
                    'price' => floatval($trainer->pack_4_rate) ?: floatval($trainer->hourly_rate) * 4 * (1 - $trainer->pack_4_discount / 100),
                    'discount' => intval($trainer->pack_4_discount),
                    'per_session' => (floatval($trainer->pack_4_rate) ?: floatval($trainer->hourly_rate) * 4 * (1 - $trainer->pack_4_discount / 100)) / 4
                ),
                'pack_8' => array(
                    'price' => floatval($trainer->pack_8_rate) ?: floatval($trainer->hourly_rate) * 8 * (1 - $trainer->pack_8_discount / 100),
                    'discount' => intval($trainer->pack_8_discount),
                    'per_session' => (floatval($trainer->pack_8_rate) ?: floatval($trainer->hourly_rate) * 8 * (1 - $trainer->pack_8_discount / 100)) / 8
                )
            );
            
            // Locations
            global $wpdb;
            $data['locations'] = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_trainer_locations WHERE trainer_id = %d",
                $trainer->id
            ));
            
            // Reviews preview
            $data['reviews'] = PTP_Database::get_trainer_reviews($trainer->id, 5);
        }
        
        return $data;
    }
    
    public function get_trainer_reviews($request) {
        $trainer_id = $request->get_param('id');
        $limit = $request->get_param('limit') ?: 10;
        $offset = $request->get_param('offset') ?: 0;
        
        global $wpdb;
        $reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_reviews
             WHERE trainer_id = %d AND status = 'published'
             ORDER BY is_featured DESC, created_at DESC
             LIMIT %d OFFSET %d",
            $trainer_id, $limit, $offset
        ));
        
        return rest_ensure_response($reviews);
    }
    
    public function get_trainer_availability($request) {
        $trainer_id = $request->get_param('id');
        $availability = PTP_Database::get_trainer_availability($trainer_id);
        
        $formatted = array();
        foreach ($availability as $slot) {
            $formatted[$slot->day_of_week][] = array(
                'start' => $slot->start_time,
                'end' => $slot->end_time
            );
        }
        
        return rest_ensure_response($formatted);
    }
    
    public function get_available_slots($request) {
        $trainer_id = $request->get_param('id');
        $date = $request->get_param('date');
        
        if (!$date) {
            return new WP_Error('missing_date', 'Date is required', array('status' => 400));
        }
        
        $slots = PTP_Database::get_available_slots($trainer_id, $date);
        
        return rest_ensure_response($slots);
    }
    
    public function get_filters($request) {
        global $wpdb;
        
        $states = $wpdb->get_col(
            "SELECT DISTINCT primary_location_state FROM {$wpdb->prefix}ptp_trainers
             WHERE status = 'approved' AND primary_location_state != ''
             ORDER BY primary_location_state"
        );
        
        $specialties = array(
            'Ball Control',
            'Finishing',
            '1v1 Moves',
            'Defending',
            'Goalkeeping',
            'Speed & Agility',
            'Game IQ',
            'Passing & Vision',
            'First Touch',
            'Weak Foot Development'
        );
        
        $age_groups = array(
            '6-8' => 'Ages 6-8',
            '9-11' => 'Ages 9-11',
            '12-14' => 'Ages 12-14',
            '15+' => 'Ages 15+'
        );
        
        return rest_ensure_response(array(
            'states' => $states,
            'specialties' => $specialties,
            'age_groups' => $age_groups
        ));
    }
    
    // Applications
    public function submit_application($request) {
        global $wpdb;
        
        $data = array(
            'email' => sanitize_email($request->get_param('email')),
            'first_name' => sanitize_text_field($request->get_param('first_name')),
            'last_name' => sanitize_text_field($request->get_param('last_name')),
            'phone' => sanitize_text_field($request->get_param('phone')),
            // Support both `experience_summary` and simpler `summary` from the frontend form
            'experience_summary' => sanitize_textarea_field(
                $request->get_param('experience_summary') ?: $request->get_param('summary')
            ),
            // Support both `playing_background` and `playing_experience`
            'playing_background' => sanitize_textarea_field(
                $request->get_param('playing_background') ?: $request->get_param('playing_experience')
            ),
            'coaching_experience' => sanitize_textarea_field($request->get_param('coaching_experience')),
            'certifications' => sanitize_textarea_field($request->get_param('certifications')),
            'location_city' => sanitize_text_field($request->get_param('city')),
            'location_state' => sanitize_text_field($request->get_param('state')),
            'location_zip' => sanitize_text_field($request->get_param('zip')),
            // Accept either `intro_video_url` or `intro_video` from the form
            'intro_video_url' => esc_url_raw(
                $request->get_param('intro_video_url') ?: $request->get_param('intro_video')
            ),
            'instagram_handle' => sanitize_text_field($request->get_param('instagram')),
            // Accept either `referral_source` or `referral`
            'referral_source' => sanitize_text_field(
                $request->get_param('referral_source') ?: $request->get_param('referral')
            ),
            'why_join' => sanitize_textarea_field($request->get_param('why_join')),
            'availability_notes' => sanitize_textarea_field($request->get_param('availability'))
        );
        
        // Validation
        if (empty($data['email']) || empty($data['first_name']) || empty($data['last_name'])) {
            return new WP_Error('missing_fields', 'Required fields are missing', array('status' => 400));
        }
        
        // Check for existing application
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_applications WHERE email = %s AND status = 'pending'",
            $data['email']
        ));
        
        if ($existing) {
            return new WP_Error('duplicate', 'An application with this email is already pending', array('status' => 400));
        }
        
        $wpdb->insert("{$wpdb->prefix}ptp_applications", $data);
        $app_id = $wpdb->insert_id;
        
        // Send notification emails
        $this->send_application_emails($app_id, $data);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Application submitted successfully'
        ));
    }
    
    private function send_application_emails($app_id, $data) {
        // Admin notification
        $admin_email = get_option('admin_email');
        $subject = 'New Trainer Application: ' . $data['first_name'] . ' ' . $data['last_name'];
        $message = "A new trainer application has been submitted.\n\n";
        $message .= "Name: {$data['first_name']} {$data['last_name']}\n";
        $message .= "Email: {$data['email']}\n";
        $message .= "Phone: {$data['phone']}\n";
        $message .= "Location: {$data['location_city']}, {$data['location_state']}\n\n";
        $message .= "Review in admin: " . admin_url('admin.php?page=ptp-training-applications');
        
        wp_mail($admin_email, $subject, $message);
        
        // Applicant confirmation
        $subject = 'PTP Training Application Received';
        $message = "Hi {$data['first_name']},\n\n";
        $message .= "Thank you for applying to become a PTP trainer! We've received your application and will review it within 2-3 business days.\n\n";
        $message .= "What happens next:\n";
        $message .= "1. Our team reviews your application\n";
        $message .= "2. If approved, you'll receive onboarding instructions\n";
        $message .= "3. Set up your profile and start accepting bookings\n\n";
        $message .= "Questions? Reply to this email.\n\n";
        $message .= "Best,\nThe PTP Team";
        
        wp_mail($data['email'], $subject, $message);
    }
    
    // Booking
    public function create_booking($request) {
        $user_id = get_current_user_id();
        $trainer_id = $request->get_param('trainer_id');
        $pack_type = $request->get_param('pack_type');
        $athlete_name = sanitize_text_field($request->get_param('athlete_name'));
        $athlete_age = intval($request->get_param('athlete_age'));
        $athlete_skill = sanitize_text_field($request->get_param('athlete_skill'));
        $athlete_goals = sanitize_textarea_field($request->get_param('athlete_goals'));
        
        $trainer = PTP_Database::get_trainer($trainer_id);
        if (!$trainer) {
            return new WP_Error('invalid_trainer', 'Trainer not found', array('status' => 404));
        }
        
        // Calculate pricing
        switch ($pack_type) {
            case 'pack_4':
                $sessions = 4;
                $price = floatval($trainer->pack_4_rate) ?: floatval($trainer->hourly_rate) * 4 * (1 - $trainer->pack_4_discount / 100);
                break;
            case 'pack_8':
                $sessions = 8;
                $price = floatval($trainer->pack_8_rate) ?: floatval($trainer->hourly_rate) * 8 * (1 - $trainer->pack_8_discount / 100);
                break;
            default:
                $sessions = 1;
                $price = floatval($trainer->hourly_rate);
        }
        
        // Create Stripe checkout session
        if (class_exists('PTP_Stripe')) {
            $checkout = PTP_Stripe::create_checkout_session(array(
                'trainer_id' => $trainer_id,
                'customer_id' => $user_id,
                'pack_type' => $pack_type,
                'sessions' => $sessions,
                'price' => $price,
                'athlete_name' => $athlete_name,
                'athlete_age' => $athlete_age,
                'athlete_skill' => $athlete_skill,
                'athlete_goals' => $athlete_goals
            ));
            
            if (is_wp_error($checkout)) {
                return $checkout;
            }
            
            return rest_ensure_response(array(
                'checkout_url' => $checkout['url']
            ));
        }
        
        return new WP_Error('stripe_not_configured', 'Payment system not configured', array('status' => 500));
    }
    
    public function schedule_session($request) {
        $session_id = $request->get_param('id');
        $date = $request->get_param('date');
        $time = $request->get_param('time');
        $location_id = $request->get_param('location_id');
        
        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_sessions WHERE id = %d",
            $session_id
        ));
        
        if (!$session) {
            return new WP_Error('not_found', 'Session not found', array('status' => 404));
        }
        
        // Verify user owns this session or is the trainer
        $user_id = get_current_user_id();
        if ($session->customer_id != $user_id && $session->trainer_id != PTP_Database::get_trainer_by_user($user_id)->id) {
            return new WP_Error('forbidden', 'You cannot modify this session', array('status' => 403));
        }
        
        // Check slot availability
        $slots = PTP_Database::get_available_slots($session->trainer_id, $date);
        $slot_available = false;
        foreach ($slots as $slot) {
            if ($slot['start'] === $time) {
                $slot_available = true;
                break;
            }
        }
        
        if (!$slot_available) {
            return new WP_Error('slot_unavailable', 'This time slot is no longer available', array('status' => 400));
        }
        
        // Update session
        $wpdb->update(
            "{$wpdb->prefix}ptp_sessions",
            array(
                'session_date' => $date,
                'start_time' => $time,
                'end_time' => date('H:i:s', strtotime($time) + 3600),
                'location_id' => $location_id,
                'status' => 'scheduled'
            ),
            array('id' => $session_id),
            array('%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        // Sync to Google Calendar
        if (class_exists('PTP_Google_Calendar')) {
            PTP_Google_Calendar::sync_session($session_id);
        }
        
        // Send notifications
        $this->send_session_scheduled_emails($session_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Session scheduled successfully'
        ));
    }
    
    public function cancel_session($request) {
        $session_id = $request->get_param('id');
        $reason = sanitize_textarea_field($request->get_param('reason'));
        
        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_sessions WHERE id = %d",
            $session_id
        ));
        
        if (!$session) {
            return new WP_Error('not_found', 'Session not found', array('status' => 404));
        }
        
        // Check cancellation policy (24 hours before)
        $session_datetime = strtotime($session->session_date . ' ' . $session->start_time);
        if ($session_datetime - time() < 86400) {
            return new WP_Error('too_late', 'Sessions must be cancelled at least 24 hours in advance', array('status' => 400));
        }
        
        $wpdb->update(
            "{$wpdb->prefix}ptp_sessions",
            array(
                'status' => 'cancelled',
                'cancelled_at' => current_time('mysql'),
                'cancellation_reason' => $reason
            ),
            array('id' => $session_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Restore session to pack
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ptp_lesson_packs SET sessions_used = sessions_used - 1, sessions_remaining = sessions_remaining + 1 WHERE id = %d",
            $session->pack_id
        ));
        
        // Remove from Google Calendar
        if (class_exists('PTP_Google_Calendar') && $session->google_event_id) {
            PTP_Google_Calendar::delete_event($session->trainer_id, $session->google_event_id);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Session cancelled'
        ));
    }
    
    // Reviews
    public function submit_review($request) {
        $user_id = get_current_user_id();
        $trainer_id = $request->get_param('trainer_id');
        $pack_id = $request->get_param('pack_id');
        $rating = intval($request->get_param('rating'));
        $review_text = sanitize_textarea_field($request->get_param('review'));
        $experience = sanitize_text_field($request->get_param('experience'));
        $skills = $request->get_param('skills_improved');
        
        if ($rating < 1 || $rating > 5) {
            return new WP_Error('invalid_rating', 'Rating must be between 1 and 5', array('status' => 400));
        }
        
        // Verify user has completed sessions with this trainer
        global $wpdb;
        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions
             WHERE customer_id = %d AND trainer_id = %d AND status = 'completed'",
            $user_id, $trainer_id
        ));
        
        if (!$completed) {
            return new WP_Error('no_sessions', 'You must complete at least one session before leaving a review', array('status' => 400));
        }
        
        $user = get_user_by('ID', $user_id);
        
        $review_id = PTP_Database::add_review(array(
            'trainer_id' => $trainer_id,
            'customer_id' => $user_id,
            'pack_id' => $pack_id,
            'rating' => $rating,
            'review_text' => $review_text,
            'reviewer_name' => $user->display_name,
            'reviewer_experience' => $experience,
            'skills_improved' => is_array($skills) ? json_encode($skills) : $skills
        ));
        
        return rest_ensure_response(array(
            'success' => true,
            'review_id' => $review_id
        ));
    }
    
    // Customer Endpoints
    public function get_my_packs($request) {
        $packs = PTP_Database::get_customer_packs(get_current_user_id());
        return rest_ensure_response($packs);
    }
    
    public function get_my_sessions($request) {
        $sessions = PTP_Database::get_customer_sessions(get_current_user_id());
        return rest_ensure_response($sessions);
    }
    
    // Trainer Dashboard Endpoints
    public function trainer_profile($request) {
        $trainer = PTP_Database::get_trainer_by_user(get_current_user_id());
        
        if ($request->get_method() === 'GET') {
            return rest_ensure_response($this->format_trainer($trainer, true));
        }
        
        // POST - Update profile
        global $wpdb;
        
        $updates = array();
        $fields = array('bio', 'tagline', 'hourly_rate', 'pack_4_discount', 'pack_8_discount', 'service_radius_miles');
        
        foreach ($fields as $field) {
            if ($request->has_param($field)) {
                $updates[$field] = sanitize_text_field($request->get_param($field));
            }
        }
        
        if ($request->has_param('specialties')) {
            $updates['specialties'] = json_encode($request->get_param('specialties'));
        }
        
        if ($request->has_param('age_groups')) {
            $updates['age_groups'] = json_encode($request->get_param('age_groups'));
        }
        
        if (!empty($updates)) {
            $wpdb->update(
                "{$wpdb->prefix}ptp_trainers",
                $updates,
                array('id' => $trainer->id)
            );
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    public function update_availability($request) {
        $trainer = PTP_Database::get_trainer_by_user(get_current_user_id());
        $availability = $request->get_param('availability');
        
        global $wpdb;
        
        // Clear existing availability
        $wpdb->delete("{$wpdb->prefix}ptp_availability", array('trainer_id' => $trainer->id));
        
        // Insert new availability
        foreach ($availability as $day => $slots) {
            foreach ($slots as $slot) {
                $wpdb->insert(
                    "{$wpdb->prefix}ptp_availability",
                    array(
                        'trainer_id' => $trainer->id,
                        'day_of_week' => $day,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'is_active' => 1
                    ),
                    array('%d', '%d', '%s', '%s', '%d')
                );
            }
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    public function get_trainer_sessions($request) {
        $trainer = PTP_Database::get_trainer_by_user(get_current_user_id());
        $status = $request->get_param('status');
        $from_date = $request->get_param('from') ?: date('Y-m-d');
        
        $sessions = PTP_Database::get_trainer_sessions($trainer->id, $status, $from_date);
        
        return rest_ensure_response($sessions);
    }
    
    public function complete_session($request) {
        $session_id = $request->get_param('id');
        $trainer = PTP_Database::get_trainer_by_user(get_current_user_id());
        
        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_sessions WHERE id = %d AND trainer_id = %d",
            $session_id, $trainer->id
        ));
        
        if (!$session) {
            return new WP_Error('not_found', 'Session not found', array('status' => 404));
        }
        
        $notes = sanitize_textarea_field($request->get_param('notes'));
        $homework = sanitize_textarea_field($request->get_param('homework'));
        $skills = $request->get_param('skills_worked');
        
        $wpdb->update(
            "{$wpdb->prefix}ptp_sessions",
            array(
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'trainer_notes' => $notes,
                'homework' => $homework,
                'skills_worked' => is_array($skills) ? json_encode($skills) : $skills
            ),
            array('id' => $session_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        // Update trainer stats
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ptp_trainers SET total_sessions = total_sessions + 1 WHERE id = %d",
            $trainer->id
        ));
        
        // Create payout record
        $pack = PTP_Database::get_pack($session->pack_id);
        $per_session = $pack->price_per_session;
        $platform_fee = $per_session * 0.20; // 20% platform fee
        $trainer_payout = $per_session - $platform_fee;
        
        $wpdb->insert(
            "{$wpdb->prefix}ptp_payouts",
            array(
                'trainer_id' => $trainer->id,
                'session_id' => $session_id,
                'pack_id' => $session->pack_id,
                'gross_amount' => $per_session,
                'platform_fee' => $platform_fee,
                'trainer_payout' => $trainer_payout,
                'status' => 'pending'
            ),
            array('%d', '%d', '%d', '%f', '%f', '%f', '%s')
        );
        
        // Send session summary to customer
        $this->send_session_summary_email($session_id, $notes, $homework);
        
        return rest_ensure_response(array('success' => true));
    }
    
    public function get_trainer_stats($request) {
        $trainer = PTP_Database::get_trainer_by_user(get_current_user_id());
        $stats = PTP_Database::get_trainer_stats($trainer->id);
        
        return rest_ensure_response($stats);
    }
    
    public function stripe_connect($request) {
        $trainer = PTP_Database::get_trainer_by_user(get_current_user_id());
        
        if (class_exists('PTP_Stripe')) {
            $url = PTP_Stripe::create_connect_account_link($trainer->id);
            return rest_ensure_response(array('url' => $url));
        }
        
        return new WP_Error('stripe_not_configured', 'Stripe not configured', array('status' => 500));
    }
    
    public function calendar_connect($request) {
        $trainer = PTP_Database::get_trainer_by_user(get_current_user_id());
        
        if (class_exists('PTP_Google_Calendar')) {
            $url = PTP_Google_Calendar::get_auth_url($trainer->id);
            return rest_ensure_response(array('url' => $url));
        }
        
        return new WP_Error('calendar_not_configured', 'Google Calendar not configured', array('status' => 500));
    }
    
    public function upload_intro_video($request) {
        $trainer = PTP_Database::get_trainer_by_user(get_current_user_id());
        
        if (class_exists('PTP_Video')) {
            $result = PTP_Video::handle_upload($trainer->id, $_FILES['video']);
            
            if (is_wp_error($result)) {
                return $result;
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'video_url' => $result['url'],
                'thumbnail_url' => $result['thumbnail']
            ));
        }
        
        return new WP_Error('video_not_configured', 'Video upload not configured', array('status' => 500));
    }
    
    // Helper methods
    private function send_session_scheduled_emails($session_id) {
        // Implementation for email notifications
    }
    
    private function send_session_summary_email($session_id, $notes, $homework) {
        // Implementation for session summary email
    }
    
    // Stripe Webhook
    public function stripe_webhook($request) {
        if (class_exists('PTP_Stripe')) {
            return PTP_Stripe::handle_webhook($request);
        }
        return new WP_Error('stripe_not_configured', 'Stripe not configured', array('status' => 500));
    }
    
    // Twilio Webhook (inbound SMS)
    public function twilio_webhook($request) {
        if (class_exists('PTP_Twilio')) {
            $params = $request->get_params();
            PTP_Twilio::handle_inbound_sms($params);
            
            // Return TwiML response
            header('Content-Type: text/xml');
            echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
            exit;
        }
        return new WP_Error('twilio_not_configured', 'Twilio not configured', array('status' => 500));
    }
}

new PTP_REST_API();
