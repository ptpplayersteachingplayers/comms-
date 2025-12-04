# PTP Comms Hub - Debug & Troubleshooting Guide

## Quick Debug Checklist

### Before Debugging
- [ ] Enable WP_DEBUG in wp-config.php
- [ ] Check PHP error logs
- [ ] Review WordPress debug.log
- [ ] Check browser console for JS errors
- [ ] Review PTP Comms Hub logs (in admin)

---

## Common Issues & Debugging Steps

### 1. Messages Not Appearing in Inbox

**Symptoms:**
- Parent sends SMS but nothing shows in inbox
- No notification in Teams
- No database entry

**Debug Steps:**

1. **Check Twilio Webhook Configuration**
```bash
# Test webhook URL is accessible
curl -X POST https://yoursite.com/wp-json/ptp-comms/v1/webhooks/twilio

# Expected response: Should not return 404
```

2. **Verify REST API is Working**
```bash
# Test REST API endpoint
curl https://yoursite.com/wp-json/wp/v2/

# Should return JSON, not 404 or HTML
```

3. **Check Twilio Debugger**
   - Go to [Twilio Console → Monitor → Logs → Errors](https://console.twilio.com/monitor/logs/errors)
   - Look for webhook errors (11200, 11205, etc.)
   - Check response codes from your server

4. **Enable PTP Debug Mode**
Add to `wp-config.php`:
```php
define('PTP_COMMS_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

5. **Check Logs**
   - WordPress: `wp-content/debug.log`
   - PTP Comms: Admin → PTP Comms → Logs
   - Server: Check Apache/Nginx error logs

6. **Test Webhook Manually**
```bash
# Simulate Twilio webhook
curl -X POST https://yoursite.com/wp-json/ptp-comms/v1/webhooks/twilio \
  -d "MessageSid=TEST123" \
  -d "From=+15551234567" \
  -d "To=+15559876543" \
  -d "Body=Test message"
```

**Common Fixes:**
- Flush permalinks: Settings → Permalinks → Save Changes
- Check .htaccess for rewrite rules
- Verify webhook URL has no typos
- Ensure PHP has write permissions to database

---

### 2. Can't Send Messages from Inbox

**Symptoms:**
- Send button does nothing
- Error message appears
- Message doesn't reach parent

**Debug Steps:**

1. **Check Twilio Credentials**
```php
// Add to functions.php temporarily
add_action('admin_init', function() {
    $account_sid = ptp_comms_get_setting('twilio_account_sid');
    $auth_token = ptp_comms_get_setting('twilio_auth_token');
    $phone = ptp_comms_get_setting('twilio_phone_number');
    
    error_log('Twilio SID: ' . ($account_sid ? 'SET' : 'MISSING'));
    error_log('Twilio Token: ' . ($auth_token ? 'SET' : 'MISSING'));
    error_log('Twilio Phone: ' . $phone);
});
```

2. **Test Twilio API Connection**
```php
// Test script - add to functions.php temporarily
add_action('admin_init', function() {
    if (!isset($_GET['test_twilio'])) return;
    
    require_once plugin_dir_path(__FILE__) . 'includes/class-sms-service.php';
    $sms = new PTP_Comms_Hub_SMS_Service();
    
    $result = $sms->send_sms('+15551234567', 'Test message');
    
    error_log('Twilio Test Result: ' . print_r($result, true));
    wp_die('Check error log');
});
// Then visit: /wp-admin/?test_twilio=1
```

3. **Check Contact Opt-In Status**
```sql
-- Run in phpMyAdmin
SELECT id, parent_first_name, parent_last_name, parent_phone, opt_in_status
FROM wp_ptp_contacts
WHERE id = [CONTACT_ID];

-- Should show: opted_in (not opted_out or pending)
```

4. **Verify Twilio Account Balance**
   - Go to [Twilio Console → Home](https://console.twilio.com)
   - Check account balance > $0
   - Verify phone number is active

5. **Check Browser Console**
   - Open DevTools (F12)
   - Go to Console tab
   - Look for JavaScript errors
   - Check Network tab for failed AJAX requests

**Common Fixes:**
- Re-enter Twilio credentials
- Verify contact opted in
- Add funds to Twilio account
- Check contact phone number format (+1XXXXXXXXXX)

---

### 3. Teams Integration Not Working

**Symptoms:**
- No notifications in Teams
- Test button fails
- Webhook URL errors

**Debug Steps:**

1. **Verify Webhook URL Format**
```
Correct format:
https://outlook.office.com/webhook/xxxxx@xxxxx/IncomingWebhook/xxxxx/xxxxx

Should NOT have:
- Extra spaces
- Line breaks
- Special characters
- http:// (should be https://)
```

2. **Test Webhook Directly**
```bash
# Test Teams webhook
curl -X POST https://outlook.office.com/webhook/YOUR_WEBHOOK_URL \
  -H "Content-Type: application/json" \
  -d '{
    "@type": "MessageCard",
    "@context": "https://schema.org/extensions",
    "text": "Test from PTP Comms Hub"
  }'
```

3. **Check Teams Integration Class**
Add debug logging to `includes/class-teams-integration.php`:
```php
public static function send_message($message, $card = null, $channel = null) {
    $webhook_url = self::get_webhook_url();
    error_log('Teams Webhook URL: ' . $webhook_url);
    
    if (empty($webhook_url)) {
        error_log('Teams webhook URL is empty!');
        return false;
    }
    
    // ... rest of code
    
    error_log('Teams Response: ' . print_r($response, true));
}
```

4. **Check Connector Status in Teams**
   - Open Teams channel
   - Click three dots → Connectors
   - Verify "Incoming Webhook" is listed
   - If missing, recreate webhook

5. **Test REST API Endpoints**
```bash
# Test Teams reply endpoint
curl -X POST https://yoursite.com/wp-json/ptp-comms/v1/teams-reply \
  -H "Content-Type: application/json" \
  -d '{"contact_id": 1, "contact_phone": "+15551234567", "reply_message": "Test"}'
```

**Common Fixes:**
- Re-create incoming webhook in Teams
- Copy webhook URL carefully (no extra characters)
- Check WordPress can make outbound HTTPS requests
- Verify connector hasn't been disabled

---

### 4. Database Sync Issues

**Symptoms:**
- Conversations missing
- Message count incorrect
- Contact info outdated

**Debug Steps:**

1. **Check Database Tables**
```sql
-- Verify tables exist
SHOW TABLES LIKE 'wp_ptp_%';

-- Should show:
-- wp_ptp_contacts
-- wp_ptp_messages
-- wp_ptp_conversations
-- wp_ptp_campaigns
-- wp_ptp_automations
```

2. **Check Table Structure**
```sql
DESCRIBE wp_ptp_conversations;
-- Should have: id, contact_id, last_message, last_message_at, unread_count, status
```

3. **Test Conversation Creation**
```php
add_action('admin_init', function() {
    if (!isset($_GET['test_convo'])) return;
    
    global $wpdb;
    $convo = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ptp_conversations WHERE id = 1");
    
    error_log('Conversation: ' . print_r($convo, true));
    wp_die('Check error log');
});
```

4. **Run Database Repair**
```php
// Add to functions.php temporarily
add_action('admin_init', function() {
    if (!isset($_GET['repair_ptp'])) return;
    
    require_once plugin_dir_path(__FILE__) . 'includes/class-activator.php';
    PTP_Comms_Hub_Activator::activate();
    
    wp_die('Database tables repaired! Check for errors in log.');
});
// Visit: /wp-admin/?repair_ptp=1
```

5. **Check Foreign Key Relationships**
```sql
-- Find orphaned messages (no conversation)
SELECT m.* FROM wp_ptp_messages m
LEFT JOIN wp_ptp_conversations c ON m.conversation_id = c.id
WHERE c.id IS NULL;

-- Find orphaned conversations (no contact)
SELECT c.* FROM wp_ptp_conversations c
LEFT JOIN wp_ptp_contacts ct ON c.contact_id = ct.id
WHERE ct.id IS NULL;
```

**Common Fixes:**
- Deactivate and reactivate plugin (recreates tables)
- Run database repair script above
- Check MySQL user has CREATE/ALTER permissions
- Delete orphaned records

---

### 5. Real-Time Updates Not Working

**Symptoms:**
- Have to refresh to see new messages
- Inbox doesn't auto-update
- Unread count doesn't change

**Debug Steps:**

1. **Check Browser Console**
```javascript
// Open DevTools Console
// Look for:
console.log('Checking for new messages...');
// Should appear every 5 seconds
```

2. **Test REST API Polling**
```bash
# Test new messages endpoint
curl "https://yoursite.com/wp-json/ptp-comms/v1/messages/1?since_id=0" \
  -H "Cookie: wordpress_logged_in_xxx=xxx"
```

3. **Add Debug Logging to JavaScript**
Edit `admin/js/admin.js`:
```javascript
function pollForNewMessages() {
    console.log('[PTP Debug] Polling for conversation:', currentConversationId);
    
    fetch('/wp-json/ptp-comms/v1/messages/' + currentConversationId + '?since_id=' + lastMessageId)
        .then(response => {
            console.log('[PTP Debug] Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('[PTP Debug] New messages:', data.count);
        });
}
```

4. **Check Network Tab**
   - Open DevTools → Network
   - Filter by "messages"
   - Should see requests every 5 seconds
   - Check response codes (should be 200)

5. **Verify Authentication**
```php
// Add to REST API class
public static function get_messages($request) {
    error_log('User ID: ' . get_current_user_id());
    error_log('Can manage options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
    
    // ... rest of code
}
```

**Common Fixes:**
- Clear browser cache
- Check JavaScript errors in console
- Verify user is logged in
- Check REST API authentication

---

### 6. Campaign Send Failures

**Symptoms:**
- Campaign shows failed status
- Only some messages sent
- Error in logs

**Debug Steps:**

1. **Check Campaign Status**
```sql
SELECT * FROM wp_ptp_campaigns WHERE id = [CAMPAIGN_ID];
-- Check status, total_recipients, messages_sent, messages_failed
```

2. **Review Failed Messages**
```sql
SELECT m.*, c.parent_first_name, c.parent_last_name, c.parent_phone
FROM wp_ptp_messages m
JOIN wp_ptp_contacts c ON m.contact_id = c.id
WHERE m.campaign_id = [CAMPAIGN_ID] AND m.status = 'failed';
```

3. **Check Twilio Logs**
   - Go to [Twilio Console → Monitor → Logs](https://console.twilio.com/monitor/logs)
   - Filter by date of campaign
   - Look for error codes:
     - 21211: Invalid phone number
     - 21610: Recipient opted out
     - 30003: Unreachable destination
     - 30005: Unknown destination

4. **Test Single Send**
```php
add_action('admin_init', function() {
    if (!isset($_GET['test_single'])) return;
    
    $sms = new PTP_Comms_Hub_SMS_Service();
    $result = $sms->send_sms('+15551234567', 'Test campaign message');
    
    error_log('Single send result: ' . print_r($result, true));
    wp_die('Check error log');
});
```

5. **Check Contact Opt-In Status**
```sql
-- Count recipients by opt-in status
SELECT opt_in_status, COUNT(*) as count
FROM wp_ptp_contacts
WHERE id IN (SELECT contact_id FROM wp_ptp_campaign_recipients WHERE campaign_id = [CAMPAIGN_ID])
GROUP BY opt_in_status;
```

**Common Fixes:**
- Re-send to failed recipients only
- Clean invalid phone numbers
- Verify all contacts opted in
- Check Twilio account balance
- Review rate limits

---

### 7. HubSpot Sync Issues

**Symptoms:**
- Contacts not syncing
- Deals not created
- API errors in logs

**Debug Steps:**

1. **Verify API Key**
```php
add_action('admin_init', function() {
    if (!isset($_GET['test_hubspot'])) return;
    
    $api_key = ptp_comms_get_setting('hubspot_api_key');
    
    $response = wp_remote_get('https://api.hubapi.com/crm/v3/objects/contacts?limit=1', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key
        )
    ));
    
    error_log('HubSpot Response: ' . print_r($response, true));
    wp_die('Check error log');
});
```

2. **Check HubSpot Integration Class**
Add logging to `includes/class-hubspot-sync.php`:
```php
public static function sync_contact($contact) {
    error_log('Syncing contact ID: ' . $contact->id);
    error_log('Contact email: ' . $contact->parent_email);
    
    // ... sync code
    
    error_log('Sync result: ' . print_r($result, true));
}
```

3. **Test API Scopes**
   - Go to [HubSpot App Settings](https://app.hubspot.com/private-apps)
   - Verify scopes include:
     - crm.objects.contacts.write
     - crm.objects.deals.write
     - crm.objects.companies.write

4. **Check Rate Limits**
```php
// Add to sync function
if (wp_remote_retrieve_response_code($response) === 429) {
    error_log('HubSpot rate limit hit!');
}
```

**Common Fixes:**
- Regenerate HubSpot API key
- Check required scopes
- Add rate limit handling
- Verify account has access to APIs

---

## Advanced Debugging Tools

### PHP Debug Script
Save as `wp-content/plugins/ptp-comms-hub-enterprise/debug.php`:

```php
<?php
/**
 * PTP Comms Hub Debug Script
 * Access: /wp-content/plugins/ptp-comms-hub-enterprise/debug.php
 */

require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

header('Content-Type: text/plain');

echo "PTP COMMS HUB DEBUG REPORT\n";
echo "==========================\n\n";

// Plugin Status
echo "PLUGIN STATUS\n";
echo "-------------\n";
echo "Plugin Active: " . (is_plugin_active('ptp-comms-hub-enterprise/ptp-comms-hub.php') ? 'YES' : 'NO') . "\n";
echo "Version: " . get_option('ptp_comms_version', 'Unknown') . "\n\n";

// Database Tables
global $wpdb;
echo "DATABASE TABLES\n";
echo "---------------\n";
$tables = ['ptp_contacts', 'ptp_messages', 'ptp_conversations', 'ptp_campaigns'];
foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'");
    echo "{$table}: " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table}");
        echo "  - Row count: {$count}\n";
    }
}
echo "\n";

// Settings
echo "TWILIO SETTINGS\n";
echo "---------------\n";
echo "Account SID: " . (ptp_comms_get_setting('twilio_account_sid') ? 'SET' : 'MISSING') . "\n";
echo "Auth Token: " . (ptp_comms_get_setting('twilio_auth_token') ? 'SET' : 'MISSING') . "\n";
echo "Phone Number: " . (ptp_comms_get_setting('twilio_phone_number') ?: 'MISSING') . "\n\n";

echo "TEAMS SETTINGS\n";
echo "--------------\n";
echo "Webhook URL: " . (ptp_comms_get_setting('teams_webhook_url') ? 'SET' : 'MISSING') . "\n\n";

echo "HUBSPOT SETTINGS\n";
echo "----------------\n";
echo "API Key: " . (ptp_comms_get_setting('hubspot_api_key') ? 'SET' : 'MISSING') . "\n\n";

// REST API Test
echo "REST API TEST\n";
echo "-------------\n";
$rest_url = rest_url('ptp-comms/v1/contacts');
echo "REST URL: {$rest_url}\n";

$response = wp_remote_get($rest_url, array(
    'cookies' => $_COOKIE
));

if (is_wp_error($response)) {
    echo "Error: " . $response->get_error_message() . "\n";
} else {
    echo "Status: " . wp_remote_retrieve_response_code($response) . "\n";
}
echo "\n";

// Recent Errors
echo "RECENT ERRORS (Last 24h)\n";
echo "------------------------\n";
$logs = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}ptp_logs 
    WHERE log_level = 'error' 
    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");

if ($logs) {
    foreach ($logs as $log) {
        echo "[{$log->created_at}] {$log->message}\n";
    }
} else {
    echo "No recent errors\n";
}
echo "\n";

echo "END DEBUG REPORT\n";
```

### SQL Diagnostic Queries

```sql
-- Check for orphaned messages
SELECT COUNT(*) as orphaned_messages
FROM wp_ptp_messages m
LEFT JOIN wp_ptp_conversations c ON m.conversation_id = c.id
WHERE c.id IS NULL;

-- Check for conversations with no messages
SELECT c.*
FROM wp_ptp_conversations c
LEFT JOIN wp_ptp_messages m ON c.id = m.conversation_id
WHERE m.id IS NULL;

-- Check message counts match
SELECT c.id, c.unread_count, 
       (SELECT COUNT(*) FROM wp_ptp_messages WHERE conversation_id = c.id AND direction = 'inbound' AND read_at IS NULL) as actual_unread
FROM wp_ptp_conversations c
WHERE c.unread_count != (SELECT COUNT(*) FROM wp_ptp_messages WHERE conversation_id = c.id AND direction = 'inbound' AND read_at IS NULL);

-- Check for duplicate contacts (same phone)
SELECT parent_phone, COUNT(*) as count
FROM wp_ptp_contacts
GROUP BY parent_phone
HAVING count > 1;

-- Campaign performance
SELECT 
    c.name,
    c.status,
    c.total_recipients,
    c.messages_sent,
    c.messages_failed,
    ROUND((c.messages_sent / c.total_recipients * 100), 2) as success_rate
FROM wp_ptp_campaigns c
ORDER BY c.created_at DESC
LIMIT 10;
```

---

## Performance Monitoring

### Slow Query Identification

Add to `wp-config.php`:
```php
define('SAVEQUERIES', true);
```

Then add to your theme's `functions.php`:
```php
add_action('wp_footer', function() {
    if (current_user_can('manage_options')) {
        global $wpdb;
        echo '<pre style="background: #000; color: #0f0; padding: 20px;">';
        print_r($wpdb->queries);
        echo '</pre>';
    }
});
```

### Memory Usage Monitoring

```php
add_action('admin_footer', function() {
    if (current_user_can('manage_options')) {
        echo '<div style="position: fixed; bottom: 10px; right: 10px; background: #000; color: #0f0; padding: 10px; border-radius: 5px; z-index: 9999;">';
        echo 'Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB';
        echo '</div>';
    }
});
```

---

## Getting Professional Help

### Information to Provide

When contacting support, include:

1. **Debug Report** (from debug.php script above)
2. **Error Log** (last 50 lines)
3. **Screenshot** of issue
4. **Steps to reproduce**
5. **WordPress version**
6. **PHP version**
7. **Active plugins list**
8. **Theme name**

### Where to Get Help

1. **WordPress Error Logs:** `wp-content/debug.log`
2. **PTP Comms Logs:** Admin → PTP Comms → Logs
3. **Twilio Debugger:** [console.twilio.com/monitor/logs](https://console.twilio.com/monitor/logs)
4. **Browser Console:** F12 → Console tab
5. **Server Logs:** Check with hosting provider

---

**Last Updated:** November 2025  
**Plugin Version:** 3.0  
**Author:** PTP Soccer Camps Development Team
