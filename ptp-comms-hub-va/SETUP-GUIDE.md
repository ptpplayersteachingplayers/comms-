# PTP Comms Hub Enterprise - Complete Setup Guide

##  Overview

PTP Comms Hub Enterprise is a comprehensive communication platform for PTP Soccer Camps that integrates:
- **Twilio** (SMS & Voice)
- **HubSpot** (CRM Sync)
- **Microsoft Teams** (Team Notifications & Commands)
- **WooCommerce** (Order Sync)

## Table of Contents

1. [Installation](#installation)
2. [Twilio Setup](#twilio-setup)
3. [HubSpot Setup](#hubspot-setup)
4. [Microsoft Teams Setup](#teams-setup)
5. [WooCommerce Integration](#woocommerce-integration)
6. [Webhook URLs](#webhook-urls)
7. [Features](#features)
8. [Troubleshooting](#troubleshooting)

---

##  Installation

### Step 1: Upload Plugin

1. Upload `ptp-comms-hub-enterprise` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Navigate to **PTP Comms** in the admin menu

### Step 2: Database Tables

Upon activation, the plugin automatically creates these tables:
- `wp_ptp_contacts` - Customer/parent contact information
- `wp_ptp_registrations` - Event registrations
- `wp_ptp_campaigns` - SMS campaigns
- `wp_ptp_communication_logs` - All message history
- `wp_ptp_templates` - Message templates
- `wp_ptp_conversations` - Threaded conversations
- `wp_ptp_automations` - Automation rules
- `wp_ptp_product_settings` - Product-specific settings
- `wp_ptp_teams_messages` - Microsoft Teams message history

---

##  Twilio Setup

### Prerequisites
- Twilio account ([sign up](https://www.twilio.com/try-twilio))
- Phone number purchased in Twilio

### Configuration Steps

1. **Get Credentials**
   - Log into [Twilio Console](https://console.twilio.com)
   - Copy your **Account SID** and **Auth Token**
   - Note your purchased **Phone Number**

2. **Configure in WordPress**
   - Go to **PTP Comms → Settings → Twilio**
   - Enter:
     - Account SID
     - Auth Token  
     - Phone Number (in E.164 format: +12025551234)
   - Click **Save Settings**

3. **Configure Webhooks in Twilio**
   - Go to your Twilio phone number settings
   - Under **Messaging**, set:
     - **A MESSAGE COMES IN**: `https://yourdomain.com/ptp-comms/sms-webhook`
     - **METHOD**: POST
   - Under **Voice**, set:
     - **A CALL COMES IN**: `https://yourdomain.com/ptp-comms/voice-webhook`
     - **METHOD**: POST

### Testing
- Send a text to your Twilio number
- Check **PTP Comms → Inbox** to see the message

---

## 🔄 HubSpot Setup

### Prerequisites
- HubSpot account with **Sales Hub** or higher
- Admin access to create Private Apps

### Configuration Steps

1. **Create Private App**
   - Go to HubSpot Settings → Integrations → Private Apps
   - Click **Create a private app**
   - Name it "PTP Comms Hub"
   
2. **Configure Scopes**
   Enable these scopes:
   - `crm.objects.contacts.write` - Create/update contacts
   - `crm.objects.contacts.read` - Read contact data
   - `crm.objects.deals.write` - Create deals from orders
   - `crm.schemas.contacts.read` - Read custom properties
   - `crm.objects.deals.read` - Read deal data

3. **Get API Key**
   - Copy the **Private App Token** (starts with `pat-na1-...`)
   
4. **Configure in WordPress**
   - Go to **PTP Comms → Settings → HubSpot**
   - Enter the Private App Token
   - Enable **Auto-Sync Contacts**
   - Set **Sync Frequency** (Daily recommended)
   - Click **Save Settings**

### Custom Properties (Optional)

Add these custom properties in HubSpot for better tracking:
- `ptp_child_name` (Single-line text)
- `ptp_child_age` (Number)
- `ptp_last_event_date` (Date)
- `ptp_total_registrations` (Number)

### Testing
1. Place a test order in WooCommerce
2. Check HubSpot Contacts - should see the new contact
3. Verify the deal was created and associated

---

## 💬 Microsoft Teams Setup

### Two Options:

#### Option A: Basic (Outgoing Notifications Only)

1. **Create Incoming Webhook**
   - Go to your Microsoft Teams workspace
   - Navigate to **Apps → Incoming Webhooks**
   - Click **Add to Microsoft Teams**
   - Choose a channel (e.g., #ptp-notifications)
   - Copy the **Webhook URL**

2. **Configure in WordPress**
   - Go to **PTP Comms → Settings → Microsoft Teams**
   - Paste Webhook URL
   - Enable desired notifications
   - Click **Save Settings**

#### Option B: Full (Two-Way with Commands)

1. **Create Microsoft Teams App**
   - Go to [api.teams.com/apps](https://api.teams.com/apps)
   - Click **Create New App** → From scratch
   - Name it "PTP Comms Hub"
   - Select your workspace

2. **Configure OAuth & Permissions**
   - Go to **OAuth & Permissions**
   - Add these **Bot Token Scopes**:
     - `chat:write` - Send messages
     - `users:read` - Read user info
     - `channels:history` - Read messages
     - `app_mentions:read` - Get @mentions
   - Click **Install to Workspace**
   - Copy the **Bot User OAuth Token** (starts with `xoxb-...`)

3. **Configure Event Subscriptions**
   - Go to **Event Subscriptions**
   - Enable Events: **ON**
   - Set Request URL: `https://yourdomain.com/ptp-comms/teams-webhook`
   - Add **Bot Events**:
     - `message.channels` - Listen to messages
     - `app_mention` - Get mentioned
   - Click **Save Changes**

4. **Configure Interactive Components**
   - Go to **Interactivity & Shortcuts**
   - Enable: **ON**
   - Request URL: `https://yourdomain.com/ptp-comms/teams-interactive`
   - Click **Save Changes**

5. **Get Signing Secret**
   - Go to **Basic Information**
   - Under **App Credentials**, copy **Signing Secret**

6. **Configure in WordPress**
   - Go to **PTP Comms → Settings → Microsoft Teams**
   - Enter:
     - Webhook URL (for notifications)
     - Bot User OAuth Token
     - Signing Secret
   - Enable desired notifications
   - Click **Save Settings**

### Microsoft Teams Commands

Once configured, you can use these in Microsoft Teams:
- `@PTP Comms Hub help` - Show available commands
- `@PTP Comms Hub stats` - View statistics
- `@PTP Comms Hub search [phone/email]` - Find a contact

### Testing
1. Send a test message: `@PTP Comms Hub stats`
2. Should receive statistics response
3. Place an order - should see Microsoft Teams notification

---

## 🛒 WooCommerce Integration

### Prerequisites
- WooCommerce installed and activated
- Products configured with these meta fields:
  - `_program_type` (e.g., "camp", "clinic", "training")
  - `_market_slug` (e.g., "philadelphia", "new-york")

### Configuration Steps

1. **Configure Settings**
   - Go to **PTP Comms → Settings → WooCommerce**
   - Enable:
     - Automatically create contacts from orders
     - Auto opt-in new contacts
     - Send SMS confirmation
   - Select a **Confirmation Template**
   - Click **Save Settings**

2. **Add Custom Meta to Products**

```php
// Add this to your theme's functions.php or custom plugin:

add_action('woocommerce_product_options_general_product_data', function() {
    woocommerce_wp_text_input([
        'id' => '_program_type',
        'label' => 'Program Type',
        'placeholder' => 'camp, clinic, training',
        'desc_tip' => true,
        'description' => 'Type of program for automations'
    ]);
    
    woocommerce_wp_text_input([
        'id' => '_market_slug',
        'label' => 'Market Slug',
        'placeholder' => 'philadelphia, new-york',
        'desc_tip' => true,
        'description' => 'Market location slug'
    ]);
});

add_action('woocommerce_process_product_meta', function($post_id) {
    update_post_meta($post_id, '_program_type', sanitize_text_field($_POST['_program_type']));
    update_post_meta($post_id, '_market_slug', sanitize_text_field($_POST['_market_slug']));
});
```

### Order Flow

When an order is completed:
1. Contact created/updated
2. Registration record created
3. HubSpot contact synced (if enabled)
4. Microsoft Teams notification sent (if enabled)
5. Confirmation SMS sent (if enabled)
6. Automations triggered

---

## 🔗 Webhook URLs

Configure these URLs in your external services:

### Twilio Webhooks
- **SMS Incoming**: `https://yourdomain.com/ptp-comms/sms-webhook`
- **Voice Incoming**: `https://yourdomain.com/ptp-comms/voice-webhook`

### Microsoft Teams Webhooks
- **Events API**: `https://yourdomain.com/ptp-comms/teams-webhook`
- **Interactive Components**: `https://yourdomain.com/ptp-comms/teams-interactive`

**Important**: Replace `yourdomain.com` with your actual domain. Must use HTTPS.

---

## ✨ Features

### 📊 Dashboard
- Real-time statistics
- Quick actions
- System status indicators
- Connection monitoring

### 👥 Contacts
- Import/export contacts
- Bulk operations
- Opt-in/opt-out management
- HubSpot sync status
- Communication history

###  SMS Campaigns
- Scheduled sending
- Segment targeting
- Template usage
- Delivery tracking
- Real-time analytics

### 📧 Templates
- Pre-built templates
- Variable replacement
- Category organization
- Usage tracking

### 💬 Inbox
- Two-way conversations
- Threaded messages
- Quick replies
- Contact context
- Assignment workflow

### 🤖 Automations
- Trigger-based actions
- Delay scheduling
- Conditional logic
- Template integration
- Performance tracking

### 📈 Logs
- Complete message history
- Delivery status
- Error tracking
- Export capabilities
- Search and filter

---

## 🐛 Troubleshooting

### Twilio Messages Not Sending

**Check:**
1. Verify credentials in Settings → Twilio
2. Ensure phone number is in E.164 format (+12025551234)
3. Check Twilio console for error logs
4. Verify contact has opted in
5. Check **PTP Comms → Logs** for errors

**Common Issues:**
- Missing country code: Add +1 to US numbers
- Invalid auth token: Regenerate in Twilio console
- Unverified numbers: Add them in Twilio console (free trial accounts)

### Twilio Webhooks Not Working

**Check:**
1. Webhook URLs are configured in Twilio console
2. URLs use HTTPS (required by Twilio)
3. WordPress permalinks are set to "Post name" or custom
4. Run this code to flush rewrite rules:
```php
flush_rewrite_rules();
```

**Test Webhook:**
```bash
curl -X POST https://yourdomain.com/ptp-comms/sms-webhook \
  -d "From=+12025551234" \
  -d "Body=Test message"
```

### HubSpot Sync Failures

**Check:**
1. API key is valid (test in HubSpot API console)
2. Private App has required scopes
3. HubSpot plan supports API access
4. Custom properties are created (if used)
5. Check **PTP Comms → Logs** for API errors

**Test Sync Manually:**
```php
// Add this to a page template temporarily:
PTP_Comms_Hub_HubSpot_Sync::sync_contact(1); // Use actual contact ID
```

### Microsoft Teams Notifications Not Appearing

**Check:**
1. Webhook URL is correct
2. Bot is invited to the channel
3. Notification preferences are enabled
4. Test with this code:
```php
PTP_Comms_Hub_Microsoft Teams_Integration::send_message('Test notification');
```

### Microsoft Teams Commands Not Responding

**Check:**
1. Event Subscriptions are enabled
2. Request URL is verified (green checkmark)
3. Bot has required scopes
4. Signing secret is correct
5. Bot is invited to channels

**Verify URL:**
- Microsoft Teams should show "Verified" next to Request URL
- If not, save the URL and wait for Microsoft Teams to ping it
- Check WordPress debug.log for errors

### WooCommerce Orders Not Creating Contacts

**Check:**
1. WooCommerce is active
2. Auto-create is enabled in Settings → WooCommerce
3. Order has a billing phone number
4. Order status is "completed" or "processing"
5. Check **PTP Comms → Contacts** after order

**Test Hook:**
```php
// Test with existing order ID:
do_action('woocommerce_order_status_completed', 123);
```

### Messages Show as Sent But Not Received

**Check:**
1. Contact has valid phone number
2. Phone number is in E.164 format
3. Contact is opted in
4. Check Twilio console message logs
5. Verify carrier compatibility

---

## 🔐 Security

### Best Practices

1. **Keep Credentials Secure**
   - Never commit credentials to version control
   - Use environment variables when possible
   - Regenerate tokens if exposed

2. **Webhook Security**
   - Twilio webhooks are automatically validated
   - Microsoft Teams requests are signature-verified
   - All webhooks require HTTPS

3. **User Permissions**
   - Only administrators can access settings
   - Logs contain sensitive information
   - Regular audit of user access

### Environment Variables (Optional)

```php
// In wp-config.php:
define('PTP_TWILIO_SID', 'ACxxxxxxxx');
define('PTP_TWILIO_TOKEN', 'your-token');
define('PTP_TWILIO_PHONE', '+12025551234');
define('PTP_HUBSPOT_KEY', 'pat-na1-xxxxx');
define('PTP_TEAMS_WEBHOOK', 'https://hooks.teams.com/...');
```

---

## 📊 Performance

### Optimization Tips

1. **Cron Jobs**
   - Automations run hourly
   - HubSpot sync runs daily (configurable)
   - Use WP-Cron alternative for reliability

2. **Database**
   - Tables are indexed for performance
   - Regular cleanup of old logs recommended
   - Use caching plugin

3. **API Rate Limits**
   - Twilio: 60 messages/second (default)
   - HubSpot: 100 requests/10 seconds
   - Microsoft Teams: 1 message/second/channel

---

## 🆘 Support

### Debug Mode

Enable WordPress debug mode:
```php
// In wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check `/wp-content/debug.log` for errors.

### Enable Plugin Logging

Go to **Settings → General** and enable "Detailed logging"

### Getting Help

1. Check documentation above
2. Review WordPress debug.log
3. Check external service dashboards
4. Test webhooks with curl
5. Verify all credentials are current

---

## 📝 Changelog

### Version 1.0.0
- Initial release
- Twilio SMS/Voice integration
- HubSpot contact sync
- Microsoft Teams notifications & commands
- WooCommerce order sync
- Campaign management
- Template system
- Automation engine
- Conversation inbox
- Complete admin interface

---

## 📄 License

GPL-2.0+

Copyright © 2024 PTP Soccer Camps
