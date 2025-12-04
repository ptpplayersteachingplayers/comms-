<?php
/**
 * Public-facing functionality
 */
class PTP_Comms_Hub_Public {
    
    public function enqueue_styles() {
        wp_enqueue_style(
            'ptp-comms-hub-public',
            PTP_COMMS_HUB_URL . 'public/css/public.css',
            array(),
            PTP_COMMS_HUB_VERSION
        );
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script(
            'ptp-comms-hub-public',
            PTP_COMMS_HUB_URL . 'public/js/public.js',
            array('jquery'),
            PTP_COMMS_HUB_VERSION,
            true
        );
    }
}
