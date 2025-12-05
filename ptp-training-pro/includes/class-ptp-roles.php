<?php
/**
 * User Roles
 */

if (!defined('ABSPATH')) exit;

class PTP_Roles {
    
    public static function create_roles() {
        // Trainer role
        add_role('ptp_trainer', 'PTP Trainer', array(
            'read' => true,
            'upload_files' => true,
            'edit_posts' => false,
            'delete_posts' => false
        ));
    }
    
    public static function remove_roles() {
        remove_role('ptp_trainer');
    }
    
    /**
     * Check if user is a trainer
     */
    public static function is_trainer($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $user = get_user_by('ID', $user_id);
        return $user && in_array('ptp_trainer', (array) $user->roles);
    }
    
    /**
     * Make user a trainer
     */
    public static function make_trainer($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }
        
        $user->add_role('ptp_trainer');
        return true;
    }
    
    /**
     * Remove trainer role from user
     */
    public static function remove_trainer($user_id) {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }
        
        $user->remove_role('ptp_trainer');
        return true;
    }
}
