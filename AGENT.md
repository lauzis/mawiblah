# MAWIBLAH Plugin - Agent Knowledge Base

## Overview
MAWIBLAH is a WordPress plugin that provides Mailchimp-like functionality for sending email campaigns to subscribers. It's designed for small projects with tight budgets as an alternative to paid email marketing services.

## Project Structure

### Core Classes (`/classes/`)
- **Actions.php** - Handles WordPress actions and admin dashboard widget registration
- **Campaigns.php** - Campaign management and statistics
- **GravityForms.php** - Integration with Gravity Forms for subscriber collection
- **Helpers.php** - Utility functions and URL generators
- **Init.php** - Plugin initialization
- **Logs.php** - Action logging for debugging
- **Renderer.php** - Template rendering
- **RestRoutes.php** - REST API endpoints for email sending
- **Settings.php** - Plugin settings management
- **ShortCodes.php** - WordPress shortcode handlers
- **Subscribers.php** - Subscriber management
- **Templates.php** - Email template management
- **Tests.php** - Testing functionality
- **Unsubscribe.php** - Unsubscribe functionality
- **Visits.php** - Click tracking for campaigns

### Admin Interface (`/admin/`)
- **dashboard.php** - WordPress admin dashboard widget (functionality moved to Actions.php)

### Templates (`/templates/`)
- Campaign management templates
- Settings pages
- Unsubscribe pages
- Test pages

## Key Features

### Campaign Management
- Create and manage email campaigns
- Track campaign statistics (emails sent, failed, skipped, unsubscribed)
- Test mode for campaign validation before sending
- Approval workflow (test → approve → send)

### Campaign Statistics
Campaign objects include these meta fields:
- `emailsSend` - Total emails successfully sent
- `emailsFailed` - Total emails that failed to send
- `emailsSkipped` - Total emails skipped (unsubed, doNotDisturb, etc.)
- `emailsUnsubed` - Total unsubscribed users encountered
- `emailsNewlyUnsubed` - Users who unsubscribed during this campaign
- `uniqueUserClicks` - Unique users who clicked any link
- `linksClicked` - Unique link clicks (per session)
- `linksClickedTotal` - Total link clicks (including duplicates)
- `links` - JSON object with individual URL click counts
- `campaignHash` - Unique campaign identifier (MD5 hash)
- `testStarted`, `testFinished`, `testApproved` - Test workflow timestamps
- `campaignStarted`, `campaignFinished` - Campaign execution timestamps

### Counter Update Logic
**Important:** Counters are only updated during actual campaign sends, NOT during:
- Test mode
- When email sending is disabled
- Already sent emails (to avoid double counting)

Counters ARE updated for:
- Successfully sent emails (not in test mode)
- Failed email sends (not in test mode)
- Skipped unsubscribed users (not in test mode)
- Skipped "do not disturb" threshold users (not in test mode)

### Email Sending Flow
1. Campaign is created with title, subject, content, audiences, and template
2. Test mode: Send to testers for validation
3. Approval: Review and approve the campaign
4. Send: Process through all subscribers in selected audiences
5. Tracking: Monitor sent/failed/skipped counts and link clicks

### Key Methods in Campaigns.php

#### Getting Campaigns
```php
Campaigns::getCampaigns() // Get all campaigns
Campaigns::getLastCampaigns(int $limit = 5) // Get recent campaigns (ordered by date DESC)
Campaigns::getCampaignById(int $id) // Get specific campaign
Campaigns::getCampaignByHash(string $campaignHash) // Get by campaign hash ID
```

#### Campaign Statistics
```php
Campaigns::appendMeta($post) // Attaches all stats to campaign object
Campaigns::getCounters($campaign) // Returns email counters object
Campaigns::updateCounters($campaign, $sent, $failed, $skipped, $unsubed) // Updates DB
Campaigns::linkClicked($campaignHash, $url) // Tracks link clicks
```

#### Campaign Workflow
```php
Campaigns::testStart($campaignId)
Campaigns::testFinish($campaignId)
Campaigns::testApprove($campaignId)
Campaigns::testReset($campaignId)
Campaigns::campaignStart($campaignId)
Campaigns::campaignFinish($campaignId)
```

### Subscriber Management
- Import from Gravity Forms entries
- Manual subscriber management
- Unsubscribe functionality
- Import unsubscribed list from Mailchimp
- Track last interaction time
- "Do not disturb" threshold to prevent over-emailing
- Tester flagging for test campaigns

### Email Sending (RestRoutes.php)
Email sending is handled via REST API endpoint `/wp-json/mawiblah/v1/send-email` with:
- Individual email sending (one by one)
- Configurable delay between emails
- Skip logic for:
  - Unsubscribed users
  - Already sent emails
  - "Do not disturb" threshold
  - Non-testers in test mode
  - Disabled email sending (debug mode)
- Success/failure tracking
- Logging for debugging

### Settings
- Time between emails (to avoid server overload)
- "Do not disturb" threshold (minimum time between emails to same subscriber)
- Enable/disable actual email sending (for testing)
- Database logging toggle
- IP-restricted debugging

### Dashboard Widget
The plugin adds a WordPress admin dashboard widget showing:
- Campaign statistics
- Email subscription stats from Gravity Forms
- Visual graphs for monthly and yearly trends

Widget is registered in `Actions::init()` via:
```php
add_action('wp_dashboard_setup', [Actions::class, 'registerDashboardWidget']);
```

## Technical Details

### WordPress Integration
- Custom post types for campaigns, subscribers
- Uses `wp_mail()` for email sending
- REST API for async email processing
- AJAX for frontend interactions
- WordPress shortcodes for email templates

### Performance Considerations
- Sends emails individually (not batch) - necessary for personalization
- Configurable delays between sends
- Async processing via JavaScript
- Not suitable for 100k+ email lists

### Email Templates
- Shortcode-based template system
- Dynamic content replacement:
  - `{campaignId}` - Campaign identifier
  - `{subscriberHash}` - Subscriber identifier
  - `{email}` - Subscriber email
  - `[gdlnks_newsletter_title]` - Campaign title
  - `[gdlnks_newsletter_content]` - Campaign content

### Click Tracking
- URLs are tracked per campaign
- Session-based duplicate click prevention
- Stores click timestamps for timing analysis
- Both unique and total click counts

## Recent Changes (Session 2025-11-30)

1. **Enhanced Statistics Dashboard**
   - Added Subscriber Growth and Unsubscribe Growth graphs
   - Added Activity Rating and Overall Active Hours metrics
   - Visualized campaign performance with bar graphs

2. **Individual Campaign Statistics**
   - Added detailed stats to campaign view (Raw, Conversion, Links, Days, Hours)
   - Implemented `Campaigns::getStatsForCampaign()` and `Campaigns::getConversionStatsForCampaign()`

3. **Refactoring**
   - Split dashboard templates into modular components (`templates/stats/`)
   - Improved graph rendering with `templates/campaign/bar-graph.php`
   - Refactored `campaignId` (hash) to `campaignHash` to distinguish from post ID (`campaignPostId`)

4. **Bug Fixes & Improvements**
   - Fixed null-safety bug in `campaignStart()`
   - Fixed variable shadowing in stats templates
   - Internationalized "Weekdays", "Hours", "Max", "Avg" and table headers
   - Fixed typo in `classes/Tests.php`
   - Preserved `campaignId` in unsubscribe flow to fix `emailsNewlyUnsubed` counter

## Recent Changes (Session 2025-10-24)

1. **Added `getLastCampaigns()` method** in Campaigns.php
   - Returns most recent campaigns ordered by date (DESC)
   - Accepts optional limit parameter (default: 5)

2. **Moved dashboard widget to Actions.php**
   - Registration: `Actions::registerDashboardWidget()`
   - Rendering: `Actions::renderDashboardWidget()`
   - Uses new `getLastCampaigns()` method

3. **Fixed campaign counter updates**
   - Counters now properly update in database via `updateCounters()`
   - Only updates during actual campaigns (not test mode)
   - Proper handling of sent/failed/skipped/unsubscribed counts
   - Excludes "already sent" from new counts (avoids double counting)

## Development Guidelines

### Code Style
- WordPress coding standards
- Namespaced under `Mawiblah\`
- Static methods for utility classes
- Minimal comments (code should be self-explanatory)

### Making Changes
- **Surgical modifications** - change as few lines as possible
- Only update counters during actual campaign sends (not tests)
- Maintain backward compatibility
- Don't break existing functionality
- Validate changes don't affect unrelated features

### Counter Logic Rules
1. Never increment counters in test mode
2. Never increment for "already sent" emails
3. Always check `$testMode` before updating counters
4. Update counters immediately before returning from send function
5. Use `Campaigns::updateCounters()` - don't update meta directly

## Support & Limitations
- Free plugin with limited support
- Built for specific project needs
- Not designed for universal compatibility
- Budget-friendly alternative to Mailchimp
- Requires PHP 7.4+ and WordPress 5.0+

## Future Considerations
- Potential for making it more universal
- Could add automation features
- Open tracking (currently not implemented)
- Batch sending optimization
- Advanced segmentation
