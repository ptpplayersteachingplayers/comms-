# Microsoft Teams Integration Setup Guide

## Overview
PTP Comms Hub now integrates with Microsoft Teams for real-time notifications and two-way communication. This replaces the previous Slack integration with a more robust Teams solution.

## What You Get
- **Real-time notifications** for SMS messages, voicemails, orders, and errors
- **Actionable cards** with buttons to reply, view contacts, and access the dashboard
- **Rich formatting** with PTP branding colors
- **Campaign completion reports** with success metrics
- **Error alerts** for system issues

## Setup Steps

### 1. Create Incoming Webhook in Teams

1. Open Microsoft Teams and navigate to the channel where you want notifications
2. Click the three dots (...) next to the channel name
3. Select **Connectors**
4. Search for **Incoming Webhook** and click **Add**
5. Give it a name like "PTP Comms Hub"
6. Optionally upload the PTP logo
7. Click **Create** and copy the webhook URL

### 2. Configure in WordPress

1. Go to **PTP Comms Hub → Settings**
2. Find the **Microsoft Teams Integration** section
3. Paste your webhook URL
4. Click **Save Changes**

### 3. Test the Connection

Click the **Send Test Message** button to verify Teams is receiving notifications.

## Notification Types

### 📱 SMS Messages
Get instant notifications when parents text you with:
- Parent name and phone number
- Full message content
- Quick action buttons to reply or view contact

### 📞 Voicemails
When parents leave voicemails, you'll see:
- Caller information
- Recording duration
- Link to listen to the recording
- Transcription (when available)

### 🎉 New Registrations
Celebrate new orders with cards showing:
- Parent and child information
- Order details and total
- Camp registration items
- Quick links to order and contact

### 📊 Campaign Completions
Get summaries when SMS campaigns finish:
- Messages sent vs failed
- Success rate percentage
- Color-coded status (green/yellow/red)

### ⚠️ Error Alerts
Stay informed of system issues with:
- Error message details
- Context about what triggered it
- Link to view logs

## Advanced Features

### Custom Channels
You can send different notification types to different channels by:
1. Creating multiple webhooks in Teams
2. Configuring webhook URLs for specific notification types
3. Routing notifications based on priority or type

### Bot Integration (Optional)
For two-way communication:
1. Register a bot in Azure Bot Service
2. Add bot credentials to settings
3. Enable commands like:
   - `/stats` - View communication statistics
   - `/search <phone>` - Find a contact
   - `/recent` - Show recent messages

## Troubleshooting

**Notifications not appearing?**
- Verify webhook URL is correct
- Check that the connector is still active in Teams
- Ensure your WordPress site can make outbound HTTPS requests

**Cards look broken?**
- Update to the latest version of Teams
- Check that adaptive cards are enabled in your Teams admin center

**Want to customize messages?**
Edit the notification templates in `includes/class-teams-integration.php`

## Migration from Slack

If you were using Slack previously:
1. All functionality has been ported to Teams
2. Update webhook URL in settings
3. Test all notification types
4. Remove old Slack webhook URL

Your notification preferences and history remain intact.
