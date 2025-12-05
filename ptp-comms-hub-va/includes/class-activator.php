<?php
/**
 * Fired during plugin activation
 * v4.0.0 - Added VA relationship management tables
 */
class PTP_Comms_Hub_Activator {
    
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Contacts table - Enhanced with VA relationship fields
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_contacts (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_first_name varchar(100) DEFAULT '',
            parent_last_name varchar(100) DEFAULT '',
            parent_phone varchar(20) DEFAULT '',
            parent_email varchar(255) DEFAULT '',
            child_name varchar(100) DEFAULT '',
            child_age int(3) DEFAULT 0,
            child_birthday date DEFAULT NULL,
            state varchar(50) DEFAULT '',
            city varchar(100) DEFAULT '',
            zip_code varchar(10) DEFAULT '',
            address text DEFAULT NULL,
            segments text DEFAULT NULL,
            tags text DEFAULT NULL,
            source varchar(100) DEFAULT '',
            woo_order_id bigint(20) UNSIGNED DEFAULT NULL,
            opted_in tinyint(1) DEFAULT 0,
            opted_out tinyint(1) DEFAULT 0,
            hubspot_contact_id varchar(100) DEFAULT NULL,
            hubspot_last_sync datetime DEFAULT NULL,
            hubspot_lifecycle_stage varchar(50) DEFAULT '',
            hubspot_owner_id varchar(100) DEFAULT NULL,
            teams_thread_id varchar(255) DEFAULT NULL,
            assigned_va bigint(20) UNSIGNED DEFAULT NULL,
            relationship_score int(3) DEFAULT 50,
            last_interaction_at datetime DEFAULT NULL,
            last_message_at datetime DEFAULT NULL,
            total_interactions int(11) DEFAULT 0,
            total_orders int(11) DEFAULT 0,
            lifetime_value decimal(10,2) DEFAULT 0.00,
            preferred_contact_method varchar(20) DEFAULT 'sms',
            preferred_contact_time varchar(50) DEFAULT '',
            do_not_contact tinyint(1) DEFAULT 0,
            vip_status tinyint(1) DEFAULT 0,
            notes_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY parent_phone (parent_phone),
            KEY parent_email (parent_email),
            KEY opted_in (opted_in),
            KEY state (state),
            KEY city (city),
            KEY zip_code (zip_code),
            KEY woo_order_id (woo_order_id),
            KEY teams_thread_id (teams_thread_id),
            KEY hubspot_contact_id (hubspot_contact_id),
            KEY assigned_va (assigned_va),
            KEY relationship_score (relationship_score),
            KEY last_interaction_at (last_interaction_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Add new columns if they don't exist (for upgrades from v3.x)
        self::add_column_if_not_exists('ptp_contacts', 'child_birthday', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN child_birthday date DEFAULT NULL AFTER child_age");
        self::add_column_if_not_exists('ptp_contacts', 'address', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN address text DEFAULT NULL AFTER zip_code");
        self::add_column_if_not_exists('ptp_contacts', 'tags', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN tags text DEFAULT NULL AFTER segments");
        self::add_column_if_not_exists('ptp_contacts', 'hubspot_last_sync', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN hubspot_last_sync datetime DEFAULT NULL AFTER hubspot_contact_id");
        self::add_column_if_not_exists('ptp_contacts', 'hubspot_lifecycle_stage', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN hubspot_lifecycle_stage varchar(50) DEFAULT '' AFTER hubspot_last_sync");
        self::add_column_if_not_exists('ptp_contacts', 'hubspot_owner_id', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN hubspot_owner_id varchar(100) DEFAULT NULL AFTER hubspot_lifecycle_stage");
        self::add_column_if_not_exists('ptp_contacts', 'assigned_va', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN assigned_va bigint(20) UNSIGNED DEFAULT NULL AFTER teams_thread_id");
        self::add_column_if_not_exists('ptp_contacts', 'relationship_score', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN relationship_score int(3) DEFAULT 50 AFTER assigned_va");
        self::add_column_if_not_exists('ptp_contacts', 'last_interaction_at', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN last_interaction_at datetime DEFAULT NULL AFTER relationship_score");
        self::add_column_if_not_exists('ptp_contacts', 'total_interactions', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN total_interactions int(11) DEFAULT 0 AFTER last_message_at");
        self::add_column_if_not_exists('ptp_contacts', 'total_orders', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN total_orders int(11) DEFAULT 0 AFTER total_interactions");
        self::add_column_if_not_exists('ptp_contacts', 'lifetime_value', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN lifetime_value decimal(10,2) DEFAULT 0.00 AFTER total_orders");
        self::add_column_if_not_exists('ptp_contacts', 'preferred_contact_method', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN preferred_contact_method varchar(20) DEFAULT 'sms' AFTER lifetime_value");
        self::add_column_if_not_exists('ptp_contacts', 'preferred_contact_time', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN preferred_contact_time varchar(50) DEFAULT '' AFTER preferred_contact_method");
        self::add_column_if_not_exists('ptp_contacts', 'do_not_contact', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN do_not_contact tinyint(1) DEFAULT 0 AFTER preferred_contact_time");
        self::add_column_if_not_exists('ptp_contacts', 'vip_status', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN vip_status tinyint(1) DEFAULT 0 AFTER do_not_contact");
        self::add_column_if_not_exists('ptp_contacts', 'notes_count', "ALTER TABLE {$wpdb->prefix}ptp_contacts ADD COLUMN notes_count int(11) DEFAULT 0 AFTER vip_status");
        
        // Contact Notes table - For VA relationship building
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_contact_notes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            note_type varchar(50) DEFAULT 'general',
            title varchar(255) DEFAULT '',
            content text NOT NULL,
            is_pinned tinyint(1) DEFAULT 0,
            is_private tinyint(1) DEFAULT 0,
            sentiment varchar(20) DEFAULT 'neutral',
            related_order_id bigint(20) UNSIGNED DEFAULT NULL,
            related_interaction_id bigint(20) UNSIGNED DEFAULT NULL,
            follow_up_date date DEFAULT NULL,
            follow_up_completed tinyint(1) DEFAULT 0,
            hubspot_synced tinyint(1) DEFAULT 0,
            hubspot_note_id varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY user_id (user_id),
            KEY note_type (note_type),
            KEY is_pinned (is_pinned),
            KEY follow_up_date (follow_up_date),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Reminders table - For VA task management
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_reminders (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            assigned_to bigint(20) UNSIGNED DEFAULT NULL,
            reminder_type varchar(50) DEFAULT 'follow_up',
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            priority varchar(20) DEFAULT 'normal',
            due_date datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            snoozed_until datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            notification_sent tinyint(1) DEFAULT 0,
            notification_method varchar(50) DEFAULT 'email',
            recurring varchar(20) DEFAULT NULL,
            recurring_end_date date DEFAULT NULL,
            parent_reminder_id bigint(20) UNSIGNED DEFAULT NULL,
            related_order_id bigint(20) UNSIGNED DEFAULT NULL,
            related_note_id bigint(20) UNSIGNED DEFAULT NULL,
            action_url varchar(500) DEFAULT NULL,
            meta_data text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY user_id (user_id),
            KEY assigned_to (assigned_to),
            KEY due_date (due_date),
            KEY status (status),
            KEY reminder_type (reminder_type),
            KEY priority (priority)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Notifications table - For VA alerts
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_notifications (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            notification_type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            action_url varchar(500) DEFAULT NULL,
            action_text varchar(100) DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            is_dismissed tinyint(1) DEFAULT 0,
            priority varchar(20) DEFAULT 'normal',
            expires_at datetime DEFAULT NULL,
            meta_data text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            read_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY contact_id (contact_id),
            KEY notification_type (notification_type),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Relationship Timeline/Activity Log table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_activity_log (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            activity_type varchar(50) NOT NULL,
            activity_subtype varchar(50) DEFAULT NULL,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            related_id bigint(20) UNSIGNED DEFAULT NULL,
            related_type varchar(50) DEFAULT NULL,
            importance int(3) DEFAULT 5,
            hubspot_synced tinyint(1) DEFAULT 0,
            meta_data text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY user_id (user_id),
            KEY activity_type (activity_type),
            KEY created_at (created_at),
            KEY contact_activity (contact_id, activity_type, created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Saved Segments table - For targeting campaigns
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_saved_segments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            segment_type varchar(50) DEFAULT 'custom',
            criteria text NOT NULL,
            is_dynamic tinyint(1) DEFAULT 1,
            cached_count int(11) DEFAULT 0,
            cache_updated_at datetime DEFAULT NULL,
            hubspot_list_id varchar(100) DEFAULT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY segment_type (segment_type),
            KEY is_active (is_active),
            KEY created_by (created_by)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Segment Members table (for static segments)
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_segment_members (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            segment_id bigint(20) UNSIGNED NOT NULL,
            contact_id bigint(20) UNSIGNED NOT NULL,
            added_by bigint(20) UNSIGNED DEFAULT NULL,
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY segment_contact (segment_id, contact_id),
            KEY segment_id (segment_id),
            KEY contact_id (contact_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // HubSpot Sync Queue table - For robust sync
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_hubspot_sync_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            hubspot_contact_id varchar(100) DEFAULT NULL,
            sync_type varchar(50) NOT NULL,
            sync_direction varchar(20) DEFAULT 'to_hubspot',
            data_payload text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            attempts int(3) DEFAULT 0,
            last_attempt_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            priority int(3) DEFAULT 5,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY sync_type (sync_type),
            KEY status (status),
            KEY priority (priority),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Registrations table - Enhanced
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_registrations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            order_item_id bigint(20) UNSIGNED DEFAULT NULL,
            product_id bigint(20) UNSIGNED DEFAULT NULL,
            product_name varchar(255) DEFAULT '',
            product_sku varchar(100) DEFAULT '',
            quantity int(11) DEFAULT 1,
            line_total decimal(10,2) DEFAULT 0.00,
            child_name varchar(200) DEFAULT '',
            child_age int(3) DEFAULT 0,
            camper_level varchar(50) DEFAULT '',
            current_team varchar(100) DEFAULT '',
            tshirt_size varchar(20) DEFAULT '',
            improvement_areas text DEFAULT NULL,
            special_needs text DEFAULT NULL,
            emergency_contact varchar(200) DEFAULT '',
            emergency_phone varchar(20) DEFAULT '',
            how_did_you_hear varchar(100) DEFAULT '',
            friend_name varchar(200) DEFAULT '',
            friend_email varchar(255) DEFAULT '',
            waiver_agreed varchar(10) DEFAULT '',
            refund_policy_agreed varchar(10) DEFAULT '',
            photo_permission varchar(10) DEFAULT '',
            event_date date DEFAULT NULL,
            event_end_date date DEFAULT NULL,
            event_time varchar(100) DEFAULT '',
            event_location varchar(255) DEFAULT '',
            event_address text DEFAULT NULL,
            program_type varchar(100) DEFAULT '',
            market varchar(50) DEFAULT '',
            what_to_bring text DEFAULT NULL,
            head_coach varchar(100) DEFAULT '',
            registration_status varchar(50) DEFAULT 'pending',
            reminder_1day_sent datetime DEFAULT NULL,
            reminder_3day_sent datetime DEFAULT NULL,
            reminder_7day_sent datetime DEFAULT NULL,
            follow_up_sent datetime DEFAULT NULL,
            nps_score int(2) DEFAULT NULL,
            feedback_text text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY event_date (event_date),
            KEY registration_status (registration_status),
            KEY market (market)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Campaigns table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_campaigns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            message_type varchar(50) DEFAULT 'sms',
            message_content text DEFAULT NULL,
            message_preview varchar(200) DEFAULT '',
            target_segment varchar(100) DEFAULT 'all',
            target_segment_id bigint(20) UNSIGNED DEFAULT NULL,
            target_criteria text DEFAULT NULL,
            schedule_time datetime DEFAULT NULL,
            status varchar(50) DEFAULT 'draft',
            total_recipients int(11) DEFAULT 0,
            sent_count int(11) DEFAULT 0,
            delivered_count int(11) DEFAULT 0,
            failed_count int(11) DEFAULT 0,
            opened_count int(11) DEFAULT 0,
            clicked_count int(11) DEFAULT 0,
            replied_count int(11) DEFAULT 0,
            opted_out_count int(11) DEFAULT 0,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY schedule_time (schedule_time),
            KEY created_by (created_by),
            KEY target_segment_id (target_segment_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Campaign queue table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_campaign_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            contact_id bigint(20) UNSIGNED NOT NULL,
            phone_number varchar(20) NOT NULL,
            personalized_message text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            twilio_sid varchar(100) DEFAULT '',
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            delivered_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id),
            KEY contact_id (contact_id),
            KEY status (status),
            KEY campaign_status (campaign_id, status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Conversations table - For inbox/messaging threads
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_conversations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            status varchar(20) DEFAULT 'active',
            channel varchar(20) DEFAULT 'sms',
            unread_count int(11) DEFAULT 0,
            last_message text DEFAULT NULL,
            last_message_direction varchar(20) DEFAULT NULL,
            last_message_at datetime DEFAULT NULL,
            assigned_to bigint(20) UNSIGNED DEFAULT NULL,
            priority varchar(20) DEFAULT 'normal',
            tags text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY status (status),
            KEY channel (channel),
            KEY unread_count (unread_count),
            KEY last_message_at (last_message_at),
            KEY assigned_to (assigned_to)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Messages table - For individual SMS/voice messages in conversations
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) UNSIGNED DEFAULT NULL,
            contact_id bigint(20) UNSIGNED NOT NULL,
            direction varchar(20) NOT NULL DEFAULT 'outbound',
            message_type varchar(20) DEFAULT 'sms',
            message_body text NOT NULL,
            twilio_sid varchar(100) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            error_code varchar(20) DEFAULT NULL,
            error_message text DEFAULT NULL,
            media_url text DEFAULT NULL,
            segments int(3) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY conversation_id (conversation_id),
            KEY contact_id (contact_id),
            KEY direction (direction),
            KEY message_type (message_type),
            KEY status (status),
            KEY twilio_sid (twilio_sid),
            KEY created_at (created_at),
            KEY conv_id_polling (conversation_id, id)
        ) $charset_collate;";
        dbDelta($sql);

        // Communication logs table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_communication_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            conversation_id bigint(20) UNSIGNED DEFAULT NULL,
            campaign_id bigint(20) UNSIGNED DEFAULT NULL,
            message_type varchar(20) NOT NULL DEFAULT 'sms',
            direction varchar(20) NOT NULL DEFAULT 'outbound',
            message_content text NOT NULL,
            status varchar(50) DEFAULT 'pending',
            twilio_sid varchar(100) DEFAULT '',
            error_message text DEFAULT NULL,
            meta_data text DEFAULT NULL,
            sentiment varchar(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY conversation_id (conversation_id),
            KEY campaign_id (campaign_id),
            KEY message_type (message_type),
            KEY direction (direction),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Templates table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            content text NOT NULL,
            category varchar(50) DEFAULT 'general',
            message_type varchar(20) DEFAULT 'sms',
            is_active tinyint(1) DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY category (category),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Automations table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_automations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            trigger_type varchar(100) NOT NULL,
            trigger_conditions text DEFAULT NULL,
            action_type varchar(100) DEFAULT 'send_sms',
            template_id bigint(20) UNSIGNED DEFAULT NULL,
            delay_minutes int(11) DEFAULT 0,
            conditions text DEFAULT NULL,
            target_segment_id bigint(20) UNSIGNED DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            execution_count int(11) DEFAULT 0,
            last_execution_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY trigger_type (trigger_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Canned replies table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_canned_replies (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            shortcut varchar(50) DEFAULT '',
            content text NOT NULL,
            category varchar(50) DEFAULT 'general',
            sort_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY category (category),
            KEY shortcut (shortcut),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Product settings table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_product_settings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            confirmation_template_id bigint(20) UNSIGNED DEFAULT NULL,
            reminder_template_id bigint(20) UNSIGNED DEFAULT NULL,
            reminder_days int(3) DEFAULT 7,
            enable_automations tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Voicemails table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_voicemails (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            from_number varchar(20) NOT NULL,
            recording_url text DEFAULT NULL,
            recording_sid varchar(100) DEFAULT '',
            transcription text DEFAULT NULL,
            status varchar(50) DEFAULT 'new',
            duration int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            listened_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY from_number (from_number),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Call logs table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_call_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            call_sid varchar(100) NOT NULL,
            contact_id bigint(20) UNSIGNED DEFAULT NULL,
            from_number varchar(20) DEFAULT '',
            to_number varchar(20) DEFAULT '',
            status varchar(50) DEFAULT '',
            direction varchar(20) DEFAULT 'outbound',
            duration int(11) DEFAULT 0,
            recording_url text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY call_sid (call_sid),
            KEY contact_id (contact_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Create default templates
        self::create_default_templates();
        
        // Create default saved segments
        self::create_default_segments();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('ptp_comms_process_automations')) {
            wp_schedule_event(time(), 'hourly', 'ptp_comms_process_automations');
        }
        
        if (!wp_next_scheduled('ptp_comms_sync_hubspot')) {
            wp_schedule_event(time(), 'every_fifteen_minutes', 'ptp_comms_sync_hubspot');
        }
        
        if (!wp_next_scheduled('ptp_comms_process_reminders')) {
            wp_schedule_event(time(), 'every_five_minutes', 'ptp_comms_process_reminders');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Update version
        update_option('ptp_comms_hub_version', PTP_COMMS_HUB_VERSION);
    }
    
    /**
     * Helper to add column if not exists
     */
    private static function add_column_if_not_exists($table, $column, $query) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE '{$column}'");
        if (empty($column_exists)) {
            $wpdb->query($query);
        }
    }
    
    private static function create_default_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'ptp_templates';
        
        $templates = array(
            array(
                'name' => 'Registration Confirmation',
                'content' => 'Hi {parent_first_name}! Thanks for registering {child_name} for {event_name} on {event_date}. We\'re excited to see you at {event_location}! Reply STOP to opt out.',
                'category' => 'confirmation',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Event Reminder - 7 Days',
                'content' => 'Hi {parent_first_name}! Just a reminder that {child_name}\'s {event_name} is coming up on {event_date} at {event_location}. Can\'t wait to see you there!',
                'category' => 'reminder',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Event Reminder - 1 Day',
                'content' => 'Tomorrow\'s the day! {child_name}\'s {event_name} at {event_location}. See you at {event_date}!',
                'category' => 'reminder',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Thank You Follow-up',
                'content' => 'Thanks for joining us at {event_name}, {parent_first_name}! We hope {child_name} had a great time. Check ptpsoccercamps.com for more camps coming soon!',
                'category' => 'follow_up',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Welcome New Contact',
                'content' => 'Welcome to PTP Soccer Camps! We\'re excited to have you join our community. Stay tuned for upcoming camps and training sessions. Visit ptpsoccercamps.com to learn more!',
                'category' => 'welcome',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Birthday Message',
                'content' => 'Happy Birthday to {child_name}! 🎉 From all of us at PTP Soccer Camps, we hope they have an amazing day! As a special gift, use code BIRTHDAY15 for 15% off their next camp!',
                'category' => 'birthday',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Personal Check-in',
                'content' => 'Hi {parent_first_name}! Just checking in to see how {child_name} is doing with their soccer skills. Any questions about our upcoming camps? We\'re here to help!',
                'category' => 'relationship',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'VIP Exclusive Offer',
                'content' => 'Hi {parent_first_name}! As one of our valued VIP families, you get early access to our new {event_name} camp. Register now before spots fill up! 🌟',
                'category' => 'vip',
                'message_type' => 'sms'
            )
        );
        
        foreach ($templates as $template) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE name = %s",
                $template['name']
            ));
            
            if (!$existing) {
                $wpdb->insert($table, $template);
            }
        }
    }
    
    private static function create_default_segments() {
        global $wpdb;
        $table = $wpdb->prefix . 'ptp_saved_segments';
        
        $segments = array(
            array(
                'name' => 'VIP Customers',
                'description' => 'Customers marked as VIP or with lifetime value over $500',
                'segment_type' => 'smart',
                'criteria' => json_encode(array(
                    'logic' => 'OR',
                    'conditions' => array(
                        array('field' => 'vip_status', 'operator' => '=', 'value' => 1),
                        array('field' => 'lifetime_value', 'operator' => '>=', 'value' => 500)
                    )
                )),
                'is_dynamic' => 1,
                'is_active' => 1
            ),
            array(
                'name' => 'Highly Engaged',
                'description' => 'Contacts with relationship score above 75',
                'segment_type' => 'smart',
                'criteria' => json_encode(array(
                    'logic' => 'AND',
                    'conditions' => array(
                        array('field' => 'relationship_score', 'operator' => '>=', 'value' => 75),
                        array('field' => 'opted_in', 'operator' => '=', 'value' => 1)
                    )
                )),
                'is_dynamic' => 1,
                'is_active' => 1
            ),
            array(
                'name' => 'Needs Attention',
                'description' => 'Contacts with low engagement (score below 30) who haven\'t been contacted in 30+ days',
                'segment_type' => 'smart',
                'criteria' => json_encode(array(
                    'logic' => 'AND',
                    'conditions' => array(
                        array('field' => 'relationship_score', 'operator' => '<', 'value' => 30),
                        array('field' => 'last_interaction_at', 'operator' => 'older_than', 'value' => 30)
                    )
                )),
                'is_dynamic' => 1,
                'is_active' => 1
            ),
            array(
                'name' => 'New Contacts (Last 30 Days)',
                'description' => 'Contacts created in the last 30 days',
                'segment_type' => 'smart',
                'criteria' => json_encode(array(
                    'logic' => 'AND',
                    'conditions' => array(
                        array('field' => 'created_at', 'operator' => 'within', 'value' => 30)
                    )
                )),
                'is_dynamic' => 1,
                'is_active' => 1
            ),
            array(
                'name' => 'Repeat Customers',
                'description' => 'Contacts with 2+ orders',
                'segment_type' => 'smart',
                'criteria' => json_encode(array(
                    'logic' => 'AND',
                    'conditions' => array(
                        array('field' => 'total_orders', 'operator' => '>=', 'value' => 2)
                    )
                )),
                'is_dynamic' => 1,
                'is_active' => 1
            )
        );
        
        foreach ($segments as $segment) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE name = %s",
                $segment['name']
            ));
            
            if (!$existing) {
                $wpdb->insert($table, $segment);
            }
        }
    }
}
