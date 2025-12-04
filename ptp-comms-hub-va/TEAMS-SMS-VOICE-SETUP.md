# PTP Comms Hub v2.0 - Complete Setup Guide
## Microsoft Teams, Voice IVR, and Advanced Segmentation

This guide covers the full setup of your enhanced PTP Communications Hub with bidirectional Microsoft Teams integration, voice calling with live human transfer, and advanced HubSpot/WooCommerce segmentation.

---

## 🎯 New Features in v2.0

### 1. **Microsoft Teams Bidirectional Integration**
- Receive SMS notifications in Teams with reply buttons
- Reply to customer SMS directly from Teams
- Quick reply templates built into Teams messages
- Actionable message cards with one-click actions

### 2. **Enhanced Voice Configuration**
- IVR menu system with customizable options
- Live transfer to real humans during business hours
- After-hours voicemail with transcription
- Multiple forwarding numbers support
- Smart business hours routing

### 3. **Advanced Segmentation**
- 16 pre-built segments including:
  - WooCommerce: By product, repeat customers, first-time buyers
  - HubSpot: Lifecycle stages, custom properties, lists
  - Behavioral: Birthday month, engagement levels
- Personalized messages with merge tags
- Multi-criteria segment builder

---

## 📋 Prerequisites

Before you begin, ensure you have:
- WordPress with WooCommerce installed
- Active Twilio account with phone number
- Microsoft Teams workspace (free or paid)
- HubSpot account with API access (optional)
- Admin access to your WordPress site

---

## Part 1: Microsoft Teams Setup

### Step 1: Create Teams Incoming Webhook

1. **Open Microsoft Teams** and navigate to the channel where you want SMS notifications
2. **Click the three dots** next to the channel name → **Connectors**
3. **Find "Incoming Webhook"** → Click **Configure**
4. **Name your webhook**: "PTP SMS Notifications"
5. **Upload an icon** (optional): Use your PTP logo
6. **Click Create** and **copy the webhook URL**
   - It will look like: `https://your-org.webhook.office.com/webhookb2/...`

### Step 2: Configure in WordPress

1. Go to **WordPress Admin** → **PTP Comms Hub** → **Settings**
2. Find the **Microsoft Teams** section
3. Paste your webhook URL into **Teams Webhook URL**
4. **Enable Teams Notifications**
5. Click **Save Settings**

### Step 3: Test Teams Integration

1. Send a test SMS to your Twilio number
2. You should receive a Teams notification with:
   - Customer name and phone
   - Message content
   - **Reply via SMS** button (with text input)
   - **Quick Reply** buttons (pre-written responses)
   - Links to view full conversation

### Step 4: Using Teams SMS Replies

**To reply from Teams:**
1. When you receive an SMS notification, click **Reply via SMS**
2. Type your response in the text field
3. Click **Send SMS Reply**
4. The message will be sent via Twilio and logged in WordPress

**Quick Replies:**
- Click **Thanks!** to send: "Thanks for reaching out! A team member will be in touch soon."
- Click **Will Call You** to send: "Thanks! We'll give you a call shortly to discuss."
- Click **Check Website** to send: "For more information, please visit www.ptpsoccercamps.com"

---

## Part 2: Voice IVR & Live Human Transfer

### Step 1: Configure Voice Settings

1. Go to **PTP Comms Hub** → **Settings** → **Voice** tab
2. Configure your IVR greeting:
   ```
   Greeting: "Thank you for calling PTP Soccer Camps."
   ```

3. Set up your menu options:
   ```
   Menu Prompt: "Press 1 to speak with a camp coordinator. 
   Press 2 for registration information. 
   Press 3 for camp locations and dates. 
   Press 0 to repeat this menu."
   ```

### Step 2: Add Forwarding Numbers for Live Transfer

1. In the **Voice** settings, find **Forwarding Numbers**
2. Add phone numbers where calls should be routed:
   - Format: +1234567890 (include country code)
   - Add multiple numbers (they'll be dialed sequentially)
   
   **Example:**
   ```
   Primary: +12155551234 (Office)
   Secondary: +12155551235 (Cell phone)
   ```

3. Set **Dial Timeout**: How long to ring each number (default: 20 seconds)

### Step 3: Configure Business Hours

1. **Enable Business Hours Routing** (recommended)
2. Set your hours:
   ```
   Days: Monday - Friday
   Start Time: 09:00 AM
   End Time: 5:00 PM
   Timezone: America/New_York
   ```

3. During business hours: Callers pressing 1 will reach a human
4. After hours: Callers pressing 1 will leave voicemail

### Step 4: Customize IVR Messages

**Registration Information (Press 2):**
```
"To register for our camps, please visit www.ptpsoccercamps.com 
or call back during business hours to speak with a coordinator."
```

**Camp Information (Press 3):**
```
"For camp locations and dates, please visit www.ptpsoccercamps.com 
or check your email for our latest camp schedule."
```

**After Hours Message:**
```
"Our office is currently closed. Please leave a message 
or visit our website at www.ptpsoccercamps.com."
```

### Step 5: Configure Twilio for Voice

1. Log into **Twilio Console**
2. Go to **Phone Numbers** → Click your number
3. Under **Voice & Fax**, set:
   - **A Call Comes In**: Webhook
   - **URL**: `https://yoursite.com/wp-json/ptp-comms/v1/ivr-menu`
   - **Method**: HTTP POST

4. Under **Status Callback URL** (optional):
   - **URL**: `https://yoursite.com/wp-json/ptp-comms/v1/call-status`
   - **Method**: HTTP POST

5. Click **Save**

### Step 6: Test Voice Features

**Test the IVR Menu:**
1. Call your Twilio number
2. Listen to the greeting and menu
3. Press **1** to test live transfer
   - During business hours: Should ring your forwarding numbers
   - After hours: Should go to voicemail

**Test Voicemail:**
1. Call after hours or when no one answers
2. Leave a voicemail
3. Check:
   - Teams notification with voicemail recording link
   - WordPress admin → **PTP Comms Hub** → **Voicemails**
   - Transcription appears within 1-2 minutes

---

## Part 3: Advanced Segmentation

### Understanding Segment Types

Your plugin now includes 16 powerful segmentation options:

#### WooCommerce-Based Segments

1. **New Registrations** - Last 7 days (customizable)
   ```php
   Options: ['days' => 14] // Change timeframe
   ```

2. **Upcoming Camp Attendees** - Next 14 days
   ```php
   Options: ['days_ahead' => 21] // Change timeframe
   ```

3. **By Camp/Product Type** - Specific camp products
   ```php
   Options: [
       'product_id' => 123, // Specific product ID
       'product_name' => 'Summer Camp' // Or by name
   ]
   ```

4. **Repeat Customers** - Multiple purchases
   ```php
   Options: ['min_orders' => 2] // Minimum orders
   ```

5. **First-Time Buyers** - New customers
   ```php
   Options: ['days' => 30] // Within last X days
   ```

6. **High Value Customers** - Total spending threshold
   ```php
   Options: ['min_value' => 500] // Minimum $500 spent
   ```

7. **Abandoned Cart** - Last 48 hours
   ```php
   Options: ['hours' => 48] // Customize hours
   ```

#### HubSpot-Based Segments

8. **HubSpot List** - Import from specific lists
   ```php
   Options: ['list_id' => 12345] // Your HubSpot list ID
   ```

9. **HubSpot Lifecycle Stage** - Lead, Customer, etc.
   ```php
   Options: [
       'lifecycle_stage' => 'customer' 
       // Options: lead, marketingqualifiedlead, 
       //          salesqualifiedlead, opportunity, customer
   ]
   ```

10. **HubSpot Custom Property** - Any HubSpot field
    ```php
    Options: [
        'property_name' => 'camp_interest',
        'property_value' => 'summer_2025',
        'operator' => 'EQ' // EQ, NEQ, CONTAINS, etc.
    ]
    ```

#### Contact-Based Segments

11. **By State/Region**
    ```php
    Options: ['states' => ['PA', 'NJ', 'DE']]
    ```

12. **By Child Age Group**
    ```php
    Options: ['min_age' => 8, 'max_age' => 12]
    ```

13. **Birthday Month** - Send birthday specials
    ```php
    Options: ['month' => 7] // 1-12 for January-December
    ```

#### Engagement-Based Segments

14. **Engaged Contacts** - Recently replied
    ```php
    Options: ['days' => 30] // Replied in last 30 days
    ```

15. **Unengaged Contacts** - No recent replies
    ```php
    Options: ['days' => 90] // No reply in 90+ days
    ```

16. **Past Customers** - Previous attendees
    ```php
    Options: ['exclude_recent_days' => 30]
    ```

---

### Using Segments in Campaigns

#### Example 1: Welcome New Registrations

```
Segment: New Registrations (7 days)
Message: "Hi {{first_name}}! Welcome to PTP Soccer Camps! 
{{child_name}} is registered for {{camp_names}}. 
We can't wait to see you!"
```

#### Example 2: Reminder for Upcoming Camps

```
Segment: Upcoming Camp Attendees (7 days ahead)
Message: "Hi {{first_name}}! Just a reminder that 
{{child_name}}'s camp starts in a few days. 
Don't forget to bring sunscreen and water! 
Any questions? Just reply to this text."
```

#### Example 3: Re-engage Past Customers

```
Segment: Past Customers (exclude last 365 days)
Message: "Hi {{first_name}}! We miss {{child_name}} at camp! 
Our summer 2025 schedule is now available. 
Ready to register? Visit ptpsoccercamps.com"
```

#### Example 4: Birthday Special

```
Segment: Birthday Month
Message: "Happy Birthday Month to {{child_name}}! 
Celebrate with 20% off your next camp registration. 
Use code BDAY20 at checkout!"
```

---

### Multi-Criteria Segmentation

Build complex segments by combining multiple filters:

```
Campaign: Summer Camp Promotion in Pennsylvania

Filters:
1. By State: PA (AND)
2. By Age Group: 10-14 (AND)
3. Past Customers: Exclude last 180 days (AND)
4. HubSpot Lifecycle: Lead or Customer (OR)

Result: PA parents of 10-14 year olds who haven't 
registered in 6 months and are in your CRM
```

---

### Personalization Variables

Use these merge tags in your messages:

```
{{first_name}} - Parent first name
{{last_name}} - Parent last name
{{full_name}} - Parent full name
{{child_name}} - Child's name
{{child_age}} - Child's age
{{state}} - State abbreviation
{{city}} - City name
{{zip}} - ZIP code
{{order_number}} - Latest order number
{{order_total}} - Latest order total
{{order_date}} - Latest order date
{{camp_names}} - Camps registered for
```

---

## Part 4: HubSpot Integration

### Step 1: Get Your HubSpot API Key

1. Log into **HubSpot**
2. Click **Settings** (gear icon) → **Integrations** → **API Key**
3. Click **Create key** if you don't have one
4. **Copy your API key**

### Step 2: Configure in WordPress

1. Go to **PTP Comms Hub** → **Settings** → **HubSpot** tab
2. Paste your **HubSpot API Key**
3. **Enable HubSpot Sync**
4. Choose sync options:
   - ☑ Sync contacts to HubSpot
   - ☑ Sync message activity
   - ☑ Update HubSpot properties

5. Click **Save Settings**

### Step 3: Map Contact Fields

Map your WordPress contact fields to HubSpot properties:

```
PTP Field → HubSpot Property
parent_phone → phone
parent_email → email
parent_first_name → firstname
parent_last_name → lastname
child_name → child_name (custom)
child_age → child_age (custom)
state → state
opted_in → sms_opted_in (custom)
```

### Step 4: Using HubSpot Segments

**Get HubSpot List IDs:**
1. In HubSpot, go to **Contacts** → **Lists**
2. Click on a list
3. Look at the URL: `lists/12345` - the number is your list ID

**Campaign Example:**
```
Segment: HubSpot List
Options: list_id = 12345
Message: Custom message for this list
```

---

## Part 5: Campaign Workflows

### Automated Welcome Sequence

```
Trigger: New WooCommerce Order
Segment: New Registrations (1 day)

Day 1: Welcome + Confirmation
"Hi {{first_name}}! Thank you for registering 
{{child_name}} for {{camp_names}}!"

Day 3: What to Bring
"Quick reminder for {{child_name}}'s camp: 
Bring water bottle, sunscreen, and cleats!"

Day 7 (if camp is 14+ days away): Camp Details
"Camp info for {{child_name}}: 
Check-in is at 9am. Questions? Just reply!"
```

### Re-engagement Campaign

```
Segment: Unengaged Contacts (90 days) 
+ Past Customers (6+ months ago)

Week 1: "Miss you!"
"Hi {{first_name}}! We miss seeing {{child_name}} 
at PTP camps. Want to hear about summer 2025?"

Week 2: "Special offer"
"{{first_name}}, as a valued past customer, 
enjoy 15% off your next registration!"

Week 3: "Last chance"
"Final reminder: Your 15% discount expires in 3 days. 
Register {{child_name}} for summer camp today!"
```

---

## Part 6: Monitoring & Analytics

### Teams Notifications

You'll receive Teams notifications for:
- ✅ Incoming SMS messages (with reply buttons)
- ✅ Incoming voicemails (with transcription)
- ✅ New WooCommerce orders
- ✅ Campaign completions
- ✅ Error alerts

### Dashboard Metrics

Track in **PTP Comms Hub** → **Dashboard**:
- Messages sent/received today
- Response rate
- Campaign performance
- Opt-in/opt-out trends
- Revenue attributed to SMS campaigns

---

## Part 7: Best Practices

### SMS Best Practices

1. **Always get consent** - Only message opted-in contacts
2. **Keep it short** - Aim for under 160 characters
3. **Include branding** - Mention "PTP Soccer Camps"
4. **Provide value** - Useful info, not just promotions
5. **Enable replies** - Monitor and respond to incoming messages
6. **Timing matters** - Send 9am-8pm in recipient's timezone
7. **Include opt-out** - "Reply STOP to unsubscribe"

### Voice Best Practices

1. **Keep IVR menus simple** - Max 4 options
2. **Update after-hours message** - Include current hours
3. **Test regularly** - Call your number weekly
4. **Monitor voicemails** - Respond within 4 hours
5. **Use business hours routing** - Don't miss calls during office hours

### Segmentation Best Practices

1. **Start specific, then broaden** - Test narrow segments first
2. **Combine criteria** - More targeted = better results
3. **Exclude recent contacts** - Don't over-message
4. **Test messages** - Preview with different contacts
5. **Track performance** - Compare segment response rates

---

## Troubleshooting

### Teams Not Receiving Notifications

1. **Check webhook URL** - Must start with `https://`
2. **Test webhook** - Send test from WordPress settings
3. **Check channel** - Webhook must be added to specific channel
4. **Firewall** - Ensure WordPress can make outbound HTTPS requests

### Teams Reply Not Working

1. **Check REST API** - Visit: `yoursite.com/wp-json/ptp-comms/v1/`
2. **SSL certificate** - Must have valid HTTPS
3. **Twilio credentials** - Verify in Settings
4. **Contact phone** - Ensure contact has valid phone number

### Voice Calls Not Forwarding

1. **Check forwarding numbers** - Must include country code (+1)
2. **Test Twilio webhook** - View Twilio debugger console
3. **Business hours** - Verify timezone settings
4. **Phone format** - +12155551234 (no spaces/dashes)

### Segments Returning No Contacts

1. **Check opt-in status** - Segments only include opted-in contacts
2. **Verify criteria** - Age ranges, dates, etc.
3. **Test query** - Use "Preview Segment" feature
4. **HubSpot sync** - Ensure contacts synced with HubSpot IDs

---

## Support & Resources

### Documentation
- [Twilio SMS Setup](./TWILIO-WEBHOOK-SETUP.md)
- [IVR Voice Setup](./IVR-VOICE-SETUP.md)
- [Segmentation Guide](./SEGMENTATION-GUIDE.md)

### Testing Endpoints
- SMS Webhook: `yoursite.com/ptp-comms/sms-webhook`
- Voice Webhook: `yoursite.com/wp-json/ptp-comms/v1/ivr-menu`
- Teams Reply: `yoursite.com/wp-json/ptp-comms/v1/teams-reply`
- Test: `yoursite.com/ptp-comms/test-webhook`

### Need Help?
- Check error logs: **WordPress Admin** → **Tools** → **Site Health** → **Info** → **Error Log**
- View Twilio logs: **Twilio Console** → **Monitor** → **Logs**
- Review campaign logs: **PTP Comms Hub** → **Logs**

---

## Version History

### v2.0.0 (Current)
- ✅ Microsoft Teams bidirectional SMS integration
- ✅ Enhanced IVR with live human transfer
- ✅ 16 advanced segmentation options
- ✅ HubSpot custom property filtering
- ✅ WooCommerce product-based segments
- ✅ Birthday month campaigns
- ✅ Repeat customer identification
- ✅ Teams actionable message cards

### v1.0.0
- Basic SMS sending via Twilio
- Contact management
- Simple campaigns
- Microsoft Teams integration

---

**You're all set!** Your PTP Communications Hub is now fully configured with Teams integration, voice calling, and advanced segmentation. Start creating targeted campaigns and watch your engagement soar! 🚀
