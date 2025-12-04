<?php
/**
 * Fired during plugin deactivation
 */
class PTP_Comms_Hub_Deactivator {
    
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('ptp_comms_process_automations');
        wp_clear_scheduled_hook('ptp_comms_sync_hubspot');
        wp_clear_scheduled_hook('ptp_comms_process_campaign_queue');
        
        // Clear any delayed automation schedules
        $crons = _get_cron_array();
        foreach ($crons as $timestamp => $cron) {
            if (isset($cron['ptp_comms_execute_delayed_automation'])) {
                unset($crons[$timestamp]['ptp_comms_execute_delayed_automation']);
            }
        }
        _set_cron_array($crons);
    }
}
