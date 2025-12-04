<?php
/**
 * Google Maps Integration
 * Geocoding and map functionality
 */

if (!defined('ABSPATH')) exit;

class PTP_Maps {
    
    /**
     * Get API key
     */
    private static function get_api_key() {
        return get_option('ptp_google_maps_key', '');
    }
    
    /**
     * Geocode an address to coordinates
     */
    public static function geocode($address) {
        $api_key = self::get_api_key();
        
        if (!$api_key) {
            return new WP_Error('no_api_key', 'Google Maps API key not configured');
        }
        
        $url = add_query_arg(array(
            'address' => urlencode($address),
            'key' => $api_key
        ), 'https://maps.googleapis.com/maps/api/geocode/json');
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data['status'] !== 'OK' || empty($data['results'])) {
            return new WP_Error('geocode_failed', 'Could not geocode address');
        }
        
        $result = $data['results'][0];
        
        return array(
            'lat' => $result['geometry']['location']['lat'],
            'lng' => $result['geometry']['location']['lng'],
            'formatted_address' => $result['formatted_address'],
            'components' => self::parse_address_components($result['address_components'])
        );
    }
    
    /**
     * Parse address components from geocode response
     */
    private static function parse_address_components($components) {
        $parsed = array(
            'street_number' => '',
            'street' => '',
            'city' => '',
            'state' => '',
            'state_code' => '',
            'zip' => '',
            'country' => ''
        );
        
        foreach ($components as $component) {
            $types = $component['types'];
            
            if (in_array('street_number', $types)) {
                $parsed['street_number'] = $component['long_name'];
            } elseif (in_array('route', $types)) {
                $parsed['street'] = $component['long_name'];
            } elseif (in_array('locality', $types)) {
                $parsed['city'] = $component['long_name'];
            } elseif (in_array('administrative_area_level_1', $types)) {
                $parsed['state'] = $component['long_name'];
                $parsed['state_code'] = $component['short_name'];
            } elseif (in_array('postal_code', $types)) {
                $parsed['zip'] = $component['long_name'];
            } elseif (in_array('country', $types)) {
                $parsed['country'] = $component['short_name'];
            }
        }
        
        return $parsed;
    }
    
    /**
     * Calculate distance between two points
     */
    public static function calculate_distance($lat1, $lng1, $lat2, $lng2, $unit = 'miles') {
        $theta = $lng1 - $lng2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + 
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        
        if ($unit === 'km') {
            return $miles * 1.609344;
        }
        
        return $miles;
    }
    
    /**
     * Update trainer location from address
     */
    public static function update_trainer_location($trainer_id, $address) {
        $geocode = self::geocode($address);
        
        if (is_wp_error($geocode)) {
            return $geocode;
        }
        
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}ptp_trainers",
            array(
                'primary_location_lat' => $geocode['lat'],
                'primary_location_lng' => $geocode['lng'],
                'primary_location_address' => $geocode['formatted_address'],
                'primary_location_city' => $geocode['components']['city'],
                'primary_location_state' => $geocode['components']['state_code'],
                'primary_location_zip' => $geocode['components']['zip']
            ),
            array('id' => $trainer_id)
        );
        
        return $geocode;
    }
    
    /**
     * Get trainers within radius of a point
     */
    public static function get_trainers_near($lat, $lng, $radius_miles = 25) {
        global $wpdb;
        
        // Haversine formula in SQL
        $sql = $wpdb->prepare(
            "SELECT *, 
             (3959 * acos(cos(radians(%f)) * cos(radians(primary_location_lat)) * 
             cos(radians(primary_location_lng) - radians(%f)) + 
             sin(radians(%f)) * sin(radians(primary_location_lat)))) AS distance 
             FROM {$wpdb->prefix}ptp_trainers 
             WHERE status = 'approved'
             AND primary_location_lat IS NOT NULL 
             HAVING distance < %d 
             ORDER BY distance ASC",
            $lat, $lng, $lat, $radius_miles
        );
        
        return $wpdb->get_results($sql);
    }
}
