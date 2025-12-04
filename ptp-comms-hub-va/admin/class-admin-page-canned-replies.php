<?php
/**
 * PTP Communications Hub - Canned Replies Admin Page
 * Manage quick response templates/snippets
 * v3.4.0
 */
class PTP_Comms_Hub_Admin_Page_Canned_Replies {
    
    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        // Handle POST submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('ptp_comms_canned_reply');
            
            if (isset($_POST['save_reply'])) {
                self::handle_save($id);
            } elseif (isset($_POST['delete_reply'])) {
                self::handle_delete($id);
            } elseif (isset($_POST['install_defaults'])) {
                PTP_Comms_Hub_Canned_Replies::install_defaults();
                wp_safe_redirect(admin_url('admin.php?page=ptp-comms-canned-replies&message=defaults_installed'));
                exit;
            }
        }
        
        if ($action === 'new' || $action === 'edit') {
            self::render_form($id);
        } else {
            self::render_list();
        }
    }
    
    private static function render_list() {
        $replies = PTP_Comms_Hub_Canned_Replies::get_all();
        $categories = PTP_Comms_Hub_Canned_Replies::get_categories();
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin: 0;">
                    <span class="dashicons dashicons-format-chat" style="color: #FCB900; vertical-align: middle;"></span>
                    Canned Replies
                </h1>
                <div style="display: flex; gap: 10px;">
                    <?php if (empty($replies)): ?>
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field('ptp_comms_canned_reply'); ?>
                        <button type="submit" name="install_defaults" class="button">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            Install Default Replies
                        </button>
                    </form>
                    <?php endif; ?>
                    <a href="?page=ptp-comms-canned-replies&action=new" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                        Add Reply
                    </a>
                </div>
            </div>
            
            <?php if ($message === 'saved'): ?>
            <div class="notice notice-success"><p>Canned reply saved successfully!</p></div>
            <?php elseif ($message === 'deleted'): ?>
            <div class="notice notice-success"><p>Canned reply deleted.</p></div>
            <?php elseif ($message === 'defaults_installed'): ?>
            <div class="notice notice-success"><p>Default canned replies installed!</p></div>
            <?php endif; ?>
            
            <div class="ptp-comms-card" style="margin-bottom: 20px;">
                <p style="margin: 0; color: #666;">
                    <strong>💡 Tip:</strong> Use canned replies for quick responses in the inbox. 
                    Type <code>/shortcut</code> in the message field to auto-insert a reply.
                </p>
            </div>
            
            <?php if (empty($replies)): ?>
            <div class="ptp-comms-card">
                <div style="text-align: center; padding: 60px 20px;">
                    <span class="dashicons dashicons-format-chat" style="font-size: 80px; opacity: 0.2; color: #FCB900;"></span>
                    <h3>No canned replies yet</h3>
                    <p style="color: #666;">Create quick replies to speed up your inbox responses.</p>
                    <a href="?page=ptp-comms-canned-replies&action=new" class="button button-primary">Create First Reply</a>
                </div>
            </div>
            <?php else: ?>
            <div class="ptp-comms-card">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Name</th>
                            <th style="width: 100px;">Shortcut</th>
                            <th>Content Preview</th>
                            <th style="width: 120px;">Category</th>
                            <th style="width: 80px; text-align: center;">Status</th>
                            <th style="width: 100px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($replies as $reply): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($reply->name); ?></strong>
                            </td>
                            <td>
                                <?php if ($reply->shortcut): ?>
                                <code style="background: #f0f0f1; padding: 2px 8px; border-radius: 3px;">/<?php echo esc_html($reply->shortcut); ?></code>
                                <?php else: ?>
                                <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #666;">
                                <?php echo esc_html(wp_trim_words($reply->content, 15, '...')); ?>
                            </td>
                            <td>
                                <span class="ptp-comms-badge secondary">
                                    <?php echo esc_html($categories[$reply->category] ?? ucfirst($reply->category)); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($reply->is_active): ?>
                                <span class="ptp-comms-badge success">Active</span>
                                <?php else: ?>
                                <span class="ptp-comms-badge secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="?page=ptp-comms-canned-replies&action=edit&id=<?php echo $reply->id; ?>" 
                                   class="button button-small">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .ptp-comms-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .ptp-comms-badge.success { background: #d4edda; color: #155724; }
        .ptp-comms-badge.secondary { background: #e2e3e5; color: #383d41; }
        </style>
        <?php
    }
    
    private static function render_form($id = 0) {
        $reply = null;
        if ($id > 0) {
            $reply = PTP_Comms_Hub_Canned_Replies::get($id);
            if (!$reply) {
                echo '<div class="wrap"><p>Reply not found.</p></div>';
                return;
            }
        }
        
        $categories = PTP_Comms_Hub_Canned_Replies::get_categories();
        
        ?>
        <div class="wrap ptp-comms-wrap ptp-comms-admin">
            <div style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-canned-replies" class="button">
                    <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span>
                    Back to Canned Replies
                </a>
            </div>
            
            <div class="ptp-comms-card" style="max-width: 800px;">
                <h1 style="margin-top: 0;">
                    <?php echo $id ? 'Edit Canned Reply' : 'Add New Canned Reply'; ?>
                </h1>
                
                <form method="post">
                    <?php wp_nonce_field('ptp_comms_canned_reply'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="name">Name *</label></th>
                            <td>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo $reply ? esc_attr($reply->name) : ''; ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description">A descriptive name for this reply (e.g., "Weather Cancellation")</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="shortcut">Shortcut</label></th>
                            <td>
                                <input type="text" 
                                       id="shortcut" 
                                       name="shortcut" 
                                       value="<?php echo $reply ? esc_attr($reply->shortcut) : ''; ?>" 
                                       class="regular-text"
                                       pattern="[a-z0-9_]+"
                                       style="max-width: 200px;">
                                <p class="description">Optional. Type <code>/shortcut</code> in inbox to insert this reply. Use lowercase letters, numbers, and underscores only.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="content">Content *</label></th>
                            <td>
                                <textarea id="content" 
                                          name="content" 
                                          rows="6" 
                                          class="large-text code" 
                                          required><?php echo $reply ? esc_textarea($reply->content) : ''; ?></textarea>
                                <p class="description">
                                    Use variables: <code>{parent_name}</code>, <code>{child_name}</code>, <code>{event_name}</code>, <code>{event_date}</code>, <code>{event_location}</code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="category">Category</label></th>
                            <td>
                                <select id="category" name="category">
                                    <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" 
                                            <?php selected($reply ? $reply->category : 'general', $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="is_active">Status</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="is_active" 
                                           value="1" 
                                           <?php checked(!$reply || $reply->is_active); ?>>
                                    Active (show in inbox dropdown)
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="save_reply" class="button button-primary">
                            <?php echo $id ? 'Update Reply' : 'Create Reply'; ?>
                        </button>
                        
                        <?php if ($id): ?>
                        <button type="submit" 
                                name="delete_reply" 
                                class="button" 
                                style="color: #dc3232;" 
                                onclick="return confirm('Are you sure you want to delete this reply?');">
                            Delete
                        </button>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    private static function handle_save($id) {
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'shortcut' => sanitize_key($_POST['shortcut']),
            'content' => sanitize_textarea_field($_POST['content']),
            'category' => sanitize_key($_POST['category']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        if ($id > 0) {
            PTP_Comms_Hub_Canned_Replies::update($id, $data);
        } else {
            $id = PTP_Comms_Hub_Canned_Replies::create($data);
        }
        
        wp_safe_redirect(admin_url('admin.php?page=ptp-comms-canned-replies&message=saved'));
        exit;
    }
    
    private static function handle_delete($id) {
        if ($id > 0) {
            PTP_Comms_Hub_Canned_Replies::delete($id);
        }
        
        wp_safe_redirect(admin_url('admin.php?page=ptp-comms-canned-replies&message=deleted'));
        exit;
    }
}
