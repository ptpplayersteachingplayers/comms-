<?php
/**
 * PTP Comms Hub - WooCommerce Orders & Camps Admin Page
 * Version: 3.3.0 - Improved camp registrations display + Google Sheets
 */
class PTP_Comms_Hub_Admin_Page_Orders {
    
    /**
     * Main render function
     */
    public static function render() {
        // Handle POST submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'ptp_comms_orders_action')) {
                self::handle_post_request();
            }
        }
        
        // Get current tab
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'camps';
        
        ?>
        <div class="wrap ptp-comms-admin">
            <h1><?php _e('Orders & Camps', 'ptp-comms-hub'); ?></h1>
            <p class="ptp-intro-text">
                <?php _e('Track WooCommerce orders, camp registrations, and sync to Google Sheets.', 'ptp-comms-hub'); ?>
            </p>
            
            <!-- Tab Navigation -->
            <div class="ptp-tabs">
                <a href="?page=ptp-comms-orders&tab=camps" class="ptp-tab <?php echo $tab === 'camps' ? 'ptp-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt" style="margin-right: 4px;"></span>
                    <?php _e('Camps', 'ptp-comms-hub'); ?>
                </a>
                <a href="?page=ptp-comms-orders&tab=registrations" class="ptp-tab <?php echo $tab === 'registrations' ? 'ptp-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-groups" style="margin-right: 4px;"></span>
                    <?php _e('Registrations', 'ptp-comms-hub'); ?>
                </a>
                <a href="?page=ptp-comms-orders&tab=orders" class="ptp-tab <?php echo $tab === 'orders' ? 'ptp-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-cart" style="margin-right: 4px;"></span>
                    <?php _e('Orders', 'ptp-comms-hub'); ?>
                </a>
                <a href="?page=ptp-comms-orders&tab=google-sheets" class="ptp-tab <?php echo $tab === 'google-sheets' ? 'ptp-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-media-spreadsheet" style="margin-right: 4px;"></span>
                    <?php _e('Google Sheets', 'ptp-comms-hub'); ?>
                </a>
            </div>
            
            <?php
            switch ($tab) {
                case 'registrations':
                    self::render_registrations_tab();
                    break;
                case 'orders':
                    self::render_orders_tab();
                    break;
                case 'google-sheets':
                    self::render_google_sheets_tab();
                    break;
                default:
                    self::render_camps_tab();
                    break;
            }
            ?>
        </div>
        
        <script>
        // Use the localized nonce from WordPress, with fallback
        var ptpCommsNonce = (typeof ptpCommsData !== 'undefined' && ptpCommsData.nonce) ? ptpCommsData.nonce : '<?php echo wp_create_nonce('ptp_comms_hub_nonce'); ?>';
        var ptpAjaxUrl = (typeof ptpCommsData !== 'undefined' && ptpCommsData.ajax_url) ? ptpCommsData.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';
        
        console.log('[PTP Debug] Nonce:', ptpCommsNonce);
        console.log('[PTP Debug] AJAX URL:', ptpAjaxUrl);
        
        function ptpSyncAllOrders() {
            var btn = document.getElementById('sync-all-btn');
            var status = document.getElementById('sync-status');
            
            if (!btn) {
                console.error('[PTP Debug] Sync button not found');
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '🔄 Syncing...';
            if (status) status.innerHTML = '<span style="color: #666;">Processing orders...</span>';
            
            console.log('[PTP Debug] Starting sync request to:', ptpAjaxUrl);
            
            jQuery.ajax({
                url: ptpAjaxUrl,
                type: 'POST',
                data: {
                    action: 'ptp_sync_all_orders',
                    nonce: ptpCommsNonce
                },
                success: function(response) {
                    console.log('[PTP Debug] Response:', response);
                    btn.disabled = false;
                    btn.innerHTML = '🔄 Sync All Orders';
                    
                    if (response.success) {
                        var data = response.data || {};
                        if (status) status.innerHTML = '<span style="color: green;">✅ Synced ' + (data.synced || 0) + ' orders (' + (data.errors || 0) + ' errors)</span>';
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        var errorMsg = 'Unknown error';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        }
                        if (status) status.innerHTML = '<span style="color: red;">❌ Sync failed: ' + errorMsg + '</span>';
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('[PTP Debug] AJAX Error:', textStatus, errorThrown);
                    console.error('[PTP Debug] Response:', xhr.responseText);
                    btn.disabled = false;
                    btn.innerHTML = '🔄 Sync All Orders';
                    if (status) status.innerHTML = '<span style="color: red;">❌ Request failed: ' + (errorThrown || textStatus) + '</span>';
                }
            });
        }
        
        function ptpSyncOrder(id) {
            console.log('[PTP Debug] Syncing order:', id);
            
            jQuery.ajax({
                url: ptpAjaxUrl,
                type: 'POST',
                data: {
                    action: 'ptp_sync_single_order',
                    order_id: id,
                    nonce: ptpCommsNonce
                },
                success: function(response) {
                    console.log('[PTP Debug] Response:', response);
                    if (response.success) {
                        location.reload();
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                        alert('Sync failed: ' + errorMsg);
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('[PTP Debug] AJAX Error:', textStatus, errorThrown);
                    alert('Request failed: ' + (errorThrown || textStatus));
                }
            });
        }
        
        function ptpSyncGoogleSheets(days) {
            var btn = document.getElementById('sheets-sync-btn');
            var status = document.getElementById('sheets-sync-status');
            
            if (!btn) return;
            
            btn.disabled = true;
            btn.innerHTML = '📊 Syncing...';
            if (status) status.innerHTML = '<span style="color: #666;">Sending to Google Sheets...</span>';
            
            jQuery.ajax({
                url: ptpAjaxUrl,
                type: 'POST',
                data: {
                    action: 'ptp_sync_google_sheets_now',
                    days: days,
                    nonce: ptpCommsNonce
                },
                success: function(response) {
                    btn.disabled = false;
                    btn.innerHTML = '📊 Sync to Google Sheets';
                    
                    if (response.success && response.data && response.data.success) {
                        if (status) status.innerHTML = '<span style="color: green;">✅ ' + response.data.message + '</span>';
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : 'Unknown error';
                        if (status) status.innerHTML = '<span style="color: red;">❌ ' + errorMsg + '</span>';
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    btn.disabled = false;
                    btn.innerHTML = '📊 Sync to Google Sheets';
                    if (status) status.innerHTML = '<span style="color: red;">❌ Request failed: ' + (errorThrown || textStatus) + '</span>';
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Handle POST requests
     */
    private static function handle_post_request() {
        // Handle sync all orders
        if (isset($_POST['sync_all_orders'])) {
            set_time_limit(300);
            $result = PTP_Comms_Hub_WooCommerce::sync_all_orders();
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-orders&tab=orders&message=synced&synced=' . $result['synced'] . '&errors=' . $result['errors']));
            exit;
        }
        
        // Handle sync single order
        if (isset($_POST['sync_order_id'])) {
            $order_id = intval($_POST['sync_order_id']);
            PTP_Comms_Hub_WooCommerce::process_order($order_id, true);
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-orders&tab=orders&message=order_synced'));
            exit;
        }
        
        // Handle Google Sheets settings save
        if (isset($_POST['save_google_sheets'])) {
            // Use the Settings class to save (correct option name: ptp_comms_hub_settings)
            PTP_Comms_Hub_Settings::set('google_sheets_enabled', isset($_POST['google_sheets_enabled']) ? 'yes' : 'no');
            PTP_Comms_Hub_Settings::set('google_sheets_webhook_url', sanitize_url($_POST['google_sheets_webhook_url'] ?? ''));
            wp_safe_redirect(admin_url('admin.php?page=ptp-comms-orders&tab=google-sheets&message=saved'));
            exit;
        }
    }
    
    /**
     * Render Camps Tab - Shows each camp with registration counts
     */
    private static function render_camps_tab() {
        global $wpdb;
        
        if (!class_exists('WooCommerce')) {
            self::render_woo_not_active();
            return;
        }
        
        $show_past = isset($_GET['show_past']) ? (bool) $_GET['show_past'] : false;
        $filter_market = isset($_GET['market']) ? sanitize_text_field($_GET['market']) : '';
        
        // Get camps with registration data
        $camps = PTP_Comms_Hub_WooCommerce::get_camps_with_registrations($show_past);
        
        // Filter by market
        if ($filter_market) {
            $camps = array_filter($camps, function($camp) use ($filter_market) {
                return strtoupper($camp['market']) === strtoupper($filter_market);
            });
        }
        
        // Get stats
        $stats = PTP_Comms_Hub_WooCommerce::get_registration_stats();
        
        // Check if any orders exist
        $total_orders = 0;
        if (function_exists('wc_get_orders')) {
            $total_orders = count(wc_get_orders(array('limit' => 1, 'return' => 'ids')));
        }
        
        ?>
        <!-- Stats -->
        <div class="ptp-stats-row">
            <div class="ptp-stat-card">
                <div class="ptp-stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="ptp-stat-label">Total Registrations</div>
            </div>
            <div class="ptp-stat-card">
                <div class="ptp-stat-value" style="color: var(--ptp-success);"><?php echo number_format($stats['confirmed']); ?></div>
                <div class="ptp-stat-label">Confirmed</div>
            </div>
            <div class="ptp-stat-card">
                <div class="ptp-stat-value" style="color: var(--ptp-yellow);"><?php echo number_format($stats['pending']); ?></div>
                <div class="ptp-stat-label">Pending</div>
            </div>
            <div class="ptp-stat-card">
                <div class="ptp-stat-value" style="color: #3b82f6;"><?php echo number_format($stats['upcoming']); ?></div>
                <div class="ptp-stat-label">Upcoming</div>
            </div>
        </div>
        
        <!-- Sync Notice -->
        <?php if ($stats['total'] == 0 && $total_orders > 0): ?>
        <div class="ptp-info-box" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; background: #fffbeb; border-color: #fcd34d;">
            <div>
                <strong>⚠️ Registrations not synced!</strong><br>
                <span style="color: var(--ptp-muted);">You have WooCommerce orders but no registrations synced yet. Click "Sync All Orders" to populate registration data.</span>
            </div>
            <button type="button" id="sync-all-btn" onclick="ptpSyncAllOrders()" class="button button-primary">
                🔄 Sync All Orders
            </button>
        </div>
        <div id="sync-status" style="margin-bottom: 16px;"></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="ptp-card">
            <form method="get" action="">
                <input type="hidden" name="page" value="ptp-comms-orders">
                <input type="hidden" name="tab" value="camps">
                
                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--ptp-muted); margin-bottom: 4px;">Show</label>
                        <select name="show_past" style="min-width: 180px;">
                            <option value="0" <?php selected($show_past, false); ?>>Upcoming Camps Only</option>
                            <option value="1" <?php selected($show_past, true); ?>>All Camps (incl. Past)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--ptp-muted); margin-bottom: 4px;">Market</label>
                        <select name="market" style="min-width: 140px;">
                            <option value="">All Markets</option>
                            <?php foreach (array('PA', 'NJ', 'DE', 'MD', 'NY') as $state): ?>
                            <option value="<?php echo $state; ?>" <?php selected($filter_market, $state); ?>><?php echo $state; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="button button-primary">Filter</button>
                        <a href="?page=ptp-comms-orders&tab=camps" class="button">Reset</a>
                    </div>
                    
                    <div style="margin-left: auto;">
                        <button type="button" id="sync-all-btn" onclick="ptpSyncAllOrders()" class="button">
                            🔄 Sync All Orders
                        </button>
                        <span id="sync-status"></span>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Camps Grid -->
        <?php if (empty($camps)): ?>
        <div class="ptp-comms-empty">
            <span class="dashicons dashicons-calendar-alt"></span>
            <div class="ptp-comms-empty-title">No Camps Found</div>
            <div class="ptp-comms-empty-body">
                <?php if ($show_past): ?>
                No products found. Add products with camp dates to your WooCommerce store.
                <?php else: ?>
                No upcoming camps found. Try showing past camps or add camp dates to your products.
                <?php endif; ?>
            </div>
            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-primary">View Products</a>
        </div>
        <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px;">
            <?php foreach ($camps as $camp): 
                $is_full = $camp['capacity'] && $camp['confirmed'] >= $camp['capacity'];
                $capacity_pct = $camp['capacity'] ? round(($camp['confirmed'] / $camp['capacity']) * 100) : 0;
            ?>
            <div class="ptp-card" style="<?php echo $camp['is_past'] ? 'opacity: 0.7;' : ''; ?>">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <h3 style="margin: 0 0 4px 0; font-size: 16px;">
                            <a href="<?php echo get_edit_post_link($camp['product_id']); ?>" style="color: var(--ptp-ink); text-decoration: none;">
                                <?php echo esc_html($camp['name']); ?>
                            </a>
                        </h3>
                        <?php if ($camp['camp_date']): ?>
                        <div style="color: var(--ptp-muted); font-size: 13px;">
                            📅 <?php echo date('M j, Y', strtotime($camp['camp_date'])); ?>
                            <?php if ($camp['camp_end_date'] && $camp['camp_end_date'] !== $camp['camp_date']): ?>
                            - <?php echo date('M j', strtotime($camp['camp_end_date'])); ?>
                            <?php endif; ?>
                            <?php if ($camp['camp_time']): ?>
                            <span style="margin-left: 8px;">⏰ <?php echo esc_html($camp['camp_time']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($camp['is_past']): ?>
                        <span class="ptp-status-badge" style="background: #f3f4f6; color: #6b7280;">Past</span>
                        <?php elseif ($is_full): ?>
                        <span class="ptp-status-badge ptp-status-missing">Full</span>
                        <?php else: ?>
                        <span class="ptp-status-badge ptp-status-ok">Active</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($camp['location']): ?>
                <div style="color: var(--ptp-muted); font-size: 13px; margin-bottom: 8px;">
                    📍 <?php echo esc_html($camp['location']); ?>
                    <?php if ($camp['market']): ?>
                    <span style="margin-left: 8px; background: #f3f4f6; padding: 2px 8px; border-radius: 4px; font-size: 11px;"><?php echo esc_html($camp['market']); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Capacity Bar -->
                <?php if ($camp['capacity']): ?>
                <div style="margin-top: 12px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                        <span style="color: var(--ptp-muted);">Capacity</span>
                        <span style="font-weight: 600;"><?php echo $camp['confirmed']; ?> / <?php echo $camp['capacity']; ?></span>
                    </div>
                    <div style="height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?php echo min($capacity_pct, 100); ?>%; background: <?php echo $capacity_pct >= 100 ? '#ef4444' : ($capacity_pct >= 75 ? '#f59e0b' : '#10b981'); ?>; border-radius: 4px;"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Stats -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--ptp-border);">
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--ptp-success);">
                            <?php echo number_format($camp['confirmed']); ?>
                        </div>
                        <div style="font-size: 11px; color: var(--ptp-muted); text-transform: uppercase;">Confirmed</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: 700; color: var(--ptp-yellow);">
                            <?php echo number_format($camp['pending']); ?>
                        </div>
                        <div style="font-size: 11px; color: var(--ptp-muted); text-transform: uppercase;">Pending</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 24px; font-weight: 700; color: #3b82f6;">
                            $<?php echo number_format($camp['revenue'], 0); ?>
                        </div>
                        <div style="font-size: 11px; color: var(--ptp-muted); text-transform: uppercase;">Revenue</div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div style="margin-top: 16px; display: flex; gap: 8px;">
                    <a href="?page=ptp-comms-orders&tab=registrations&product_id=<?php echo $camp['product_id']; ?>" class="button" style="flex: 1; text-align: center;">
                        👥 View Registrations
                    </a>
                    <?php if ($camp['total_registrations'] > 0): ?>
                    <a href="?page=ptp-comms-campaigns&action=new&product_id=<?php echo $camp['product_id']; ?>" class="button" style="background: var(--ptp-yellow); border-color: var(--ptp-yellow); color: #000;">
                        📱 Send SMS
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render Registrations Tab
     */
    private static function render_registrations_tab() {
        global $wpdb;
        
        // Get filter values
        $filter_product = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        $filter_status = isset($_GET['reg_status']) ? sanitize_text_field($_GET['reg_status']) : '';
        $filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $filter_date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        
        // Build query
        $where = array('1=1');
        $params = array();
        
        if ($filter_product) {
            $where[] = 'r.product_id = %d';
            $params[] = $filter_product;
        }
        
        if ($filter_status) {
            $where[] = 'r.registration_status = %s';
            $params[] = $filter_status;
        }
        
        if ($filter_date_from) {
            $where[] = 'r.event_date >= %s';
            $params[] = $filter_date_from;
        }
        
        if ($filter_date_to) {
            $where[] = 'r.event_date <= %s';
            $params[] = $filter_date_to;
        }
        
        if ($search) {
            $where[] = '(r.child_name LIKE %s OR r.product_name LIKE %s OR c.parent_first_name LIKE %s OR c.parent_last_name LIKE %s OR c.parent_phone LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_registrations r 
                       LEFT JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id 
                       WHERE {$where_sql}";
        if (!empty($params)) {
            $count_query = $wpdb->prepare($count_query, $params);
        }
        $total = $wpdb->get_var($count_query);
        $total_pages = ceil($total / $per_page);
        
        // Get registrations
        $query = "SELECT r.*, c.parent_phone, c.parent_email, c.parent_first_name, c.parent_last_name, c.opted_in
                  FROM {$wpdb->prefix}ptp_registrations r
                  LEFT JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
                  WHERE {$where_sql}
                  ORDER BY r.created_at DESC
                  LIMIT %d OFFSET %d";
        
        $all_params = array_merge($params, array($per_page, ($paged - 1) * $per_page));
        $registrations = $wpdb->get_results($wpdb->prepare($query, $all_params));
        
        // Get products for filter dropdown
        $products = function_exists('wc_get_products') ? wc_get_products(array('limit' => -1, 'status' => 'publish')) : array();
        
        // Get selected product name
        $selected_product_name = '';
        if ($filter_product && function_exists('wc_get_product')) {
            $product = wc_get_product($filter_product);
            if ($product) {
                $selected_product_name = $product->get_name();
            }
        }
        
        ?>
        <!-- Filters -->
        <div class="ptp-card">
            <form method="get" action="">
                <input type="hidden" name="page" value="ptp-comms-orders">
                <input type="hidden" name="tab" value="registrations">
                
                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--ptp-muted); margin-bottom: 4px;">Search</label>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Name, phone, camp..." style="min-width: 200px;">
                    </div>
                    
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--ptp-muted); margin-bottom: 4px;">Camp/Product</label>
                        <select name="product_id" style="min-width: 200px;">
                            <option value="">All Camps</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product->get_id(); ?>" <?php selected($filter_product, $product->get_id()); ?>>
                                <?php echo esc_html($product->get_name()); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--ptp-muted); margin-bottom: 4px;">Status</label>
                        <select name="reg_status" style="min-width: 120px;">
                            <option value="">All</option>
                            <option value="confirmed" <?php selected($filter_status, 'confirmed'); ?>>Confirmed</option>
                            <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pending</option>
                            <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Cancelled</option>
                            <option value="refunded" <?php selected($filter_status, 'refunded'); ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--ptp-muted); margin-bottom: 4px;">Event Date From</label>
                        <input type="date" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                    </div>
                    
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--ptp-muted); margin-bottom: 4px;">To</label>
                        <input type="date" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                    </div>
                    
                    <div>
                        <button type="submit" class="button button-primary">Filter</button>
                        <a href="?page=ptp-comms-orders&tab=registrations" class="button">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if ($selected_product_name): ?>
        <div class="ptp-info-box" style="margin-bottom: 16px;">
            <strong>Showing registrations for:</strong> <?php echo esc_html($selected_product_name); ?>
            <a href="?page=ptp-comms-orders&tab=registrations" style="margin-left: 12px;">Clear filter</a>
        </div>
        <?php endif; ?>
        
        <!-- Registrations Table -->
        <div class="ptp-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 12px 16px; background: #fafafa; border-bottom: 1px solid var(--ptp-border);">
                <span style="color: var(--ptp-muted); font-size: 13px;">
                    Showing <?php echo number_format(count($registrations)); ?> of <?php echo number_format($total); ?> registrations
                </span>
            </div>
            
            <table class="wp-list-table widefat fixed striped" style="border: none; border-radius: 0;">
                <thead>
                    <tr>
                        <th style="width: 60px;">Order</th>
                        <th>Parent</th>
                        <th>Camper</th>
                        <th>Camp/Product</th>
                        <th>Event Date</th>
                        <th style="width: 80px;">Amount</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 100px;">SMS</th>
                        <th style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registrations)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: var(--ptp-muted);">
                            No registrations found matching your criteria.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($registrations as $reg): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $reg->order_id . '&action=edit'); ?>" style="font-weight: 600;">
                                #<?php echo $reg->order_id; ?>
                            </a>
                        </td>
                        <td>
                            <strong><?php echo esc_html($reg->parent_first_name . ' ' . $reg->parent_last_name); ?></strong>
                            <br><small style="color: var(--ptp-muted);"><?php echo esc_html($reg->parent_phone); ?></small>
                        </td>
                        <td>
                            <?php echo esc_html($reg->child_name ?: '—'); ?>
                            <?php if ($reg->child_age): ?>
                            <br><small style="color: var(--ptp-muted);">Age: <?php echo esc_html($reg->child_age); ?></small>
                            <?php endif; ?>
                            <?php if ($reg->tshirt_size): ?>
                            <small style="color: var(--ptp-muted); margin-left: 8px;">Size: <?php echo esc_html($reg->tshirt_size); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo get_edit_post_link($reg->product_id); ?>"><?php echo esc_html($reg->product_name); ?></a>
                            <?php if ($reg->quantity > 1): ?>
                            <span style="color: var(--ptp-muted);">×<?php echo $reg->quantity; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($reg->event_date): ?>
                            <?php echo date('M j, Y', strtotime($reg->event_date)); ?>
                            <?php if ($reg->event_time): ?>
                            <br><small style="color: var(--ptp-muted);"><?php echo esc_html($reg->event_time); ?></small>
                            <?php endif; ?>
                            <?php else: ?>
                            <span style="color: var(--ptp-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 600;">
                            $<?php echo number_format($reg->line_total, 2); ?>
                        </td>
                        <td>
                            <?php 
                            $status_class = '';
                            switch ($reg->registration_status) {
                                case 'confirmed': $status_class = 'ptp-status-ok'; break;
                                case 'pending': $status_class = 'ptp-status-warning'; break;
                                case 'cancelled':
                                case 'refunded': $status_class = 'ptp-status-missing'; break;
                            }
                            ?>
                            <span class="ptp-status-badge <?php echo $status_class; ?>">
                                <?php echo ucfirst($reg->registration_status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($reg->opted_in): ?>
                            <span style="color: var(--ptp-success);">✅ Opted In</span>
                            <?php else: ?>
                            <span style="color: var(--ptp-muted);">❌ No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="<?php echo admin_url('post.php?post=' . $reg->order_id . '&action=edit'); ?>">Order</a>
                                <?php if ($reg->contact_id): ?>
                                | <a href="<?php echo admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $reg->contact_id); ?>">Contact</a>
                                | <a href="<?php echo admin_url('admin.php?page=ptp-comms-inbox&contact_id=' . $reg->contact_id); ?>">Message</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="padding: 12px 16px; background: #fafafa; border-top: 1px solid var(--ptp-border);">
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $base_url = add_query_arg(array_filter(array(
                            'page' => 'ptp-comms-orders',
                            'tab' => 'registrations',
                            'product_id' => $filter_product,
                            'reg_status' => $filter_status,
                            'date_from' => $filter_date_from,
                            'date_to' => $filter_date_to,
                            's' => $search,
                        )), admin_url('admin.php'));
                        
                        echo paginate_links(array(
                            'base' => $base_url . '&paged=%#%',
                            'format' => '',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => '&laquo; Previous',
                            'next_text' => 'Next &raquo;',
                        ));
                        ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render Orders Tab
     */
    private static function render_orders_tab() {
        global $wpdb;
        
        if (!class_exists('WooCommerce')) {
            self::render_woo_not_active();
            return;
        }
        
        // Show messages
        if (isset($_GET['message'])) {
            $msg = sanitize_text_field($_GET['message']);
            if ($msg === 'synced') {
                $synced = intval($_GET['synced'] ?? 0);
                $errors = intval($_GET['errors'] ?? 0);
                echo '<div class="notice notice-success is-dismissible"><p>✅ Synced <strong>' . $synced . '</strong> orders (' . $errors . ' errors)</p></div>';
            } elseif ($msg === 'order_synced') {
                echo '<div class="notice notice-success is-dismissible"><p>✅ Order synced successfully</p></div>';
            }
        }
        
        // Get filter values
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_synced = isset($_GET['synced']) ? sanitize_text_field($_GET['synced']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 25;
        
        // Build order query
        $args = array(
            'limit' => $per_page,
            'offset' => ($paged - 1) * $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'paginate' => true,
        );
        
        if ($filter_status) {
            $args['status'] = $filter_status;
        } else {
            $args['status'] = array('completed', 'processing', 'on-hold', 'pending', 'cancelled', 'refunded');
        }
        
        if ($search) {
            $args['s'] = $search;
        }
        
        $results = wc_get_orders($args);
        $orders = $results->orders;
        $total_orders = $results->total;
        $total_pages = $results->max_num_pages;
        
        ?>
        <!-- Filters -->
        <div class="ptp-card">
            <form method="get" action="">
                <input type="hidden" name="page" value="ptp-comms-orders">
                <input type="hidden" name="tab" value="orders">
                
                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--ptp-muted); margin-bottom: 4px;">Search</label>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Order #, name, email..." style="min-width: 200px;">
                    </div>
                    
                    <div>
                        <label style="display: block; font-size: 12px; color: var(--ptp-muted); margin-bottom: 4px;">Status</label>
                        <select name="status" style="min-width: 140px;">
                            <option value="">All Statuses</option>
                            <option value="completed" <?php selected($filter_status, 'completed'); ?>>Completed</option>
                            <option value="processing" <?php selected($filter_status, 'processing'); ?>>Processing</option>
                            <option value="on-hold" <?php selected($filter_status, 'on-hold'); ?>>On Hold</option>
                            <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pending</option>
                            <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Cancelled</option>
                            <option value="refunded" <?php selected($filter_status, 'refunded'); ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="button button-primary">Filter</button>
                        <a href="?page=ptp-comms-orders&tab=orders" class="button">Reset</a>
                    </div>
                    
                    <div style="margin-left: auto;">
                        <button type="button" id="sync-all-btn" onclick="ptpSyncAllOrders()" class="button button-primary">
                            🔄 Sync All Orders
                        </button>
                        <span id="sync-status"></span>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Orders Table -->
        <div class="ptp-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 12px 16px; background: #fafafa; border-bottom: 1px solid var(--ptp-border);">
                <span style="color: var(--ptp-muted); font-size: 13px;">
                    Showing <?php echo number_format(count($orders)); ?> of <?php echo number_format($total_orders); ?> orders
                </span>
            </div>
            
            <table class="wp-list-table widefat fixed striped" style="border: none; border-radius: 0;">
                <thead>
                    <tr>
                        <th style="width: 80px;">Order</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Items</th>
                        <th>Camper</th>
                        <th style="width: 90px;">Total</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 100px;">Date</th>
                        <th style="width: 60px;">Synced</th>
                        <th style="width: 80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: var(--ptp-muted);">
                            No orders found.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): 
                        $order_id = $order->get_id();
                        $child_name = trim($order->get_meta('_ptp_child_first_name') . ' ' . $order->get_meta('_ptp_child_last_name'));
                        $child_age = $order->get_meta('_ptp_child_age');
                        $sms_optin = $order->get_meta('_ptp_sms_optin');
                        $contact_id = $order->get_meta('_ptp_comms_contact_id');
                        $processed = $order->get_meta('_ptp_comms_processed');
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" style="font-weight: 600;">
                                #<?php echo $order->get_order_number(); ?>
                            </a>
                        </td>
                        <td>
                            <strong><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></strong>
                            <br><small style="color: var(--ptp-muted);"><?php echo esc_html($order->get_billing_email()); ?></small>
                        </td>
                        <td>
                            <?php echo esc_html($order->get_billing_phone()); ?>
                            <?php if ($sms_optin === 'yes'): ?>
                            <span title="SMS Opted In" style="color: var(--ptp-success);">📱</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $items = $order->get_items();
                            $count = 0;
                            foreach ($items as $item):
                                if ($count >= 2) {
                                    echo '<small style="color: var(--ptp-muted);">+' . (count($items) - 2) . ' more</small>';
                                    break;
                                }
                                $product = $item->get_product();
                                $camp_date = $product ? get_post_meta($product->get_id(), '_ptp_camp_date', true) : '';
                            ?>
                            <div style="margin-bottom: 2px;">
                                <span style="font-weight: 500;"><?php echo esc_html($item->get_name()); ?></span>
                                <?php if ($camp_date): ?>
                                <small style="color: var(--ptp-muted);">(<?php echo date('M j', strtotime($camp_date)); ?>)</small>
                                <?php endif; ?>
                            </div>
                            <?php 
                                $count++;
                            endforeach; 
                            ?>
                        </td>
                        <td>
                            <?php if ($child_name): ?>
                            <?php echo esc_html($child_name); ?>
                            <?php if ($child_age): ?>
                            <br><small style="color: var(--ptp-muted);">Age: <?php echo esc_html($child_age); ?></small>
                            <?php endif; ?>
                            <?php else: ?>
                            <span style="color: var(--ptp-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 600;">
                            <?php echo $order->get_formatted_order_total(); ?>
                        </td>
                        <td>
                            <?php 
                            $status = $order->get_status();
                            $status_class = '';
                            switch ($status) {
                                case 'completed': $status_class = 'ptp-status-ok'; break;
                                case 'processing':
                                case 'on-hold': $status_class = 'ptp-status-warning'; break;
                                case 'cancelled':
                                case 'refunded':
                                case 'failed': $status_class = 'ptp-status-missing'; break;
                            }
                            ?>
                            <span class="ptp-status-badge <?php echo $status_class; ?>">
                                <?php echo wc_get_order_status_name($status); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $date_created = $order->get_date_created();
                            if ($date_created): 
                                echo $date_created->date('M j, Y');
                            endif;
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($processed): ?>
                            <span style="color: var(--ptp-success); font-size: 18px;" title="Synced: <?php echo esc_attr($processed); ?>">✓</span>
                            <?php else: ?>
                            <button type="button" onclick="ptpSyncOrder(<?php echo $order_id; ?>)" class="button button-small" title="Sync this order">
                                🔄
                            </button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>">View</a>
                                <?php if ($contact_id): ?>
                                | <a href="<?php echo admin_url('admin.php?page=ptp-comms-inbox&contact_id=' . $contact_id); ?>">💬</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="padding: 12px 16px; background: #fafafa; border-top: 1px solid var(--ptp-border);">
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $base_url = add_query_arg(array_filter(array(
                            'page' => 'ptp-comms-orders',
                            'tab' => 'orders',
                            'status' => $filter_status,
                            's' => $search,
                        )), admin_url('admin.php'));
                        
                        echo paginate_links(array(
                            'base' => $base_url . '&paged=%#%',
                            'format' => '',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => '&laquo; Previous',
                            'next_text' => 'Next &raquo;',
                        ));
                        ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render Google Sheets Tab
     */
    private static function render_google_sheets_tab() {
        $settings = PTP_Comms_Hub_Settings::get_all();
        $enabled = isset($settings['google_sheets_enabled']) && $settings['google_sheets_enabled'] === 'yes';
        $webhook_url = $settings['google_sheets_webhook_url'] ?? '';
        
        // Show messages
        if (isset($_GET['message'])) {
            if ($_GET['message'] === 'saved') {
                echo '<div class="notice notice-success is-dismissible"><p>✅ Google Sheets settings saved</p></div>';
            }
        }
        
        ?>
        <div class="ptp-card">
            <h2 style="margin-top: 0;">📊 Google Sheets Integration</h2>
            <p style="color: var(--ptp-muted);">
                Automatically sync all camp registrations to a Google Sheet for easy tracking, reporting, and sharing with your team.
            </p>
            
            <form method="post" action="">
                <?php wp_nonce_field('ptp_comms_orders_action'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Google Sheets Sync</th>
                        <td>
                            <label>
                                <input type="checkbox" name="google_sheets_enabled" value="1" <?php checked($enabled); ?>>
                                Automatically sync new orders to Google Sheets
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook URL</th>
                        <td>
                            <input type="url" name="google_sheets_webhook_url" value="<?php echo esc_attr($webhook_url); ?>" class="regular-text" placeholder="https://script.google.com/macros/s/...">
                            <p class="description">
                                Enter your Google Apps Script Web App URL. See setup instructions below.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="submit" name="save_google_sheets" value="1" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        
        <?php if ($enabled && $webhook_url): ?>
        <div class="ptp-card">
            <h3 style="margin-top: 0;">Manual Sync</h3>
            <p style="color: var(--ptp-muted);">Sync all registrations from the selected time period to Google Sheets.</p>
            
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <select id="sync-days" style="min-width: 150px;">
                    <option value="7">Last 7 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="365">Last year</option>
                    <option value="9999">All time</option>
                </select>
                <button type="button" id="sheets-sync-btn" onclick="ptpSyncGoogleSheets(document.getElementById('sync-days').value)" class="button button-primary">
                    📊 Sync to Google Sheets
                </button>
                <button type="button" id="sheets-test-btn" onclick="ptpTestGoogleSheets()" class="button">
                    🔗 Test Connection
                </button>
            </div>
            <div id="sheets-sync-status" style="margin-top: 10px;"></div>
        </div>
        
        <script>
        function ptpTestGoogleSheets() {
            var btn = document.getElementById('sheets-test-btn');
            var status = document.getElementById('sheets-sync-status');
            var webhookUrl = '<?php echo esc_js($webhook_url); ?>';
            
            if (!btn) return;
            
            btn.disabled = true;
            btn.innerHTML = '🔗 Testing...';
            if (status) status.innerHTML = '<span style="color: #666;">Testing connection to Google Sheets...</span>';
            
            // Test by sending a minimal payload
            jQuery.ajax({
                url: ptpAjaxUrl,
                type: 'POST',
                data: {
                    action: 'ptp_test_google_sheets',
                    nonce: ptpCommsNonce
                },
                success: function(response) {
                    btn.disabled = false;
                    btn.innerHTML = '🔗 Test Connection';
                    
                    if (response.success && response.data && response.data.success) {
                        if (status) status.innerHTML = '<span style="color: green;">✅ ' + response.data.message + '</span>';
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : 'Unknown error';
                        if (status) status.innerHTML = '<span style="color: red;">❌ ' + errorMsg + '</span>';
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    btn.disabled = false;
                    btn.innerHTML = '🔗 Test Connection';
                    if (status) status.innerHTML = '<span style="color: red;">❌ Request failed: ' + (errorThrown || textStatus) + '</span>';
                }
            });
        }
        </script>
        <?php endif; ?>
        
        <div class="ptp-card">
            <h3 style="margin-top: 0;">📋 Setup Instructions</h3>
            
            <ol style="line-height: 2;">
                <li>
                    <strong>Create a new Google Sheet</strong>
                    <br>Go to <a href="https://sheets.google.com" target="_blank">Google Sheets</a> and create a new spreadsheet.
                </li>
                <li>
                    <strong>Open Apps Script</strong>
                    <br>Click Extensions → Apps Script
                </li>
                <li>
                    <strong>Paste the following code:</strong>
                    <br>
                    <details style="margin-top: 8px;">
                        <summary style="cursor: pointer; color: #0073aa;">Click to show code (ORGANIZES BY PRODUCT)</summary>
                        <pre style="background: #f5f5f5; padding: 16px; overflow-x: auto; margin-top: 8px; font-size: 11px; border-radius: 4px; white-space: pre-wrap;">
function doPost(e) {
  try {
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var data = JSON.parse(e.postData.contents);
    
    // Handle test request
    if (data.test) {
      return ContentService.createTextOutput(JSON.stringify({success: true, message: 'Connected!'}))
        .setMimeType(ContentService.MimeType.JSON);
    }
    
    var rows = data.rows;
    if (!rows || rows.length === 0) {
      return ContentService.createTextOutput(JSON.stringify({success: true, count: 0}))
        .setMimeType(ContentService.MimeType.JSON);
    }
    
    // Group rows by product (sheet_name field)
    var productGroups = {};
    rows.forEach(function(row) {
      var sheetName = row.sheet_name || 'All Registrations';
      // Clean sheet name (max 100 chars, no special chars)
      sheetName = sheetName.substring(0, 100).replace(/[\\\/\?\*\[\]]/g, '');
      if (!productGroups[sheetName]) {
        productGroups[sheetName] = [];
      }
      productGroups[sheetName].push(row);
    });
    
    var totalCount = 0;
    
    // Process each product group into its own sheet
    for (var sheetName in productGroups) {
      var productRows = productGroups[sheetName];
      var sheet = ss.getSheetByName(sheetName);
      
      // Create sheet if doesn't exist
      if (!sheet) {
        sheet = ss.insertSheet(sheetName);
      }
      
      // Get headers (exclude sheet_name from display)
      var allHeaders = Object.keys(productRows[0]).filter(function(h) { return h !== 'sheet_name'; });
      
      // Add headers if sheet is empty
      if (sheet.getLastRow() === 0) {
        sheet.appendRow(allHeaders);
        sheet.getRange(1, 1, 1, allHeaders.length).setFontWeight('bold').setBackground('#FCD116');
        sheet.setFrozenRows(1);
      }
      
      // Get existing headers
      var headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
      
      // Add each row
      productRows.forEach(function(row) {
        var rowData = headers.map(function(header) {
          return row[header] || '';
        });
        sheet.appendRow(rowData);
        totalCount++;
      });
      
      // Auto-resize columns
      sheet.autoResizeColumns(1, headers.length);
    }
    
    // Also add to master "All Registrations" sheet
    var masterSheet = ss.getSheetByName('All Registrations');
    if (!masterSheet) {
      masterSheet = ss.insertSheet('All Registrations');
      ss.setActiveSheet(masterSheet);
      ss.moveActiveSheet(1); // Move to first position
    }
    
    var masterHeaders = Object.keys(rows[0]).filter(function(h) { return h !== 'sheet_name'; });
    if (masterSheet.getLastRow() === 0) {
      masterSheet.appendRow(masterHeaders);
      masterSheet.getRange(1, 1, 1, masterHeaders.length).setFontWeight('bold').setBackground('#1a1a1a').setFontColor('#FCD116');
      masterSheet.setFrozenRows(1);
    }
    
    var mHeaders = masterSheet.getRange(1, 1, 1, masterSheet.getLastColumn()).getValues()[0];
    rows.forEach(function(row) {
      var rowData = mHeaders.map(function(header) { return row[header] || ''; });
      masterSheet.appendRow(rowData);
    });
    
    return ContentService.createTextOutput(JSON.stringify({success: true, count: totalCount}))
      .setMimeType(ContentService.MimeType.JSON);
      
  } catch(error) {
    return ContentService.createTextOutput(JSON.stringify({success: false, error: error.toString()}))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

function doGet(e) {
  return ContentService.createTextOutput('PTP Google Sheets - Ready');
}
                        </pre>
                    </details>
                </li>
                <li>
                    <strong>Deploy as Web App</strong>
                    <br>Click Deploy → New deployment → Select "Web app" → Execute as "Me" → Who has access "<strong>Anyone</strong>" → Deploy
                </li>
                <li>
                    <strong>Copy the Web App URL</strong>
                    <br>Copy the URL (ends in /exec) and paste it in the Webhook URL field above.
                </li>
            </ol>
            
            <div class="ptp-info-box" style="margin-top: 16px; background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: #fff; padding: 20px; border-radius: 8px;">
                <strong style="color: #FCD116;">🗂️ How Product Organization Works:</strong>
                <ul style="margin: 12px 0 0 0; padding-left: 20px; line-height: 1.8;">
                    <li><strong style="color: #FCD116;">All Registrations</strong> - Master sheet with ALL data</li>
                    <li><strong>Separate tabs</strong> - Each product gets its own tab automatically</li>
                    <li>Product names become tab names (e.g., "Summer Camp Week 1", "Elite Training Dec")</li>
                    <li>Easy filtering and tracking per camp/product!</li>
                </ul>
            </div>
            
            <div class="ptp-info-box" style="margin-top: 16px;">
                <strong>📌 All Fields Synced (ACF Compatible):</strong>
                <div style="margin-top: 8px; font-size: 12px; line-height: 1.8;">
                    order_id, order_number, order_date, order_status, parent_first_name, parent_last_name, 
                    parent_email, parent_phone, billing_city, billing_state, billing_zip, 
                    <strong>camper_first_name, camper_last_name</strong>, child_name, child_age, 
                    <strong>camper_level, camper_team</strong>, tshirt_size, <strong>skills_to_improve</strong>, 
                    emergency_contact, <strong>emergency_contact_phone_number, medical_allergies</strong>, special_needs, 
                    <strong>how_did_you_hear, friend_name, friend_email, ptp_referral, waiver_agreed, refund_policy_agreed, photo_permission</strong>, 
                    product_name, product_id, quantity, line_total, event_date, event_time, event_location, 
                    market, program_type, registration_status, sms_opted_in
                </div>
                <p style="margin-top: 10px; color: #d63638; font-size: 12px;">
                    <strong>⚠️ Important:</strong> If you added new fields, clear your Google Sheet first (delete all rows including headers), then sync again to get all columns.
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render WooCommerce not active notice
     */
    private static function render_woo_not_active() {
        ?>
        <div class="ptp-comms-empty">
            <span class="dashicons dashicons-warning"></span>
            <div class="ptp-comms-empty-title">WooCommerce Required</div>
            <div class="ptp-comms-empty-body">
                WooCommerce must be installed and activated to use order and camp tracking features.
            </div>
            <a href="<?php echo admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'); ?>" class="button button-primary">Install WooCommerce</a>
        </div>
        <?php
    }
}
