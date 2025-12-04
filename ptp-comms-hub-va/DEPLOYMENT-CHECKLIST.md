# PTP Comms Hub - Quick Start Checklist

## Pre-Deployment

- [ ] WordPress 5.8+ installed
- [ ] WooCommerce 5.0+ installed
- [ ] PHP 7.4+ enabled
- [ ] SSL certificate installed (HTTPS required)
- [ ] Permalinks set to "Post name" or custom

## Plugin Installation

- [ ] Upload plugin to `/wp-content/plugins/ptp-comms-hub-enterprise`
- [ ] Activate plugin in WordPress admin
- [ ] Verify all database tables created (check phpMyAdmin)
- [ ] Flush permalinks (Settings → Permalinks → Save)

## Twilio Configuration (REQUIRED for SMS)

### Get Credentials
- [ ] Create Twilio account at twilio.com
- [ ] Purchase phone number
- [ ] Copy Account SID from console
- [ ] Copy Auth Token from console

### Configure in WordPress
- [ ] Go to PTP Comms → Settings → Twilio
- [ ] Enter Account SID
- [ ] Enter Auth Token
- [ ] Enter Phone Number (+12025551234 format)
- [ ] Save Settings
- [ ] Verify "Connected" status appears

### Configure Webhooks in Twilio
- [ ] Go to Twilio Console → Phone Numbers
- [ ] Click your phone number
- [ ] Under Messaging:
  - [ ] Set webhook: `https://yourdomain.com/ptp-comms/sms-webhook`
  - [ ] Method: POST
- [ ] Under Voice:
  - [ ] Set webhook: `https://yourdomain.com/ptp-comms/voice-webhook`
  - [ ] Method: POST
- [ ] Save

### Test
- [ ] Send test SMS to your Twilio number
- [ ] Check PTP Comms → Inbox for message
- [ ] Reply "TEST" - should receive auto-response

## HubSpot Configuration (OPTIONAL)

### Create Private App
- [ ] Log into HubSpot
- [ ] Settings → Integrations → Private Apps
- [ ] Create new app: "PTP Comms Hub"
- [ ] Enable scopes:
  - [ ] crm.objects.contacts.write
  - [ ] crm.objects.contacts.read
  - [ ] crm.objects.deals.write
  - [ ] crm.schemas.contacts.read
- [ ] Generate and copy token

### Configure in WordPress
- [ ] Go to PTP Comms → Settings → HubSpot
- [ ] Paste API Key
- [ ] Enable Auto-Sync
- [ ] Set frequency to Daily
- [ ] Save Settings
- [ ] Verify "Connected" status appears

### Test
- [ ] Place test order
- [ ] Check HubSpot Contacts - new contact should appear
- [ ] Verify deal created and associated

## Microsoft Teams Configuration (OPTIONAL)

### Quick Setup (Notifications Only)
- [ ] Go to teams.com/apps
- [ ] Add "Incoming Webhooks"
- [ ] Choose channel (e.g., #ptp-notifications)
- [ ] Copy webhook URL
- [ ] Paste in PTP Comms → Settings → Microsoft Teams
- [ ] Enable desired notifications
- [ ] Save Settings

### Full Setup (Commands & Two-Way)
- [ ] Create app at api.teams.com/apps
- [ ] Name: "PTP Comms Hub"
- [ ] Add Bot Token Scopes:
  - [ ] chat:write
  - [ ] users:read
  - [ ] channels:history
  - [ ] app_mentions:read
- [ ] Install to workspace
- [ ] Copy Bot User OAuth Token
- [ ] Enable Event Subscriptions
- [ ] Set Request URL: `https://yourdomain.com/ptp-comms/teams-webhook`
- [ ] Subscribe to events:
  - [ ] message.channels
  - [ ] app_mention
- [ ] Enable Interactive Components
- [ ] Set Request URL: `https://yourdomain.com/ptp-comms/teams-interactive`
- [ ] Copy Signing Secret from Basic Information
- [ ] Paste all credentials in PTP Comms → Settings → Microsoft Teams
- [ ] Save Settings

### Test
- [ ] Invite bot to a channel: `/invite @PTP Comms Hub`
- [ ] Send message: `@PTP Comms Hub stats`
- [ ] Should receive statistics response
- [ ] Place test order - should see Microsoft Teams notification

## WooCommerce Configuration

### Settings
- [ ] Go to PTP Comms → Settings → WooCommerce
- [ ] Enable "Auto-create contacts"
- [ ] Enable "Auto opt-in"
- [ ] Enable "Send confirmation"
- [ ] Select confirmation template
- [ ] Save Settings

### Product Setup
- [ ] Add custom fields to products:
  - [ ] _program_type (camp, clinic, training)
  - [ ] _market_slug (philadelphia, new-york, etc)
- [ ] Test with sample product

### Test
- [ ] Place test order with phone number
- [ ] Check PTP Comms → Contacts - should see new contact
- [ ] Check PTP Comms → Logs - should see SMS sent
- [ ] Verify phone receives confirmation SMS

## Templates Configuration

- [ ] Go to PTP Comms → Templates
- [ ] Review default templates
- [ ] Customize as needed:
  - [ ] Registration Confirmation
  - [ ] Event Reminder - 7 Days
  - [ ] Event Reminder - 1 Day
  - [ ] Thank You Follow-up
- [ ] Test templates with variables

## Automations Setup

- [ ] Go to PTP Comms → Automations
- [ ] Create automation: "Order Confirmation"
  - [ ] Trigger: order_placed
  - [ ] Template: Registration Confirmation
  - [ ] Delay: 0 minutes
  - [ ] Save and activate
- [ ] Create automation: "7-Day Reminder"
  - [ ] Trigger: event_upcoming
  - [ ] Template: Event Reminder - 7 Days
  - [ ] Delay: 7 days before event
  - [ ] Save and activate
- [ ] Test with sample order

## Testing & Verification

### SMS Flow
- [ ] Send SMS to Twilio number
- [ ] Verify appears in Inbox
- [ ] Reply from Inbox
- [ ] Verify parent receives reply
- [ ] Test STOP command (opt-out)
- [ ] Test START command (opt-in)

### Order Flow
- [ ] Create test order with valid phone
- [ ] Verify contact created
- [ ] Verify SMS confirmation sent
- [ ] Verify HubSpot contact created (if enabled)
- [ ] Verify Microsoft Teams notification (if enabled)
- [ ] Check all logs for errors

### Campaign Flow
- [ ] Go to PTP Comms → Campaigns
- [ ] Create test campaign
- [ ] Select 1-2 test contacts
- [ ] Send immediately
- [ ] Verify messages received
- [ ] Check delivery reports

### Dashboard
- [ ] Review statistics
- [ ] Verify all integrations show "Connected"
- [ ] Check recent activity
- [ ] Confirm no errors

## Security Checklist

- [ ] All credentials entered and saved
- [ ] HTTPS enabled on site
- [ ] SSL certificate valid
- [ ] Webhook URLs using HTTPS
- [ ] Test Twilio signature validation
- [ ] Test Microsoft Teams signature validation
- [ ] Verify admin-only access to plugin
- [ ] Review user permissions

## Performance & Monitoring

- [ ] Enable WP-Cron alternative (if needed)
- [ ] Set up cron monitoring
- [ ] Configure error notifications
- [ ] Set up uptime monitoring
- [ ] Test high-volume sending
- [ ] Monitor API rate limits

## Documentation

- [ ] Review SETUP-GUIDE.md
- [ ] Bookmark Twilio console
- [ ] Bookmark HubSpot settings
- [ ] Bookmark Microsoft Teams app settings
- [ ] Document custom configurations
- [ ] Train team on usage

## Launch Day

- [ ] Disable WordPress debug mode
- [ ] Clear all test data
- [ ] Import real contacts
- [ ] Send welcome campaign
- [ ] Monitor all integrations
- [ ] Check logs for errors
- [ ] Verify all automations running

## Post-Launch

- [ ] Monitor daily statistics
- [ ] Review weekly reports
- [ ] Check delivery rates
- [ ] Monitor opt-out rates
- [ ] Review automation performance
- [ ] Optimize templates based on response
- [ ] Regular database cleanup

---

## 🆘 Quick Troubleshooting

### SMS Not Sending?
1. Check Twilio credentials in Settings
2. Verify contact is opted in
3. Check PTP Comms → Logs for errors
4. Verify Twilio console for API errors

### Webhooks Not Working?
1. Flush permalinks (Settings → Permalinks → Save)
2. Verify HTTPS is enabled
3. Test webhook URL with curl
4. Check WordPress debug.log

### HubSpot Not Syncing?
1. Verify API key is correct
2. Check scopes are enabled
3. Test sync manually from Contacts page
4. Review HubSpot API logs

### Microsoft Teams Not Responding?
1. Invite bot to channel
2. Verify Event Subscriptions are enabled
3. Check Request URL is verified
4. Test with `@PTP Comms Hub help`

---

## Support Resources

- **Documentation**: See SETUP-GUIDE.md
- **Twilio Docs**: https://www.twilio.com/docs
- **HubSpot API**: https://developers.hubspot.com
- **Microsoft Teams API**: https://api.teams.com
- **Debug Log**: /wp-content/debug.log

---

## ✨ Success Indicators

After completing this checklist, you should have:

All integrations showing "Connected" status
Test order generating contact and SMS
Twilio messages sending and receiving
HubSpot contacts syncing (if enabled)
Microsoft Teams notifications working (if enabled)
Automations running on schedule
Dashboard showing real-time stats
Zero errors in logs

**Ready to go live!**
