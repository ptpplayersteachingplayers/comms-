<?php
/**
 * Settings management class
 */
class PTP_Comms_Hub_Settings {
    
    private static $option_name = 'ptp_comms_hub_settings';
    
    public static function get($key, $default = '') {
        $settings = get_option(self::$option_name, array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    public static function set($key, $value) {
        $settings = get_option(self::$option_name, array());
        $settings[$key] = $value;
        return update_option(self::$option_name, $settings);
    }
    
    public static function get_all() {
        return get_option(self::$option_name, array());
    }
    
    public static function update_all($settings) {
        return update_option(self::$option_name, $settings);
    }
    
    public static function delete($key) {
        $settings = get_option(self::$option_name, array());
        unset($settings[$key]);
        return update_option(self::$option_name, $settings);
    }
}
