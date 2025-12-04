<?php
/**
 * PTP Comms Hub - Enhanced Contacts Admin Page
 * v4.0.0 - VA Relationship Management
 */
class PTP_Comms_Hub_Admin_Page_Contacts {
    
    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handle_post_request();
        }
        
        switch ($action) {
            case 'new':
            case 'edit':
                self::render_form($action === 'edit' ? intval($_GET['id'] ?? 0) : 0);
                break;
            case 'view':
                self::render_contact_profile(intval($_GET['id'] ?? 0));
                break;
            case 'import':
                self::render_import_form();
                break;
            case 'manage_segments':
                self::render_manage_segments();
                break;
            default:
                self::render_list();
                break;
        }
    }
    
    /**
     * Render contact profile page (relationship management view)
     */
    private static function render_contact_profile($contact_id) {
        global $wpdb;
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            echo '<div class="wrap"><div class="ptp-comms-alert error">Contact not found.</div></div>';
            return;
        }
        
        // Get notes
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as author_name 
             FROM {$wpdb->prefix}ptp_contact_notes n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE n.contact_id = %d 
             ORDER BY n.is_pinned DESC, n.created_at DESC
             LIMIT 20",
            $contact_id
        ));
        
        // Get activity timeline
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_activity_log 
             WHERE contact_id = %d 
             ORDER BY created_at DESC 
             LIMIT 30",
            $contact_id
        ));
        
        // Get registrations/orders
        $registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_registrations 
             WHERE contact_id = %d 
             ORDER BY created_at DESC",
            $contact_id
        ));
        
        // Get reminders
        $reminders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_reminders 
             WHERE contact_id = %d AND status = 'pending'
             ORDER BY due_date ASC",
            $contact_id
        ));
        
        // Get communication history
        $communications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_communication_logs 
             WHERE contact_id = %d 
             ORDER BY created_at DESC 
             LIMIT 20",
            $contact_id
        ));
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div class="ptp-profile-header">
                <a href="?page=ptp-comms-contacts" class="ptp-back-link">
                    ← Back to Contacts
                </a>
                <div class="ptp-profile-actions">
                    <a href="?page=ptp-comms-inbox&contact=<?php echo $contact_id; ?>" class="ptp-comms-button">
                        <span class="dashicons dashicons-format-chat"></span> Send Message
                    </a>
                    <a href="?page=ptp-comms-contacts&action=edit&id=<?php echo $contact_id; ?>" class="ptp-comms-button secondary">
                        <span class="dashicons dashicons-edit"></span> Edit
                    </a>
                </div>
            </div>
            
            <div class="ptp-profile-grid">
                <!-- Left Sidebar - Contact Info -->
                <div class="ptp-profile-sidebar">
                    <div class="ptp-comms-card ptp-contact-card">
                        <div class="ptp-contact-header">
                            <div class="ptp-contact-avatar">
                                <?php echo strtoupper(substr($contact->parent_first_name, 0, 1) . substr($contact->parent_last_name, 0, 1)); ?>
                            </div>
                            <div class="ptp-contact-name">
                                <h2><?php echo esc_html($contact->parent_first_name . ' ' . $contact->parent_last_name); ?></h2>
                                <?php if ($contact->vip_status): ?>
                                <span class="ptp-vip-badge">⭐ VIP</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="ptp-relationship-score-large">
                            <div class="ptp-score-circle <?php echo self::get_score_class($contact->relationship_score); ?>">
                                <span class="score"><?php echo intval($contact->relationship_score); ?></span>
                                <span class="label">Relationship</span>
                            </div>
                        </div>
                        
                        <div class="ptp-contact-details">
                            <div class="ptp-detail-item">
                                <span class="dashicons dashicons-phone"></span>
                                <a href="tel:<?php echo esc_attr($contact->parent_phone); ?>">
                                    <?php echo esc_html(ptp_comms_format_phone($contact->parent_phone)); ?>
                                </a>
                            </div>
                            <?php if ($contact->parent_email): ?>
                            <div class="ptp-detail-item">
                                <span class="dashicons dashicons-email"></span>
                                <a href="mailto:<?php echo esc_attr($contact->parent_email); ?>">
                                    <?php echo esc_html($contact->parent_email); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($contact->city || $contact->state): ?>
                            <div class="ptp-detail-item">
                                <span class="dashicons dashicons-location"></span>
                                <?php echo esc_html(trim($contact->city . ', ' . $contact->state, ', ')); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($contact->child_name): ?>
                            <div class="ptp-detail-item">
                                <span class="dashicons dashicons-admin-users"></span>
                                Child: <?php echo esc_html($contact->child_name); ?>
                                <?php if ($contact->child_age): ?>
                                (Age <?php echo intval($contact->child_age); ?>)
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ptp-contact-stats">
                            <div class="ptp-stat">
                                <span class="value"><?php echo intval($contact->total_orders); ?></span>
                                <span class="label">Orders</span>
                            </div>
                            <div class="ptp-stat">
                                <span class="value">$<?php echo number_format($contact->lifetime_value, 0); ?></span>
                                <span class="label">Lifetime Value</span>
                            </div>
                            <div class="ptp-stat">
                                <span class="value"><?php echo intval($contact->total_interactions); ?></span>
                                <span class="label">Interactions</span>
                            </div>
                        </div>
                        
                        <div class="ptp-contact-meta">
                            <p><strong>Source:</strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $contact->source))); ?></p>
                            <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($contact->created_at)); ?></p>
                            <p><strong>Last Interaction:</strong> 
                                <?php echo $contact->last_interaction_at 
                                    ? human_time_diff(strtotime($contact->last_interaction_at)) . ' ago' 
                                    : 'Never'; ?>
                            </p>
                            <?php if ($contact->hubspot_contact_id): ?>
                            <p><strong>HubSpot:</strong> <span class="ptp-badge success">Synced</span></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="ptp-quick-actions-vertical">
                            <button type="button" class="ptp-comms-button secondary small full-width" id="add-note-btn">
                                <span class="dashicons dashicons-edit"></span> Add Note
                            </button>
                            <button type="button" class="ptp-comms-button secondary small full-width" id="add-reminder-btn">
                                <span class="dashicons dashicons-bell"></span> Set Reminder
                            </button>
                            <?php if (!$contact->vip_status): ?>
                            <button type="button" class="ptp-comms-button secondary small full-width" id="make-vip-btn" data-id="<?php echo $contact_id; ?>">
                                <span class="dashicons dashicons-star-filled"></span> Mark as VIP
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Segments/Tags -->
                    <div class="ptp-comms-card">
                        <h3>Tags & Segments</h3>
                        <div class="ptp-tags">
                            <?php 
                            $segments = $contact->segments ? array_filter(explode(',', $contact->segments)) : array();
                            foreach ($segments as $seg): 
                            ?>
                            <span class="ptp-tag"><?php echo esc_html(trim($seg)); ?></span>
                            <?php endforeach; ?>
                            <button type="button" class="ptp-tag add-tag" id="add-tag-btn">+ Add</button>
                        </div>
                    </div>
                    
                    <!-- Pending Reminders -->
                    <?php if (!empty($reminders)): ?>
                    <div class="ptp-comms-card">
                        <h3>📅 Upcoming Reminders</h3>
                        <div class="ptp-reminder-list">
                            <?php foreach ($reminders as $reminder): ?>
                            <div class="ptp-reminder-item">
                                <span class="ptp-reminder-title"><?php echo esc_html($reminder->title); ?></span>
                                <span class="ptp-reminder-due <?php echo strtotime($reminder->due_date) < time() ? 'overdue' : ''; ?>">
                                    <?php echo date('M j, g:i A', strtotime($reminder->due_date)); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Main Content Area -->
                <div class="ptp-profile-main">
                    <!-- Tabs -->
                    <div class="ptp-profile-tabs">
                        <button class="ptp-tab active" data-tab="notes">Notes (<?php echo count($notes); ?>)</button>
                        <button class="ptp-tab" data-tab="timeline">Timeline</button>
                        <button class="ptp-tab" data-tab="orders">Orders (<?php echo count($registrations); ?>)</button>
                        <button class="ptp-tab" data-tab="messages">Messages</button>
                    </div>
                    
                    <!-- Notes Tab -->
                    <div class="ptp-tab-content active" id="tab-notes">
                        <div class="ptp-comms-card">
                            <div class="ptp-card-header">
                                <h3>Contact Notes</h3>
                                <button class="ptp-comms-button small" id="add-note-btn-2">+ Add Note</button>
                            </div>
                            
                            <?php if (empty($notes)): ?>
                            <div class="ptp-empty-state">
                                <span class="dashicons dashicons-edit" style="font-size: 48px; opacity: 0.3;"></span>
                                <p>No notes yet. Add your first note to start building this relationship!</p>
                            </div>
                            <?php else: ?>
                            <div class="ptp-notes-list">
                                <?php foreach ($notes as $note): ?>
                                <div class="ptp-note-item <?php echo $note->is_pinned ? 'pinned' : ''; ?>">
                                    <?php if ($note->is_pinned): ?>
                                    <span class="ptp-pin-icon">📌</span>
                                    <?php endif; ?>
                                    <div class="ptp-note-header">
                                        <span class="ptp-note-type <?php echo esc_attr($note->note_type); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $note->note_type)); ?>
                                        </span>
                                        <?php if ($note->sentiment && $note->sentiment !== 'neutral'): ?>
                                        <span class="ptp-sentiment <?php echo esc_attr($note->sentiment); ?>">
                                            <?php echo $note->sentiment === 'positive' ? '😊' : '😟'; ?>
                                        </span>
                                        <?php endif; ?>
                                        <span class="ptp-note-meta">
                                            <?php echo esc_html($note->author_name); ?> · 
                                            <?php echo human_time_diff(strtotime($note->created_at)); ?> ago
                                        </span>
                                    </div>
                                    <?php if ($note->title): ?>
                                    <h4 class="ptp-note-title"><?php echo esc_html($note->title); ?></h4>
                                    <?php endif; ?>
                                    <div class="ptp-note-content">
                                        <?php echo nl2br(esc_html($note->content)); ?>
                                    </div>
                                    <?php if ($note->follow_up_date && !$note->follow_up_completed): ?>
                                    <div class="ptp-note-followup">
                                        📅 Follow-up: <?php echo date('M j, Y', strtotime($note->follow_up_date)); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Timeline Tab -->
                    <div class="ptp-tab-content" id="tab-timeline">
                        <div class="ptp-comms-card">
                            <h3>Activity Timeline</h3>
                            <?php if (empty($activities)): ?>
                            <div class="ptp-empty-state">
                                <p>No activity recorded yet.</p>
                            </div>
                            <?php else: ?>
                            <div class="ptp-timeline">
                                <?php foreach ($activities as $activity): ?>
                                <div class="ptp-timeline-item">
                                    <div class="ptp-timeline-icon <?php echo esc_attr($activity->activity_type); ?>">
                                        <?php echo self::get_activity_icon($activity->activity_type); ?>
                                    </div>
                                    <div class="ptp-timeline-content">
                                        <strong><?php echo esc_html($activity->title); ?></strong>
                                        <?php if ($activity->description): ?>
                                        <p><?php echo esc_html($activity->description); ?></p>
                                        <?php endif; ?>
                                        <span class="ptp-timeline-time">
                                            <?php echo date('M j, Y g:i A', strtotime($activity->created_at)); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Orders Tab -->
                    <div class="ptp-tab-content" id="tab-orders">
                        <div class="ptp-comms-card">
                            <h3>Registrations & Orders</h3>
                            <?php if (empty($registrations)): ?>
                            <div class="ptp-empty-state">
                                <p>No orders yet.</p>
                            </div>
                            <?php else: ?>
                            <table class="ptp-comms-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Child</th>
                                        <th>Event Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $reg): ?>
                                    <tr>
                                        <td><?php echo esc_html($reg->product_name); ?></td>
                                        <td><?php echo esc_html($reg->child_name); ?></td>
                                        <td><?php echo $reg->event_date ? date('M j, Y', strtotime($reg->event_date)) : '-'; ?></td>
                                        <td>$<?php echo number_format($reg->line_total, 2); ?></td>
                                        <td>
                                            <span class="ptp-badge <?php echo $reg->registration_status; ?>">
                                                <?php echo ucfirst($reg->registration_status); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Messages Tab -->
                    <div class="ptp-tab-content" id="tab-messages">
                        <div class="ptp-comms-card">
                            <div class="ptp-card-header">
                                <h3>Message History</h3>
                                <a href="?page=ptp-comms-inbox&contact=<?php echo $contact_id; ?>" class="ptp-comms-button small">
                                    Open Conversation
                                </a>
                            </div>
                            <?php if (empty($communications)): ?>
                            <div class="ptp-empty-state">
                                <p>No messages yet.</p>
                            </div>
                            <?php else: ?>
                            <div class="ptp-message-history">
                                <?php foreach ($communications as $msg): ?>
                                <div class="ptp-message-item <?php echo $msg->direction; ?>">
                                    <div class="ptp-message-bubble">
                                        <?php echo esc_html($msg->message_content); ?>
                                    </div>
                                    <span class="ptp-message-time">
                                        <?php echo date('M j, g:i A', strtotime($msg->created_at)); ?>
                                        · <?php echo ucfirst($msg->direction); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add Note Modal -->
        <div id="add-note-modal" class="ptp-modal" style="display: none;">
            <div class="ptp-modal-content">
                <div class="ptp-modal-header">
                    <h3>Add Note</h3>
                    <button type="button" class="ptp-modal-close">&times;</button>
                </div>
                <form id="add-note-form" method="post">
                    <?php wp_nonce_field('ptp_add_note', 'note_nonce'); ?>
                    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                    <input type="hidden" name="add_note" value="1">
                    
                    <div class="ptp-form-group">
                        <label>Note Type</label>
                        <select name="note_type">
                            <option value="general">General</option>
                            <option value="call_summary">Call Summary</option>
                            <option value="meeting">Meeting Notes</option>
                            <option value="feedback">Customer Feedback</option>
                            <option value="concern">Concern/Issue</option>
                            <option value="preference">Preference</option>
                            <option value="follow_up">Follow-up Needed</option>
                        </select>
                    </div>
                    
                    <div class="ptp-form-group">
                        <label>Title (optional)</label>
                        <input type="text" name="title" placeholder="Brief summary...">
                    </div>
                    
                    <div class="ptp-form-group">
                        <label>Note Content</label>
                        <textarea name="content" rows="4" required placeholder="What did you learn about this contact?"></textarea>
                    </div>
                    
                    <div class="ptp-form-row">
                        <div class="ptp-form-group">
                            <label>Sentiment</label>
                            <select name="sentiment">
                                <option value="neutral">Neutral</option>
                                <option value="positive">Positive 😊</option>
                                <option value="negative">Negative 😟</option>
                            </select>
                        </div>
                        <div class="ptp-form-group">
                            <label>Follow-up Date</label>
                            <input type="date" name="follow_up_date">
                        </div>
                    </div>
                    
                    <div class="ptp-form-group">
                        <label>
                            <input type="checkbox" name="is_pinned" value="1"> Pin this note
                        </label>
                        <label style="margin-left: 20px;">
                            <input type="checkbox" name="sync_hubspot" value="1" checked> Sync to HubSpot
                        </label>
                    </div>
                    
                    <div class="ptp-modal-actions">
                        <button type="button" class="ptp-comms-button secondary ptp-modal-close">Cancel</button>
                        <button type="submit" class="ptp-comms-button">Save Note</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Add Reminder Modal -->
        <div id="add-reminder-modal" class="ptp-modal" style="display: none;">
            <div class="ptp-modal-content">
                <div class="ptp-modal-header">
                    <h3>Set Reminder</h3>
                    <button type="button" class="ptp-modal-close">&times;</button>
                </div>
                <form id="add-reminder-form" method="post">
                    <?php wp_nonce_field('ptp_add_reminder', 'reminder_nonce'); ?>
                    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                    <input type="hidden" name="add_reminder" value="1">
                    
                    <div class="ptp-form-group">
                        <label>Reminder Title</label>
                        <input type="text" name="title" required placeholder="e.g., Follow up about camp registration">
                    </div>
                    
                    <div class="ptp-form-group">
                        <label>Description (optional)</label>
                        <textarea name="description" rows="2" placeholder="Additional details..."></textarea>
                    </div>
                    
                    <div class="ptp-form-row">
                        <div class="ptp-form-group">
                            <label>Due Date</label>
                            <input type="datetime-local" name="due_date" required>
                        </div>
                        <div class="ptp-form-group">
                            <label>Priority</label>
                            <select name="priority">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="ptp-modal-actions">
                        <button type="button" class="ptp-comms-button secondary ptp-modal-close">Cancel</button>
                        <button type="submit" class="ptp-comms-button">Create Reminder</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tabs
            $('.ptp-tab').on('click', function() {
                var tab = $(this).data('tab');
                $('.ptp-tab').removeClass('active');
                $(this).addClass('active');
                $('.ptp-tab-content').removeClass('active');
                $('#tab-' + tab).addClass('active');
            });
            
            // Modals
            $('#add-note-btn, #add-note-btn-2').on('click', function() {
                $('#add-note-modal').fadeIn(200);
            });
            
            $('#add-reminder-btn').on('click', function() {
                $('#add-reminder-modal').fadeIn(200);
            });
            
            $('.ptp-modal-close').on('click', function() {
                $(this).closest('.ptp-modal').fadeOut(200);
            });
            
            // VIP Toggle
            $('#make-vip-btn').on('click', function() {
                var btn = $(this);
                var contactId = btn.data('id');
                
                $.post(ajaxurl, {
                    action: 'ptp_toggle_vip',
                    contact_id: contactId,
                    nonce: '<?php echo wp_create_nonce('ptp_vip_action'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render contact list
     */
    private static function render_list() {
        global $wpdb;
        
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 25;
        $offset = ($page - 1) * $per_page;
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $segment_filter = isset($_GET['segment']) ? sanitize_text_field($_GET['segment']) : '';
        $sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        
        $where = "1=1";
        $params = array();
        
        if ($search) {
            $where .= " AND (parent_first_name LIKE %s OR parent_last_name LIKE %s OR parent_email LIKE %s OR parent_phone LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $params = array_merge($params, array($search_like, $search_like, $search_like, $search_like));
        }
        
        if ($segment_filter === 'vip') {
            $where .= " AND vip_status = 1";
        } elseif ($segment_filter === 'needs_attention') {
            $where .= " AND relationship_score < 30 AND (last_interaction_at IS NULL OR last_interaction_at < DATE_SUB(NOW(), INTERVAL 30 DAY))";
        } elseif ($segment_filter === 'opted_in') {
            $where .= " AND opted_in = 1";
        } elseif ($segment_filter === 'opted_out') {
            $where .= " AND opted_out = 1";
        }
        
        $valid_sorts = array('created_at', 'parent_last_name', 'relationship_score', 'lifetime_value', 'last_interaction_at');
        if (!in_array($sort, $valid_sorts)) $sort = 'created_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE $where";
        $total = empty($params) ? $wpdb->get_var($total_query) : $wpdb->get_var($wpdb->prepare($total_query, $params));
        
        $query = "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE $where ORDER BY $sort $order LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $contacts = $wpdb->get_results($wpdb->prepare($query, $params));
        
        $total_pages = ceil($total / $per_page);
        
        // Stats
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts"),
            'opted_in' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1"),
            'vip' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE vip_status = 1"),
            'needs_attention' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE relationship_score < 30")
        );
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div class="ptp-page-header">
                <h1>Contacts</h1>
                <div class="ptp-header-actions">
                    <a href="?page=ptp-comms-contacts&action=import" class="ptp-comms-button secondary">
                        <span class="dashicons dashicons-upload"></span> Import
                    </a>
                    <a href="?page=ptp-comms-contacts&action=new" class="ptp-comms-button">
                        <span class="dashicons dashicons-plus-alt2"></span> Add Contact
                    </a>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="ptp-comms-stats">
                <a href="?page=ptp-comms-contacts" class="ptp-comms-stat-box <?php echo empty($segment_filter) ? 'active' : ''; ?>">
                    <h2><?php echo number_format($stats['total']); ?></h2>
                    <p>Total Contacts</p>
                </a>
                <a href="?page=ptp-comms-contacts&segment=opted_in" class="ptp-comms-stat-box green <?php echo $segment_filter === 'opted_in' ? 'active' : ''; ?>">
                    <h2><?php echo number_format($stats['opted_in']); ?></h2>
                    <p>Opted In</p>
                </a>
                <a href="?page=ptp-comms-contacts&segment=vip" class="ptp-comms-stat-box gold <?php echo $segment_filter === 'vip' ? 'active' : ''; ?>">
                    <h2><?php echo number_format($stats['vip']); ?></h2>
                    <p>VIP Families</p>
                </a>
                <a href="?page=ptp-comms-contacts&segment=needs_attention" class="ptp-comms-stat-box red <?php echo $segment_filter === 'needs_attention' ? 'active' : ''; ?>">
                    <h2><?php echo number_format($stats['needs_attention']); ?></h2>
                    <p>Needs Attention</p>
                </a>
            </div>
            
            <!-- Filters -->
            <div class="ptp-comms-card">
                <form method="get" class="ptp-filter-form">
                    <input type="hidden" name="page" value="ptp-comms-contacts">
                    <div class="ptp-filter-row">
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search contacts..." class="ptp-search-input">
                        <select name="sort">
                            <option value="created_at" <?php selected($sort, 'created_at'); ?>>Date Added</option>
                            <option value="parent_last_name" <?php selected($sort, 'parent_last_name'); ?>>Last Name</option>
                            <option value="relationship_score" <?php selected($sort, 'relationship_score'); ?>>Relationship Score</option>
                            <option value="lifetime_value" <?php selected($sort, 'lifetime_value'); ?>>Lifetime Value</option>
                            <option value="last_interaction_at" <?php selected($sort, 'last_interaction_at'); ?>>Last Interaction</option>
                        </select>
                        <select name="order">
                            <option value="DESC" <?php selected($order, 'DESC'); ?>>Descending</option>
                            <option value="ASC" <?php selected($order, 'ASC'); ?>>Ascending</option>
                        </select>
                        <button type="submit" class="ptp-comms-button">Filter</button>
                    </div>
                </form>
            </div>
            
            <!-- Contacts Table -->
            <div class="ptp-comms-card">
                <?php if (empty($contacts)): ?>
                <div class="ptp-empty-state">
                    <span class="dashicons dashicons-groups" style="font-size: 60px; opacity: 0.3;"></span>
                    <h3>No contacts found</h3>
                    <p>Start by adding your first contact or importing a CSV file.</p>
                </div>
                <?php else: ?>
                <table class="ptp-comms-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="select-all"></th>
                            <th>Contact</th>
                            <th style="width: 100px; text-align: center;">Score</th>
                            <th style="width: 120px;">Phone</th>
                            <th style="width: 100px; text-align: right;">Value</th>
                            <th style="width: 120px;">Last Contact</th>
                            <th style="width: 80px; text-align: center;">Status</th>
                            <th style="width: 120px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td><input type="checkbox" name="contact_ids[]" value="<?php echo $contact->id; ?>"></td>
                            <td>
                                <div class="ptp-contact-cell">
                                    <a href="?page=ptp-comms-contacts&action=view&id=<?php echo $contact->id; ?>" class="ptp-contact-name">
                                        <strong><?php echo esc_html($contact->parent_first_name . ' ' . $contact->parent_last_name); ?></strong>
                                        <?php if ($contact->vip_status): ?>
                                        <span class="ptp-vip-badge small">⭐</span>
                                        <?php endif; ?>
                                    </a>
                                    <?php if ($contact->parent_email): ?>
                                    <span class="ptp-contact-email"><?php echo esc_html($contact->parent_email); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="ptp-score-badge <?php echo self::get_score_class($contact->relationship_score); ?>">
                                    <?php echo intval($contact->relationship_score); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(ptp_comms_format_phone($contact->parent_phone)); ?></td>
                            <td style="text-align: right;">$<?php echo number_format($contact->lifetime_value, 0); ?></td>
                            <td>
                                <?php echo $contact->last_interaction_at 
                                    ? human_time_diff(strtotime($contact->last_interaction_at)) . ' ago'
                                    : '<span class="ptp-muted">Never</span>'; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($contact->opted_out): ?>
                                <span class="ptp-badge error">Opted Out</span>
                                <?php elseif ($contact->opted_in): ?>
                                <span class="ptp-badge success">Opted In</span>
                                <?php else: ?>
                                <span class="ptp-badge warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="?page=ptp-comms-contacts&action=view&id=<?php echo $contact->id; ?>" class="ptp-icon-btn" title="View Profile">
                                    <span class="dashicons dashicons-visibility"></span>
                                </a>
                                <a href="?page=ptp-comms-inbox&contact=<?php echo $contact->id; ?>" class="ptp-icon-btn" title="Send Message">
                                    <span class="dashicons dashicons-format-chat"></span>
                                </a>
                                <a href="?page=ptp-comms-contacts&action=edit&id=<?php echo $contact->id; ?>" class="ptp-icon-btn" title="Edit">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="ptp-pagination">
                    <?php
                    $base_url = admin_url('admin.php?page=ptp-comms-contacts');
                    if ($search) $base_url .= '&s=' . urlencode($search);
                    if ($segment_filter) $base_url .= '&segment=' . urlencode($segment_filter);
                    $base_url .= '&sort=' . $sort . '&order=' . $order;
                    
                    if ($page > 1): ?>
                    <a href="<?php echo $base_url . '&paged=' . ($page - 1); ?>" class="ptp-comms-button small secondary">← Previous</a>
                    <?php endif; ?>
                    
                    <span class="ptp-page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="<?php echo $base_url . '&paged=' . ($page + 1); ?>" class="ptp-comms-button small secondary">Next →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get score class for styling
     */
    private static function get_score_class($score) {
        if ($score >= 75) return 'excellent';
        if ($score >= 50) return 'good';
        if ($score >= 25) return 'fair';
        return 'poor';
    }
    
    /**
     * Get activity icon
     */
    private static function get_activity_icon($type) {
        $icons = array(
            'sms_sent' => '💬',
            'sms_received' => '📨',
            'call_made' => '📞',
            'call_received' => '📲',
            'note_added' => '📝',
            'order_placed' => '🛒',
            'registration' => '✅',
            'reminder_completed' => '☑️',
            'contact_created' => '👤',
            'segment_added' => '🏷️',
            'vip_upgrade' => '⭐'
        );
        return isset($icons[$type]) ? $icons[$type] : '📌';
    }
    
    /**
     * Handle POST requests
     */
    private static function handle_post_request() {
        global $wpdb;
        
        // Add Note
        if (isset($_POST['add_note']) && wp_verify_nonce($_POST['note_nonce'], 'ptp_add_note')) {
            $contact_id = intval($_POST['contact_id']);
            $note_data = array(
                'contact_id' => $contact_id,
                'user_id' => get_current_user_id(),
                'note_type' => sanitize_text_field($_POST['note_type']),
                'title' => sanitize_text_field($_POST['title']),
                'content' => sanitize_textarea_field($_POST['content']),
                'sentiment' => sanitize_text_field($_POST['sentiment']),
                'is_pinned' => isset($_POST['is_pinned']) ? 1 : 0,
                'follow_up_date' => !empty($_POST['follow_up_date']) ? sanitize_text_field($_POST['follow_up_date']) : null,
                'created_at' => current_time('mysql')
            );
            
            $wpdb->insert($wpdb->prefix . 'ptp_contact_notes', $note_data);
            $note_id = $wpdb->insert_id;
            
            // Update contact notes count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ptp_contacts SET notes_count = notes_count + 1 WHERE id = %d",
                $contact_id
            ));
            
            // Log activity
            if (class_exists('PTP_Comms_Hub_Activity_Log')) {
                PTP_Comms_Hub_Activity_Log::log($contact_id, 'note_added', 'Note added: ' . substr($_POST['content'], 0, 50));
            }
            
            // Sync to HubSpot
            if (isset($_POST['sync_hubspot']) && class_exists('PTP_Comms_Hub_HubSpot_Sync')) {
                PTP_Comms_Hub_HubSpot_Sync::queue_note_sync($contact_id, $note_id);
            }
            
            // Create follow-up reminder if date set
            if (!empty($_POST['follow_up_date']) && class_exists('PTP_Comms_Hub_Reminders')) {
                PTP_Comms_Hub_Reminders::create(array(
                    'contact_id' => $contact_id,
                    'title' => 'Follow up: ' . ($_POST['title'] ?: 'Note follow-up'),
                    'due_date' => $_POST['follow_up_date'] . ' 09:00:00',
                    'related_note_id' => $note_id
                ));
            }
            
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-contacts&action=view&id=' . $contact_id . '&note_added=1'));
            exit;
        }
        
        // Add Reminder
        if (isset($_POST['add_reminder']) && wp_verify_nonce($_POST['reminder_nonce'], 'ptp_add_reminder')) {
            $contact_id = intval($_POST['contact_id']);
            
            $wpdb->insert($wpdb->prefix . 'ptp_reminders', array(
                'contact_id' => $contact_id,
                'user_id' => get_current_user_id(),
                'assigned_to' => get_current_user_id(),
                'title' => sanitize_text_field($_POST['title']),
                'description' => sanitize_textarea_field($_POST['description']),
                'due_date' => sanitize_text_field($_POST['due_date']),
                'priority' => sanitize_text_field($_POST['priority']),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ));
            
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-contacts&action=view&id=' . $contact_id . '&reminder_added=1'));
            exit;
        }
    }
    
    /**
     * Render contact form (new/edit)
     */
    private static function render_form($contact_id = 0) {
        global $wpdb;
        
        $contact = $contact_id ? $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        )) : new stdClass();
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <h1><?php echo $contact_id ? 'Edit Contact' : 'Add New Contact'; ?></h1>
            
            <div class="ptp-comms-card" style="max-width: 800px;">
                <form method="post">
                    <?php wp_nonce_field('ptp_comms_contact_action'); ?>
                    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                    
                    <h3>Parent Information</h3>
                    <table class="form-table">
                        <tr>
                            <th><label>First Name</label></th>
                            <td><input type="text" name="parent_first_name" class="regular-text" value="<?php echo esc_attr($contact->parent_first_name ?? ''); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label>Last Name</label></th>
                            <td><input type="text" name="parent_last_name" class="regular-text" value="<?php echo esc_attr($contact->parent_last_name ?? ''); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label>Phone</label></th>
                            <td><input type="tel" name="parent_phone" class="regular-text" value="<?php echo esc_attr($contact->parent_phone ?? ''); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label>Email</label></th>
                            <td><input type="email" name="parent_email" class="regular-text" value="<?php echo esc_attr($contact->parent_email ?? ''); ?>"></td>
                        </tr>
                    </table>
                    
                    <h3>Child Information</h3>
                    <table class="form-table">
                        <tr>
                            <th><label>Child Name</label></th>
                            <td><input type="text" name="child_name" class="regular-text" value="<?php echo esc_attr($contact->child_name ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label>Child Age</label></th>
                            <td><input type="number" name="child_age" min="0" max="18" value="<?php echo esc_attr($contact->child_age ?? ''); ?>"></td>
                        </tr>
                    </table>
                    
                    <h3>Location</h3>
                    <table class="form-table">
                        <tr>
                            <th><label>City</label></th>
                            <td><input type="text" name="city" class="regular-text" value="<?php echo esc_attr($contact->city ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label>State</label></th>
                            <td>
                                <select name="state">
                                    <option value="">Select State</option>
                                    <?php foreach (array('PA', 'NJ', 'DE', 'MD', 'NY', 'CT', 'VA', 'WV', 'DC') as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php selected($contact->state ?? '', $st); ?>><?php echo $st; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>ZIP Code</label></th>
                            <td><input type="text" name="zip_code" class="small-text" value="<?php echo esc_attr($contact->zip_code ?? ''); ?>"></td>
                        </tr>
                    </table>
                    
                    <h3>Status & Preferences</h3>
                    <table class="form-table">
                        <tr>
                            <th>SMS Status</th>
                            <td>
                                <label><input type="checkbox" name="opted_in" value="1" <?php checked($contact->opted_in ?? 0, 1); ?>> Opted In</label>
                                <br>
                                <label><input type="checkbox" name="opted_out" value="1" <?php checked($contact->opted_out ?? 0, 1); ?>> Opted Out</label>
                            </td>
                        </tr>
                        <tr>
                            <th>VIP Status</th>
                            <td><label><input type="checkbox" name="vip_status" value="1" <?php checked($contact->vip_status ?? 0, 1); ?>> VIP Customer</label></td>
                        </tr>
                        <tr>
                            <th><label>Preferred Contact Method</label></th>
                            <td>
                                <select name="preferred_contact_method">
                                    <option value="sms" <?php selected($contact->preferred_contact_method ?? 'sms', 'sms'); ?>>SMS</option>
                                    <option value="email" <?php selected($contact->preferred_contact_method ?? '', 'email'); ?>>Email</option>
                                    <option value="call" <?php selected($contact->preferred_contact_method ?? '', 'call'); ?>>Phone Call</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="save_contact" value="1" class="ptp-comms-button">
                            <?php echo $contact_id ? 'Update Contact' : 'Add Contact'; ?>
                        </button>
                        <a href="?page=ptp-comms-contacts" class="ptp-comms-button secondary">Cancel</a>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render import form
     */
    private static function render_import_form() {
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <h1>Import Contacts</h1>
            
            <div class="ptp-comms-card" style="max-width: 700px;">
                <h3>CSV Import</h3>
                <p>Upload a CSV file with contact information. Required column: <code>parent_phone</code> or <code>phone</code></p>
                
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('ptp_comms_contact_action'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label>CSV File</label></th>
                            <td>
                                <input type="file" name="csv_file" accept=".csv" required>
                                <p class="description">Max file size: 2MB</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Options</th>
                            <td>
                                <label><input type="checkbox" name="auto_opt_in" value="1"> Auto opt-in contacts for SMS</label>
                                <br>
                                <label><input type="checkbox" name="skip_duplicates" value="1" checked> Skip duplicate phone numbers</label>
                                <br>
                                <label><input type="checkbox" name="sync_hubspot" value="1"> Sync to HubSpot after import</label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="import_csv" value="1" class="ptp-comms-button">Import Contacts</button>
                    </p>
                </form>
                
                <hr>
                
                <h4>Supported Columns</h4>
                <p><code>parent_phone</code>, <code>parent_first_name</code>, <code>parent_last_name</code>, <code>parent_email</code>, <code>child_name</code>, <code>child_age</code>, <code>state</code>, <code>city</code>, <code>zip_code</code></p>
                
                <p><a href="?page=ptp-comms-contacts">← Back to Contacts</a></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render manage segments
     */
    private static function render_manage_segments() {
        // Redirect to new segments page
        wp_safe_redirect(admin_url('admin.php?page=ptp-comms-segments'));
        exit;
    }
}
