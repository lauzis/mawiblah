=== Mawiblah ===
Contributors: lauzis
Tags: email, newsletter, marketing, mailchimp alternative, subscribers
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.25
Requires PHP: 8.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

Fff-ine, will build my own mailchimp... with blackjack and hookers.

== Description ==

MAWIBLAH is a WordPress plugin that sends out emails to the list of subscribers. It is a "weekend project" designed for small projects with tight budgets or no income streams, serving as a free alternative to paid services like Mailchimp for lists up to ~2k subscribers.

It is not suited for sending out 100k emails. It sends "individual" emails via WordPress `wp_mail` (or SMTP plugin), which is slower but reduces server load.

**Key Features:**

*   Sends out emails to the email list.
*   Audience management via WordPress taxonomy (manual or Gravity Forms).
*   Shortcode-based email template generation.
*   Unsubscribe functionality (including import from Mailchimp).
*   Tracks campaign clicks (total and unique per session).
*   Tracks click timing for optimization.
*   Action logging.
*   Detailed statistics dashboard (Subscriber growth, Activity rating, etc.).
*   Native subscription form (shortcode & Gutenberg block) with honeypot + optional reCAPTCHA v3 spam protection.
*   RFC 8058 List-Unsubscribe headers on campaign emails for one-click unsubscribe in Gmail and other modern mail clients.

**Who is it for?**

Ideal for technical users or small projects with limited budgets who need full control and no recurring cost.

**MAWIBLAH vs Mailchimp (Free Tier)**

*   **Price:** Free vs Free (up to 500 subs)
*   **Subscriber Limit:** Unlimited vs 500
*   **Email Sending:** One-by-one vs Batch
*   **Customization:** Full code access vs Closed source

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/mawiblah` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Navigate to the Mawiblah dashboard widget or settings page to configure.

== Frequently Asked Questions ==

= Is this plugin free? =

Yes, this is a free plugin, primarily built for personal use but shared for others who might find it useful.

= Can I send 100k emails with this? =

Technically yes, but it is not recommended. The plugin sends emails individually, which will take a very long time for large lists.

== Screenshots ==

1. Statistics dashboard
2. Dashboard view
3. Testing newsletter email
4. Action page
5. Do not disturb settings
6. Settings output in the test page
7. Settings page
8. MVP version

== Changelog ==

= 1.0.25 =
*   Removed: Actions admin page removed. Clear Logs is on the Logs page; Gravity Forms sync is in the Import section. Closes #81.

= 1.0.24 =
*   Fixed: migrateTo1021() now processes log posts in batches of 200 instead of all at once, preventing PHP timeouts on sites with large log histories. Remaining posts are migrated via WP-Cron. Fixes #80.

= 1.0.23 =
*   Improved: Test-mode send now pre-fetches a capped subscriber list — all testers first, then up to 100 random non-testers — instead of iterating every subscriber in the campaign audiences. Closes #25.

= 1.0.22 =
*   Fixed: Test sends and real sends now use separate meta keys (sent_test_{id} vs sent_{id}), so testers are no longer skipped when the real campaign runs. testReset() clears test-send flags when a retest is triggered. Fixes #43.

= 1.0.21 =
*   Improved: Test page redesigned with WordPress admin UI — postbox layout, checkbox scenario selection, single "Run Tests" button.
*   Improved: Help page rebuilt with WordPress admin UI — postbox cards, native WP tables, notice callouts.
*   Improved: Campaigns, Subscribers, and Logs post types moved under the Mawiblah admin menu with full sub-item navigation.
*   Changed: Logging switched from custom post type to daily log files (mawiblah-YYYY-MM-DD.log) in the uploads directory.
*   Migration: Existing log post type entries are exported to daily files and deleted from the database on first load after update.
*   Fixed: Subscription form test scenario incorrectly accessed `WP_REST_Response` as an array; now unwrapped correctly.
*   Fixed: Click tracking test scenario cleared the PHP session before starting it, causing session store to be restored and first-visit counters to be off by one.


= 1.0.20 =
*   New: "Failing Email" system audience — subscribers are automatically moved here after N failed sends (configurable threshold, default 3) and skipped in all future campaigns.
*   New: Mailer error reason captured via PHPMailer exceptions and stored per subscriber/campaign for diagnostics.

= 1.0.19 =
*   New: Block test start when campaign has no testers — shows a clear error and links back to the campaign edit page.

= 1.0.18 =
*   New: List-Unsubscribe and List-Unsubscribe-Post headers on campaign emails (RFC 8058 one-click unsubscribe).
*   New: GET|POST /wp-json/mawiblah/v1/unsubscribe endpoint — POST for mail-client one-click, GET for human redirect.
*   Fixed: Content-Type: text/html header was missing from campaign emails.

= 1.0.17 =
*   New: Native subscription form via `[mawiblah_subscribe_form]` shortcode and Gutenberg block.
*   New: Multiple audience support per form.
*   New: audienceHash — stable identifier for audiences (consistent with subscriberHash / campaignHash).
*   New: Honeypot spam protection (always active).
*   New: Optional reCAPTCHA v3 support with Settings page integration.
*   New: Re-subscribe confirmation flow for previously unsubscribed users.
*   New: PHPUnit integration test suite and Jest frontend test suite.
*   Improved: Test page refactored — button-triggered scenarios, no auto-run.

= 1.0.16 =
*   **Code Quality & Naming Consistency:** Major refactoring for better maintainability and clarity.
*   Renamed functions and meta keys from `*Id` to `*Hash` (e.g., `subscriberHash`) for security.
*   Added automatic migration `migrateTo1016()` for existing data.
*   Updated email template placeholders to `{campaignHash}`, `{subscriberHash}`, `{email}`.

= 1.0.15 =
*   **New Statistics Dashboard:** Added comprehensive activity tracking (Subscriber Growth, Unsubscribe Growth, Activity Rating).
*   **New Dashboard Widget:** Added "Activity Rating" widget.
*   **Improvements & Fixes:** Fixed percentage calculations, CSS conflicts, PHP warnings, and XSS vulnerabilities.
*   **Major:** Migrated audience system from Gravity Forms to WordPress native taxonomy.

= 1.0.14 =
*   Styling fixes.
*   Date format update.
*   Back to list after creating campaign.
*   Routing and layout updates.

= 1.0.13 =
*   Test and approval implemented.
*   Moved email sending to an ajax async process.
*   Implemented force import from gravity forms.

= 1.0.12 =
*   Added an action page with the ability to clear logs and manually sync entries/emails with Gravity Forms.

= 1.0.11 =
*   Added a meta-field to the subscribers' post-type for the last interaction.
*   Added a meta-settings field to control the time between emails to the same subscriber.

= 1.0.10 =
*   Implemented a setting to skip actual email sending for testing/debugging purposes.
*   Displayed settings output on the test page.

= 1.0.9 =
*   Introduced a dedicated settings page in the admin interface.
*   Added options to control email intervals and enable debugging with IP restrictions.
*   Added the ability to toggle database logging.

= 1.0.8 =
*   Saved click time for statistics.
*   Fixed a logical issue with "already sent" flagging.

= 1.0.7 =
*   Updated logging mechanisms.

= 1.0.6 =
*   Fixed nonce issues for AJAX requests.

= 1.0.5 =
*   Fixed an issue where two messages were sent simultaneously during unsubscribe.

= 1.0.4 =
*   Fixed an issue with registering visits from link statistics.

= 1.0.3 =
*   Removed debug code.
*   Fixed WPML translation initialization issue.

= 1.0.2 =
*   Added extra data to log content.
*   Fixed duplicate sending to case-insensitive emails.

= 1.0.1 =
*   Added minimal action logger.

= 1.0.0 =
*   Initial MVP.
