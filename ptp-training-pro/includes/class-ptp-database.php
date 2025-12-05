<?php
/**
 * Database Schema - v3.0
 * Enhanced with video profiles, lesson packs, reviews, availability
 */

if (!defined('ABSPATH')) exit;

class PTP_Database {
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Trainer Profiles
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_trainers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            status varchar(20) DEFAULT 'pending',
            display_name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            bio text,
            tagline varchar(255),
            profile_photo varchar(500),
            intro_video_url varchar(500),
            intro_video_thumbnail varchar(500),
            experience_years int(11) DEFAULT 0,
            credentials text,
            specialties text,
            age_groups text,
            certifications text,
            primary_location_lat decimal(10,8),
            primary_location_lng decimal(11,8),
            primary_location_address varchar(500),
            primary_location_city varchar(100),
            primary_location_state varchar(50),
            primary_location_zip varchar(20),
            service_radius_miles int(11) DEFAULT 15,
            hourly_rate decimal(10,2) DEFAULT 0,
            pack_4_rate decimal(10,2) DEFAULT 0,
            pack_8_rate decimal(10,2) DEFAULT 0,
            pack_4_discount int(11) DEFAULT 10,
            pack_8_discount int(11) DEFAULT 20,
            stripe_account_id varchar(255),
            stripe_onboarding_complete tinyint(1) DEFAULT 0,
            google_calendar_token text,
            google_calendar_id varchar(255),
            avg_rating decimal(3,2) DEFAULT 0,
            total_reviews int(11) DEFAULT 0,
            total_sessions int(11) DEFAULT 0,
            response_time_hours int(11) DEFAULT 24,
            is_featured tinyint(1) DEFAULT 0,
            availability_json text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY primary_location_state (primary_location_state),
            KEY avg_rating (avg_rating),
            KEY is_featured (is_featured)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Trainer Locations (multiple training spots)
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_trainer_locations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            trainer_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            address varchar(500),
            city varchar(100),
            state varchar(50),
            zip varchar(20),
            lat decimal(10,8),
            lng decimal(11,8),
            location_type varchar(50) DEFAULT 'field',
            notes text,
            is_primary tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trainer_id (trainer_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Trainer Availability
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_availability (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            trainer_id bigint(20) UNSIGNED NOT NULL,
            day_of_week tinyint(1) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY trainer_id (trainer_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Availability Exceptions (blocked dates, holidays)
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_availability_exceptions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            trainer_id bigint(20) UNSIGNED NOT NULL,
            exception_date date NOT NULL,
            is_available tinyint(1) DEFAULT 0,
            start_time time,
            end_time time,
            reason varchar(255),
            PRIMARY KEY (id),
            KEY trainer_id (trainer_id),
            KEY exception_date (exception_date)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Lesson Packs (purchased packages)
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_lesson_packs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            trainer_id bigint(20) UNSIGNED NOT NULL,
            order_id bigint(20) UNSIGNED,
            pack_type varchar(20) NOT NULL,
            total_sessions int(11) NOT NULL,
            sessions_used int(11) DEFAULT 0,
            sessions_remaining int(11) NOT NULL,
            price_paid decimal(10,2) NOT NULL,
            price_per_session decimal(10,2) NOT NULL,
            athlete_name varchar(255),
            athlete_age int(11),
            athlete_skill_level varchar(50),
            athlete_goals text,
            status varchar(20) DEFAULT 'active',
            expires_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY trainer_id (trainer_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Sessions (individual bookings)
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_sessions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            pack_id bigint(20) UNSIGNED NOT NULL,
            trainer_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            location_id bigint(20) UNSIGNED,
            session_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            duration_minutes int(11) DEFAULT 60,
            status varchar(20) DEFAULT 'scheduled',
            trainer_notes text,
            homework text,
            skills_worked text,
            google_event_id varchar(255),
            customer_google_event_id varchar(255),
            reminder_sent tinyint(1) DEFAULT 0,
            reminder_24hr_sent tinyint(1) DEFAULT 0,
            reminder_2hr_sent tinyint(1) DEFAULT 0,
            checkin_sent tinyint(1) DEFAULT 0,
            checkin_response varchar(20),
            review_request_sent tinyint(1) DEFAULT 0,
            completed_at datetime,
            cancelled_at datetime,
            cancellation_reason text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY pack_id (pack_id),
            KEY trainer_id (trainer_id),
            KEY customer_id (customer_id),
            KEY session_date (session_date),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Reviews
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_reviews (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            trainer_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            pack_id bigint(20) UNSIGNED,
            session_id bigint(20) UNSIGNED,
            rating tinyint(1) NOT NULL,
            review_text text,
            reviewer_name varchar(255),
            reviewer_experience varchar(50),
            skills_improved text,
            is_verified tinyint(1) DEFAULT 1,
            is_featured tinyint(1) DEFAULT 0,
            trainer_response text,
            trainer_response_at datetime,
            status varchar(20) DEFAULT 'published',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trainer_id (trainer_id),
            KEY customer_id (customer_id),
            KEY rating (rating),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Payouts Ledger
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_payouts (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            trainer_id bigint(20) UNSIGNED NOT NULL,
            session_id bigint(20) UNSIGNED,
            pack_id bigint(20) UNSIGNED,
            gross_amount decimal(10,2) NOT NULL,
            platform_fee decimal(10,2) NOT NULL,
            trainer_payout decimal(10,2) NOT NULL,
            stripe_transfer_id varchar(255),
            status varchar(20) DEFAULT 'pending',
            paid_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trainer_id (trainer_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Applications
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_applications (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            phone varchar(50),
            role_type varchar(50) DEFAULT 'trainer',
            experience_summary text,
            playing_background text,
            coaching_experience text,
            certifications text,
            location_city varchar(100),
            location_state varchar(50),
            location_zip varchar(20),
            intro_video_url varchar(500),
            resume_url varchar(500),
            instagram_handle varchar(100),
            referral_source varchar(255),
            why_join text,
            availability_notes text,
            status varchar(20) DEFAULT 'pending',
            admin_notes text,
            reviewed_by bigint(20) UNSIGNED,
            reviewed_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Messages (trainer-customer communication)
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id varchar(100) NOT NULL,
            sender_id bigint(20) UNSIGNED NOT NULL,
            receiver_id bigint(20) UNSIGNED NOT NULL,
            pack_id bigint(20) UNSIGNED,
            message_text text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY sender_id (sender_id),
            KEY receiver_id (receiver_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // SMS Log
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_sms_log (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            phone_number varchar(20) NOT NULL,
            message text NOT NULL,
            status varchar(20) DEFAULT 'sent',
            reference varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY phone_number (phone_number),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Trainer Referrals (track camp/clinic referrals)
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_trainer_referrals (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            trainer_id bigint(20) UNSIGNED NOT NULL,
            referral_type varchar(50) NOT NULL,
            referral_code varchar(50),
            product_id bigint(20) UNSIGNED,
            product_name varchar(255),
            clicks int(11) DEFAULT 0,
            conversions int(11) DEFAULT 0,
            revenue decimal(10,2) DEFAULT 0,
            commission_rate decimal(5,2) DEFAULT 10.00,
            commission_earned decimal(10,2) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trainer_id (trainer_id),
            KEY referral_code (referral_code),
            KEY referral_type (referral_type)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    // Trainer Methods
    public static function get_trainer($trainer_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_trainers WHERE id = %d",
            $trainer_id
        ));
    }
    
    public static function get_trainer_by_user($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_trainers WHERE user_id = %d",
            $user_id
        ));
    }
    
    public static function get_trainer_by_slug($slug) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_trainers WHERE slug = %s",
            $slug
        ));
    }
    
    public static function get_trainer_locations($trainer_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_trainer_locations WHERE trainer_id = %d ORDER BY is_primary DESC, name ASC",
            $trainer_id
        ));
    }
    
    public static function get_trainers($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'approved',
            'state' => '',
            'specialty' => '',
            'min_rating' => 0,
            'lat' => null,
            'lng' => null,
            'radius' => 50,
            'sort' => 'rating',
            'limit' => 24,
            'offset' => 0
        );
        $args = wp_parse_args($args, $defaults);
        
        $where = array("status = %s");
        $params = array($args['status']);
        
        if ($args['state']) {
            $where[] = "primary_location_state = %s";
            $params[] = $args['state'];
        }
        
        if ($args['specialty']) {
            $where[] = "specialties LIKE %s";
            $params[] = '%' . $args['specialty'] . '%';
        }
        
        if ($args['min_rating'] > 0) {
            $where[] = "avg_rating >= %f";
            $params[] = $args['min_rating'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Distance calculation if coordinates provided
        $select = "*";
        $order_by = "is_featured DESC, avg_rating DESC, total_sessions DESC";
        
        if ($args['lat'] && $args['lng']) {
            $select = "*, (3959 * acos(cos(radians(%f)) * cos(radians(primary_location_lat)) * cos(radians(primary_location_lng) - radians(%f)) + sin(radians(%f)) * sin(radians(primary_location_lat)))) AS distance";
            array_unshift($params, $args['lat'], $args['lng'], $args['lat']);
            
            $where[] = "primary_location_lat IS NOT NULL";
            $where_clause = implode(' AND ', $where);
            
            // Filter by radius
            $having = "HAVING distance <= " . intval($args['radius']);
            $order_by = "distance ASC, is_featured DESC, avg_rating DESC";
        } else {
            $having = "";
        }
        
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        $sql = "SELECT $select FROM {$wpdb->prefix}ptp_trainers WHERE $where_clause $having ORDER BY $order_by LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    public static function count_trainers($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'approved',
            'state' => '',
            'specialty' => ''
        );
        $args = wp_parse_args($args, $defaults);
        
        $where = array("status = %s");
        $params = array($args['status']);
        
        if ($args['state']) {
            $where[] = "primary_location_state = %s";
            $params[] = $args['state'];
        }
        
        if ($args['specialty']) {
            $where[] = "specialties LIKE %s";
            $params[] = '%' . $args['specialty'] . '%';
        }
        
        $where_clause = implode(' AND ', $where);
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_trainers WHERE $where_clause",
            $params
        ));
    }
    
    // Session Methods
    public static function get_session($session_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, t.display_name as trainer_name, t.profile_photo as trainer_photo
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             WHERE s.id = %d",
            $session_id
        ));
    }
    
    public static function get_trainer_sessions($trainer_id, $status = null, $from_date = null) {
        global $wpdb;
        
        $where = array("trainer_id = %d");
        $params = array($trainer_id);
        
        if ($status) {
            $where[] = "status = %s";
            $params[] = $status;
        }
        
        if ($from_date) {
            $where[] = "session_date >= %s";
            $params[] = $from_date;
        }
        
        $where_clause = implode(' AND ', $where);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, p.athlete_name, p.athlete_age, u.user_email as customer_email
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             WHERE $where_clause
             ORDER BY session_date ASC, start_time ASC",
            $params
        ));
    }
    
    public static function get_customer_sessions($customer_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, t.display_name as trainer_name, t.profile_photo as trainer_photo, p.athlete_name
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             WHERE s.customer_id = %d
             ORDER BY session_date DESC",
            $customer_id
        ));
    }
    
    // Review Methods
    public static function get_trainer_reviews($trainer_id, $limit = 10) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_reviews
             WHERE trainer_id = %d AND status = 'published'
             ORDER BY is_featured DESC, created_at DESC
             LIMIT %d",
            $trainer_id, $limit
        ));
    }
    
    public static function add_review($data) {
        global $wpdb;
        
        $wpdb->insert(
            "{$wpdb->prefix}ptp_reviews",
            array(
                'trainer_id' => $data['trainer_id'],
                'customer_id' => $data['customer_id'],
                'pack_id' => $data['pack_id'] ?? null,
                'session_id' => $data['session_id'] ?? null,
                'rating' => $data['rating'],
                'review_text' => $data['review_text'],
                'reviewer_name' => $data['reviewer_name'],
                'reviewer_experience' => $data['reviewer_experience'] ?? null,
                'skills_improved' => $data['skills_improved'] ?? null
            ),
            array('%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        // Update trainer average rating
        self::recalculate_trainer_rating($data['trainer_id']);
        
        return $wpdb->insert_id;
    }
    
    public static function recalculate_trainer_rating($trainer_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
             FROM {$wpdb->prefix}ptp_reviews
             WHERE trainer_id = %d AND status = 'published'",
            $trainer_id
        ));
        
        $wpdb->update(
            "{$wpdb->prefix}ptp_trainers",
            array(
                'avg_rating' => $stats->avg_rating ?? 0,
                'total_reviews' => $stats->total_reviews ?? 0
            ),
            array('id' => $trainer_id),
            array('%f', '%d'),
            array('%d')
        );
    }
    
    // Availability Methods
    public static function get_trainer_availability($trainer_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_availability
             WHERE trainer_id = %d AND is_active = 1
             ORDER BY day_of_week, start_time",
            $trainer_id
        ));
    }
    
    public static function get_available_slots($trainer_id, $date) {
        global $wpdb;
        
        $day_of_week = date('w', strtotime($date));
        
        // Get regular availability for this day
        $availability = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_availability
             WHERE trainer_id = %d AND day_of_week = %d AND is_active = 1",
            $trainer_id, $day_of_week
        ));
        
        // Check for exceptions
        $exception = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_availability_exceptions
             WHERE trainer_id = %d AND exception_date = %s",
            $trainer_id, $date
        ));
        
        if ($exception && !$exception->is_available) {
            return array(); // Day is blocked
        }
        
        if ($exception && $exception->is_available) {
            // Use exception times instead
            $availability = array((object)array(
                'start_time' => $exception->start_time,
                'end_time' => $exception->end_time
            ));
        }
        
        // Get booked sessions for this date
        $booked = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time, end_time FROM {$wpdb->prefix}ptp_sessions
             WHERE trainer_id = %d AND session_date = %s AND status NOT IN ('cancelled', 'no_show')",
            $trainer_id, $date
        ));
        
        $booked_times = array();
        foreach ($booked as $session) {
            $booked_times[] = array(
                'start' => $session->start_time,
                'end' => $session->end_time
            );
        }
        
        // Generate available slots
        $slots = array();
        foreach ($availability as $window) {
            $start = strtotime($window->start_time);
            $end = strtotime($window->end_time);
            
            while ($start < $end) {
                $slot_start = date('H:i:s', $start);
                $slot_end = date('H:i:s', $start + 3600); // 1 hour slots
                
                $is_available = true;
                foreach ($booked_times as $booked_time) {
                    if ($slot_start < $booked_time['end'] && $slot_end > $booked_time['start']) {
                        $is_available = false;
                        break;
                    }
                }
                
                if ($is_available) {
                    $slots[] = array(
                        'start' => $slot_start,
                        'end' => $slot_end,
                        'formatted' => date('g:i A', $start)
                    );
                }
                
                $start += 3600;
            }
        }
        
        return $slots;
    }
    
    // Pack Methods
    public static function get_pack($pack_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, t.display_name as trainer_name, t.profile_photo as trainer_photo, t.slug as trainer_slug
             FROM {$wpdb->prefix}ptp_lesson_packs p
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON p.trainer_id = t.id
             WHERE p.id = %d",
            $pack_id
        ));
    }
    
    public static function get_customer_packs($customer_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, t.display_name as trainer_name, t.profile_photo as trainer_photo, t.slug as trainer_slug
             FROM {$wpdb->prefix}ptp_lesson_packs p
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON p.trainer_id = t.id
             WHERE p.customer_id = %d
             ORDER BY created_at DESC",
            $customer_id
        ));
    }
    
    // Stats Methods
    public static function get_trainer_stats($trainer_id) {
        global $wpdb;
        
        return array(
            'total_sessions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions WHERE trainer_id = %d AND status = 'completed'",
                $trainer_id
            )),
            'upcoming_sessions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions WHERE trainer_id = %d AND status = 'scheduled' AND session_date >= CURDATE()",
                $trainer_id
            )),
            'total_earnings' => $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(trainer_payout), 0) FROM {$wpdb->prefix}ptp_payouts WHERE trainer_id = %d AND status = 'paid'",
                $trainer_id
            )),
            'pending_payout' => $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(trainer_payout), 0) FROM {$wpdb->prefix}ptp_payouts WHERE trainer_id = %d AND status = 'pending'",
                $trainer_id
            )),
            'active_students' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT customer_id) FROM {$wpdb->prefix}ptp_lesson_packs WHERE trainer_id = %d AND status = 'active'",
                $trainer_id
            ))
        );
    }
    
    public static function get_admin_stats() {
        global $wpdb;
        
        return array(
            'total_trainers' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_trainers WHERE status = 'approved'"),
            'pending_applications' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_applications WHERE status = 'pending'"),
            'total_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions WHERE status = 'completed'"),
            'total_revenue' => $wpdb->get_var("SELECT COALESCE(SUM(gross_amount), 0) FROM {$wpdb->prefix}ptp_payouts"),
            'platform_revenue' => $wpdb->get_var("SELECT COALESCE(SUM(platform_fee), 0) FROM {$wpdb->prefix}ptp_payouts"),
            'active_packs' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_lesson_packs WHERE status = 'active'")
        );
    }
}
