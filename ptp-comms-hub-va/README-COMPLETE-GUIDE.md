# PTP Communications Hub - Complete User Guide

## Table of Contents
1. [Overview](#overview)
2. [Installation](#installation)
3. [Initial Setup](#initial-setup)
4. [Twilio Configuration](#twilio-configuration)
5. [Inbox - Messaging Center](#inbox---messaging-center)
6. [WhatsApp Integration](#whatsapp-integration)
7. [Email Notifications](#email-notifications)
8. [Google Sheets Sync](#google-sheets-sync)
9. [Campaigns](#campaigns)
10. [Reminders](#reminders)
11. [Contacts Management](#contacts-management)
12. [WooCommerce Integration](#woocommerce-integration)
13. [Microsoft Teams Integration](#microsoft-teams-integration)
14. [Private Training Integration](#private-training-integration)
15. [Webhook URLs Reference](#webhook-urls-reference)
16. [Troubleshooting](#troubleshooting)

---

## Overview

PTP Communications Hub is an enterprise WordPress plugin for managing communications with parents, trainers, and staff. It provides:

- **Multi-channel messaging**: SMS, WhatsApp, Voice, Teams
- **Shared inbox**: Real-time messaging with notification alerts
- **Campaign management**: Bulk SMS with queue-based sending
- **Contact CRM**: Full contact management with relationship scoring
- **WooCommerce integration**: Auto-sync orders and registrations
- **Google Sheets sync**: Export registrations automatically
- **Reminders & tasks**: VA task management system

---

## Installation

1. Upload `ptp-comms-hub-va.zip` to WordPress via **Plugins > Add New > Upload**
2. Activate the plugin
3. Go to **PTP Comms > Settings** to configure

### Requirements
- WordPress 5.8+
- PHP 7.4+
- WooCommerce (optional, for order sync)
- SSL certificate (required for webhooks)

---

## Initial Setup

### Step 1: Configure Twilio

1. Go to **PTP Comms > Settings > Twilio**
2. Enter your credentials:
   - **Account SID**: From Twilio Console
   - **Auth Token**: From Twilio Console
   - **Phone Number**: Your Twilio number (format: +1234567890)

### Step 2: Set Up Webhooks in Twilio

In your Twilio Console, configure these webhook URLs:

**For SMS:**
```
Messaging > Phone Numbers > Your Number > Messaging Configuration

When a message comes in: https://yoursite.com/wp-json/ptp-comms/v1/sms-incoming
Status callback URL: https://yoursite.com/wp-json/ptp-comms/v1/sms-status
```

**For Voice:**
```
Voice Configuration > When a call comes in:
https://yoursite.com/wp-json/ptp-comms/v1/incoming-call
```

### Step 3: Test the Connection

1. Go to **PTP Comms > Settings**
2. Click "Test Twilio Connection"
3. Send a test SMS to verify

---

## Twilio Configuration

### SMS Settings

| Setting | Description |
|---------|-------------|
| Account SID | Your Twilio Account SID |
| Auth Token | Your Twilio Auth Token |
| Phone Number | Your Twilio phone number |
| SMS Enabled | Enable/disable SMS sending |

### Webhook URLs (Use These in Twilio)

| Purpose | URL |
|---------|-----|
| SMS Incoming | `https://yoursite.com/wp-json/ptp-comms/v1/sms-incoming` |
| SMS Status | `https://yoursite.com/wp-json/ptp-comms/v1/sms-status` |
| WhatsApp Incoming | `https://yoursite.com/wp-json/ptp-comms/v1/whatsapp-incoming` |
| WhatsApp Status | `https://yoursite.com/wp-json/ptp-comms/v1/whatsapp-status` |
| Voice Incoming | `https://yoursite.com/wp-json/ptp-comms/v1/incoming-call` |
| Voicemail | `https://yoursite.com/wp-json/ptp-comms/v1/voicemail` |

---

## Inbox - Messaging Center

The inbox is your central hub for all communications.

### Accessing the Inbox
Go to **PTP Comms > Inbox**

### Features

1. **Conversation List** (Left Panel)
   - Shows all conversations sorted by recent activity
   - Unread count badges
   - Filter by channel (SMS, WhatsApp, Voice, Teams)
   - Search contacts

2. **Message Thread** (Center Panel)
   - Full conversation history
   - Message timestamps and delivery status
   - Channel indicators (SMS, WhatsApp, etc.)

3. **Send Messages** (Bottom)
   - Select channel (SMS, WhatsApp, Voice)
   - Character counter with SMS segment calculator
   - Enter to send (Shift+Enter for new line)

### Real-time Updates
- Messages poll every 8 seconds
- New incoming messages appear automatically
- Notification sound for new messages

### Keyboard Shortcuts
- `Enter` - Send message
- `Shift + Enter` - New line

---

## WhatsApp Integration

### Setup WhatsApp

1. Go to **PTP Comms > Settings > WhatsApp**
2. Configure:
   - **Enable WhatsApp**: Check to enable
   - **WhatsApp Phone Number**: Your WhatsApp-enabled Twilio number
   - **Sandbox Mode**: Enable for testing

### Configure Twilio WhatsApp Webhooks

In Twilio Console > Messaging > WhatsApp:

```
When a message comes in: https://yoursite.com/wp-json/ptp-comms/v1/whatsapp-incoming
Status callback: https://yoursite.com/wp-json/ptp-comms/v1/whatsapp-status
```

### Sandbox Mode (Testing)
1. Twilio Sandbox number: +14155238886
2. Users must send join code to opt-in
3. 24-hour session window applies

### Production Mode
- Apply for WhatsApp Business API via Twilio
- No opt-in required for responses
- Use templates for business-initiated messages

### User WhatsApp Notifications

Staff can receive WhatsApp alerts for new messages:

1. Go to **Users > Your Profile**
2. Scroll to **PTP WhatsApp Notification Settings**
3. Enable notifications and enter your WhatsApp number
4. Select notification types to receive

---

## Email Notifications

When a new SMS/WhatsApp arrives, the system sends email notifications.

### How It Works
1. Incoming message received via webhook
2. System creates in-app notification
3. Email sent to assigned VA or all admins
4. WhatsApp notification sent (if enabled)

### Email Contains
- Contact name and phone
- Message preview
- Direct link to conversation
- Professional HTML template

### Disable Email Notifications

Users can disable in their profile:
1. Go to **Users > Profile**
2. Set `ptp_email_notifications` to `disabled`

---

## Google Sheets Sync

Automatically sync WooCommerce orders/registrations to Google Sheets.

### Setup

#### Step 1: Create Google Apps Script

1. Go to [script.google.com](https://script.google.com)
2. Create new project
3. Paste this code:

```javascript
function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var rows = data.rows;

    if (!rows || rows.length === 0) {
      return ContentService.createTextOutput(JSON.stringify({success: true, message: 'No data'}));
    }

    var ss = SpreadsheetApp.getActiveSpreadsheet();

    // Group rows by sheet_name
    var grouped = {};
    rows.forEach(function(row) {
      var sheetName = row.sheet_name || 'Orders';
      if (!grouped[sheetName]) grouped[sheetName] = [];
      grouped[sheetName].push(row);
    });

    // Process each group
    for (var sheetName in grouped) {
      var sheet = ss.getSheetByName(sheetName);
      if (!sheet) {
        sheet = ss.insertSheet(sheetName);
        // Add headers
        var headers = Object.keys(grouped[sheetName][0]);
        sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
      }

      // Add rows
      grouped[sheetName].forEach(function(row) {
        var values = Object.values(row);
        sheet.appendRow(values);
      });
    }

    return ContentService.createTextOutput(JSON.stringify({success: true}));
  } catch(err) {
    return ContentService.createTextOutput(JSON.stringify({success: false, error: err.message}));
  }
}
```

4. Deploy as Web App:
   - Execute as: Me
   - Who has access: Anyone
5. Copy the Web App URL

#### Step 2: Configure in WordPress

1. Go to **PTP Comms > Settings > Google Sheets**
2. Enable Google Sheets Sync
3. Paste your Web App URL
4. Click "Test Connection"

#### Step 3: Sync Orders

- **Auto Sync**: Orders sync automatically when processed
- **Manual Sync**: Click "Sync Now" button
- **Bulk Sync**: Select date range (7/30/90 days or all)

### Data Synced

| Field | Description |
|-------|-------------|
| Order ID | WooCommerce order number |
| Parent Info | Name, email, phone |
| Camper Info | Name, age, level, team |
| Product | Camp/event purchased |
| Event Details | Date, time, location |
| Emergency Contact | Name and phone |
| Medical Info | Allergies, special needs |

---

## Campaigns

Send bulk SMS messages with queue-based delivery.

### Create Campaign

1. Go to **PTP Comms > Campaigns**
2. Click "New Campaign"
3. Configure:
   - **Name**: Campaign identifier
   - **Message**: Your SMS content (use variables)
   - **Segment**: Target audience
   - **Schedule**: Send now or later

### Message Variables

Use these in your message:
```
{first_name} - Parent's first name
{child_name} - Child's name
{event_date} - Event date
{event_location} - Event location
```

### Campaign Actions

- **Start**: Begin sending
- **Pause**: Temporarily stop
- **Resume**: Continue sending
- **View Progress**: See sent/failed counts

### Rate Limiting
- Max 10 campaigns per hour per user
- 50 messages per batch (configurable)
- 0.1 second delay between messages

---

## Reminders

Task management for VAs and staff.

### Create Reminder

1. Go to **PTP Comms > Reminders**
2. Click "Add Reminder"
3. Fill in:
   - **Title**: What needs to be done
   - **Contact**: Related contact (optional)
   - **Due Date**: When it's due
   - **Priority**: Urgent/High/Normal/Low
   - **Recurring**: One-time or recurring

### Reminder Types

| Type | Use For |
|------|---------|
| Follow Up | General follow-ups |
| Callback | Phone call requests |
| Birthday | Birthday messages |
| Event Prep | Pre-event tasks |
| Payment | Payment reminders |
| Feedback | Request feedback |

### Notification Methods
- Email
- Teams
- In-app notification
- WhatsApp (if enabled)

### Recurring Options
- Daily
- Weekly
- Bi-weekly
- Monthly
- Quarterly
- Yearly

---

## Contacts Management

### View Contacts
Go to **PTP Comms > Contacts**

### Contact Fields

| Field | Description |
|-------|-------------|
| Parent Name | First and last name |
| Phone | Primary phone number |
| Email | Email address |
| Child Name | Child's name |
| Child Age | Child's age |
| Location | City, State, ZIP |
| Segments | Auto-assigned tags |
| Relationship Score | Engagement metric (0-100) |

### Contact Actions
- **Edit**: Update contact info
- **View Conversation**: Open inbox
- **Add Note**: Add internal notes
- **Create Reminder**: Set follow-up

### Segments

Contacts are auto-segmented by:
- Market (PA, NJ, DE, etc.)
- Program type (half_day, full_day, clinic)
- Age group (u6, u8, u10, u12, teen)
- Registration year

### Opt-In/Opt-Out

- Contacts who text STOP are auto-opted-out
- Contacts who text START are auto-opted-in
- Manual opt-in/out in contact edit page

---

## WooCommerce Integration

### Auto-Sync Orders

Orders automatically sync when status changes to:
- Processing
- Completed
- On-Hold

### What Gets Synced
- Contact created/updated
- Registration record created
- Segments updated
- Google Sheets synced

### Manual Sync

1. Go to **PTP Comms > Orders**
2. Click "Sync All Orders"
3. Or sync individual orders

### Checkout Fields

The plugin adds these checkout fields:
- Camper First/Last Name
- Camper Age
- T-Shirt Size
- Medical/Special Needs
- Emergency Contact
- SMS Opt-in Checkbox

### Product Camp Fields

Add to WooCommerce products:
- Camp Date/Time
- Location/Address
- Program Type
- Age Range
- Capacity
- Head Coach

---

## Microsoft Teams Integration

### Setup Teams Connector

1. In Teams, go to your channel
2. Click **...** > **Connectors**
3. Add **Incoming Webhook**
4. Copy the webhook URL

### Configure in WordPress

1. Go to **PTP Comms > Settings > Teams**
2. Paste webhook URL
3. Enable Teams notifications

### Teams Shared Inbox

For bidirectional messaging:
1. Set up Teams Shared Inbox webhook URL
2. Messages appear in Teams channel
3. Reply from Teams to respond

---

## Private Training Integration

For trainer-parent-VA communication.

### Features
- Session booking notifications
- Session reminders (24h, 1h before)
- Feedback collection
- Trainer messaging

### Shortcodes

Add to your site:
```
[ptp_message_trainer trainer_id="123"]  - Message trainer form
[ptp_training_inbox]                     - View message history
```

### Action Hooks

For developers integrating with training plugins:
```php
// Session booked
do_action('ptp_training_session_booked', $session_id, $parent_id, $trainer_id);

// Session cancelled
do_action('ptp_training_session_cancelled', $session_id, $parent_id, $trainer_id);

// Session completed
do_action('ptp_training_session_completed', $session_id, $parent_id, $trainer_id);
```

---

## Webhook URLs Reference

### REST API Endpoints (Recommended)

| Purpose | URL | Method |
|---------|-----|--------|
| SMS Incoming | `/wp-json/ptp-comms/v1/sms-incoming` | POST |
| SMS Status | `/wp-json/ptp-comms/v1/sms-status` | POST |
| WhatsApp Incoming | `/wp-json/ptp-comms/v1/whatsapp-incoming` | POST |
| WhatsApp Status | `/wp-json/ptp-comms/v1/whatsapp-status` | POST |
| Voice Incoming | `/wp-json/ptp-comms/v1/incoming-call` | POST |
| Voicemail | `/wp-json/ptp-comms/v1/voicemail` | POST |
| Teams Reply | `/wp-json/ptp-comms/v1/teams-reply` | POST |

### Legacy Rewrite URLs (Backup)

| Purpose | URL |
|---------|-----|
| SMS | `/ptp-comms/sms-webhook` |
| SMS Status | `/ptp-comms/sms-status` |
| Voice | `/ptp-comms/voice-webhook` |
| Test | `/ptp-comms/test-webhook` |

---

## Troubleshooting

### Messages Not Sending

1. Check Twilio credentials in Settings
2. Verify phone number format (+1234567890)
3. Check Twilio console for errors
4. Enable WordPress debug log

### Not Receiving Incoming Messages

1. Verify webhook URLs in Twilio
2. Check SSL certificate is valid
3. Test webhook URL: `yoursite.com/ptp-comms/test-webhook`
4. Check WordPress error log

### Double Messages Sending

Fixed in latest version. If still occurring:
1. Update to latest plugin version
2. Clear browser cache
3. Only one form handler should be active

### Google Sheets Not Syncing

1. Verify Web App URL is correct
2. Re-deploy Google Apps Script
3. Check "Anyone" has access
4. Test connection in settings

### Webhooks Not Working

1. Check `.htaccess` allows REST API
2. Verify permalink structure is not "Plain"
3. Flush permalinks: Settings > Permalinks > Save

### Debug Mode

Enable WordPress debug logging:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at: `/wp-content/debug.log`

---

## Support

- **Plugin Issues**: Check `/wp-content/debug.log`
- **Twilio Issues**: [Twilio Support](https://support.twilio.com)
- **WhatsApp Business**: [WhatsApp Help](https://business.whatsapp.com/help)

---

## Quick Reference Card

### Send SMS
```php
$sms = new PTP_Comms_Hub_SMS_Service();
$result = $sms->send_sms('+1234567890', 'Hello!');
```

### Send WhatsApp
```php
ptp_comms_send_whatsapp('+1234567890', 'Hello via WhatsApp!');
```

### Check Configuration
```php
if (ptp_comms_is_twilio_configured()) { /* Twilio ready */ }
if (ptp_comms_is_whatsapp_configured()) { /* WhatsApp ready */ }
if (ptp_comms_is_teams_configured()) { /* Teams ready */ }
```

### Create Contact
```php
$contact_id = ptp_comms_get_or_create_contact('+1234567890', array(
    'parent_first_name' => 'John',
    'parent_last_name' => 'Doe',
    'parent_email' => 'john@example.com'
));
```

### Log Message
```php
ptp_comms_log_message($contact_id, 'sms', 'outbound', 'Message text', array(
    'twilio_sid' => $sid
));
```

---

*PTP Communications Hub Enterprise v4.0.0*
