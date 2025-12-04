# PTP Comms Hub v3.2.0 - WooCommerce Orders & Camps

## What's New

### New "Orders & Camps" Admin Page
A powerful new admin section for tracking WooCommerce orders, products, registrations, and creating targeted segments.

#### Orders Tab
- View all WooCommerce orders with full details
- See camp dates, camper info, and SMS opt-in status at a glance
- Filter by status, product, date range, state
- Bulk actions: Add to segment, Send SMS, Export CSV
- Direct links to contact profiles and conversations
- Order statistics: total orders, completed, processing, revenue, unique customers

#### Camps/Products Tab
- Grid view of all camp products with key metrics
- Shows registrations count, orders, and revenue per camp
- Visual indicators for active, past, and full camps
- Camp date ranges and location information
- Quick links to view registrations or create segments
- Filter by upcoming/past camps and market

#### Registrations Tab
- Detailed list of all camp registrations
- Camper details: name, age, t-shirt size
- Parent contact info with SMS opt-in indicator
- Event dates, times, and locations
- Reminder status tracking (7-day, 3-day, 1-day)
- Bulk actions for segment management

#### Create Segment Tab
- **Quick Segments**: Pre-built segment templates with one-click creation
  - Past Camp Attendees
  - Upcoming Camp Registrations
  - Repeat Customers (2+ orders)
  - High Value Customers ($500+ spend)
  - New Customers (30 days)
  - SMS Opted-In Customers

- **Custom Segment Builder**: Create segments with multiple filter criteria
  - Filter by product/camp
  - Filter by order status
  - Filter by event date range
  - Filter by order date range
  - Filter by state/market
  - Filter by child age range
  - Filter by minimum orders
  - Filter by minimum spend
  - Filter by SMS status
  - Live preview of matching contacts

- **Existing Segments**: View and manage created segments

### Enhanced Segmentation Class
New segment types available throughout the plugin:
- `past_camp_attendees` - Everyone who attended a completed camp
- `by_event_date_range` - Filter by camp/event date range
- `by_order_date_range` - Filter by order/purchase date range
- `by_order_status` - Filter by WooCommerce order status
- `needs_reminder` - Registrations missing reminders
- `by_market` - Filter by camp market/location
- `by_total_spend` - Filter by customer spend range
- `sms_opted_in` - Contacts who opted in for SMS
- `custom_segment` - Contacts tagged with custom segments

New utility methods:
- `get_existing_custom_segments()` - List all custom segment names
- `get_segment_stats()` - Dashboard statistics for segments

## Menu Location
The new Orders & Camps page appears in the PTP Comms menu between Campaigns and Logs.

## Usage Tips

### Creating Effective Segments
1. **For camp follow-ups**: Use "Past Camp Attendees" segment, then filter by specific product
2. **For reminders**: Use the Custom Segment Builder with event date filters
3. **For re-engagement**: Combine "Past Customers" with time filters
4. **For upselling**: Target "Repeat Customers" or "High Value Customers"

### Bulk Operations
1. Select multiple orders/registrations using checkboxes
2. Choose action: Add to Segment, Send SMS, or Export
3. For "Add to Segment", enter a segment name
4. Applied segments can be used in Campaigns for targeted messaging

### CSV Export
Exports include:
- Contact information (name, phone, email)
- Child details (name, age)
- Location (state, city, zip)
- Products purchased
- Total spend and order count
- SMS opt-in status
- Assigned segments

## Technical Notes
- All queries are optimized with proper indexing
- Pagination implemented for large datasets (50+ records)
- AJAX-based segment preview for instant feedback
- Nonce verification on all form submissions
- Proper escaping of all output

## Files Changed
- `includes/class-admin-menu.php` - Added Orders & Camps menu item
- `includes/class-loader.php` - Added new admin page class
- `includes/class-segmentation.php` - Added new segment types and methods
- `admin/class-admin-page-orders.php` - New file (Orders & Camps page)
- `ptp-comms-hub.php` - Version bump to 3.2.0
