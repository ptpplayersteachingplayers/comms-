# Customer Segmentation & Personalization Guide

## Overview
The Segmentation feature allows you to send highly targeted, personalized SMS messages to specific groups of parents based on their behavior, demographics, and engagement. This dramatically improves response rates and parent satisfaction.

## Key Benefits

- **Higher engagement** - Messages relevant to recipient's situation
- **Better conversion** - Target parents ready to register
- **Reduced opt-outs** - Don't spam with irrelevant messages
- **Automated personalization** - Insert parent/child names, camp details
- **Multi-source data** - Combine WooCommerce, HubSpot, and contact data

## Available Segments

### 1. New Registrations (Last 7 Days)
**Who**: Parents who registered in the last week
**Best for**: Welcome messages, first-time parent tips, upcoming camp reminders
**Example**: 
```
Hi {{first_name}}! Welcome to PTP Soccer Camps. {{child_name}} is going 
to love {{camp_names}}! We'll send you details soon.
```

### 2. Upcoming Camp Attendees
**Who**: Parents with camps starting in the next 14 days
**Best for**: Final reminders, what to bring, weather updates, location details
**Example**:
```
{{child_name}}'s camp starts in 3 days! Don't forget to bring: water bottle, 
cleats, shin guards. See you at {{event_location}}!
```

### 3. Past Customers
**Who**: Parents who attended previous camps (excluding last 30 days)
**Best for**: Re-engagement, new season announcements, returning customer discounts
**Example**:
```
Hi {{first_name}}! {{child_name}} had a great time last season. Our summer 
camps are now open - save 10% with code RETURNPRO.
```

### 4. High Value Customers
**Who**: Parents who spent $500+ total
**Best for**: VIP offers, early registration, multi-camp discounts, referral program
**Example**:
```
Thanks for being a valued PTP family, {{first_name}}! You've invested 
{{order_total}} in {{child_name}}'s development. Here's early access to 
our premier camps...
```

### 5. Abandoned Cart
**Who**: Parents who started but didn't complete registration (last 48 hours)
**Best for**: Recovery messages, discount offers, urgency messaging
**Example**:
```
{{first_name}}, you were just one click away from registering {{child_name}} 
for {{camp_names}}. Complete your registration in the next 24 hours and save $10!
```

### 6. By State/Region
**Who**: Filter by PA, NJ, DE, MD, NY
**Best for**: Location-specific announcements, regional camps, weather alerts
**Example**:
```
Calling all NJ parents! We're adding 3 new camp locations in Bergen County. 
Early bird pricing ends Friday!
```

### 7. By Child Age Group
**Who**: Filter by age ranges (e.g., 6-9, 10-13, 14-17)
**Best for**: Age-appropriate camps, skill level programs
**Example**:
```
Parents of 10-13 year olds: Our Elite Skills Academy is perfect for 
{{child_name}} (age {{child_age}}). Advanced training starts June 15th.
```

### 8. HubSpot Lists
**Who**: Import contacts from your HubSpot contact lists
**Best for**: Marketing campaign coordination, lead nurturing sequences
**Example**:
```
Special offer for our newsletter subscribers! {{first_name}}, register 
{{child_name}} by Friday and get 20% off.
```

### 9. Engaged Contacts
**Who**: Parents who replied to SMS in the last 30 days
**Best for**: Surveys, feedback requests, community building
**Example**:
```
{{first_name}}, thanks for being an active PTP parent! Quick question: 
How would you rate {{child_name}}'s recent camp experience? Reply 1-5.
```

### 10. Unengaged Contacts
**Who**: Parents who haven't replied in 90+ days
**Best for**: Re-engagement, feedback requests, sunset campaigns
**Example**:
```
Hi {{first_name}}, we noticed it's been a while since {{child_name}} 
attended. We miss you! Reply CAMPS to see what's new, or STOP if you'd 
like to opt out.
```

## Using Segments in Campaigns

### Step 1: Create Campaign
1. Go to **PTP Comms Hub → Campaigns**
2. Click **New Campaign**
3. Enter campaign name (e.g., "Summer 2025 Early Bird")

### Step 2: Select Segment
1. Click **Choose Segment**
2. Select from available segments
3. View estimated recipient count
4. Click **Preview Contacts** to see who will receive

### Step 3: Write Personalized Message
Use personalization variables:
```
Hi {{first_name}}! 

Great news - {{child_name}} can save $50 on summer camps when you 
register by {{deadline}}. 

Your favorite location {{event_location}} still has spots!

Register now: [link]

- Coach Mike, PTP Soccer Camps
```

### Step 4: Review & Send
1. Preview with sample contact data
2. Check segment size
3. Schedule or send immediately
4. Track results in dashboard

## Personalization Variables

Insert dynamic content for each parent:

### Basic Contact Info
- `{{first_name}}` - Parent first name
- `{{last_name}}` - Parent last name
- `{{full_name}}` - Parent full name
- `{{child_name}}` - Child's name
- `{{child_age}}` - Child's age

### Location Data
- `{{city}}` - Parent's city
- `{{state}}` - Parent's state
- `{{zip}}` - Parent's ZIP code

### Order Information
- `{{order_number}}` - Most recent order number
- `{{order_total}}` - Total spent (formatted: $125.00)
- `{{order_date}}` - Order date (formatted: January 15, 2025)
- `{{camp_names}}` - Registered camp names

### Example Usage
```
Hi {{first_name}} from {{city}}!

We noticed {{child_name}} (age {{child_age}}) loved our summer camp last year. 
You spent {{order_total}} on order #{{order_number}}.

Want to come back? We're offering returning customers like you an exclusive 
20% discount for 2025!

Reply YES to claim your discount.
```

## Advanced Segmentation

### Combine Multiple Filters

Create custom segments with AND/OR logic:

**Example 1: High-Value NJ Parents**
```php
Filters:
- State = NJ (AND)
- Total spent > $500 (AND)
- Last order within 180 days
```

**Example 2: Re-engagement Campaign**
```php
Filters:
- Last order 6-12 months ago (AND)
- Never replied to SMS (AND)
- Child age 8-12
```

### Custom Segment Builder

Go to **Campaigns → New Campaign → Custom Segment**:

1. **Add Filter** - Choose segment type
2. **Configure Options** - Set parameters (days, amounts, states, etc.)
3. **Logic** - Select AND or OR
4. **Add Another Filter** - Build complex rules
5. **Preview** - See resulting contact list

### Segment Options by Type

#### New Registrations
```
Options:
- Days: 7, 14, 30, 60, 90
```

#### Upcoming Camps
```
Options:
- Days ahead: 3, 7, 14, 21, 30
```

#### Past Customers
```
Options:
- Exclude recent: 30, 60, 90, 180 days
```

#### High Value
```
Options:
- Minimum spent: $250, $500, $1000, $2500
```

#### Abandoned Cart
```
Options:
- Hours: 24, 48, 72
```

#### Age Group
```
Options:
- Min age: 4-18
- Max age: 4-18
```

#### Engagement
```
Options:
- Engaged days: 7, 14, 30, 60, 90
- Unengaged days: 30, 60, 90, 180, 365
```

## HubSpot Integration

### Sync Contact Lists

1. **In HubSpot**: Create a contact list
2. **Get List ID**: From the list URL (e.g., `/contacts/12345`)
3. **In PTP**: Go to Campaigns → Custom Segment
4. Select "HubSpot List"
5. Enter List ID: `12345`
6. Preview contacts

### Two-Way Sync

Contacts are synchronized:
- **To HubSpot**: When orders are placed in WooCommerce
- **From HubSpot**: When lists are selected for campaigns
- **Engagement**: SMS replies logged as HubSpot activities

### HubSpot Properties

These contact properties sync:
- First name, last name
- Email, phone
- Child name, child age
- Last order date, total spent
- SMS opt-in status
- Last message date

## WooCommerce Integration

### Automatic Segmentation

Segments automatically pull from:
- Order history
- Product purchases
- Order dates
- Customer lifetime value
- Abandoned carts (if plugin installed)

### Product-Specific Campaigns

Target parents who purchased specific camp types:

```
Filter by product category:
- Summer camps
- Winter training
- Elite academies
- Birthday parties
```

### Order Status Filtering

Send messages based on order status:
- Completed (payment received)
- Processing (pending fulfillment)
- On-hold (awaiting payment)

## Best Practices

### 1. Start Broad, Then Narrow
- First campaign: All opted-in contacts
- Analyze results
- Create tighter segments based on performance

### 2. Test Personalization
- Always preview with real contact data
- Check all variables populate correctly
- Test on yourself first

### 3. Segment Size Guidelines
- **100-500 contacts**: Perfect for targeted campaigns
- **500-1000 contacts**: Good for announcements
- **1000+ contacts**: Consider splitting into smaller groups

### 4. Timing by Segment
- **New registrations**: Within 24 hours
- **Upcoming camps**: 7 days, 3 days, 1 day before
- **Past customers**: Off-season, 30 days before registration opens
- **Abandoned cart**: 24 hours, 48 hours after
- **Re-engagement**: 60-90 days of inactivity

### 5. Message Tone by Segment
- **New parents**: Welcoming, informative
- **Returning parents**: Familiar, appreciative
- **High-value**: Exclusive, VIP treatment
- **Unengaged**: Understanding, give them control

### 6. A/B Testing
Test different approaches:
- Segment A: High-value customers, 20% discount
- Segment B: High-value customers, free gift
- Compare open rates and conversions

### 7. Frequency Caps
Don't over-message segments:
- Maximum 1 message per week to same segment
- Maximum 2 messages per week per contact
- Exception: Time-sensitive (registration closing, camp starting)

## Analytics

Track segment performance:

### Campaign Metrics by Segment
- **Delivery rate**: % successfully delivered
- **Response rate**: % who replied
- **Opt-out rate**: % who unsubscribed
- **Conversion rate**: % who took desired action

### Segment Health
- **Growth rate**: New contacts added per week
- **Engagement score**: Average response rate
- **Revenue per contact**: For ROI tracking

## Examples by Use Case

### Use Case 1: Summer Camp Launch
```
Segment: Past customers (exclude last 30 days)
Message: "{{first_name}}, summer camps are open! {{child_name}} had 
a blast last time. Register by March 1st = $75 off. [link]"
Timing: Early February
Expected: 25-35% conversion
```

### Use Case 2: Last-Minute Spots
```
Segment: By State (PA only) + Child age 10-13
Message: "PA families! 5 spots left in our Elite Camp (perfect for 
{{child_name}}'s age group). Starts Monday. Interested? Reply YES."
Timing: Friday before Monday start
Expected: 60-80% open rate
```

### Use Case 3: Feedback Request
```
Segment: Engaged contacts + Recent attendees
Message: "{{first_name}}, how did {{child_name}} like the camp at 
{{event_location}}? Reply 1-5 stars + optional comment. Your feedback 
helps us improve!"
Timing: 2 days after camp ends
Expected: 40-50% response rate
```

### Use Case 4: Win-Back Campaign
```
Segment: Unengaged 180+ days
Message: "We miss you {{first_name}}! It's been a while since 
{{child_name}} joined us. Can we win you back? Reply YES for 30% off 
your next camp, or STOP to opt out."
Timing: Off-season
Expected: 10-15% conversion, 5-10% opt-out
```

## Troubleshooting

**Segment shows 0 contacts?**
- Check filter criteria aren't too restrictive
- Verify contact data is complete (missing states, ages, etc.)
- Ensure contacts are opted in

**Personalization variables blank?**
- Check contact record has data filled in
- Some variables require WooCommerce order history
- Test with complete contact records

**HubSpot list not syncing?**
- Verify API key is correct
- Check list ID matches HubSpot
- Ensure contacts have phone numbers in HubSpot

**Low response rates?**
- Message too generic (add more personalization)
- Sent at wrong time (test different times)
- Segment too broad (narrow the criteria)
- No clear call-to-action (add specific request)

## Support

Questions about segmentation?
- Review segment definitions above
- Preview contacts before sending
- Start with pre-built segments
- Test with small groups first
- Track and optimize based on results
