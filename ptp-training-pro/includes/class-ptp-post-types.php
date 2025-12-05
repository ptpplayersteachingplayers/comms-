<?php
/**
 * Custom Post Types and Rewrite Rules
 */

if (!defined('ABSPATH')) exit;

class PTP_Post_Types {
    
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        
        self::$initialized = true;
    }
    
    public static function add_rewrite_rules() {
        // Trainer profile URLs: /trainer/john-doe/
        add_rewrite_rule(
            '^trainer/([^/]+)/?$',
            'index.php?pagename=trainer&trainer_slug=$matches[1]',
            'top'
        );
    }
    
    public static function add_query_vars($vars) {
        $vars[] = 'trainer_slug';
        return $vars;
    }
}

PTP_Post_Types::init();
