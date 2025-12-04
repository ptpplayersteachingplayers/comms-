<?php
/**
 * PTP Comms Hub - Segments Admin Page
 * v4.0.0 - Advanced Segment Management
 */
class PTP_Comms_Hub_Admin_Page_Segments {
    
    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        // Handle POST submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handle_post_request();
        }
        
        switch ($action) {
            case 'new':
            case 'edit':
                self::render_form($action === 'edit' ? intval($_GET['id'] ?? 0) : 0);
                break;
            case 'view':
                self::render_detail(intval($_GET['id'] ?? 0));
                break;
            default:
                self::render_list();
                break;
        }
    }
    
    private static function handle_post_request() {
        if (!isset($_POST['_wpnonce'])) return;
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ptp_comms_segment_action')) {
            wp_die('Security check failed.');
        }
        
        if (isset($_POST['save_segment'])) {
            self::handle_save_segment();
        } elseif (isset($_POST['delete_segment'])) {
            PTP_Comms_Hub_Saved_Segments::delete(intval($_POST['segment_id']));
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-segments&message=deleted'));
            exit;
        } elseif (isset($_POST['duplicate_segment'])) {
            $new_id = PTP_Comms_Hub_Saved_Segments::duplicate(intval($_POST['segment_id']));
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-segments&action=edit&id=' . $new_id . '&message=duplicated'));
            exit;
        } elseif (isset($_POST['refresh_count'])) {
            PTP_Comms_Hub_Saved_Segments::update_cached_count(intval($_POST['segment_id']));
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-segments&action=view&id=' . intval($_POST['segment_id']) . '&message=refreshed'));
            exit;
        }
    }
    
    private static function handle_save_segment() {
        $segment_id = intval($_POST['segment_id'] ?? 0);
        
        // Build criteria from form
        $criteria = array(
            'logic' => sanitize_text_field($_POST['logic'] ?? 'AND'),
            'conditions' => array()
        );
        
        if (isset($_POST['conditions']) && is_array($_POST['conditions'])) {
            foreach ($_POST['conditions'] as $cond) {
                if (!empty($cond['field'])) {
                    $criteria['conditions'][] = array(
                        'field' => sanitize_text_field($cond['field']),
                        'operator' => sanitize_text_field($cond['operator'] ?? '='),
                        'value' => sanitize_text_field($cond['value'] ?? '')
                    );
                }
            }
        }
        
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'segment_type' => sanitize_text_field($_POST['segment_type'] ?? 'smart'),
            'criteria' => $criteria,
            'is_dynamic' => isset($_POST['is_dynamic']) ? 1 : 0
        );
        
        if ($segment_id > 0) {
            PTP_Comms_Hub_Saved_Segments::update($segment_id, $data);
            $message = 'updated';
        } else {
            $segment_id = PTP_Comms_Hub_Saved_Segments::create($data);
            $message = 'created';
        }
        
        wp_safe_redirect(admin_url('admin.php?page=ptp-comms-segments&action=view&id=' . $segment_id . '&message=' . $message));
        exit;
    }
    
    private static function render_list() {
        $segments = PTP_Comms_Hub_Saved_Segments::get_all(array('active_only' => false));
        $segment_types = PTP_Comms_Hub_Saved_Segments::get_segment_types();
        
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1>Saved Segments</h1>
                <a href="?page=ptp-comms-segments&action=new" class="ptp-comms-button">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span> New Segment
                </a>
            </div>
            
            <?php if ($message): ?>
            <div class="notice notice-success is-dismissible">
                <p>Segment <?php echo esc_html($message); ?> successfully!</p>
            </div>
            <?php endif; ?>
            
            <div class="ptp-comms-card">
                <p style="margin-top: 0;">
                    Segments allow you to target specific groups of contacts for campaigns, automations, and reporting. 
                    Smart segments automatically update based on criteria, while static segments require manual membership.
                </p>
                
                <?php if (empty($segments)): ?>
                <div class="ptp-comms-empty-state">
                    <span class="dashicons dashicons-groups" style="font-size: 60px; opacity: 0.3;"></span>
                    <h3>No segments yet</h3>
                    <p>Create segments to target specific groups of contacts.</p>
                    <a href="?page=ptp-comms-segments&action=new" class="ptp-comms-button">Create Segment</a>
                </div>
                <?php else: ?>
                <table class="ptp-comms-table">
                    <thead>
                        <tr>
                            <th>Segment Name</th>
                            <th style="width: 120px;">Type</th>
                            <th style="width: 100px;">Contacts</th>
                            <th style="width: 150px;">Last Updated</th>
                            <th style="width: 80px;">Status</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($segments as $segment): ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="?page=ptp-comms-segments&action=view&id=<?php echo $segment->id; ?>">
                                        <?php echo esc_html($segment->name); ?>
                                    </a>
                                </strong>
                                <?php if ($segment->description): ?>
                                <br><small style="color: #666;"><?php echo esc_html(substr($segment->description, 0, 60)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ptp-comms-badge <?php echo $segment->segment_type === 'smart' ? 'info' : 'secondary'; ?>">
                                    <?php echo esc_html($segment_types[$segment->segment_type] ?? $segment->segment_type); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <strong><?php echo number_format($segment->cached_count); ?></strong>
                            </td>
                            <td>
                                <?php echo $segment->cache_updated_at ? date('M j, Y g:i A', strtotime($segment->cache_updated_at)) : '—'; ?>
                            </td>
                            <td>
                                <span class="ptp-comms-badge <?php echo $segment->is_active ? 'success' : 'secondary'; ?>">
                                    <?php echo $segment->is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?page=ptp-comms-segments&action=view&id=<?php echo $segment->id; ?>" class="ptp-comms-button small secondary">View</a>
                                <a href="?page=ptp-comms-segments&action=edit&id=<?php echo $segment->id; ?>" class="ptp-comms-button small secondary">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private static function render_form($segment_id = 0) {
        $segment = $segment_id ? PTP_Comms_Hub_Saved_Segments::get($segment_id) : null;
        $segment_types = PTP_Comms_Hub_Saved_Segments::get_segment_types();
        $operators = PTP_Comms_Hub_Saved_Segments::get_operators();
        $available_fields = PTP_Comms_Hub_Saved_Segments::get_available_fields();
        
        $criteria = $segment && $segment->criteria ? $segment->criteria : array('logic' => 'AND', 'conditions' => array());
        
        // Ensure at least one empty condition for new segments
        if (empty($criteria['conditions'])) {
            $criteria['conditions'][] = array('field' => '', 'operator' => '=', 'value' => '');
        }
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-segments" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> Back
                </a>
            </div>
            
            <div class="ptp-comms-card">
                <h2><?php echo $segment_id ? 'Edit Segment' : 'Create New Segment'; ?></h2>
                
                <form method="post" id="segment-form">
                    <?php wp_nonce_field('ptp_comms_segment_action'); ?>
                    <input type="hidden" name="segment_id" value="<?php echo $segment_id; ?>">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="name">Segment Name *</label></th>
                            <td>
                                <input type="text" name="name" id="name" class="regular-text" required
                                       value="<?php echo esc_attr($segment->name ?? ''); ?>"
                                       placeholder="e.g., VIP Customers, PA Contacts, High Engagement">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="description">Description</label></th>
                            <td>
                                <textarea name="description" id="description" rows="2" class="large-text"
                                          placeholder="What is this segment for?"><?php echo esc_textarea($segment->description ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="segment_type">Segment Type</label></th>
                            <td>
                                <select name="segment_type" id="segment_type">
                                    <?php foreach ($segment_types as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($segment->segment_type ?? 'smart', $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Smart segments automatically include contacts matching the criteria.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <hr style="margin: 30px 0;">
                    
                    <h3>Segment Criteria</h3>
                    <p>Define the conditions that contacts must match to be included in this segment.</p>
                    
                    <div style="margin-bottom: 20px;">
                        <label>
                            <strong>Match</strong>
                            <select name="logic" style="margin: 0 10px;">
                                <option value="AND" <?php selected($criteria['logic'] ?? 'AND', 'AND'); ?>>ALL</option>
                                <option value="OR" <?php selected($criteria['logic'] ?? 'AND', 'OR'); ?>>ANY</option>
                            </select>
                            <strong>of the following conditions:</strong>
                        </label>
                    </div>
                    
                    <div id="conditions-container">
                        <?php foreach ($criteria['conditions'] as $index => $condition): ?>
                        <div class="condition-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #f9f9f9; padding: 10px; border-radius: 4px;">
                            <select name="conditions[<?php echo $index; ?>][field]" class="condition-field" style="width: 200px;">
                                <option value="">— Select Field —</option>
                                <?php foreach ($available_fields as $group_key => $group): ?>
                                <optgroup label="<?php echo esc_attr($group['label']); ?>">
                                    <?php foreach ($group['fields'] as $field_key => $field_label): ?>
                                    <option value="<?php echo esc_attr($field_key); ?>" <?php selected($condition['field'] ?? '', $field_key); ?>>
                                        <?php echo esc_html($field_label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                            
                            <select name="conditions[<?php echo $index; ?>][operator]" class="condition-operator" style="width: 180px;">
                                <?php foreach ($operators as $op_key => $op_label): ?>
                                <option value="<?php echo esc_attr($op_key); ?>" <?php selected($condition['operator'] ?? '=', $op_key); ?>>
                                    <?php echo esc_html($op_label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <input type="text" name="conditions[<?php echo $index; ?>][value]" class="condition-value"
                                   value="<?php echo esc_attr($condition['value'] ?? ''); ?>"
                                   placeholder="Value" style="flex: 1;">
                            
                            <button type="button" class="ptp-comms-button small secondary remove-condition" title="Remove">✕</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <p>
                        <button type="button" id="add-condition" class="ptp-comms-button secondary small">
                            <span class="dashicons dashicons-plus" style="vertical-align: middle;"></span> Add Condition
                        </button>
                    </p>
                    
                    <hr style="margin: 30px 0;">
                    
                    <p>
                        <button type="submit" name="save_segment" value="1" class="ptp-comms-button">
                            <?php echo $segment_id ? 'Update Segment' : 'Create Segment'; ?>
                        </button>
                        <?php if ($segment_id): ?>
                        <button type="submit" name="duplicate_segment" value="1" class="ptp-comms-button secondary">Duplicate</button>
                        <button type="submit" name="delete_segment" value="1" class="ptp-comms-button secondary" 
                                onclick="return confirm('Delete this segment? This cannot be undone.');">Delete</button>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var conditionIndex = <?php echo count($criteria['conditions']); ?>;
            
            // Add condition
            $('#add-condition').on('click', function() {
                var html = `
                <div class="condition-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #f9f9f9; padding: 10px; border-radius: 4px;">
                    <select name="conditions[${conditionIndex}][field]" class="condition-field" style="width: 200px;">
                        <option value="">— Select Field —</option>
                        <?php foreach ($available_fields as $group_key => $group): ?>
                        <optgroup label="<?php echo esc_attr($group['label']); ?>">
                            <?php foreach ($group['fields'] as $field_key => $field_label): ?>
                            <option value="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field_label); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <select name="conditions[${conditionIndex}][operator]" class="condition-operator" style="width: 180px;">
                        <?php foreach ($operators as $op_key => $op_label): ?>
                        <option value="<?php echo esc_attr($op_key); ?>"><?php echo esc_html($op_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="conditions[${conditionIndex}][value]" class="condition-value" placeholder="Value" style="flex: 1;">
                    <button type="button" class="ptp-comms-button small secondary remove-condition" title="Remove">✕</button>
                </div>`;
                
                $('#conditions-container').append(html);
                conditionIndex++;
            });
            
            // Remove condition
            $(document).on('click', '.remove-condition', function() {
                if ($('.condition-row').length > 1) {
                    $(this).closest('.condition-row').remove();
                } else {
                    alert('You need at least one condition.');
                }
            });
        });
        </script>
        <?php
    }
    
    private static function render_detail($segment_id) {
        $segment = PTP_Comms_Hub_Saved_Segments::get($segment_id);
        
        if (!$segment) {
            echo '<div class="wrap"><p>Segment not found.</p></div>';
            return;
        }
        
        $segment_types = PTP_Comms_Hub_Saved_Segments::get_segment_types();
        $available_fields = PTP_Comms_Hub_Saved_Segments::get_available_fields();
        $operators = PTP_Comms_Hub_Saved_Segments::get_operators();
        
        // Get contacts (limited for display)
        $contacts = PTP_Comms_Hub_Saved_Segments::get_contacts($segment_id, 100, 0);
        $total_count = PTP_Comms_Hub_Saved_Segments::count_contacts($segment_id);
        
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        
        // Flatten fields for lookup
        $field_labels = array();
        foreach ($available_fields as $group) {
            foreach ($group['fields'] as $key => $label) {
                $field_labels[$key] = $label;
            }
        }
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-segments" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> Back
                </a>
            </div>
            
            <?php if ($message): ?>
            <div class="notice notice-success is-dismissible">
                <p>Segment <?php echo esc_html($message); ?> successfully!</p>
            </div>
            <?php endif; ?>
            
            <div class="ptp-comms-card" style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h2 style="margin: 0 0 10px 0;"><?php echo esc_html($segment->name); ?></h2>
                        <?php if ($segment->description): ?>
                        <p style="margin: 0 0 10px 0; color: #666;"><?php echo esc_html($segment->description); ?></p>
                        <?php endif; ?>
                        <p style="margin: 0;">
                            <span class="ptp-comms-badge <?php echo $segment->segment_type === 'smart' ? 'info' : 'secondary'; ?>">
                                <?php echo esc_html($segment_types[$segment->segment_type] ?? $segment->segment_type); ?>
                            </span>
                            <span class="ptp-comms-badge <?php echo $segment->is_active ? 'success' : 'secondary'; ?>">
                                <?php echo $segment->is_active ? 'Active' : 'Inactive'; ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <a href="?page=ptp-comms-segments&action=edit&id=<?php echo $segment->id; ?>" class="ptp-comms-button secondary">Edit Segment</a>
                        <a href="?page=ptp-comms-campaigns&action=new&segment_id=<?php echo $segment->id; ?>" class="ptp-comms-button">Send Campaign</a>
                    </div>
                </div>
                
                <hr style="margin: 20px 0;">
                
                <div style="display: flex; gap: 40px;">
                    <div>
                        <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666; text-transform: uppercase;">Total Contacts</h3>
                        <p style="margin: 0; font-size: 36px; font-weight: 600;"><?php echo number_format($total_count); ?></p>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666; text-transform: uppercase;">Last Updated</h3>
                        <p style="margin: 0; font-size: 14px;">
                            <?php echo $segment->cache_updated_at ? date('M j, Y g:i A', strtotime($segment->cache_updated_at)) : 'Never'; ?>
                        </p>
                        <form method="post" style="margin-top: 5px;">
                            <?php wp_nonce_field('ptp_comms_segment_action'); ?>
                            <input type="hidden" name="segment_id" value="<?php echo $segment->id; ?>">
                            <button type="submit" name="refresh_count" value="1" class="ptp-comms-button small secondary">Refresh Count</button>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($segment->criteria['conditions'])): ?>
                <hr style="margin: 20px 0;">
                <h3 style="margin-bottom: 15px;">Criteria</h3>
                <p style="margin-bottom: 10px;">
                    <strong>Match <?php echo strtolower($segment->criteria['logic'] ?? 'AND') === 'and' ? 'ALL' : 'ANY'; ?> of:</strong>
                </p>
                <ul style="margin: 0; padding: 0 0 0 20px;">
                    <?php foreach ($segment->criteria['conditions'] as $cond): ?>
                    <li style="margin-bottom: 5px;">
                        <strong><?php echo esc_html($field_labels[$cond['field']] ?? $cond['field']); ?></strong>
                        <em><?php echo esc_html($operators[$cond['operator']] ?? $cond['operator']); ?></em>
                        "<?php echo esc_html($cond['value']); ?>"
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            
            <!-- Contacts List -->
            <div class="ptp-comms-card">
                <h3 style="margin-top: 0;">Contacts in Segment <?php if ($total_count > 100) echo '(showing first 100)'; ?></h3>
                
                <?php if (empty($contacts)): ?>
                <p style="color: #666;">No contacts match this segment's criteria.</p>
                <?php else: ?>
                <table class="ptp-comms-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Child</th>
                            <th>State</th>
                            <th>Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td>
                                <a href="?page=ptp-comms-contacts&action=edit&id=<?php echo $contact->id; ?>">
                                    <?php echo esc_html($contact->parent_first_name . ' ' . $contact->parent_last_name); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($contact->parent_phone); ?></td>
                            <td><?php echo esc_html($contact->parent_email); ?></td>
                            <td><?php echo esc_html($contact->child_name); ?></td>
                            <td><?php echo esc_html($contact->state); ?></td>
                            <td>
                                <span class="ptp-comms-badge <?php 
                                    if ($contact->relationship_score >= 70) echo 'success';
                                    elseif ($contact->relationship_score >= 40) echo 'info';
                                    else echo 'warning';
                                ?>">
                                    <?php echo $contact->relationship_score; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?page=ptp-comms-inbox&contact=<?php echo $contact->id; ?>" class="ptp-comms-button small secondary">Message</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
