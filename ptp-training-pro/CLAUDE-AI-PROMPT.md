# PTP Training Pro - Claude AI Development Prompt

**Version:** 4.0.0 (December 2024)

## Changes in v4.0.0

### Admin UI/UX Overhaul
1. **Polished Admin CSS** - Complete redesign with PTP brand tokens, cards, stats, tables, modals
2. **Enhanced Applications Page** - Status tabs, search, detailed view, quick actions
3. **Consistent Design Language** - Unified spacing, typography, and visual hierarchy
4. **Stats Dashboard** - Visual stat cards with icons and color-coded categories

### Front-End Application Form
1. **TeachMeTo-Style Layout** - Full-width hero, visual storytelling, mobile-first
2. **Multi-Step Form** - Organized sections with numbered steps
3. **AJAX Submission** - Real-time validation, loading states, success/error messages
4. **Email Notifications** - Admin notification + applicant confirmation emails
5. **Phone Formatting** - Auto-format phone input as (555) 555-5555

### New Shortcodes
- `[ptp_application_form]` - TeachMeTo-style trainer application form

### Backend Wiring
1. **Application AJAX Handler** - Secure submission with nonce verification
2. **Database Integration** - Applications saved to ptp_applications table
3. **Email Workflow** - Automated admin + applicant email notifications
4. **Role Type Field** - Added role_type column to applications table

## Previous Changes in v3.3.0

1. **WooCommerce Integration** - Added optional WooCommerce integration with graceful fallback
2. **Shortcode Aliases** - Added spec-compliant shortcode names (ptp_training_directory, ptp_parent_dashboard, etc.)
3. **Hero Section** - Updated headline to "Don't just dream. Train." with spec CSS classes
4. **Messaging System** - Added Messages tab to both parent and trainer dashboards
5. **Availability Editor** - Added weekly availability editor to trainer dashboard
6. **CSS Improvements** - Added all spec-required CSS class names and styling

---

Use this prompt when working with Claude to continue developing the PTP Private Training plugin. Copy and paste this entire prompt, then describe what you want to build.

---

## CONTEXT PROMPT

```
You are helping me develop a WordPress plugin called "PTP Training Pro" for PTP Soccer Camps (ptpsummercamps.com). This is a private training marketplace that connects youth soccer players with trainers (MLS players, D1 athletes) for 1-on-1 sessions.

### BUSINESS CONTEXT
- PTP Soccer Camps runs summer camps across PA, NJ, DE, MD, NY
- Private training is a new revenue stream alongside camps
- Target customers: Parents of youth soccer players ages 6-14
- Trainers: Current MLS players, D1 college athletes, professional coaches
- Competitive reference: TeachMe.to (marketplace for sports lessons)

### CURRENT PLUGIN ARCHITECTURE (v3.0)

**Database Tables (prefix: ptp_):**
- trainers: Trainer profiles with Stripe/Calendar integration
- trainer_locations: Multiple training locations per trainer
- availability: Weekly recurring availability slots
- availability_exceptions: Blocked dates, holidays
- lesson_packs: Purchased session packages (1/4/8 sessions)
- sessions: Individual booked sessions
- reviews: Customer reviews with skill tags
- payouts: Revenue tracking and Stripe payouts
- applications: Trainer applications
- messages: Trainer-customer messaging

**Key Features Implemented:**
- Map-based trainer discovery with geolocation
- Trainer video profiles (upload or YouTube/Vimeo)
- Lesson pack pricing (single, 4-pack with 10% off, 8-pack with 20% off)
- Stripe Connect for trainer payouts (80/20 split default)
- Google Calendar sync for sessions
- Availability calendar with slot booking
- Reviews with skill improvement tags
- Trainer dashboard with earnings, sessions, integrations
- Admin panel for applications, payouts, settings
- Mobile-first responsive design

**Tech Stack:**
- WordPress 6.0+ / PHP 7.4+
- WooCommerce (optional, for camp cross-sells)
- Stripe Connect (Express accounts)
- Google Maps API (geocoding, map display)
- Google Calendar API (OAuth 2.0)
- REST API (20+ endpoints)
- Vanilla JS frontend (no jQuery dependency for public)

**File Structure:**
```
ptp-training-pro/
├── ptp-training-pro.php (main plugin file)
├── includes/
│   ├── class-ptp-database.php
│   ├── class-ptp-admin.php
│   ├── class-ptp-roles.php
│   ├── class-ptp-post-types.php
│   ├── api/class-ptp-rest-api.php
│   ├── stripe/class-ptp-stripe.php
│   ├── calendar/class-ptp-google-calendar.php
│   ├── maps/class-ptp-maps.php
│   ├── video/class-ptp-video.php
│   └── reviews/class-ptp-reviews.php
├── templates/frontend/
│   ├── marketplace.php
│   ├── trainer-profile.php
│   ├── trainer-dashboard.php
│   └── [other templates]
└── assets/
    ├── css/frontend.css
    ├── js/frontend.js
    └── images/
```

**Design System:**
- Primary: #FCB900 (PTP Yellow)
- Ink: #0E0F11
- Off-white: #F4F3F0
- Font: Inter
- Mobile-first with 640px/768px/1024px breakpoints
- Card-based UI with subtle shadows
- Professional but approachable tone

### SHORTCODES
- [ptp_trainer_marketplace] - Main discovery page with map/list toggle
- [ptp_trainer_profile] - Individual trainer profile
- [ptp_trainer_dashboard] - Trainer management portal
- [ptp_trainer_application] - Become a trainer form
- [ptp_my_training] - Customer's booked sessions
- [ptp_checkout] - Booking checkout flow

### API ENDPOINTS (ptp-training/v1/)
Public:
- GET /trainers - List with filters (state, specialty, location)
- GET /trainers/{slug} - Single trainer
- GET /trainers/{id}/reviews
- GET /trainers/{id}/availability
- GET /trainers/{id}/slots?date=YYYY-MM-DD
- GET /filters - Available filter options
- POST /applications - Submit trainer application

Authenticated:
- POST /book - Create booking/checkout
- POST /sessions/{id}/schedule
- POST /sessions/{id}/cancel
- POST /reviews
- GET /my/packs
- GET /my/sessions

Trainer:
- GET/POST /trainer/profile
- POST /trainer/availability
- GET /trainer/sessions
- POST /trainer/sessions/{id}/complete
- GET /trainer/stats
- POST /trainer/stripe-connect
- POST /trainer/calendar-connect
- POST /trainer/video-upload

### WHAT I NEED HELP WITH
[Describe your specific request here - new feature, bug fix, optimization, etc.]

### REQUIREMENTS
1. Follow existing code patterns and naming conventions
2. Mobile-first, responsive design
3. Use PTP brand colors and design system
4. Include proper sanitization/escaping
5. Add REST API endpoints for new features
6. Consider performance (lazy loading, caching)
7. Support both logged-in and guest users where appropriate
```

---

## FEATURE REQUEST TEMPLATES

### New Feature Template
```
Add [FEATURE NAME] to the PTP Training Pro plugin.

**User Story:**
As a [trainer/parent/admin], I want to [action] so that [benefit].

**Requirements:**
- [Requirement 1]
- [Requirement 2]
- [Requirement 3]

**Acceptance Criteria:**
- [ ] [Criterion 1]
- [ ] [Criterion 2]

**Design Notes:**
[Any specific design requirements or references]
```

### Bug Fix Template
```
Fix [BUG DESCRIPTION] in PTP Training Pro.

**Current Behavior:**
[What's happening now]

**Expected Behavior:**
[What should happen]

**Steps to Reproduce:**
1. [Step 1]
2. [Step 2]

**Affected Files:**
- [File path if known]
```

---

## PRIORITY IMPROVEMENTS BACKLOG

### High Priority (Competitive Parity)

1. **SMS Notifications via Twilio**
   - Session reminders (24hr, 1hr before)
   - Booking confirmations
   - Cancellation alerts
   - Settings already in admin panel

2. **Recurring Subscription Lessons**
   - Weekly/bi-weekly auto-booking
   - Subscription pricing (monthly rate)
   - Auto-charge via Stripe subscriptions
   - Pause/cancel subscription

3. **Enhanced Availability System**
   - Block specific dates easily
   - Vacation mode
   - Buffer time between sessions
   - Max sessions per day limit

4. **Customer Messaging System**
   - In-app chat between trainer and parent
   - Message notifications
   - Pre-booking inquiries
   - Session notes/homework sharing

### Medium Priority (Enhanced UX)

5. **Trainer Onboarding Wizard**
   - Step-by-step profile setup
   - Photo upload with cropping
   - Video recording/upload guide
   - Stripe connection flow
   - Calendar sync walkthrough

6. **Progress Tracking**
   - Session notes history
   - Skill assessments
   - Progress photos/videos
   - Parent dashboard view

7. **Referral Program**
   - Customer referral codes
   - Trainer referral incentives
   - Tracking and rewards

8. **Advanced Search Filters**
   - Price range slider
   - Availability filter (available this week)
   - Experience level filter
   - Distance sorting

### Lower Priority (Nice to Have)

9. **AI Features** (like TeachMe.to)
   - AI lesson summaries
   - Personalized drill recommendations
   - Progress predictions

10. **Mobile App Wrapper**
    - PWA manifest
    - Push notifications
    - Offline booking view

11. **Group Training**
    - Small group sessions (2-4 players)
    - Sibling discounts
    - Group scheduling

12. **Camp Integration**
    - Cross-sell camps on trainer profiles
    - "Train with me at camp" feature
    - Camp counselor profiles

---

## CODE SNIPPETS FOR COMMON TASKS

### Add New Database Table
```php
// In class-ptp-database.php, add to create_tables()
$sql = "CREATE TABLE {$wpdb->prefix}ptp_[table_name] (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    // ... columns
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";
dbDelta($sql);
```

### Add REST API Endpoint
```php
// In class-ptp-rest-api.php
register_rest_route($namespace, '/endpoint', array(
    'methods' => 'GET',
    'callback' => array($this, 'callback_method'),
    'permission_callback' => array($this, 'check_permission')
));
```

### Add Admin Setting
```php
// In class-ptp-admin.php register_settings()
register_setting('ptp_training_settings', 'ptp_setting_name');

// In render_settings()
<tr>
    <th>Setting Label</th>
    <td><input type="text" name="ptp_setting_name" value="<?php echo esc_attr(get_option('ptp_setting_name')); ?>" /></td>
</tr>
```

### Add Frontend Template
```php
// In main plugin file, add shortcode
add_shortcode('ptp_new_feature', array($this, 'render_new_feature'));

public function render_new_feature($atts) {
    ob_start();
    include PTP_TRAINING_PATH . 'templates/frontend/new-feature.php';
    return ob_get_clean();
}
```

---

## TESTING CHECKLIST

Before deploying any changes:

- [ ] Test on mobile (iPhone, Android)
- [ ] Test with no trainers in database
- [ ] Test with logged-out user
- [ ] Test with trainer role
- [ ] Test with admin role
- [ ] Check console for JS errors
- [ ] Validate all form submissions
- [ ] Test Stripe in test mode
- [ ] Check email notifications
- [ ] Verify database queries are optimized
- [ ] Test pagination/load more
- [ ] Check accessibility (keyboard nav, screen readers)

---

## DEPLOYMENT NOTES

1. Always backup database before deploying
2. Run database migrations via plugin activation
3. Flush rewrite rules after adding new URL patterns
4. Clear any caching plugins after CSS/JS changes
5. Test Stripe webhooks with Stripe CLI locally
6. Verify Google API credentials are set for production domain

---

Remember: This plugin serves busy parents looking to improve their kids' soccer skills. Every feature should be fast, mobile-friendly, and reduce friction in the booking process.
