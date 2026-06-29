<?php defined('ABSPATH') || exit; ?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Mawiblah — Help', 'mawiblah'); ?></h1>
    <hr class="wp-header-end">

    <div style="background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin-bottom:20px;max-width:600px;">
        <strong><?php esc_html_e('Contents', 'mawiblah'); ?></strong>
        <ol style="margin:8px 0 0;padding-left:1.4em;">
            <li><a href="#help-subscription-form"><?php esc_html_e('Subscription Form', 'mawiblah'); ?></a></li>
            <li><a href="#help-developer-integration"><?php esc_html_e('Developer Integration', 'mawiblah'); ?></a></li>
            <li><a href="#help-template-overriding"><?php esc_html_e('Template Overriding', 'mawiblah'); ?></a></li>
            <li><a href="#help-settings-reference"><?php esc_html_e('Settings Reference', 'mawiblah'); ?></a></li>
            <li><a href="#help-cron-setup"><?php esc_html_e('Background Send & Real Cron Setup', 'mawiblah'); ?></a></li>
            <li><a href="#help-settings-background"><?php esc_html_e('Settings Reference — Background Send & Open Tracking', 'mawiblah'); ?></a></li>
            <li><a href="#help-scheduler"><?php esc_html_e('Campaign Scheduler', 'mawiblah'); ?></a></li>
        </ol>
    </div>

    <div class="metabox-holder">

    <!-- ── Subscription Form ─────────────────────────────────────────────── -->
    <div id="help-subscription-form" class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Subscription Form', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">
            <p><?php esc_html_e('Add a subscription form anywhere on your site using a shortcode or a Gutenberg block.', 'mawiblah'); ?></p>

            <h3><?php esc_html_e('Shortcode', 'mawiblah'); ?></h3>
            <p><?php esc_html_e('Paste the shortcode into any post, page or widget area:', 'mawiblah'); ?></p>
            <pre><code>[mawiblah_subscribe_form]</code></pre>

            <p><?php esc_html_e('All attributes are optional:', 'mawiblah'); ?></p>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th style="width:18%"><?php esc_html_e('Attribute', 'mawiblah'); ?></th>
                        <th style="width:22%"><?php esc_html_e('Default', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>audiences</code></td>
                        <td><em><?php esc_html_e('(none)', 'mawiblah'); ?></em></td>
                        <td><?php esc_html_e('Comma-separated audience hashes. Subscribers are added to these audiences. Omit to subscribe without assigning an audience.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><code>label</code></td>
                        <td><code>Email</code></td>
                        <td><?php esc_html_e('Label text shown above the email input.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><code>placeholder</code></td>
                        <td><code>your@email.com</code></td>
                        <td><?php esc_html_e('Placeholder text inside the email input.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><code>button</code></td>
                        <td><code>Subscribe</code></td>
                        <td><?php esc_html_e('Submit button label.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><code>success</code></td>
                        <td><em><?php esc_html_e('(server message)', 'mawiblah'); ?></em></td>
                        <td><?php esc_html_e('Custom success message shown after a successful submission. Leave empty to use the default server message.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><code>error</code></td>
                        <td><em><?php esc_html_e('(server message)', 'mawiblah'); ?></em></td>
                        <td><?php esc_html_e('Custom error message shown when submission fails. Leave empty to use the default server message.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>

            <p style="margin-top:16px;"><?php esc_html_e('Example with all attributes:', 'mawiblah'); ?></p>
            <pre><code>[mawiblah_subscribe_form audiences="abc123,def456" label="Your email" placeholder="name@example.com" button="Sign me up" success="Thanks! Check your inbox." error="Something went wrong, please try again."]</code></pre>

            <div class="notice notice-info inline" style="margin:12px 0 0;">
                <p>
                    <?php esc_html_e('Audience hashes can be found in', 'mawiblah'); ?>
                    <strong><?php esc_html_e('Subscribers → Audiences', 'mawiblah'); ?></strong>
                    — <?php esc_html_e('each term shows its hash in the term meta.', 'mawiblah'); ?>
                </p>
            </div>

            <h3 style="margin-top:24px;"><?php esc_html_e('Gutenberg Block', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('Search for', 'mawiblah'); ?>
                <strong><?php esc_html_e('Mawiblah Subscribe', 'mawiblah'); ?></strong>
                <?php esc_html_e('in the block inserter. The block renders the same form as the shortcode.', 'mawiblah'); ?>
            </p>
            <p><?php esc_html_e('Block settings (Inspector Controls sidebar):', 'mawiblah'); ?></p>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th style="width:30%"><?php esc_html_e('Setting', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Audiences', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Checkboxes — select one or more audiences. Subscribers are added to the checked audiences on submit.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Field label', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Overrides the default "Email" label.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Input placeholder', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Overrides the default "your@email.com" placeholder.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Button text', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Overrides the default "Subscribe" button label.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Success message', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Custom message shown after a successful submission. Leave empty to use the server default.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Error message', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Custom message shown when submission fails. Leave empty to use the server default.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Developer Integration ─────────────────────────────────────────── -->
    <div id="help-developer-integration" class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Developer Integration', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">
            <p>
                <?php esc_html_e('Use the', 'mawiblah'); ?>
                <code>mawiblah_subscribe()</code>
                <?php esc_html_e('function to subscribe an email address from any PHP code — theme functions, plugin callbacks, WP-CLI scripts, etc.', 'mawiblah'); ?>
            </p>

            <h3><?php esc_html_e('Function signature', 'mawiblah'); ?></h3>
            <pre><code>mawiblah_subscribe( string $email, array $audienceHashes = [] ): array</code></pre>
            <p><?php esc_html_e('Returns an array with:', 'mawiblah'); ?></p>
            <ul style="list-style:disc;padding-left:1.5em;margin-bottom:16px;">
                <li><code>status</code> — <code>'ok'</code> <?php esc_html_e('or', 'mawiblah'); ?> <code>'error'</code></li>
                <li><code>message</code> — <?php esc_html_e('a human-readable description of the result', 'mawiblah'); ?></li>
            </ul>

            <h3><?php esc_html_e('Basic example', 'mawiblah'); ?></h3>
            <pre><code>$result = mawiblah_subscribe( 'user@example.com', [ 'abc123', 'def456' ] );

if ( $result['status'] === 'ok' ) {
    // subscribed (or resubscription email sent)
} else {
    // $result['message'] explains what went wrong
}</code></pre>

            <h3><?php esc_html_e('Gravity Forms example', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('Subscribe the submitter of a specific form (replace', 'mawiblah'); ?>
                <code>123</code>
                <?php esc_html_e('with your form ID and', 'mawiblah'); ?>
                <code>abc123</code>
                <?php esc_html_e('with your audience hash):', 'mawiblah'); ?>
            </p>
            <pre><code>add_action( 'gform_after_submission', function ( $entry, $form ) {

    if ( (int) $form['id'] !== 123 ) {
        return;
    }

    $email = '';
    foreach ( $form['fields'] as $field ) {
        if ( $field->type === 'email' ) {
            $email = $entry[ $field->id ] ?? '';
            break;
        }
    }

    if ( $email ) {
        mawiblah_subscribe( $email, [ 'abc123' ] );
    }

}, 10, 2 );</code></pre>

            <h3><?php esc_html_e('Reacting to a subscription', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('The', 'mawiblah'); ?>
                <code>mawiblah_subscribed</code>
                <?php esc_html_e('action fires after every successful subscription (from the form, from code, or from any integration):', 'mawiblah'); ?>
            </p>
            <pre><code>add_action( 'mawiblah_subscribed', function ( $email, $audienceHashes, $subscriber ) {
    // $email          — the subscribed email address
    // $audienceHashes — array of audience hashes that were requested
    // $subscriber     — the subscriber object (has ->id, ->subscriberHash, etc.)
}, 10, 3 );</code></pre>
        </div>
    </div>

    <!-- ── Template Overriding ────────────────────────────────────────────── -->
    <div id="help-template-overriding" class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Template Overriding', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">
            <p>
                <?php esc_html_e('Mawiblah supports two independent template override systems — one for email HTML templates and one for PHP front-end partials. Both work by placing a file in your theme at the correct path; the plugin checks the theme first and falls back to its own copy.', 'mawiblah'); ?>
            </p>

            <h3><?php esc_html_e('Email templates (HTML)', 'mawiblah'); ?></h3>
            <p><?php esc_html_e('To override an email template, copy the file from the plugin into your theme:', 'mawiblah'); ?></p>
            <table class="wp-list-table widefat fixed striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th style="width:45%"><?php esc_html_e('Plugin file', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Override path in your theme', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>email_templates/mawiblah-newsletter-template.html</code></td>
                        <td><code>your-theme/mawiblah/email_templates/mawiblah-newsletter-template.html</code></td>
                    </tr>
                </tbody>
            </table>
            <div class="notice notice-info inline" style="margin:12px 0 0;">
                <p><?php esc_html_e('Lookup order: child theme → parent theme → plugin. The first file found is used. You can also add entirely new templates by placing additional HTML files in', 'mawiblah'); ?> <code>your-theme/mawiblah/email_templates/</code> — <?php esc_html_e('they will appear in the template selector when creating a campaign.', 'mawiblah'); ?></p>
            </div>

            <h3 style="margin-top:24px;"><?php esc_html_e('PHP partials', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('Partials loaded via', 'mawiblah'); ?>
                <code>Templates::loadTemplate()</code>
                <?php esc_html_e('can be overridden by placing a file in your theme at', 'mawiblah'); ?>
                <code>your-theme/mawiblah/{relative-path}</code>.
            </p>
            <table class="wp-list-table widefat fixed striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th style="width:45%"><?php esc_html_e('Relative path', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Used for', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>campaign/bar-graph.php</code></td><td><?php esc_html_e('Bar chart on the dashboard widget', 'mawiblah'); ?></td></tr>
                    <tr><td><code>campaign/edit-fields.php</code></td><td><?php esc_html_e('Campaign edit form fields', 'mawiblah'); ?></td></tr>
                    <tr><td><code>campaign/table-stats.php</code></td><td><?php esc_html_e('Stats table shared by campaign detail views', 'mawiblah'); ?></td></tr>
                    <tr><td><code>stats/styles.php</code></td><td><?php esc_html_e('Inline styles for stats charts', 'mawiblah'); ?></td></tr>
                    <tr><td><code>stats/subscriber-growth.php</code></td><td><?php esc_html_e('Subscriber growth chart', 'mawiblah'); ?></td></tr>
                    <tr><td><code>stats/unsubscribe-growth.php</code></td><td><?php esc_html_e('Unsubscribe growth chart', 'mawiblah'); ?></td></tr>
                    <tr><td><code>stats/overall-*.php</code></td><td><?php esc_html_e('Overall stat cards (sent, unique opens, rating, clicks, days, hours)', 'mawiblah'); ?></td></tr>
                    <tr><td><code>stats/last-*.php</code></td><td><?php esc_html_e('Last campaign stat cards (raw, conversion, links, days, hours)', 'mawiblah'); ?></td></tr>
                    <tr><td><code>stats/campaign-raw.php</code></td><td><?php esc_html_e('Campaign raw stats', 'mawiblah'); ?></td></tr>
                    <tr><td><code>stats/campaign-conversion.php</code></td><td><?php esc_html_e('Campaign conversion stats', 'mawiblah'); ?></td></tr>
                </tbody>
            </table>

            <p style="margin-top:12px;"><?php esc_html_e('Example — override the subscriber growth chart:', 'mawiblah'); ?></p>
            <pre><code>your-theme/
  mawiblah/
    stats/
      subscriber-growth.php   ← your custom version</code></pre>

            <h3><?php esc_html_e('Templates that cannot be overridden yet', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('The following templates use a direct', 'mawiblah'); ?>
                <code>include</code>
                <?php esc_html_e('and cannot be overridden from a theme at this time:', 'mawiblah'); ?>
            </p>
            <table class="wp-list-table widefat fixed striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th style="width:55%"><?php esc_html_e('Template', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Used for', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>subscription-form/form.php</code></td><td><?php esc_html_e('Subscription form HTML', 'mawiblah'); ?></td></tr>
                    <tr><td><code>subscription-form/resubscribe-confirm.php</code></td><td><?php esc_html_e('Re-subscribe success page', 'mawiblah'); ?></td></tr>
                    <tr><td><code>subscription-form/resubscribe-invalid.php</code></td><td><?php esc_html_e('Re-subscribe invalid token page', 'mawiblah'); ?></td></tr>
                    <tr><td><code>unsubscribe/are-you-sure.php</code></td><td><?php esc_html_e('Unsubscribe confirmation prompt', 'mawiblah'); ?></td></tr>
                    <tr><td><code>unsubscribe/unsubed.php</code></td><td><?php esc_html_e('Unsubscribe success page', 'mawiblah'); ?></td></tr>
                    <tr><td><code>unsubscribe/already-unsubed.php</code></td><td><?php esc_html_e('Already unsubscribed page', 'mawiblah'); ?></td></tr>
                    <tr><td><code>unsubscribe/not-found.php</code></td><td><?php esc_html_e('Subscriber not found page', 'mawiblah'); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Settings ──────────────────────────────────────────────────────── -->
    <div id="help-settings-reference" class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Settings Reference', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">

            <h3><?php esc_html_e('Sending', 'mawiblah'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th style="width:30%"><?php esc_html_e('Setting', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e("Don't Disturb Threshold", 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Minimum time in seconds before the same subscriber can be contacted again. Default: 2592000 (30 days).', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Time Between Emails', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Delay in seconds between each individual send during a campaign — useful for rate-limiting. Default: 1 second.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Email sending', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Set to "Don\'t send emails" to suppress all outgoing mail. All other logic runs normally — useful during development and testing.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top:24px;"><?php esc_html_e('Debug', 'mawiblah'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th style="width:30%"><?php esc_html_e('Setting', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Debug mode', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Disabled by default. Set to "Enable DB log" to activate file-based logging — entries are written to daily files at {uploads}/gae-logs/mawiblah-YYYY-MM-DD.log. Use the Actions page to view and clear log files.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Restrict output by IP', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Comma-separated IP addresses. When set, debug output is shown only to visitors from those IPs — lets you debug on a live site without exposing output to other visitors.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top:24px;"><?php esc_html_e('reCAPTCHA v3', 'mawiblah'); ?></h3>
            <p><?php esc_html_e('Optional Google reCAPTCHA v3 protection for the subscription form. When disabled, only the honeypot field is active.', 'mawiblah'); ?></p>
            <p><?php esc_html_e('To enable:', 'mawiblah'); ?></p>
            <ol style="padding-left:1.5em;">
                <li>
                    <?php
                    printf(
                        wp_kses(
                            __('Register your site at <a href="https://www.google.com/recaptcha/admin/" target="_blank" rel="noopener">google.com/recaptcha/admin</a> and choose <strong>reCAPTCHA v3</strong>.', 'mawiblah'),
                            ['a' => ['href' => [], 'target' => [], 'rel' => []], 'strong' => []]
                        )
                    );
                    ?>
                </li>
                <li><?php esc_html_e('Copy the Site Key (public) and Secret Key (private) into the plugin settings.', 'mawiblah'); ?></li>
                <li><?php esc_html_e('Set "Enable reCAPTCHA v3" to Enabled and save.', 'mawiblah'); ?></li>
            </ol>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;margin-top:12px;">
                <thead>
                    <tr>
                        <th style="width:30%"><?php esc_html_e('Setting', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Enable reCAPTCHA v3', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Only activates when Enabled AND both keys are filled in. Submissions scoring below 0.5 are rejected.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Site Key (public)', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Used in the browser to load the reCAPTCHA widget.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Secret Key (private)', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Used server-side to verify tokens with Google. Never expose this publicly.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>
            <h3 style="margin-top:24px;"><?php esc_html_e('Failing Email', 'mawiblah'); ?></h3>
            <p><?php esc_html_e('When sending to a subscriber fails repeatedly, the plugin automatically flags their address to avoid wasting future sends.', 'mawiblah'); ?></p>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;margin-top:12px;">
                <thead>
                    <tr>
                        <th style="width:30%"><?php esc_html_e('Setting', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Failure threshold', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Number of failed sends across all campaigns before a subscriber is moved to the "Failing Email" audience and skipped in future campaigns. Default: 3. Minimum: 1.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>
            <p style="margin-top:8px;"><?php esc_html_e('Each failure also stores the mailer error reason (e.g. SMTP rejection message) in the subscriber\'s meta for diagnostics. To re-enable sending, manually remove the subscriber from the "Failing Email" audience.', 'mawiblah'); ?></p>
        </div>
    </div>

    <!-- ── Background Send & Cron Setup ─────────────────────────────────── -->
    <div id="help-cron-setup" class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Background Send & Real Cron Setup', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">

            <p>
                <?php esc_html_e('Mawiblah can send campaigns in the background via WP Cron so you can close the browser tab. By default WordPress uses "poor man\'s cron" — cron jobs only run when a visitor loads a page. On low-traffic sites this means background sends can stall for minutes or hours.', 'mawiblah'); ?>
            </p>
            <p>
                <?php esc_html_e('Setting up a real system cron job on your Linux host fixes this: cron runs on a schedule regardless of site traffic, and sends proceed at full speed.', 'mawiblah'); ?>
            </p>

            <h3><?php esc_html_e('Why the built-in WP Cron slows down your site', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('WordPress\'s built-in cron works by piggybacking on real page requests. On every page load, WordPress checks whether any scheduled tasks are due — and if they are, it fires a non-blocking HTTP request back to the site to run them. This has two side effects:', 'mawiblah'); ?>
            </p>
            <ul style="list-style:disc;padding-left:1.5em;margin-bottom:12px;">
                <li>
                    <strong><?php esc_html_e('Slower page loads:', 'mawiblah'); ?></strong>
                    <?php esc_html_e('the extra loopback HTTP request is made during the page load. Even though it is non-blocking, it still opens a connection and adds overhead — especially noticeable on shared hosting where outbound connections are slow or rate-limited.', 'mawiblah'); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Unreliable timing:', 'mawiblah'); ?></strong>
                    <?php esc_html_e('tasks only run when someone visits the site. A scheduled send at 09:00 may not fire until the first visitor arrives after that time. On low-traffic sites this can mean delays of hours.', 'mawiblah'); ?>
                </li>
            </ul>
            <p>
                <?php esc_html_e('Disabling the built-in cron trigger (see Step 1 below) and replacing it with a real system cron job eliminates both problems: pages load faster and tasks fire exactly on time.', 'mawiblah'); ?>
            </p>

            <div class="notice notice-warning inline" style="margin:0 0 16px;">
                <p>
                    <strong><?php esc_html_e('Without a real cron:', 'mawiblah'); ?></strong>
                    <?php esc_html_e('background sends only advance when someone visits the site. A 2 000-subscriber campaign sending 100 per batch takes at least 20 page loads to complete.', 'mawiblah'); ?>
                </p>
            </div>

            <h3><?php esc_html_e('Step 1 — Disable WP\'s built-in cron trigger', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('Open', 'mawiblah'); ?>
                <code>wp-config.php</code>
                <?php esc_html_e('and add this line above the "That\'s all" comment:', 'mawiblah'); ?>
            </p>
            <pre><code>define( 'DISABLE_WP_CRON', true );</code></pre>
            <p><?php esc_html_e('This prevents WordPress from spawning a background HTTP request on every page load — once the real cron takes over you no longer need it.', 'mawiblah'); ?></p>

            <h3 style="margin-top:24px;"><?php esc_html_e('Step 2 — Add a system cron job', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('Run', 'mawiblah'); ?> <code>crontab -e</code>
                <?php esc_html_e('as the web server user (e.g.', 'mawiblah'); ?> <code>www-data</code>
                <?php esc_html_e(') or as root, and add one of the lines below. Every 1 minute is recommended to keep background sends moving.', 'mawiblah'); ?>
            </p>

            <?php
            $siteUrl  = esc_attr(get_site_url());
            $abspath  = esc_attr(rtrim(ABSPATH, '/'));
            $cronUrl  = esc_attr(get_site_url() . '/wp-cron.php?doing_wp_cron');
            $cronFile = esc_attr(rtrim(ABSPATH, '/') . '/wp-cron.php');
            ?>

            <h4><?php esc_html_e('Option A — WP-CLI (recommended)', 'mawiblah'); ?></h4>
            <p><?php esc_html_e('WP-CLI is the cleanest approach: no HTTP overhead, works even when the site has no visitors, and reports errors to the cron log.', 'mawiblah'); ?></p>
            <pre><code># Run WP Cron every minute via WP-CLI
* * * * * www-data wp cron event run --due-now --path=<?php echo $abspath; ?> --url=<?php echo $siteUrl; ?> >> /var/log/wp-cron.log 2>&1</code></pre>
            <p>
                <?php esc_html_e('If WP-CLI is not in the system PATH, use its full path, e.g.', 'mawiblah'); ?> <code>/usr/local/bin/wp</code>.
            </p>

            <h4 style="margin-top:16px;"><?php esc_html_e('Option B — curl', 'mawiblah'); ?></h4>
            <p><?php esc_html_e('Works without WP-CLI. Triggers cron over HTTP — requires the site to be reachable from the server itself.', 'mawiblah'); ?></p>
            <pre><code># Run WP Cron every minute via curl
* * * * * www-data curl -s "<?php echo $cronUrl; ?>" > /dev/null 2>&1</code></pre>

            <h4 style="margin-top:16px;"><?php esc_html_e('Option C — PHP CLI', 'mawiblah'); ?></h4>
            <p><?php esc_html_e('Direct PHP invocation — faster than curl, no WP-CLI needed.', 'mawiblah'); ?></p>
            <pre><code># Run WP Cron every minute via PHP
* * * * * www-data php <?php echo $cronFile; ?> > /dev/null 2>&1</code></pre>

            <h3 style="margin-top:24px;"><?php esc_html_e('Step 3 — Verify it works', 'mawiblah'); ?></h3>
            <ol style="padding-left:1.5em;">
                <li><?php esc_html_e('Wait 2–3 minutes after saving the crontab.', 'mawiblah'); ?></li>
                <li>
                    <?php esc_html_e('Start a background campaign send. The counter on the progress page should advance every minute without any browser interaction.', 'mawiblah'); ?>
                </li>
                <li>
                    <?php esc_html_e('Optional: install the', 'mawiblah'); ?>
                    <strong>WP Crontrol</strong>
                    <?php esc_html_e('plugin to inspect scheduled events and confirm', 'mawiblah'); ?>
                    <code>mawiblah_background_send</code>
                    <?php esc_html_e('appears in the event list while a background send is running.', 'mawiblah'); ?>
                </li>
            </ol>

            <h3 style="margin-top:24px;"><?php esc_html_e('Nginx — open tracking pixel', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('The open tracking pixel endpoint', 'mawiblah'); ?>
                (<code>/wp-json/mawiblah/v1/open</code>)
                <?php esc_html_e('is a standard WordPress REST API route. On a typical Nginx + PHP-FPM setup no extra configuration is needed — the existing WordPress rewrite rules handle it.', 'mawiblah'); ?>
            </p>
            <p>
                <?php esc_html_e('If your Nginx config bypasses PHP for static assets, make sure the REST API base path is not excluded. A minimal working location block:', 'mawiblah'); ?>
            </p>
            <pre><code>location /wp-json/ {
    try_files $uri $uri/ /index.php?$args;
}</code></pre>
            <p>
                <?php esc_html_e('The endpoint returns a 1×1 transparent GIF and records the open as a side effect — no separate nginx scripting is required.', 'mawiblah'); ?>
            </p>

            <h3 style="margin-top:24px;"><?php esc_html_e('Background send — how it works', 'mawiblah'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th style="width:28%"><?php esc_html_e('Phase', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('What happens', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Click "BG" button', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Campaign is marked started, backgroundStarted flag is set, first cron event is scheduled.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Cron fires', 'mawiblah'); ?></td>
                        <td>
                            <?php
                            printf(
                                /* translators: %s: batch size link */
                                esc_html__('Processes up to N subscribers (configured in Settings → Subscribers per cron batch, default 100). Applies all normal send rules: do-not-disturb threshold, unsubscribed, failing email, open tracking pixel. Reschedules itself in 60 seconds if more subscribers remain.', 'mawiblah'),
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Progress page', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Polls the REST API every 5 seconds and updates the sent/failed/skipped/unsubscribed counters live. Safe to close — the send continues regardless.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Stop button', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('Clears the backgroundStarted flag and cancels the pending cron event. Already-sent subscribers are recorded — restarting the campaign would skip them.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Completion', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('When the last subscriber is processed, the campaign is marked finished and the backgroundStarted flag is automatically cleared.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>

        </div>
    </div>

    <!-- ── Settings Reference (updated) ─────────────────────────────────── -->
    <div id="help-settings-background" class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Settings Reference — Background Send & Open Tracking', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">

            <h3><?php esc_html_e('Background Send (WP Cron)', 'mawiblah'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th style="width:30%"><?php esc_html_e('Setting', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Subscribers per cron batch', 'mawiblah'); ?></td>
                        <td><?php esc_html_e('How many subscribers are processed in a single WP Cron run. Higher = fewer cron runs needed; lower = shorter PHP execution time per run. Default: 100. Reduce if your host has a tight PHP max_execution_time.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top:24px;"><?php esc_html_e('Email Open Tracking', 'mawiblah'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th style="width:30%"><?php esc_html_e('Setting', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Enable open tracking', 'mawiblah'); ?></td>
                        <td>
                            <?php esc_html_e('When enabled, a 1×1 transparent GIF pixel is appended to every outgoing campaign email (real sends only — not test mode). The pixel URL is:', 'mawiblah'); ?>
                            <code><?php echo esc_html(rest_url('mawiblah/v1/open')); ?></code>.
                            <?php esc_html_e('Opens are recorded uniquely per subscriber per campaign with a timestamp. The campaign list shows an "Emails opened" column with count and percentage when tracking is active.', 'mawiblah'); ?>
                            <br><br>
                            <strong><?php esc_html_e('Privacy:', 'mawiblah'); ?></strong>
                            <?php esc_html_e('inform your subscribers about open tracking in your site\'s privacy policy before enabling.', 'mawiblah'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>

        </div>
    </div>

    <!-- ── Scheduler ────────────────────────────────────────────────────── -->
    <div id="help-scheduler" class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Campaign Scheduler', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">

            <p>
                <?php esc_html_e('The Scheduler lets you send campaigns automatically on a fixed schedule — once at a specific date/time, every week on a chosen day, or every month on a chosen day.', 'mawiblah'); ?>
            </p>

            <h3><?php esc_html_e('How to set up a schedule', 'mawiblah'); ?></h3>
            <ol style="padding-left:1.5em;">
                <li><?php esc_html_e('Approve the campaign through the test phase (the campaign must be test-approved before it can be scheduled).', 'mawiblah'); ?></li>
                <li>
                    <?php
                    printf(
                        wp_kses(
                            /* translators: %s: scheduler page link */
                            __('Go to <strong>Mawiblah → Scheduler</strong> and click <em>Create new schedule</em>.', 'mawiblah'),
                            ['strong' => [], 'em' => []]
                        )
                    );
                    ?>
                </li>
                <li><?php esc_html_e('Choose a schedule type, set the date/day and time, then save.', 'mawiblah'); ?></li>
                <li><?php esc_html_e('The schedule will fire automatically via WP Cron at the configured time.', 'mawiblah'); ?></li>
            </ol>

            <h3 style="margin-top:24px;"><?php esc_html_e('Schedule types', 'mawiblah'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th style="width:20%"><?php esc_html_e('Type', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Behaviour', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Once', 'mawiblah'); ?></strong></td>
                        <td><?php esc_html_e('Fires once on a specific date and time, then marks itself completed.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Weekly', 'mawiblah'); ?></strong></td>
                        <td><?php esc_html_e('Fires every week on the chosen day at the chosen time. Runs forever unless an End Date is set.', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Monthly', 'mawiblah'); ?></strong></td>
                        <td><?php esc_html_e('Fires on the chosen day of each month at the chosen time. If the month is shorter than the chosen day (e.g. February when day 31 is selected), the last available day of that month is used. Runs forever unless an End Date is set.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top:24px;"><?php esc_html_e('How recurring sends work', 'mawiblah'); ?></h3>
            <p>
                <?php esc_html_e('Each time a schedule fires, the campaign\'s send state is fully reset so that all current subscribers receive the email — regardless of whether they received a previous scheduled send of the same campaign. The live subscriber list on the campaign at fire time is used (no snapshots).', 'mawiblah'); ?>
            </p>
            <p>
                <?php esc_html_e('Normal sending rules still apply: do-not-disturb threshold, unsubscribed flag, failing-email audience, and email-sending enabled/disabled setting.', 'mawiblah'); ?>
            </p>

            <h3 style="margin-top:24px;"><?php esc_html_e('WP Cron events triggered by the Scheduler', 'mawiblah'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th style="width:40%"><?php esc_html_e('Hook', 'mawiblah'); ?></th>
                        <th><?php esc_html_e('Purpose', 'mawiblah'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>mawiblah_scheduler_check</code></td>
                        <td><?php esc_html_e('Checks all active schedules and fires a background campaign send for any whose next_send time has passed. The check frequency is configurable in Settings → Scheduler (default: every 1 hour).', 'mawiblah'); ?></td>
                    </tr>
                    <tr>
                        <td><code>mawiblah_background_send</code></td>
                        <td><?php esc_html_e('Triggered by the scheduler (and manually via the "BG" button on a campaign). Processes the next batch of subscriber emails and reschedules itself until the send is complete.', 'mawiblah'); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="notice notice-warning inline" style="margin:12px 0 0;">
                <p>
                    <strong><?php esc_html_e('Send time is approximate:', 'mawiblah'); ?></strong>
                    <?php esc_html_e('Campaigns will not fire at the exact configured time. The scheduler check runs on a fixed interval (e.g. every hour), so a send can be delayed by up to the full length of that interval depending on when the check last ran. For example, with a 1-hour check interval, a campaign set for 09:00 may not send until 09:59 if the previous check ran at 09:00:01. To minimise the delay, reduce the check interval in Settings → Scheduler.', 'mawiblah'); ?>
                </p>
            </div>

            <div class="notice notice-info inline" style="margin:12px 0 0;">
                <p>
                    <strong><?php esc_html_e('Real cron required:', 'mawiblah'); ?></strong>
                    <?php esc_html_e('The scheduler relies on WP Cron running on time. On low-traffic sites, configure a real system cron job to trigger WP Cron every minute — see the "Background Send & Real Cron Setup" section above for instructions.', 'mawiblah'); ?>
                </p>
            </div>

        </div>
    </div>

    </div><!-- /.metabox-holder -->
</div>
