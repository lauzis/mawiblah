# MAWIBLAH Documentation

## Table of Contents
- [Features Overview](#features-overview)
- [Campaign Fields & Counters](#campaign-fields--counters)
- [Click Tracking](#click-tracking)
- [Email Templates](#email-templates)
- [Subscriber Management](#subscriber-management)
- [Settings](#settings)

## Features Overview

MAWIBLAH is a WordPress email campaign plugin that provides basic email marketing functionality without recurring costs.

## Campaign Fields & Counters

Each campaign in MAWIBLAH tracks various metrics and metadata stored as WordPress post meta fields:

### Basic Campaign Information
- **`campaignId`** - Unique MD5 hash identifier for the campaign (generated from post ID)
- **`contentTitle`** - Internal title/name for the campaign
- **`subject`** - Email subject line
- **`template`** - Email template to use
- **`audiences`** - Array of WordPress taxonomy term IDs representing subscriber audiences (uses `mawiblah_subscriber_category` taxonomy)
- **`status`** - Current campaign status (draft, sending-in-progress, completed, etc.)

### Email Delivery Counters
- **`emailsSend`** - Total number of emails successfully sent
- **`emailsFailed`** - Number of emails that failed to send
- **`emailsSkipped`** - Number of emails skipped (e.g., due to throttling, duplicates, or "don't disturb" threshold)
- **`emailsUnsubed`** - Number of recipients who were already unsubscribed when campaign ran
- **`emailsNewlyUnsubed`** - Number of recipients who unsubscribed after receiving this specific campaign

### Click Tracking Counters
- **`linksClickedTotal`** - Total number of all link clicks (includes duplicates from same user/session)
- **`linksClicked`** - Unique link clicks per session (duplicate clicks from same session don't count)
- **`uniqueUserClicks`** - Number of unique users/subscribers who clicked any link in the campaign (counted once per subscriber)
- **`links`** - JSON object tracking individual URL click counts: `{"https://example.com": 5, "https://example.com/page": 3}`
- **`click_time`** - Timestamps of when links were clicked (stored as multiple meta entries for timing analysis)

### Campaign Workflow Status
- **`testStarted`** - Timestamp when test phase was initiated (or `false` if not started)
- **`testFinished`** - Timestamp when test phase completed (or `false` if not finished)
- **`testApproved`** - Timestamp when test was approved (or `false` if not approved)
- **`campaignStarted`** - Timestamp when actual campaign sending began (or `false` if not started)
- **`campaignFinished`** - Timestamp when campaign sending completed (or `false` if not finished)

### Counter Usage Examples

**Calculating unique user engagement rate:**
```
User Engagement Rate = (uniqueUserClicks / emailsSend) * 100
```

**Calculating engagement rate (unique link clicks):**
```
Link Engagement Rate = (linksClicked / emailsSend) * 100
```

**Calculating total interactions:**
```
Total Interactions = linksClickedTotal
```

**Calculating average clicks per engaged user:**
```
Avg Clicks Per User = linksClickedTotal / uniqueUserClicks
```

**Calculating campaign effectiveness:**
```
Delivery Rate = (emailsSend / (emailsSend + emailsFailed + emailsSkipped)) * 100
```

**Tracking unsubscribe impact:**
```
Unsubscribe Rate = (emailsNewlyUnsubed / emailsSend) * 100
```

## Click Tracking

MAWIBLAH tracks link clicks in three different ways to provide comprehensive engagement metrics:

### linksClickedTotal
**Total clicks including duplicates**

This metric counts every single click on links in your campaign, including multiple clicks from the same user/session. It represents the total engagement with your campaign links.

- Incremented on every link click
- Includes duplicate clicks from same subscriber
- Useful for measuring overall engagement and interest
- Example: If one person clicks a link 5 times, this counts as 5

### linksClicked
**Unique clicks per session**

This metric counts only unique clicks per user session. If a subscriber clicks the same link multiple times during their session, it only counts once.

- Incremented only once per URL per session
- Duplicate clicks from same subscriber/session are ignored
- Session is tracked using PHP sessions with `campaignId`, `subscriberId`, and URL
- Useful for measuring unique link engagement
- Example: If one person clicks 3 different links, this counts as 3

### uniqueUserClicks
**Unique visitors/users**

This metric counts the number of unique subscribers who clicked any link in the campaign. Each subscriber is counted only once, regardless of how many links they click.

- Incremented only once per subscriber per campaign
- Tracks unique visitors who engaged with the campaign
- Session is tracked using PHP sessions with `campaignId` and `subscriberId`
- Useful for measuring reach and user-level engagement
- Example: If one person clicks 5 different links multiple times, this counts as 1

### Implementation Details

When a link is clicked:
1. `linksClickedTotal` is always incremented (every click)
2. Session is checked for existing campaign/subscriber visit
3. If new subscriber (no `campaignId` or `subscriberId` in session), `uniqueUserClicks` IS incremented
4. If URL was not clicked in this session (`$_SESSION[$url]` not set), `linksClicked` IS incremented
5. If session already exists for that URL, only `linksClickedTotal` is updated

This triple-tracking approach gives you:
- **Total engagement** (linksClickedTotal: how many times content was accessed)
- **Unique link engagement** (linksClicked: how many different links were clicked across sessions)
- **Unique user reach** (uniqueUserClicks: how many different subscribers engaged)

## Email Templates

Email templates are created using shortcodes and can include HTML content. Templates are processed through WordPress's shortcode system before being sent.

## Subscriber Management

### Audience/Category Management
Subscribers are organized using WordPress taxonomy (`mawiblah_subscriber_category`). This provides:
- **Native WordPress integration** - Uses standard WordPress taxonomy system
- **Flexible categorization** - Create unlimited audience segments
- **Easy management** - Manage audiences through WordPress admin interface
- **Campaign targeting** - Select multiple audiences when creating campaigns

### Import Sources
- **Gravity Forms**: Automatically imports from form entries (legacy support maintained)
- **Manual Import**: Add subscribers directly
- **Mailchimp Import**: Import unsubscribed users from Mailchimp

### Subscriber Features
- Unsubscribe functionality with confirmation
- Last interaction tracking (first and last interaction timestamps)
- Email throttling (configurable time between emails to same subscriber)
- Duplicate detection (case-insensitive email matching)
- Taxonomy-based audience assignment

## Settings

### Email Intervals
Control the minimum time between emails sent to the same subscriber to avoid overwhelming them.

### Debugging
- Enable debugging with IP restrictions
- Skip actual email sending for testing
- Database logging toggle

### Click Timing
Campaign click times are logged to analyze when subscribers are most active, helping optimize send times.

## Dashboard Statistics

The dashboard provides comprehensive analytics to help optimize campaign performance. These statistics are available on the main plugin dashboard and as WordPress dashboard widgets.

### Overall Active Days & Campaign Start Days
Compares two datasets to identify alignment between sending schedules and user activity:
- **Active Days:** Aggregates click timestamps by day of the week for the last 12 campaigns.
- **Start Days:** Aggregates campaign start timestamps by day of the week for the last 12 campaigns.

### Activity Rating
A calculated metric to evaluate the efficiency of sending days:
```
Activity Rating = Active Days Count / Campaign Start Days Count
```
- **High Rating (>1):** Users are more active on these days than you are sending campaigns (Opportunity).
- **Low Rating (<1):** You are sending more campaigns than users are engaging with (Potential oversaturation).

### Overall Active Hours
Aggregates click timestamps by hour of the day (0-23) for the last 12 campaigns to identify peak engagement hours.

## API Functions

### Campaign Statistics
**`Campaigns::getClickTimesByDayOfWeekForLastCampaigns(int $limit = 12): array`**

Aggregates click data by day of the week for the specified number of recent campaigns:
```php
$stats = Campaigns::getClickTimesByDayOfWeekForLastCampaigns(12);
// Returns ['Monday' => 50, 'Tuesday' => 30, ...]
```

**`Campaigns::getCampaignStartTimesByDayOfWeek(int $limit = 12): array`**

Aggregates campaign start times by day of the week:
```php
$stats = Campaigns::getCampaignStartTimesByDayOfWeek(12);
// Returns ['Monday' => 2, 'Tuesday' => 1, ...]
```

**`Campaigns::getClickTimesByHourOfDayForLastCampaigns(int $limit = 12): array`**

Aggregates click data by hour of the day (0-23):
```php
$stats = Campaigns::getClickTimesByHourOfDayForLastCampaigns(12);
// Returns [0 => 5, 1 => 2, ..., 14 => 150, ...]
```

### Audience Management
**`Subscribers::getAllAudiences(): array`**

Retrieves all available taxonomy audiences:
```php
$audiences = Subscribers::getAllAudiences();
// Returns array of audience objects with term_id, name, description
```

**`Subscribers::getSubscribersByAudience(int $audienceId): array`**

Gets all subscribers for a specific audience using WordPress tax_query:
```php
$subscribers = Subscribers::getSubscribersByAudience($audienceId);
// Returns array of subscriber objects
```

**`Subscribers::validateAudiences(array $audiences): bool`**

Validates that audience IDs exist in the taxonomy:
```php
$isValid = Subscribers::validateAudiences([1, 2, 3]);
// Returns true if all audience IDs are valid taxonomy terms
```

### Table Rendering
**`Templates::renderTable(array $headers, array $data): void`**

Renders a styled data table using the `campaign/table-stats.php` template:
```php
$headers = ['Campaign', 'Sent', 'Failed', 'Opened'];
$data = [
    ['Summer Sale', '1000', '5', '750'],
    ['Winter Newsletter', '850', '3', '620']
];
Templates::renderTable($headers, $data);
```
