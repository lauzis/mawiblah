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
- **Logs.php** - File-based action logging. Writes daily log files to `{uploads}/gae-logs/mawiblah-YYYY-MM-DD.log`. Public API: `addLog()`, `clearLogs()`, `getLogCount()`, `getLogFiles()`. Enabled when `mawiblah-debug` option equals `enable-db-log`.
- **Renderer.php** - Template rendering
- **RestRoutes.php** - REST API endpoint for sending individual campaign emails (`/send-email`)
- **Settings.php** - Plugin settings management; includes `recaptchaReady()` for safe reCAPTCHA gate
- **ShortCodes.php** - WordPress shortcode handlers including `[mawiblah_subscribe_form]` and the built-in send-condition shortcode `[mawiblah_new_posts_since_last_sent campaign_id="N"]`
- **Subscribers.php** - Subscriber management, audience taxonomy, audience hash generation
- **SubscriptionForm.php** - Subscription form rendering, REST `/subscribe` endpoint, re-subscribe flow, programmatic `subscribeByEmail()`
- **Subscription form captcha** - `Settings::recaptchaVersion()` answers
  `disabled`, `v2` or `v3`. v2 renders the checkbox and the browser sends what
  the visitor ticked; v3 renders nothing and fetches a token on submit, and the
  answer has to score at least 0.5. `'enabled'`, the value from when v3 was the
  only option, is migrated to `'v3'` in 1.0.40 -- Carbon Fields returns a
  select's default for any value not in its options, so leaving it would have
  read as *disabled*
- **Scheduler / SchedulerCron** - A scheduler is its own post (`campaign_id`,
  `status`, `schedule_type` once|weekly|monthly, `send_time`, `send_day`,
  `send_date`, `next_send`, `end_date`). One WP-Cron event,
  `mawiblah_scheduler_check`, runs at the interval in settings and walks every
  active scheduler: past `next_send` and the campaign is reset, background-sent
  and `next_send` advanced (or the scheduler marked `completed` for a one-off).
  A send is skipped when the campaign is not test-approved, when the campaign's
  `send_condition_shortcode` returns nothing, and when a previous send is still
  running -- **started and not finished**, both halves. Reading
  `backgroundStarted` alone retired a schedule for ever the first time that flag
  outlived its send (fixed in 1.0.41)
- **Templates.php** - Email template management with child/parent theme override
  support. A theme overrides any letter by name from
  `<theme>/mawiblah/email_templates/<name>.html`; `resubscribe-confirm` is the
  subscription confirmation, and a theme that provides it gets an HTML letter
  with `{{confirm_url}}`, `{{site_name}}` and `{{subscriber_email}}` filled in
  after shortcodes run. Without one, the plain-text default is sent
- **Shipped email templates** - `email_templates/mawiblah-newsletter-template.html`
  is the default letter and renders the latest 3 articles via
  `[mawiblah_newest_articles count="3"]`.
  `email_templates/mawiblah-all-variables-test.html` is a diagnostic letter
  carrying every supported variable exactly once, each wrapped in
  `<li data-mawiblah-marker="<name>">…</li>`. **A shortcode added to
  `ShortCodes::register()` must get a marker here** — `EmailTemplateTest` walks
  `$GLOBALS['shortcode_tags']` and fails on any `mawiblah_` shortcode the
  template does not exercise
- **Tests.php** - In-browser integration test scenarios (button-triggered, self-contained).
  `default-templates` renders the shipped letters and reports any variable left
  unreplaced by its marker name
- **Unsubscribe.php** - Unsubscribe confirmation flow + RFC 8058 one-click REST endpoint (`/unsubscribe`)
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
- File logging toggle (writes to daily log files, not the database)
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
- Static placeholders, replaced by `str_replace`:
  - `{campaignHash}` - Campaign identifier
  - `{subscriberHash}` - Subscriber identifier
  - `{email}` - Subscriber email
  - plus the URL-encoded variants `%7BcampaignHash%7D`, `%7BsubscriberHash%7D`, `%7Bemail%7D`
- Shortcodes: no allow-list. `do_shortcode()` runs over the template in `lockTemplate()` and
  again per recipient in `fillTemplate()`, so **any** shortcode registered in WordPress — the
  plugin's, the theme's, a third party's — is evaluated, in the template and in the campaign
  content alike. The second pass is what expands shortcodes carried in by the campaign content.
- `[mawiblah_title]` / `[mawiblah_content]` render the active campaign. `ShortCodes::setCampaign()`
  is called around both `do_shortcode()` passes; without it these fall back to the global post,
  which is not the campaign when a template is rendered outside the loop.
- `[gdlnks_newsletter_title]` and `[gdlnks_newsletter_content]` were site-specific hardcoded
  `str_replace` calls and were removed in 1.0.42 — do not reintroduce per-site tags here.

### Click Tracking
- URLs are tracked per campaign
- Session-based duplicate click prevention
- Stores click timestamps for timing analysis
- Both unique and total click counts


## Documentation Files

| File | Audience | Purpose |
|---|---|---|
| `README.md` | GitHub / developers | Feature overview, comparison table, full changelog (`### --- X.Y.Z ---`) |
| `readme.txt` | WordPress.org | Plugin directory listing, `Stable tag`, short changelog (`= X.Y.Z =`) |
| `DOCUMENTATION.md` | Developers | Architecture, flow diagrams, field references, API docs, REST endpoints |
| `AGENT.md` | AI agent (this file) | Codebase map, dev rules, documentation obligations |
| `templates/help.php` | Site admin (in-plugin) | End-user help page: form usage, settings, template overriding, developer integration |

## Development Guidelines

### Code Style
- WordPress coding standards
- Namespaced under `Mawiblah\`
- Static methods for utility classes
- Minimal comments (code should be self-explanatory)

### Branching (Gitflow)

- **`main`** — production only. Never commit directly. Receives merges from `develop` at release time.
- **`develop`** — integration branch. All feature/fix branches merge here via PR.
- **Feature/fix branches** — branch off `develop`, merge back to `develop`.
  - Name branches descriptively: `feature/add-unsubscribe-header`, `fix/test-emails-marked-as-sent`, etc.
  - No `1.0.x` version prefix needed — versions are assigned at release time, not per branch.
- **Releasing** — when `develop` is stable: bump version in `mawiblah.php`, `readme.txt`, `README.md`; update changelogs; then PR `develop` → `main`.

### Commit Messages
- Do **not** add `Co-Authored-By: Claude` or any AI attribution line to commit messages.
- Claude usage may be noted in `README.md` if appropriate, but not in individual commits.

### Making Changes
- **Surgical modifications** — change as few lines as possible
- Only update counters during actual campaign sends (not tests)
- Maintain backward compatibility
- Don't break existing functionality
- Validate changes don't affect unrelated features

### Tests

`composer test` runs the PHPUnit integration suite against a wp-phpunit environment. It needs
PHPUnit **9.x** — WordPress's own `WP_UnitTestCase` calls
`PHPUnit\Util\Test::parseTestMethodAnnotations()`, which PHPUnit 10 removed — plus
`yoast/phpunit-polyfills`, which the WP test bootstrap requires and does not ship. Both are
pinned in `require-dev`; do not raise PHPUnit past `^9.6` without checking wp-phpunit first.

| File | Covers |
|---|---|
| `tests/Integration/CampaignTest.php` | Campaign workflow, counters, placeholder filling |
| `tests/Integration/EmailTemplateTest.php` | Shipped templates: discovery, full render with no variable left behind, faked send via `pre_wp_mail` |
| `tests/Integration/SchedulerRerenderTest.php` | Recurring schedules release the locked template copy; one-off and `rerender_on_recurring=0` keep it |
| `tests/Integration/SubscriberTest.php` | Subscriber CRUD, hashes, audience shape |
| `tests/Integration/SubscriptionFormTest.php` | Subscription form REST endpoint |
| `tests/Integration/ClickTrackingTest.php` | Click tracking counters |

`Templates::getTemplateByNameViaRest()` posts to the site's own REST route, which has no HTTP
server behind it under PHPUnit. The template tests answer that loopback in-process through a
`pre_http_request` filter, so the real `lockTemplate()` path is still what runs.

### Versioning & Documentation Checklist

Run through this checklist whenever a version-worthy change is complete (new feature, fix, or behaviour change):

1. **`mawiblah.php`** — bump `Version:` in the plugin header and `define('MAWIBLAH_VERSION', ...)`.
2. **`README.md`** — add `### --- X.Y.Z ---` block at the top of the changelog with bullet points for every change.
3. **`readme.txt`** — update `Stable tag: X.Y.Z` and add `= X.Y.Z =` block to the changelog.
4. **`DOCUMENTATION.md`** — update any section that describes changed behaviour:
   - Flow diagrams (mermaid) if a flow changed
   - REST endpoint tables if an endpoint was added or changed
   - Field/counter reference tables if new fields were added
   - API function list if public methods were added or renamed
5. **`templates/help.php`** — update the in-plugin Help page if the change affects anything an admin configures or embeds (shortcode attributes, block settings, settings fields, template overriding, developer hooks).

**What belongs in each changelog:**
- `README.md` — full human-readable description, including context ("why" not just "what")
- `readme.txt` — concise WordPress.org-style bullets (one line per item)
- `DOCUMENTATION.md` — no changelog; keep it current and accurate, not historical

**Do not** add session-log "Recent Changes" sections to this file. The git log and changelog files are the authoritative history.

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
