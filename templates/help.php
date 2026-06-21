<?php defined('ABSPATH') || exit; ?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Mawiblah — Help', 'mawiblah'); ?></h1>
    <hr class="wp-header-end">

    <div class="metabox-holder">

    <!-- ── Subscription Form ─────────────────────────────────────────────── -->
    <div class="postbox">
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
    <div class="postbox">
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
    <div class="postbox">
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
    <div class="postbox">
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
                        <td><?php esc_html_e('Disabled by default. Enable DB log to write debug entries to the database log.', 'mawiblah'); ?></td>
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

    </div><!-- /.metabox-holder -->
</div>
