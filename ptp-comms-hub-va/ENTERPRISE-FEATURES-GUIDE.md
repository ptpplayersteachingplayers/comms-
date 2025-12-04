# PTP Comms Hub Enterprise - Complete Feature Guide

##  Overview

PTP Comms Hub Enterprise is a complete communication platform designed for high-volume youth sports organizations. This guide covers all enterprise features and best practices.

---

##  Two-Way SMS Messaging

### Parent-Initiated Conversations

Parents can text your PTP number **anytime** - not just in response to campaigns.

**How It Works:**
1. Parent sends SMS to your Twilio number
2. Webhook receives message instantly
3. Contact auto-created if unknown
4. Message appears in Inbox with unread badge
5. Microsoft Teams notification sent (optional)
6. Admin replies directly from Inbox

**Setup Required:** Configure Twilio webhook (see `TWILIO-WEBHOOK-SETUP.md`)

### Campaign-Based Messaging

Send bulk SMS to targeted groups:
- **Broadcast Messages** - Send to all opted-in contacts
- **Targeted Campaigns** - Filter by location, event, tags
- **Scheduled Campaigns** - Schedule for future delivery
- **Template-Based** - Use pre-written templates with merge tags

### Message Threading

All messages grouped by contact:
- Full conversation history
- Inbound/outbound indicators
- Timestamps for every message
- Unread tracking per conversation
- Archive completed conversations

### Auto-Responses

Built-in keyword handling:
- **STOP/UNSUBSCRIBE** → Auto opt-out + confirmation
- **START/SUBSCRIBE** → Auto opt-in + welcome message
- All other messages → Added to Inbox for manual reply

---

## 👥 Contact Management

### Automatic Contact Creation

**From WooCommerce:**
- Every order creates/updates contact
- Billing info synced automatically
- Purchase history tracked
- Auto opt-in on first purchase

**From Inbound SMS:**
- Unknown numbers auto-create contact
- Basic info populated from Twilio
- Marked for review/completion

**From CSV Import:**
- Bulk import hundreds of contacts
- Required fields: phone, email, name
- Optional fields: location, tags, custom data
- Duplicate detection and merge options

### Contact Profiles

Each contact includes:
- **Basic Info** - Name, phone, email
- **Communication Preferences** - Opt-in status, preferred channel
- **Purchase History** - All WooCommerce orders
- **Message History** - Full conversation thread
- **Custom Fields** - Location, event registrations, tags
- **HubSpot Sync Status** - Last sync time, HubSpot ID

### Opt-In/Opt-Out Management

**TCPA Compliant:**
- Explicit opt-in required for marketing messages
- Easy opt-out via STOP keyword
- Opt-out status synced across systems
- Audit trail of all preference changes
- Cannot message opted-out contacts

**Opt-In Methods:**
- WooCommerce checkout opt-in checkbox
- Manual opt-in from admin
- SMS reply with START keyword
- Import with pre-existing consent

---

##  Campaign Management

### Campaign Types

**Broadcast Campaigns:**
- Send to all opted-in contacts
- Great for announcements, promotions
- Schedule for optimal send time

**Targeted Campaigns:**
- Filter by location, event, tags
- Send to specific segments
- A/B test different messages

**Drip Campaigns:**
- Multi-message sequences
- Triggered by events
- Time-delayed follow-ups

### Campaign Creation Workflow

1. **Create Campaign** - Name, type, audience
2. **Compose Message** - Write or use template
3. **Select Recipients** - Filter or select all
4. **Schedule** - Send now or later
5. **Review & Send** - Preview before sending
6. **Track Results** - Delivery status, responses

### Message Templates

Pre-written messages with merge tags:
- **Event Reminders** - 24hr, 7-day, 1-month
- **Weather Alerts** - Rain, cold, heat advisories
- **Registration Confirmations** - Order received
- **Welcome Messages** - New contact greeting
- **Follow-Ups** - Post-event surveys, feedback

**Merge Tags Available:**
- `{parent_first_name}` - Contact first name
- `{child_name}` - Child's name from order
- `{event_name}` - Event title
- `{event_date}` - Event date formatted
- `{event_location}` - Venue/address
- `{order_number}` - WooCommerce order ID

---

## 🤖 Automation Rules

### Trigger Types

**WooCommerce Triggers:**
- Order Placed (any status)
- Order Completed
- Order Processing
- Order Refunded

**Event Triggers:**
- Event Created
- Event Approaching (7 days, 1 day, 24hr)
- Event Completed
- Registration Confirmed

**Contact Triggers:**
- New Contact Added
- Contact Opted In
- Contact Updated
- Birthday (if configured)

### Automation Actions

**Send SMS:**
- Instant or delayed
- Use templates or custom message
- Respect opt-in status

**Send Voice Call:**
- Automated voice messages
- Custom TwiML scripts
- Fallback to SMS if no answer

**Update Contact:**
- Add tags
- Update fields
- Change status

**Sync to HubSpot:**
- Create/update contact
- Create deal
- Log activity

**Notify Microsoft Teams:**
- Post to channel
- Alert specific users
- Include order details

### Example Automations

**"Order Confirmation"**
- Trigger: Order Placed (Processing)
- Action: Send SMS with order details + event info
- Template: "Thanks for registering {child_name} for {event_name}!"

**"7-Day Reminder"**
- Trigger: Event Approaching (7 days)
- Action: Send SMS reminder
- Template: "Just one week until {event_name}!"

**"Post-Event Follow-Up"**
- Trigger: Event Completed
- Delay: 24 hours
- Action: Send SMS feedback request
- Template: "How did {child_name} enjoy {event_name}?"

---

## 🔄 HubSpot Integration

### Bidirectional Sync

**WordPress → HubSpot:**
- Contacts auto-created/updated
- Orders become Deals
- SMS opt-in status tracked
- Message activity logged
- Custom properties synced

**HubSpot → WordPress:**
- Contact updates pulled
- Tags synced
- Lifecycle stage tracked

### Sync Frequency

**Real-Time Sync:**
- New WooCommerce orders
- New contact creation
- Opt-in/opt-out changes

**Scheduled Batch Sync:**
- Hourly, daily, or weekly
- Updates all contacts
- Reconciles differences
- Error logging & retry

### HubSpot Properties Created

- `ptp_sms_opted_in` (boolean)
- `ptp_last_message_date` (datetime)
- `ptp_total_messages_sent` (number)
- `ptp_total_messages_received` (number)
- `ptp_last_order_date` (datetime)
- `ptp_lifetime_value` (number)
- `ptp_events_attended` (number)

---

## 💬 Microsoft Teams Integration

### Notification Types

**New Inbound Messages:**
```
📩 New SMS from Jane Doe (+1 555-123-4567)
"Can I still register my son for next weekend's camp?"

[View in Inbox] [Reply]
```

**New Orders:**
```
🛒 New Order #12345 - $299.00
Jane Doe registered Alex Doe for Summer Camp 2024
Location: Philadelphia, PA

[View Order] [Contact]
```

**Campaign Sent:**
```
📢 Campaign "Weather Alert" sent to 245 contacts
Delivery: 243 delivered, 2 failed

[View Report]
```

### Interactive Components

- **Quick Reply** - Respond to messages from Microsoft Teams
- **View Full Thread** - Link to WordPress Inbox
- **Contact Lookup** - Search contact details
- **Campaign Stats** - Real-time delivery tracking

### Setup

1. Create Incoming Webhook in Microsoft Teams workspace
2. Add webhook URL to Settings → Microsoft Teams
3. Select notification types
4. Choose channel for notifications
5. Test with sample message

---

## 📊 Reporting & Analytics

### Built-In Reports

**Campaign Performance:**
- Messages sent vs. delivered
- Delivery rate percentage
- Response rate tracking
- Best performing templates
- Send time optimization

**Contact Analytics:**
- Total contacts over time
- Opt-in rate trends
- Geographic distribution
- Engagement scoring
- Lifetime value tracking

**System Health:**
- Twilio API status
- HubSpot sync status
- Webhook uptime
- Error rates
- Queue processing times

### Export Options

- CSV export of all data
- Contact lists with filters
- Message history by date
- Campaign performance reports
- HubSpot sync logs

---

## 🔒 Security & Compliance

### TCPA Compliance

Explicit opt-in required  
Clear opt-out mechanism (STOP)  
Consent tracking & audit log  
No messaging to opted-out contacts  
Business hours restrictions (optional)  

### Data Protection

- **Encryption** - All passwords/tokens encrypted at rest
- **Secure API Calls** - HTTPS only, validated signatures
- **Webhook Validation** - Twilio signature verification
- **Access Control** - WordPress role-based permissions
- **Audit Logging** - All actions logged with timestamps

### Privacy Features

- **Contact Deletion** - Permanent removal option
- **Data Export** - GDPR-compliant data portability
- **Opt-Out Honored** - Cannot override opt-out status
- **Retention Policy** - Configurable message retention
- **No Third-Party** - Data stays on your server

---

## ⚙️ Enterprise Administration

### User Roles & Permissions

**Administrator:**
- Full system access
- Settings configuration
- User management
- View all conversations

**Campaign Manager:**
- Create/send campaigns
- View reports
- Manage templates
- No settings access

**Inbox Agent:**
- Reply to messages
- View conversations
- Update contact info
- No campaign access

### System Requirements

**WordPress:**
- Version 6.0 or higher
- Permalink structure enabled
- SSL certificate (HTTPS)
- WP-Cron or server cron

**PHP:**
- Version 7.4 or higher
- cURL extension
- OpenSSL extension
- JSON extension
- At least 128MB memory

**Database:**
- MySQL 5.7+ or MariaDB 10.2+
- InnoDB storage engine
- UTF8MB4 character set

**Server:**
- Stable internet connection
- Outbound HTTPS allowed (Twilio API)
- Inbound HTTPS webhook access
- Sufficient disk space for logs

### Scaling Considerations

**High Volume (1000+ messages/day):**
- Enable queue processing
- Use server cron instead of WP-Cron
- Increase PHP memory limit (256MB+)
- Consider dedicated database
- Monitor Twilio rate limits

**Multiple Locations:**
- Use tags for location filtering
- Separate campaigns per location
- Location-specific templates
- Regional staff assignments

---

## 🆘 Support & Troubleshooting

### Common Issues

**Webhook Not Working:**
1. Check permalink settings (must be enabled)
2. Flush permalinks (Settings → Permalinks → Save)
3. Verify webhook URL is exact
4. Check server firewall rules
5. Test with curl command

**Messages Not Sending:**
1. Verify Twilio credentials
2. Check contact opt-in status
3. Verify phone number format (+1...)
4. Check Twilio account balance
5. Review error logs

**HubSpot Not Syncing:**
1. Verify API key/token
2. Check required scopes
3. Review sync logs
4. Test connection
5. Check rate limits

### Getting Help

- **Documentation** - All `.md` files in plugin folder
- **Error Logs** - WP Admin → PTP Comms → Logs
- **Twilio Console** - Check delivery logs
- **HubSpot Activity** - Review sync timeline
- **WordPress Debug** - Enable WP_DEBUG for detailed errors

---

##  Best Practices

### Message Content

**DO:**
- Keep messages under 160 characters
- Include clear call-to-action
- Personalize with merge tags
- Test before sending to all
- Send during business hours
- Include opt-out instructions

**DON'T:**
- Send promotional content to opted-out contacts
- Use ALL CAPS (seems like shouting)
- Send more than 2-3 messages per week
- Include prohibited content (spam keywords)
- Send time-sensitive info without lead time

### Campaign Timing

**Best Times to Send:**
- **Weekdays** - 10am-12pm, 2pm-4pm
- **Weekends** - 10am-2pm
- **Avoid** - Early morning (<9am), late evening (>8pm)

**Frequency:**
- Event reminders: 7 days, 24 hours before
- Promotional: Max 2-3 per month
- Transactional: As needed
- Follow-ups: Wait 48+ hours

### Contact Hygiene

- Regular list cleaning (remove bad numbers)
- Prompt opt-out processing
- Update contact info from orders
- Tag inactive contacts
- Archive old conversations
- Merge duplicate contacts

### Performance Optimization

- Use templates for common messages
- Schedule large campaigns during off-hours
- Batch process contact imports
- Archive old campaigns (>90 days)
- Clean up logs regularly
- Monitor queue length

---

## 📈 Success Metrics

### KPIs to Track

**Engagement:**
- Open/response rate to campaigns
- Avg response time to inbound messages
- Conversation completion rate

**Growth:**
- New contacts per month
- Opt-in rate
- Retention rate (staying opted-in)

**Revenue:**
- Orders from SMS campaigns
- Lifetime value of SMS contacts
- ROI on messaging costs

**Efficiency:**
- Time saved vs. manual communication
- Staff hours using Inbox
- Automation success rate

---

**Ready to maximize your PTP Comms Hub?** Review this guide regularly and optimize based on your specific needs!
