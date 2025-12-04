<?php
/**
 * Google Calendar Integration
 * Two-way sync for trainer availability and session bookings
 */

if (!defined('ABSPATH')) exit;

class PTP_Google_Calendar {
    
    private static $client_id;
    private static $client_secret;
    private static $redirect_uri;
    
    public static function init() {
        self::$client_id = get_option('ptp_google_client_id');
        self::$client_secret = get_option('ptp_google_client_secret');
        self::$redirect_uri = admin_url('admin.php?page=ptp-training-calendar-callback');
        
        add_action('admin_init', array(__CLASS__, 'handle_oauth_callback'));
    }
    
    /**
     * Get OAuth authorization URL
     */
    public static function get_auth_url($trainer_id) {
        $state = wp_create_nonce('ptp_calendar_' . $trainer_id);
        update_user_meta(get_current_user_id(), '_ptp_calendar_state', $state);
        
        $params = array(
            'client_id' => self::$client_id,
            'redirect_uri' => self::$redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        );
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    public static function handle_oauth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'ptp-training-calendar-callback') {
            return;
        }
        
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            wp_die('Invalid callback request');
        }
        
        $user_id = get_current_user_id();
        $saved_state = get_user_meta($user_id, '_ptp_calendar_state', true);
        
        if ($_GET['state'] !== $saved_state) {
            wp_die('Invalid state parameter');
        }
        
        // Exchange code for tokens
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => self::$client_id,
                'client_secret' => self::$client_secret,
                'code' => $_GET['code'],
                'grant_type' => 'authorization_code',
                'redirect_uri' => self::$redirect_uri
            )
        ));
        
        if (is_wp_error($response)) {
            wp_die('Failed to exchange authorization code');
        }
        
        $tokens = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($tokens['access_token'])) {
            wp_die('Failed to get access token');
        }
        
        // Store tokens
        $trainer = PTP_Database::get_trainer_by_user($user_id);
        
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}ptp_trainers",
            array(
                'google_calendar_token' => json_encode($tokens)
            ),
            array('id' => $trainer->id),
            array('%s'),
            array('%d')
        );
        
        delete_user_meta($user_id, '_ptp_calendar_state');
        
        // Redirect back to dashboard
        wp_redirect(home_url('/trainer-dashboard/?calendar=connected'));
        exit;
    }
    
    /**
     * Get valid access token (refresh if needed)
     */
    private static function get_access_token($trainer_id) {
        global $wpdb;
        $trainer = $wpdb->get_row($wpdb->prepare(
            "SELECT google_calendar_token FROM {$wpdb->prefix}ptp_trainers WHERE id = %d",
            $trainer_id
        ));
        
        if (!$trainer || !$trainer->google_calendar_token) {
            return false;
        }
        
        $tokens = json_decode($trainer->google_calendar_token, true);
        
        // Check if token is expired
        if (isset($tokens['expires_at']) && time() >= $tokens['expires_at']) {
            // Refresh token
            if (!isset($tokens['refresh_token'])) {
                return false;
            }
            
            $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
                'body' => array(
                    'client_id' => self::$client_id,
                    'client_secret' => self::$client_secret,
                    'refresh_token' => $tokens['refresh_token'],
                    'grant_type' => 'refresh_token'
                )
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $new_tokens = json_decode(wp_remote_retrieve_body($response), true);
            
            if (!isset($new_tokens['access_token'])) {
                return false;
            }
            
            $tokens['access_token'] = $new_tokens['access_token'];
            $tokens['expires_at'] = time() + $new_tokens['expires_in'];
            
            // Save updated tokens
            $wpdb->update(
                "{$wpdb->prefix}ptp_trainers",
                array('google_calendar_token' => json_encode($tokens)),
                array('id' => $trainer_id)
            );
        }
        
        return $tokens['access_token'];
    }
    
    /**
     * Create calendar event for session
     */
    public static function sync_session($session_id) {
        global $wpdb;
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, p.athlete_name, p.athlete_age, t.display_name as trainer_name,
                    t.google_calendar_id, u.user_email as customer_email, u.display_name as customer_name
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             WHERE s.id = %d",
            $session_id
        ));
        
        if (!$session) {
            return new WP_Error('not_found', 'Session not found');
        }
        
        $access_token = self::get_access_token($session->trainer_id);
        
        if (!$access_token) {
            return new WP_Error('no_token', 'Google Calendar not connected');
        }
        
        // Get location
        $location = '';
        if ($session->location_id) {
            $loc = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_trainer_locations WHERE id = %d",
                $session->location_id
            ));
            if ($loc) {
                $location = $loc->address . ', ' . $loc->city . ', ' . $loc->state;
            }
        }
        
        // Build event
        $start_datetime = $session->session_date . 'T' . $session->start_time;
        $end_datetime = $session->session_date . 'T' . $session->end_time;
        
        $event = array(
            'summary' => 'Training Session: ' . $session->athlete_name,
            'description' => sprintf(
                "Private Training Session\n\nAthlete: %s (Age %d)\nCustomer: %s\nEmail: %s\n\nBooked via PTP Training",
                $session->athlete_name,
                $session->athlete_age,
                $session->customer_name,
                $session->customer_email
            ),
            'location' => $location,
            'start' => array(
                'dateTime' => $start_datetime,
                'timeZone' => wp_timezone_string()
            ),
            'end' => array(
                'dateTime' => $end_datetime,
                'timeZone' => wp_timezone_string()
            ),
            'attendees' => array(
                array('email' => $session->customer_email)
            ),
            'reminders' => array(
                'useDefault' => false,
                'overrides' => array(
                    array('method' => 'email', 'minutes' => 1440), // 24 hours
                    array('method' => 'popup', 'minutes' => 60)    // 1 hour
                )
            )
        );
        
        $calendar_id = $session->google_calendar_id ?: 'primary';
        
        // Check if updating existing event
        if ($session->google_event_id) {
            $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events/{$session->google_event_id}";
            $method = 'PUT';
        } else {
            $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events";
            $method = 'POST';
        }
        
        $response = wp_remote_request($url, array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($event)
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($result['id'])) {
            // Save event ID
            $wpdb->update(
                "{$wpdb->prefix}ptp_sessions",
                array('google_event_id' => $result['id']),
                array('id' => $session_id)
            );
            
            return $result['id'];
        }
        
        return new WP_Error('api_error', 'Failed to create calendar event');
    }
    
    /**
     * Delete calendar event
     */
    public static function delete_event($trainer_id, $event_id) {
        $access_token = self::get_access_token($trainer_id);
        
        if (!$access_token) {
            return false;
        }
        
        global $wpdb;
        $trainer = $wpdb->get_row($wpdb->prepare(
            "SELECT google_calendar_id FROM {$wpdb->prefix}ptp_trainers WHERE id = %d",
            $trainer_id
        ));
        
        $calendar_id = $trainer->google_calendar_id ?: 'primary';
        
        $response = wp_remote_request(
            "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events/{$event_id}",
            array(
                'method' => 'DELETE',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token
                )
            )
        );
        
        return !is_wp_error($response);
    }
    
    /**
     * Get busy times from calendar (for availability checking)
     */
    public static function get_busy_times($trainer_id, $start_date, $end_date) {
        $access_token = self::get_access_token($trainer_id);
        
        if (!$access_token) {
            return array();
        }
        
        global $wpdb;
        $trainer = $wpdb->get_row($wpdb->prepare(
            "SELECT google_calendar_id FROM {$wpdb->prefix}ptp_trainers WHERE id = %d",
            $trainer_id
        ));
        
        $calendar_id = $trainer->google_calendar_id ?: 'primary';
        
        $response = wp_remote_post(
            'https://www.googleapis.com/calendar/v3/freeBusy',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'timeMin' => $start_date . 'T00:00:00Z',
                    'timeMax' => $end_date . 'T23:59:59Z',
                    'items' => array(
                        array('id' => $calendar_id)
                    )
                ))
            )
        );
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($result['calendars'][$calendar_id]['busy'])) {
            return $result['calendars'][$calendar_id]['busy'];
        }
        
        return array();
    }
    
    /**
     * Check if trainer has calendar connected
     */
    public static function is_connected($trainer_id) {
        global $wpdb;
        $token = $wpdb->get_var($wpdb->prepare(
            "SELECT google_calendar_token FROM {$wpdb->prefix}ptp_trainers WHERE id = %d",
            $trainer_id
        ));
        
        return !empty($token);
    }
    
    /**
     * Disconnect calendar
     */
    public static function disconnect($trainer_id) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}ptp_trainers",
            array(
                'google_calendar_token' => null,
                'google_calendar_id' => null
            ),
            array('id' => $trainer_id)
        );
    }
}

PTP_Google_Calendar::init();
