=== Mawiblah ===
Contributors: lauzis
Tags: email, newsletter, marketing, mailchimp alternative, subscribers
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.43
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

= 1.0.43 =
*   New: `email_templates/mawiblah-all-variables-test.html` — a diagnostic letter carrying every supported variable once (all built-in `mawiblah_` shortcodes and all six placeholders), each named by a marker so an unreplaced one identifies itself. It appears in the campaign template selector.
*   Note: the default newsletter template is unchanged — it already renders the latest 3 articles — and is now covered by tests that fail if that stops being true.
*   New: PHPUnit coverage for the shipped templates: discovery, a full render with no variable left behind, and a faked send that inspects the letter `wp_mail()` was handed, headers included.
*   New: PHPUnit coverage for recurring schedules — a weekly or monthly send re-renders with content published since the last send; a one-off send, or `rerender_on_recurring` off, keeps its snapshot.
*   New: Tests page scenario "Default Email Templates" runs the same checks in the browser.
*   Fixed: a subscriber in no audience crashed `Subscribers::isTester()` and with it the `/send-email` route — `$subscriber->audiences` is now always a list.
*   Fixed: `composer test` could not run at all. PHPUnit is pinned to `^9.6` and the required PHPUnit Polyfills are now declared.

= 1.0.42 =
*   Removed: `[gdlnks_newsletter_title]` and `[gdlnks_newsletter_content]`. They were never shortcodes — just two hardcoded `str_replace` calls named after one site's theme. The bundled template does not use them, so a default install is unaffected.
*   Fixed: `[mawiblah_title]` and `[mawiblah_content]` now render the campaign being sent instead of falling back to the month-name default, since an email template is rendered outside the WordPress loop.
*   Migration: a custom template still using the old tags will print them literally — replace them with `[mawiblah_title]` and `[mawiblah_content]`.
*   Docs: any shortcode registered in WordPress works in an email template and in the campaign content; the built-in `mawiblah_` ones are examples, not a fixed list.

= 1.0.38 =
*   Fixed: subscriber audiences were public. `register_taxonomy()` defaults `public` to true and the arguments never said otherwise, so every audience had a front-end archive at `/subscriber-category/<slug>/` — a page describing how the site segments the people who signed up to it. An SEO plugin was duly listing nine of them in the sitemap. The taxonomy is now `public => false` with no rewrite; it stays exactly as visible in wp-admin as it was.
*   Anyone upgrading should visit Settings → Permalinks once, or otherwise flush rewrite rules, to drop the old `/subscriber-category/` routes.

= 1.0.37 =
*   New: everything a subscriber reads is translated into Latvian, Russian and Spanish — the subscription form and its confirmation mail, the whole unsubscribe flow, and the headings the newsletter shortcode writes into a campaign. 31 strings, carried in the plugin's own languages/ folder under the text domain it already loads, so no translation plugin is required. The admin screens stay in English.

= 1.0.36 =
*   New: "Send a test message" button beside the Slack webhook field. It posts to whatever is in the field, saved or not, waits for Slack's answer and reports it — ordinary log traffic is fire-and-forget, so a bad webhook otherwise fails silently.
*   New: the Tests page's Logging scenario now also posts to Slack when a webhook is configured, and says so when one is not. It runs whether or not file logging is on, since errors reach Slack either way.
*   Changed: scheduler and send logging says what happened rather than which function ran. The cron tick logs "Scheduled check started" and a matching "Scheduled check finished" with how many schedulers were seen, how many fired and how long it took — so a check that died halfway no longer looks like one that found no work. A scheduled send logs "Scheduled campaign started" with the campaign name and schedule type.
*   Changed: batch logging carries the campaign name and its progress. "Batch started" records what had already been sent and failed; "Batch finished" (previously "Batch complete") adds how long the batch took; "Campaign finished" carries the final counts.
*   Changed: the bundled shared library (wp-plugin-packages) updated to 1.16.0.

= 1.0.35 =
*   New: Log entries can be sent to Slack. Fill in an incoming webhook URL under Logging and pick whether Slack gets errors only (the default) or every entry. Errors are posted even with file logging disabled — a send that failed at 3am is not something anybody finds by opening the log later.
*   Note: sending is fire-and-forget, so a campaign send never waits on Slack; a webhook Slack rejects therefore fails quietly. Only https:// URLs are used, since the webhook URL is itself a credential.
*   Note: "Every log entry" means one HTTP request per entry and Slack allows roughly one message a second per webhook — leave it on Errors only during a campaign send.
*   Changed: the bundled shared library (wp-plugin-packages) updated to 1.15.0.

= 1.0.29 =
*   New: `send_condition_shortcode` per-campaign field — enter a shortcode name and the scheduler will call it (with `campaign_id` attribute) before each scheduled send. Empty output skips the send and logs the reason; non-empty output proceeds normally.
*   New: Built-in example shortcode `mawiblah_new_posts_since_last_sent` — returns non-empty if any posts were published since the campaign's last send, otherwise returns empty to block the send.

= 1.0.28 =
*   New: `rerender_on_recurring` per-campaign setting — when enabled (default on), the locked template is cleared before each recurring (weekly/monthly) send so shortcodes, WP queries, and dynamic content are re-evaluated fresh.

= 1.0.27 =
*   Fixed: Division by zero in getConversionStatsForCampaign() when no emails have been sent yet (PHP 8 DivisionByZeroError).
*   Fixed: Import session key contained uppercase chars which sanitize_key() lowercased on read, causing every import confirmation to fail with "session expired".
*   Fixed: CronSend lockTemplate failure now calls backgroundSendStop() so the campaign is not left permanently stuck as "running".
*   Fixed: Background send failure path now calls sentEmailFailed() instead of sentEmail(), correctly updating fail count, error meta, and Failing Email audience.
*   Fixed: Scheduler skips in-progress send but now also advances next_send for recurring schedules, preventing an immediate re-fire after the current send finishes.
*   Fixed: Templates::getTemplateByNameViaRest() now fails closed on non-200 or malformed REST responses (returns false instead of partial data).
*   Fixed: backgroundProgress REST route returns a normalized payload shape on campaign-not-found instead of {error: "Not found"}.
*   Fixed: Scheduler::add() now guards against wp_insert_post() returning 0 (not only WP_Error).
*   Fixed: AJAX URL in background-progress.php now uses esc_url_raw() to prevent & being HTML-encoded and breaking the nonce query string.
*   Fixed: Import error display now escapes each message individually so <br> separators render as line breaks.
*   Fixed: Duplicate array keys for open-rate stats in campaign-conversion and campaign-raw templates (second entry silently overwrote the first).
*   Fixed: Typo "beeing" corrected to "being" on the unsubscribe confirmation page.
*   Fixed: Open timestamps in subscriber detail now use date_i18n() to respect site timezone and locale.
*   Fixed: MAWIBLAH_VERSION no longer appends time() on every request, restoring browser caching of CSS/JS assets.
*   Fixed: DOCUMENTATION.md flowchart loop-back now points to D2 (per-subscriber processing) rather than D (pre-fetch), matching the actual JS flow.

= 1.0.26 =
*   Fixed: Background send via WP Cron failed silently when no user is logged in — template fetch now bypasses the authenticated REST loopback and reads the file directly in cron context.
*   Fixed: Fatal error "Cannot access protected property WPMailSMTP\MailCatcherV6::$exceptions" — replaced phpmailer_init exceptions hook with the wp_mail_failed hook in both manual and background send paths.
*   Fixed: Scheduler could reset a campaign mid-send if the previous background send was still in progress. Scheduler now skips the occurrence if backgroundStarted is set.
*   New: Logs::addError() — always writes to PHP error_log and the Mawiblah log file regardless of whether debug mode is enabled. Used for all critical failures.
*   New: Shutdown handler in CronSend::processBatch() captures PHP fatal errors and logs them.
*   Improved: Extensive logging added throughout the background send pipeline (batch start/finish, all early-return paths, template load success/failure, REST errors).
*   Improved: Scheduler create/edit form — past dates are disabled in the date picker, current date and time shown in field labels, and a live preview of the next 3 send dates is displayed below the form.
*   Improved: Help page — added explanation of why the built-in WP Cron slows down page loads; cron command examples now use the real site URL and WordPress path.
*   Changed: Admin menu reordered — last items are now Import, Logs, Tests, Settings.

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
