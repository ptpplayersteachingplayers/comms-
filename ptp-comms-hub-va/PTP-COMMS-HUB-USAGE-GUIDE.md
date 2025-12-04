# PTP Comms Hub - Complete Usage Guide

## Table of Contents
1. [Microsoft Teams Integration Setup](#teams-integration)
2. [Using the Plugin Effectively](#effective-usage)
3. [Inbox Management](#inbox-management)
4. [Troubleshooting & Debug](#troubleshooting)

---

## Microsoft Teams Integration Setup {#teams-integration}

### How to Manage Teams Integration Within Microsoft Teams

#### **Step 1: Create an Incoming Webhook in Teams**

1. **Open Microsoft Teams** and navigate to the channel where you want to receive PTP notifications
2. Click the **three dots (...)** next to the channel name
3. Select **Connectors** or **Workflows** (depending on your Teams version)
4. In the search box, type **"Incoming Webhook"**
5. Click **Add** or **Configure** next to "Incoming Webhook"
6. Give your webhook a name: `"PTP Comms Hub"`
7. *Optional but recommended:* Upload the PTP logo (yellow and black soccer ball)
8. Click **Create**
9. **IMPORTANT:** Copy the webhook URL that appears - you'll need this in WordPress

#### **Step 2: Configure Webhook in WordPress**

1. In WordPress admin, go to **PTP Comms Hub → Settings**
2. Navigate to the **Microsoft Teams Integration** tab
3. Paste your webhook URL in the **Teams Webhook URL** field
4. Click **Save Changes**

#### **Step 3: Test the Integration**

1. Still on the Settings page, find the **Test Teams Connection** button
2. Click it to send a test message
3. Check your Teams channel - you should see a notification card appear
4. If successful, you're all set!

#### **Step 4: Understanding What You Can Do in Teams**

Once configured, Teams notifications will include:

**For Inbound SMS Messages:**
- Reply directly from Teams using the text input field
- Use Quick Reply buttons (Thanks, Will Call You, Check Website)
- Click "View Full Conversation" to open the WordPress inbox
- Click "Call Contact" to immediately dial the parent

**For New Orders/Registrations:**
- View order details without leaving Teams
- Click to send a welcome SMS
- Access full order in WooCommerce
- View parent contact information

**For Campaign Completions:**
- See success rates and metrics
- Review failed messages
- Access detailed logs

**For Voicemails:**
- Listen to recording via link
- Read transcription (if available)
- Reply via SMS directly from Teams
- View caller details

#### **Step 5: Sending Messages FROM Teams**

The plugin supports **actionable messages** - you can reply to parents directly from Teams:

**Method 1: Using the Reply Card**
1. When you receive an SMS notification in Teams, you'll see a "Reply via SMS" section
2. Type your message in the text field
3. Click "Send SMS Reply"
4. The message is sent via Twilio and logged in WordPress

**Method 2: Quick Replies**
1. Click one of the pre-written quick reply buttons:
   - "Thanks!" - Sends acknowledgment message
   - "Will Call You" - Promises a callback
   - "Check Website" - Directs to website
2. Message is sent immediately, no typing required

**Method 3: Via WordPress Inbox**
1. Click "View Full Conversation" in any Teams notification
2. This opens the WordPress inbox in your browser
3. View full conversation history and reply from there

#### **Step 6: Managing Multiple Channels (Advanced)**

You can send different notification types to different Teams channels:

1. Create multiple incoming webhooks in different Teams channels
2. In WordPress Settings, you can specify different webhooks for:
   - SMS messages
   - Voicemails
   - New orders
   - Campaign completions
   - Error alerts

3. This helps organize notifications by priority or department

#### **Troubleshooting Teams Integration**

**Problem: Notifications not appearing in Teams**
- Verify webhook URL is correct (check for extra spaces)
- Ensure the connector is still active in Teams (it can be disabled)
- Check WordPress error logs: **PTP Comms → Logs**
- Test with the "Send Test Message" button in Settings

**Problem: Can't reply from Teams**
- Your WordPress site must be publicly accessible (not localhost)
- Check that REST API is enabled (Settings → Permalinks → Save)
- Verify Twilio credentials are correct
- Check PHP error logs for REST endpoint issues

**Problem: Actionable cards not working**
- Update to the latest Teams desktop/mobile app
- Check if Adaptive Cards are enabled in Teams Admin Center
- Try refreshing Teams (Ctrl+R or Cmd+R)

**Problem: Getting duplicate notifications**
- Check if you have multiple webhooks configured
- Verify you haven't set up the same webhook multiple times
- Review Teams connector settings

---

## Using the Plugin Effectively {#effective-usage}

### Core Features Overview

#### **1. Two-Way SMS Messaging**
Parents can text your PTP phone number anytime, and messages appear in your WordPress inbox in real-time.

**Best Practices:**
- Train staff to check inbox regularly (or rely on Teams notifications)
- Set up message templates for common questions
- Use quick replies for faster responses
- Enable HubSpot sync to track all parent interactions

#### **2. Contact Management**

**Importing Contacts:**
1. Go to **PTP Comms → Contacts**
2. Click **Import CSV**
3. Map columns: Parent First Name, Parent Last Name, Phone, Email
4. Set opt-in status: `opted_in`, `opted_out`, or `pending`
5. Review import results

**Manual Contact Creation:**
- Click **Add Contact**
- Fill in parent details
- Add children with ages, camp preferences
- Note opt-in status for compliance

**Contact Segments:**
Create segments for targeted campaigns:
- By camp location (state/city)
- By age group
- By registration history
- By opt-in date

#### **3. Campaign Management**

**Creating Effective Campaigns:**

1. **Name Your Campaign**
   - Use descriptive names: "Summer 2025 Early Bird", "Weather Alert 5/15"

2. **Write Your Message**
   - Keep under 160 characters when possible (1 SMS segment = lower cost)
   - Use merge tags: `{parent_first_name}`, `{child_first_name}`
   - Include clear call-to-action
   - Always end with opt-out language: "Reply STOP to opt-out"

3. **Select Audience**
   - All contacts with opt-in
   - Specific segment
   - Specific contact list
   - Individual contacts for testing

4. **Schedule or Send**
   - Send now for urgent messages
   - Schedule for optimal times (avoid early morning/late night)
   - Recommended send times: 9am-8pm local time

**Campaign Examples:**

```
Summer Camp Registration:
Hi {parent_first_name}! Summer camp registration is OPEN! 
Reserve {child_first_name}'s spot at www.ptpsoccercamps.com
Reply with questions. Text STOP to opt-out.
```

```
Weather Update:
WEATHER ALERT: Today's camp at {camp_location} is CANCELLED 
due to lightning. Makeup date TBD. Stay safe!
Questions? Reply to this text. STOP to opt-out.
```

```
Early Bird Reminder:
Last chance! Early bird pricing ends Friday. 
Save $50 on summer camps → www.ptpsoccercamps.com
Reply HELP for assistance. STOP to opt-out.
```

#### **4. Message Templates**

Create reusable templates for common scenarios:

**Registration Questions:**
```
Template: REG_HELP
Message: Thanks for your question! You can register online at 
www.ptpsoccercamps.com or call us at (XXX) XXX-XXXX M-F 9am-5pm.
```

**Camp Location/Directions:**
```
Template: DIRECTIONS
Message: {camp_name} is at {address}. Parking available on site.
Drop-off starts at 8:45am. See you there!
```

**Weather Policy:**
```
Template: WEATHER
Message: We monitor weather closely. If camp is cancelled, you'll 
receive a text 1 hour before start time. Makeup dates are scheduled 
at season end.
```

#### **5. Automations**

Set up event-triggered messages:

**New Order Confirmation:**
- Trigger: WooCommerce order completed
- Send: Order confirmation with camp details
- Timing: Immediately

**Pre-Camp Reminder:**
- Trigger: 2 days before camp start date
- Send: What to bring checklist
- Timing: 48 hours before

**Post-Camp Follow-Up:**
- Trigger: Camp end date
- Send: Thank you + feedback request
- Timing: Same day at 6pm

**Birthday Messages:**
- Trigger: Child's birthday
- Send: Birthday wishes + discount code
- Timing: 9am on birthday

#### **6. HubSpot Integration**

Sync all SMS activity to HubSpot CRM:

**What Gets Synced:**
- Contact creation/updates
- SMS message history
- Campaign sends
- Order completions
- Engagement tracking

**Setup:**
1. Go to **Settings → HubSpot**
2. Enter HubSpot API key
3. Enable sync
4. Map custom fields if needed

**Benefits:**
- Full parent communication history in one place
- Better lead scoring based on SMS engagement
- Trigger HubSpot workflows from SMS replies
- Generate reports on SMS ROI

#### **7. Voice/Call Features**

**Inbound Calls:**
- Parents can call your Twilio number
- Smart IVR menu routes to departments
- Voicemail captured and transcribed
- Notifications sent to Teams/Email

**Outbound Calls:**
- Click-to-call from contact records
- Call history tracked
- Call recordings available (if enabled)

---

## Inbox Management {#inbox-management}

### Navigating the Inbox

**Main Inbox View:**
- **All Tab:** Every active conversation
- **Unread Tab:** Messages awaiting response (shows count badge)
- **Archived Tab:** Closed conversations

**Conversation List Features:**
- Parent name with last message preview
- Timestamp of last activity
- Unread badge (yellow dot)
- Message type indicator (SMS/Voice)
- Quick actions: Archive, Mark Read

### Replying to Messages

**Step-by-Step:**
1. Click on a conversation to open it
2. View full message thread
3. Type your reply in the text box at bottom
4. *Optional:* Use a message template (dropdown)
5. Click **Send Message**
6. Message appears immediately in thread
7. Sent via Twilio SMS

**Tips:**
- Replies are always sent as SMS
- Keep responses conversational and helpful
- Use templates for consistency
- Mark as read when handled
- Archive when conversation complete

### Using Message Templates

1. Click **Templates** dropdown above reply box
2. Select appropriate template
3. Edit if needed (templates support merge tags)
4. Send

**Template Merge Tags:**
- `{parent_first_name}` - Parent's first name
- `{parent_last_name}` - Parent's last name
- `{child_first_name}` - First child's name
- `{camp_name}` - Upcoming camp name
- `{camp_date}` - Camp date

### Bulk Actions

Select multiple conversations for bulk operations:
- **Mark as Read:** Clear unread status
- **Archive:** Move to archived
- **Export:** Download as CSV
- **Tag:** Add labels for organization

### Search & Filters

**Search by:**
- Parent name
- Phone number
- Email address
- Message content
- Date range

**Filter by:**
- Opt-in status
- Message type (SMS/Voice)
- Campaign participation
- Child age group
- Location/state

---

## Troubleshooting & Debug {#troubleshooting}

### Common Issues & Solutions

#### **Issue: Messages Not Appearing in Inbox**

**Diagnosis:**
1. Check **PTP Comms → Logs** for webhook errors
2. Verify Twilio webhook is configured correctly
3. Test by sending SMS to your PTP number

**Solution:**
1. Go to [Twilio Console](https://console.twilio.com)
2. Navigate to Phone Numbers → Active Numbers
3. Click your PTP number
4. Under "Messaging", ensure webhook URL matches what's shown in WordPress Settings
5. URL should be: `https://yoursite.com/wp-json/ptp-comms/v1/webhooks/twilio`
6. Method should be: **POST**
7. Click Save

**If still not working:**
- Go to WordPress **Settings → Permalinks**
- Click **Save Changes** (this refreshes REST API routes)
- Test again

#### **Issue: Can't Send Messages**

**Possible Causes:**
1. Invalid Twilio credentials
2. Contact not opted in
3. Twilio account issue (funds, verification)

**Solution:**
1. Verify credentials in **Settings → Twilio**
2. Test with **Send Test SMS** button
3. Check contact's opt-in status: must be `opted_in`
4. Verify Twilio account has funds: [Twilio Console](https://console.twilio.com)
5. Check Twilio phone number is SMS-enabled

#### **Issue: Teams Notifications Not Working**

**Solution:**
1. Re-copy webhook URL from Teams
2. Check for spaces or special characters
3. Paste fresh URL in WordPress Settings
4. Use Test button to verify
5. Check if connector is still active in Teams:
   - Go to Teams channel
   - Click three dots → Connectors
   - Verify "Incoming Webhook" is listed and enabled

#### **Issue: Duplicate Messages in Inbox**

**Cause:** Multiple webhook configurations

**Solution:**
1. Check Twilio Console - only ONE messaging webhook should be set
2. Remove any duplicate/old webhook URLs
3. Keep only the current WordPress webhook URL

#### **Issue: Opt-Out Not Working**

**Solution:**
1. Check **Settings → Compliance**
2. Ensure STOP/START keywords are enabled
3. Verify automatic opt-out processing is checked
4. Test by texting "STOP" to your number
5. Check Logs for processing confirmation

### Debug Mode

Enable detailed logging:

1. Add to `wp-config.php`:
```php
define('PTP_COMMS_DEBUG', true);
```

2. Check logs at **PTP Comms → Logs**

3. Look for:
   - Webhook received/processed
   - Twilio API responses
   - Teams notification attempts
   - Database operations

### Performance Optimization

**For Large Contact Lists (5,000+):**
1. Enable caching in Settings
2. Use segments instead of "All Contacts"
3. Schedule campaigns during off-peak hours
4. Consider campaign rate limiting

**For High Message Volume:**
1. Enable message queue processing
2. Set up cron jobs for async sending
3. Monitor Twilio rate limits
4. Consider short code instead of long code number

### Getting Help

**Error Codes:**
- `20003` - Twilio auth failed → Check credentials
- `21211` - Invalid phone number → Check format
- `21610` - Message blocked → Contact opted out
- `30003` - Unreachable → Invalid number
- `30005` - Unknown destination → Number doesn't exist

**Support Resources:**
1. **Plugin Logs:** PTP Comms → Logs
2. **Twilio Debugger:** [Twilio Console → Monitor → Logs](https://console.twilio.com/monitor/logs)
3. **PHP Error Log:** Check your server error logs
4. **Documentation:** Review all `.md` files in plugin folder

---

## Best Practices Summary

### Daily Operations
- Check inbox 2-3x per day (or rely on Teams notifications)
- Respond to parents within 2 hours during business hours
- Mark conversations as read when handled
- Archive completed conversations weekly

### Campaign Strategy
- Test every campaign by sending to yourself first
- Send during business hours (9am-8pm)
- Keep messages under 320 characters (2 SMS segments)
- Use merge tags for personalization
- Include clear call-to-action
- Always include opt-out instructions

### Compliance
- Only message parents who opted in
- Honor STOP requests immediately (automatic)
- Keep opt-out records for 3 years
- Include opt-out language in every campaign
- Train staff on TCPA compliance

### Contact Management
- Import contacts with proper opt-in status
- Update contact info as you learn it
- Segment contacts for targeted campaigns
- Remove duplicate contacts
- Clean list annually

### Integration Maintenance
- Test Teams integration monthly
- Verify HubSpot sync weekly
- Check Twilio webhook quarterly
- Review automation triggers seasonally
- Update templates before each camp season

---

## Quick Reference Card

### Key URLs
- **WordPress Inbox:** `/wp-admin/admin.php?page=ptp-comms-inbox`
- **Campaigns:** `/wp-admin/admin.php?page=ptp-comms-campaigns`
- **Contacts:** `/wp-admin/admin.php?page=ptp-comms-contacts`
- **Settings:** `/wp-admin/admin.php?page=ptp-comms-settings`
- **Logs:** `/wp-admin/admin.php?page=ptp-comms-logs`

### Keyboard Shortcuts (Inbox)
- `R` - Mark as read
- `A` - Archive conversation
- `N` - Next conversation
- `P` - Previous conversation
- `/` - Focus search

### Merge Tags
- `{parent_first_name}` - Parent's first name
- `{parent_last_name}` - Parent's last name
- `{child_first_name}` - Child's first name
- `{camp_name}` - Next camp name
- `{camp_date}` - Next camp date
- `{unsubscribe_url}` - Opt-out link

### SMS Compliance Keywords
- **STOP** - Opt out
- **START** - Opt back in
- **HELP** - Get help info

---

**Version:** 3.0  
**Last Updated:** November 2025  
**Plugin:** PTP Comms Hub Enterprise
