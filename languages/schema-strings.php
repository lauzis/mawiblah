<?php
/**
 * Translation manifest — GENERATED, do not edit.
 *
 * Produced by bin/schema-i18n from the settings schema JSON. Exists so
 * `wp i18n make-pot` can see strings that live in JSON rather than in PHP.
 * Never loaded at runtime.
 *
 * Regenerate with:
 *   bin/schema-i18n --domain=mawiblah --out=languages/schema-strings.php config/settings.json
 */

return;

__( '@callback:bounce_mailbox_test', 'mawiblah' );
__( '@callback:logs_slack_test', 'mawiblah' );
__( 'Bounced Emails', 'mawiblah' );
__( 'Can disable actual email sending', 'mawiblah' );
__( 'Check the mailbox every hour', 'mawiblah' );
__( 'Disabled', 'mawiblah' );
__( 'Disabled — honeypot only', 'mawiblah' );
__( 'Dont send emails', 'mawiblah' );
__( 'Embeds a 1×1 tracking pixel in outgoing emails. Requires server-side configuration (nginx must serve the pixel URL and execute PHP). When enabled, unique opens are recorded per subscriber per campaign. Note: inform subscribers in your privacy policy.', 'mawiblah' );
__( 'Enable - file log', 'mawiblah' );
__( 'Enable file-based logging to record plugin actions to daily log files. Logs are stored in {uploads}/mawiblah/logs-{random}/mawiblah-YYYY-MM-DD.log and can be viewed under Mawiblah → Logs.', 'mawiblah' );
__( 'Enable open tracking', 'mawiblah' );
__( 'Enabled', 'mawiblah' );
__( 'Encryption', 'mawiblah' );
__( 'Errors are posted even when file logging is off. Every log entry means one HTTP request per entry, and Slack rate-limits a webhook to about one message per second — only worth it while chasing a specific problem, not during a campaign send.', 'mawiblah' );
__( 'Errors only', 'mawiblah' );
__( 'Every 1 hour', 'mawiblah' );
__( 'Every 1 minute', 'mawiblah' );
__( 'Every 10 minutes', 'mawiblah' );
__( 'Every 12 hours', 'mawiblah' );
__( 'Every 15 minutes', 'mawiblah' );
__( 'Every 2 hours', 'mawiblah' );
__( 'Every 30 minutes', 'mawiblah' );
__( 'Every 4 hours', 'mawiblah' );
__( 'Every 5 minutes', 'mawiblah' );
__( 'Every 8 hours', 'mawiblah' );
__( 'Every log entry', 'mawiblah' );
__( 'Failing Email', 'mawiblah' );
__( 'Failure threshold (number of failed sends before marking as Failing Email)', 'mawiblah' );
__( 'File logging', 'mawiblah' );
__( 'Folder', 'mawiblah' );
__( 'How often WP Cron checks whether a scheduled campaign is due. Lower values mean sends fire closer to the configured time, but generate more cron runs.', 'mawiblah' );
__( 'How often a subscriber may be e-mailed, how fast a campaign goes out, and how often scheduled campaigns are checked.', 'mawiblah' );
__( 'IMAP server', 'mawiblah' );
__( 'If sending to a subscriber fails this many times across all campaigns, they are automatically added to the \'Failing Email\' audience and skipped in future sends.', 'mawiblah' );
__( 'Logging', 'mawiblah' );
__( 'Messages read per check', 'mawiblah' );
__( 'None', 'mawiblah' );
__( 'Once per day', 'mawiblah' );
__( 'Only messages whose headers say they are a delivery report are downloaded in full. A mailbox with more waiting is worked through in further runs a minute apart.', 'mawiblah' );
__( 'Open Tracking', 'mawiblah' );
__( 'Optional Google reCAPTCHA protection for the subscription form. v2 is the "I am not a robot" checkbox; v3 is invisible and scores the visit, letting it through from 0.5 up. The key pairs are not interchangeable, so the version chosen here has to match the keys below. Leave disabled to rely on the honeypot alone. Get your keys at https://www.google.com/recaptcha/admin/', 'mawiblah' );
__( 'Optional: an incoming webhook URL (https://hooks.slack.com/services/...) that log entries are also posted to. Leave empty to send nothing to Slack. Anyone holding this URL can post to the channel, so treat it as a credential. The test button posts to whatever is in this field, saved or not.', 'mawiblah' );
__( 'Password', 'mawiblah' );
__( 'Port', 'mawiblah' );
__( 'Reads bounce reports from the mailbox they are delivered to — usually the From address of your campaigns — and lists them under Mawiblah → Bounced Emails. Nothing changes, neither subscribers nor the mailbox, until a bounce is approved there. The password is stored encrypted with a key from wp-config.php; define MAWIBLAH_BOUNCE_PASS in wp-config.php to keep it out of the database altogether.', 'mawiblah' );
__( 'Restrict output by ip (comma separated)', 'mawiblah' );
__( 'SSL/TLS (usually port 993)', 'mawiblah' );
__( 'STARTTLS (usually port 143)', 'mawiblah' );
__( 'Scheduler check interval', 'mawiblah' );
__( 'Secret Key (private, never expose publicly)', 'mawiblah' );
__( 'Send emails', 'mawiblah' );
__( 'Send to Slack', 'mawiblah' );
__( 'Sending', 'mawiblah' );
__( 'Set the time between emails. If to send out with some delay.', 'mawiblah' );
__( 'Settings for background campaign sending via WP Cron. Campaigns can be sent in the background so you can close the browser tab.', 'mawiblah' );
__( 'Site Key (public)', 'mawiblah' );
__( 'Slack webhook URL', 'mawiblah' );
__( 'Stored encrypted. Leave the field as it is to keep the saved password.', 'mawiblah' );
__( 'Subscribers per cron batch', 'mawiblah' );
__( 'Subscription Form', 'mawiblah' );
__( 'The threshold/delta between emails to one subcriber. If the threshold is not reached, the email will not be sent. We dont want to bother subscribers that did recieve email aready lately.', 'mawiblah' );
__( 'Time between emails (in seconds)', 'mawiblah' );
__( 'Time between the subscriber is contacred again (in seconds)', 'mawiblah' );
__( 'Username', 'mawiblah' );
__( 'reCAPTCHA', 'mawiblah' );
__( 'v2 — the "I am not a robot" checkbox', 'mawiblah' );
__( 'v3 — invisible, scores the visit', 'mawiblah' );
