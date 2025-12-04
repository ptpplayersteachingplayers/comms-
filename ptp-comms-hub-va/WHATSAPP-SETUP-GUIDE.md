# WhatsApp Integration Setup Guide

## Overview

PTP Communications Hub supports WhatsApp messaging through the Twilio WhatsApp Business API. This allows you to send and receive WhatsApp messages directly from your inbox, with notifications pushed to your team.

## Prerequisites

1. **Twilio Account** - You need an active Twilio account
2. **Twilio WhatsApp Sender** - Either:
   - Sandbox mode for testing
   - WhatsApp Business Profile for production

## Setup Steps

### Step 1: Configure Twilio Credentials

1. Go to **PTP Comms > Settings > Twilio Tab**
2. Enter your Twilio credentials:
   - **Account SID**: Found in Twilio Console Dashboard
   - **Auth Token**: Found in Twilio Console Dashboard
   - **Phone Number**: Your Twilio phone number (for SMS)

### Step 2: Enable WhatsApp

1. Go to **PTP Comms > Settings > WhatsApp Tab**
2. Configure the following:
   - **Enable WhatsApp**: Check to enable
   - **WhatsApp Phone Number**: Your WhatsApp-enabled Twilio number (format: +1234567890)
   - **Sandbox Mode**: Enable for testing (uses Twilio sandbox number)

### Step 3: Configure Twilio WhatsApp Sandbox (Testing)

If using sandbox mode for testing:

1. Go to [Twilio Console > Messaging > Try it Out > Send a WhatsApp Message](https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-learn)
2. Note the sandbox number: `+14155238886`
3. Send the join code from your phone to activate

**Sandbox Limitations:**
- Recipients must opt-in by sending the join code
- Cannot initiate conversations (24-hour session window)
- Rate limited
- Not for production use

### Step 4: Configure Webhooks

For receiving incoming WhatsApp messages, configure these webhooks in Twilio:

1. Go to [Twilio Console > Messaging > Settings > WhatsApp Sandbox Settings](https://console.twilio.com/us1/develop/sms/settings/whatsapp-sandbox)

2. Set the following webhooks:

   **When a message comes in:**
   ```
   https://yoursite.com/wp-json/ptp-comms/v1/whatsapp-incoming
   ```

   **Status callback URL:**
   ```
   https://yoursite.com/wp-json/ptp-comms/v1/whatsapp-status
   ```

3. Set HTTP Method to **POST** for both

### Step 5: Production WhatsApp (Recommended)

For production use:

1. Apply for WhatsApp Business API access through Twilio
2. Submit your business profile for approval
3. Get your WhatsApp-enabled number approved
4. Configure message templates for business-initiated messages

**Benefits of Production:**
- Send messages without 24-hour window restrictions (using templates)
- Higher rate limits
- Professional business profile
- Verified sender badge

## User Notification Setup

### Enable WhatsApp Notifications for Team Members

Each team member can receive WhatsApp alerts for inbox activity:

1. Go to **Users > Your Profile**
2. Scroll to **PTP WhatsApp Notification Settings**
3. Configure:
   - **Enable WhatsApp Notifications**: Check to receive alerts
   - **WhatsApp Phone Number**: Your personal WhatsApp number
   - **Notification Types**: Select which events trigger WhatsApp alerts
   - **Shared Inbox Alerts**: Enable to receive all inbox messages

### Available Notification Types

- **New Message** - When any new message arrives
- **Contact Replied** - When a contact responds to your message
- **Reminder Due** - When a reminder is due
- **Voicemail** - New voicemail notifications
- **Missed Call** - Missed call alerts

## Using WhatsApp in the Inbox

### Sending WhatsApp Messages

1. Go to **PTP Comms > Inbox**
2. Select a contact or conversation
3. In the message composer, select **WhatsApp** from the channel dropdown
4. Type your message and send

### Channel Indicators

Messages display channel badges:
- 📱 SMS
- 💬 WhatsApp
- 📞 Voice
- 👥 Teams

### WhatsApp Session Window

**Important:** WhatsApp has a 24-hour session window rule:

- If a contact messages you first, you can reply freely for 24 hours
- After 24 hours without a response, you must use a template message
- Templates must be pre-approved by WhatsApp

## Troubleshooting

### Messages Not Sending

1. Check Twilio credentials are correct
2. Verify WhatsApp is enabled in settings
3. Check phone number format includes country code
4. Review Twilio console for error logs

### Not Receiving Messages

1. Verify webhook URLs are configured correctly
2. Check your site SSL certificate is valid
3. Test webhook URL is accessible publicly
4. Review WordPress debug log for errors

### Sandbox Issues

1. Ensure recipient has sent join code
2. Session window may have expired
3. Check Twilio sandbox isn't rate limited

## API Endpoints

### Incoming Message Webhook
```
POST /wp-json/ptp-comms/v1/whatsapp-incoming
```

### Status Callback Webhook
```
POST /wp-json/ptp-comms/v1/whatsapp-status
```

## Code Examples

### Sending a WhatsApp Message Programmatically

```php
// Send a WhatsApp message
$result = ptp_comms_send_whatsapp('+1234567890', 'Hello from PTP!');

if ($result['success']) {
    echo 'Message sent! SID: ' . $result['sid'];
} else {
    echo 'Error: ' . $result['error'];
}
```

### Checking WhatsApp Configuration

```php
if (ptp_comms_is_whatsapp_configured()) {
    // WhatsApp is ready to use
}
```

### Getting WhatsApp Instance

```php
$whatsapp = ptp_comms_whatsapp();
if ($whatsapp && $whatsapp->is_configured()) {
    $stats = $whatsapp->get_stats();
}
```

## Best Practices

1. **Always get consent** - Ensure contacts opt-in to WhatsApp messaging
2. **Use templates** - Create approved templates for business-initiated messages
3. **Respect timing** - Be mindful of time zones when sending
4. **Keep messages concise** - WhatsApp users expect brief, conversational messages
5. **Monitor delivery** - Check status callbacks for failed messages
6. **Test in sandbox first** - Always test before going live

## Support

For issues with:
- **Twilio**: [Twilio Support](https://support.twilio.com)
- **WhatsApp Business API**: [WhatsApp Business Help](https://business.whatsapp.com/help)
- **Plugin Issues**: Contact your administrator
