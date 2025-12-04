<?php
/**
 * Reviews Management
 */

if (!defined('ABSPATH')) exit;

class PTP_Reviews {
    
    public function __construct() {
        add_action('wp_ajax_ptp_submit_review', array($this, 'ajax_submit_review'));
        add_action('wp_ajax_ptp_trainer_respond', array($this, 'ajax_trainer_respond'));
    }
    
    /**
     * Submit a review via AJAX
     */
    public function ajax_submit_review() {
        check_ajax_referer('ptp_review_nonce', 'nonce');
        
        $trainer_id = intval($_POST['trainer_id']);
        $rating = intval($_POST['rating']);
        $review_text = sanitize_textarea_field($_POST['review']);
        $experience = sanitize_text_field($_POST['experience']);
        $skills = isset($_POST['skills']) ? array_map('sanitize_text_field', $_POST['skills']) : array();
        
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error('Invalid rating');
        }
        
        $user_id = get_current_user_id();
        
        // Verify user has sessions with this trainer
        global $wpdb;
        $has_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_sessions 
             WHERE customer_id = %d AND trainer_id = %d AND status = 'completed'",
            $user_id, $trainer_id
        ));
        
        if (!$has_sessions) {
            wp_send_json_error('You must complete a session before leaving a review');
        }
        
        // Check for existing review
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_reviews 
             WHERE customer_id = %d AND trainer_id = %d",
            $user_id, $trainer_id
        ));
        
        if ($existing) {
            wp_send_json_error('You have already reviewed this trainer');
        }
        
        $user = get_user_by('ID', $user_id);
        
        $review_id = PTP_Database::add_review(array(
            'trainer_id' => $trainer_id,
            'customer_id' => $user_id,
            'rating' => $rating,
            'review_text' => $review_text,
            'reviewer_name' => $user->display_name,
            'reviewer_experience' => $experience,
            'skills_improved' => json_encode($skills)
        ));
        
        // Notify trainer
        $this->notify_trainer_of_review($review_id);
        
        wp_send_json_success(array('review_id' => $review_id));
    }
    
    /**
     * Trainer responds to a review
     */
    public function ajax_trainer_respond() {
        check_ajax_referer('ptp_review_nonce', 'nonce');
        
        $review_id = intval($_POST['review_id']);
        $response = sanitize_textarea_field($_POST['response']);
        
        $user_id = get_current_user_id();
        $trainer = PTP_Database::get_trainer_by_user($user_id);
        
        if (!$trainer) {
            wp_send_json_error('Not authorized');
        }
        
        global $wpdb;
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_reviews WHERE id = %d",
            $review_id
        ));
        
        if (!$review || $review->trainer_id != $trainer->id) {
            wp_send_json_error('Review not found');
        }
        
        $wpdb->update(
            "{$wpdb->prefix}ptp_reviews",
            array(
                'trainer_response' => $response,
                'trainer_response_at' => current_time('mysql')
            ),
            array('id' => $review_id)
        );
        
        wp_send_json_success();
    }
    
    /**
     * Send notification to trainer about new review
     */
    private function notify_trainer_of_review($review_id) {
        global $wpdb;
        
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, t.display_name as trainer_name, u.user_email as trainer_email
             FROM {$wpdb->prefix}ptp_reviews r
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON r.trainer_id = t.id
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             WHERE r.id = %d",
            $review_id
        ));
        
        if (!$review) return;
        
        $stars = str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating);
        
        $subject = 'New Review: ' . $stars;
        $message = "Hi {$review->trainer_name},\n\n";
        $message .= "You received a new review!\n\n";
        $message .= "Rating: {$stars} ({$review->rating}/5)\n";
        $message .= "From: {$review->reviewer_name}\n\n";
        $message .= "\"{$review->review_text}\"\n\n";
        $message .= "Respond to this review in your dashboard:\n";
        $message .= home_url('/trainer-dashboard/') . "\n\n";
        $message .= "Keep up the great work!\nThe PTP Team";
        
        wp_mail($review->trainer_email, $subject, $message);
    }
    
    /**
     * Get review statistics for a trainer
     */
    public static function get_trainer_stats($trainer_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                AVG(rating) as average,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
             FROM {$wpdb->prefix}ptp_reviews 
             WHERE trainer_id = %d AND status = 'published'",
            $trainer_id
        ));
        
        return $stats;
    }
    
    /**
     * Request review from customer after session
     */
    public static function request_review($session_id) {
        global $wpdb;
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, t.display_name as trainer_name, t.slug as trainer_slug,
                    p.athlete_name, u.user_email, u.display_name as customer_name
             FROM {$wpdb->prefix}ptp_sessions s
             LEFT JOIN {$wpdb->prefix}ptp_trainers t ON s.trainer_id = t.id
             LEFT JOIN {$wpdb->prefix}ptp_lesson_packs p ON s.pack_id = p.id
             LEFT JOIN {$wpdb->users} u ON s.customer_id = u.ID
             WHERE s.id = %d",
            $session_id
        ));
        
        if (!$session) return;
        
        // Check if already reviewed
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ptp_reviews 
             WHERE customer_id = %d AND trainer_id = %d",
            $session->customer_id, $session->trainer_id
        ));
        
        if ($existing) return;
        
        $review_url = home_url('/trainer/' . $session->trainer_slug . '/?review=1');
        
        $subject = "How was {$session->athlete_name}'s session with {$session->trainer_name}?";
        $message = "Hi {$session->customer_name},\n\n";
        $message .= "We hope {$session->athlete_name} enjoyed their training session with {$session->trainer_name}!\n\n";
        $message .= "Would you take a moment to leave a review? Your feedback helps other families find great trainers.\n\n";
        $message .= "Leave a review: {$review_url}\n\n";
        $message .= "Thanks for being part of the PTP family!\n\nThe PTP Team";
        
        wp_mail($session->user_email, $subject, $message);
    }
}

new PTP_Reviews();
