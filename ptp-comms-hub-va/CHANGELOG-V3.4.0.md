# PTP Comms Hub Enterprise v3.4.0 Changelog

## Release Date: November 2024

### 🎯 Major Features

#### 1. Redesigned Automations System
- **Visual Workflow Builder**: Completely redesigned admin UI with grouped automations by category
- **Quick Start Templates**: One-click creation for common automations (Order Confirmation, 7-Day Reminder, Post-Camp Follow-up)
- **Test Automation**: Send test messages directly from the edit screen with sample data
- **Conditions Builder**: Filter automations by market (PA, NJ, DE, MD, NY) and program type
- **Date Window Triggers**: Schedule promotional automations for specific date ranges (e.g., Black Friday)
- **Visual Progress Indicators**: Status dots, execution counts, and clear grouping by trigger type

#### 2. Queue-Based Campaign System
- **Scalable Sending**: Processes 50 messages per minute via wp_cron (no more timeout issues)
- **Pause/Resume**: Stop and restart campaigns mid-send
- **Progress Tracking**: Real-time status via `get_campaign_progress()` method
- **Campaign Queue Table**: New database table for reliable large-scale sending
- **Export Results**: CSV export for campaign analytics

#### 3. Canned Replies / Quick Responses
- **8 Categories**: General, Greetings, Confirmations, Reminders, Follow-ups, Support, Weather, Upsell
- **Shortcut Expansion**: Type `/thanks` in inbox to auto-insert reply
- **Variable Support**: Use `{parent_name}`, `{child_name}`, `{event_name}` in quick replies
- **Admin Management**: Full CRUD interface at PTP Comms → Quick Replies
- **Default Templates**: 10 pre-built replies installed on activation

#### 4. WordPress Dashboard Widget
- **Key Metrics**: Unread messages, sent today, total contacts, upcoming events
- **Recent Activity**: Last 5 messages with direction indicator
- **Twilio Balance**: Cached hourly, alerts when low
- **Quick Actions**: Direct links to Inbox, New Campaign, Contacts

#### 5. CSV Export System
- **Contacts Export**: All fields including segments and opt-in status
- **Campaign Results**: Delivery status, errors, timestamps per recipient
- **Communication Logs**: Date range filtering with type/direction filters
- **Registrations**: Camp details with reminder status tracking

### 📱 Enhanced SMS Service
- **Status Callback**: Automatic delivery tracking via Twilio webhooks
- **MMS Support**: Send images with messages using `send_mms()` method
- **Phone Lookup**: Validate numbers and get carrier info via Twilio Lookup API
- **Account Balance**: Check Twilio balance with `get_account_balance()`
- **Message Status**: Retrieve delivery status for any message SID

### 🗄️ Database Schema Updates

#### New Tables
```sql
-- Campaign Queue
ptp_campaign_queue (id, campaign_id, contact_id, phone_number, status, twilio_sid, error_message, created_at, processed_at)

-- Canned Replies
ptp_canned_replies (id, name, shortcut, content, category, sort_order, is_active, usage_count, created_by, created_at, updated_at)
```

#### Updated Tables
```sql
-- Conversations: Added priority, tags, notes, snoozed_until columns
-- Registrations: reminder_1day_sent, reminder_3day_sent, reminder_7day_sent already present
```

### 🔧 Admin UI Improvements

#### Automations Page
- Grouped by category (Order, Reminders, Post-Event, Marketing)
- Visual status indicators (green/red dots)
- Inline stats showing execution counts
- Quick action buttons (Edit, Pause/Play, Delete)
- How It Works diagram

#### Inbox Page
- Quick Replies dropdown above message field
- Shortcut expansion support (`/thanks`, `/reminder`, etc.)
- Category-grouped reply options

#### Logs Page
- Date range filtering
- Type/Direction filters
- Visual status badges
- Export CSV button
- Stats summary (total, outbound, inbound)

#### Contacts Page
- Export CSV button added
- All existing functionality preserved

#### Campaigns Page
- Export button for completed campaigns
- Progress tracking for sending campaigns

### 🔌 Cron Jobs

| Schedule | Hook | Function |
|----------|------|----------|
| Every 5 min | `ptp_comms_process_automations` | Process reminders and triggers |
| Every 1 min | `ptp_comms_process_campaign_queue` | Send queued campaign messages |
| Daily | `ptp_comms_sync_hubspot` | Sync contacts with HubSpot |

### 🪝 Developer Hooks

```php
// Before any message is sent
do_action('ptp_comms_before_send_message', $contact_id, $message, $type);

// After message sent
do_action('ptp_comms_after_send_message', $contact_id, $message, $type, $result);

// After automation runs
do_action('ptp_comms_after_automation_run', $automation_id, $contact_id, $result);

// Campaign lifecycle
do_action('ptp_comms_campaign_started', $campaign_id, $queued_count);
do_action('ptp_comms_campaign_completed', $campaign_id, $sent, $failed);
```

### 📂 New Files Added
- `includes/class-canned-replies.php`
- `includes/class-dashboard-widget.php`
- `includes/class-csv-export.php`
- `admin/class-admin-page-canned-replies.php`

### 🐛 Bug Fixes
- Fixed conversation status queries to include 'open', 'active', 'pending'
- Updated deactivator to clear all cron jobs including campaign queue
- Added missing `ptp_comms_execute_delayed_automation` action hook

### ⚙️ Configuration

#### New Settings
- Campaign batch size (default: 50 per minute)
- Quiet hours (9 PM - 8 AM by default)

#### Twilio Status Callback
Configure in Twilio Console → Phone Numbers:
```
Status Callback URL: https://yoursite.com/ptp-comms/sms-status
Method: POST
```

### 📋 Upgrade Notes

1. **Database Migration**: Run plugin deactivation/reactivation or visit Settings page to create new tables
2. **Cron Jobs**: New schedules registered automatically on activation
3. **Default Canned Replies**: Installed automatically on first admin load
4. **Campaign Migration**: Existing campaigns continue to work; new campaigns use queue system

### 🔜 Coming Soon
- Inbox conversation assignment
- Conversation notes/internal comments  
- Advanced reporting dashboard with charts
- Custom reporting hooks/filters
