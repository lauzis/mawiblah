<?php defined('ABSPATH') || exit; ?>
<div class="wrap <?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>">

    <h1><?php esc_html_e('Mawiblah — Help', 'mawiblah'); ?></h1>

    <!-- ── Subscription Form ───────────────────────────────────────────── -->
    <h2><?php esc_html_e('Subscription Form', 'mawiblah'); ?></h2>
    <p><?php esc_html_e('Add a subscription form anywhere on your site using a shortcode or a Gutenberg block.', 'mawiblah'); ?></p>

    <h3><?php esc_html_e('Shortcode', 'mawiblah'); ?></h3>
    <p><?php esc_html_e('Paste the shortcode into any post, page or widget area:', 'mawiblah'); ?></p>
    <pre><code>[mawiblah_subscribe_form]</code></pre>

    <p><?php esc_html_e('All attributes are optional:', 'mawiblah'); ?></p>
    <table class="widefat striped" style="max-width:700px">
        <thead>
            <tr>
                <th><?php esc_html_e('Attribute', 'mawiblah'); ?></th>
                <th><?php esc_html_e('Default', 'mawiblah'); ?></th>
                <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>audiences</code></td>
                <td><?php esc_html_e('(none)', 'mawiblah'); ?></td>
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
                <td><?php esc_html_e('(server message)', 'mawiblah'); ?></td>
                <td><?php esc_html_e('Custom success message shown after a successful submission. Leave empty to use the default server message.', 'mawiblah'); ?></td>
            </tr>
            <tr>
                <td><code>error</code></td>
                <td><?php esc_html_e('(server message)', 'mawiblah'); ?></td>
                <td><?php esc_html_e('Custom error message shown when submission fails. Leave empty to use the default server message.', 'mawiblah'); ?></td>
            </tr>
        </tbody>
    </table>

    <p><?php esc_html_e('Example with all attributes:', 'mawiblah'); ?></p>
    <pre><code>[mawiblah_subscribe_form audiences="abc123,def456" label="Your email" placeholder="name@example.com" button="Sign me up" success="Thanks! Check your inbox." error="Something went wrong, please try again."]</code></pre>

    <p>
        <?php esc_html_e('Audience hashes can be found in the', 'mawiblah'); ?>
        <strong><?php esc_html_e('Subscribers → Audiences', 'mawiblah'); ?></strong>
        <?php esc_html_e('taxonomy screen — each term shows its hash in the term meta.', 'mawiblah'); ?>
    </p>

    <h3><?php esc_html_e('Gutenberg Block', 'mawiblah'); ?></h3>
    <p>
        <?php esc_html_e('Search for', 'mawiblah'); ?>
        <strong><?php esc_html_e('Mawiblah Subscribe', 'mawiblah'); ?></strong>
        <?php esc_html_e('in the block inserter. The block renders the same form as the shortcode.', 'mawiblah'); ?>
    </p>
    <p><?php esc_html_e('Block settings (Inspector Controls sidebar):', 'mawiblah'); ?></p>
    <table class="widefat striped" style="max-width:700px">
        <thead>
            <tr>
                <th><?php esc_html_e('Setting', 'mawiblah'); ?></th>
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

    <hr/>

    <!-- ── Developer Integration ───────────────────────────────────────── -->
    <h2><?php esc_html_e('Developer Integration', 'mawiblah'); ?></h2>
    <p>
        <?php esc_html_e('Use the', 'mawiblah'); ?>
        <code>mawiblah_subscribe()</code>
        <?php esc_html_e('function to subscribe an email address from any PHP code — theme functions, plugin callbacks, WP-CLI scripts, etc.', 'mawiblah'); ?>
    </p>

    <h3><?php esc_html_e('Function signature', 'mawiblah'); ?></h3>
    <pre><code>mawiblah_subscribe( string $email, array $audienceHashes = [] ): array</code></pre>
    <p><?php esc_html_e('Returns an array with:', 'mawiblah'); ?></p>
    <ul style="list-style:disc;padding-left:1.5em">
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

    // Find the email field value
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

    <hr/>

    <!-- ── Template Overriding ─────────────────────────────────────────── -->
    <h2><?php esc_html_e('Template Overriding', 'mawiblah'); ?></h2>
    <p>
        <?php esc_html_e('Mawiblah supports two independent template override systems — one for email HTML templates and one for PHP front-end partials. Both work by placing a file in your theme at the correct path; the plugin checks the theme first and falls back to its own copy.', 'mawiblah'); ?>
    </p>

    <h3><?php esc_html_e('Email templates (HTML)', 'mawiblah'); ?></h3>
    <p>
        <?php esc_html_e('Email templates are HTML files used when sending campaigns. To override one, copy the file from the plugin into your theme:', 'mawiblah'); ?>
    </p>
    <table class="widefat striped" style="max-width:800px">
        <thead>
            <tr>
                <th><?php esc_html_e('Plugin file', 'mawiblah'); ?></th>
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
    <p><?php esc_html_e('Lookup order: child theme → parent theme → plugin. The first file found is used.', 'mawiblah'); ?></p>
    <p>
        <?php esc_html_e('You can also add entirely new templates by placing additional HTML files in', 'mawiblah'); ?>
        <code>your-theme/mawiblah/email_templates/</code>.
        <?php esc_html_e('They will appear in the template selector when creating a campaign.', 'mawiblah'); ?>
    </p>

    <h3><?php esc_html_e('PHP partials (stats and chart templates)', 'mawiblah'); ?></h3>
    <p>
        <?php esc_html_e('Front-end partials loaded via', 'mawiblah'); ?>
        <code>Templates::loadTemplate()</code>
        <?php esc_html_e('can be overridden the same way — place a file in your theme at', 'mawiblah'); ?>
        <code>your-theme/mawiblah/{relative-path}</code>.
    </p>
    <p><?php esc_html_e('Overridable partials:', 'mawiblah'); ?></p>
    <table class="widefat striped" style="max-width:800px">
        <thead>
            <tr>
                <th><?php esc_html_e('Relative path', 'mawiblah'); ?></th>
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
    <p><?php esc_html_e('Example — override the subscriber growth chart:', 'mawiblah'); ?></p>
    <pre><code>your-theme/
  mawiblah/
    stats/
      subscriber-growth.php   ← your custom version</code></pre>

    <h3><?php esc_html_e('Templates that cannot be overridden yet', 'mawiblah'); ?></h3>
    <p>
        <?php esc_html_e('The following templates are loaded with a direct', 'mawiblah'); ?>
        <code>include</code>
        <?php esc_html_e('and do not go through the theme lookup. They cannot be overridden from a theme at this time:', 'mawiblah'); ?>
    </p>
    <ul style="list-style:disc;padding-left:1.5em">
        <li><code>subscription-form/form.php</code> — <?php esc_html_e('the subscription form HTML', 'mawiblah'); ?></li>
        <li><code>subscription-form/resubscribe-confirm.php</code> — <?php esc_html_e('re-subscribe success page', 'mawiblah'); ?></li>
        <li><code>subscription-form/resubscribe-invalid.php</code> — <?php esc_html_e('re-subscribe invalid token page', 'mawiblah'); ?></li>
        <li><code>unsubscribe/are-you-sure.php</code> — <?php esc_html_e('unsubscribe confirmation prompt', 'mawiblah'); ?></li>
        <li><code>unsubscribe/unsubed.php</code> — <?php esc_html_e('unsubscribe success page', 'mawiblah'); ?></li>
        <li><code>unsubscribe/already-unsubed.php</code> — <?php esc_html_e('already unsubscribed page', 'mawiblah'); ?></li>
        <li><code>unsubscribe/not-found.php</code> — <?php esc_html_e('subscriber not found page', 'mawiblah'); ?></li>
    </ul>

    <hr/>

    <!-- ── Settings ────────────────────────────────────────────────────── -->
    <h2><?php esc_html_e('Settings', 'mawiblah'); ?></h2>

    <h3><?php esc_html_e("Don't Disturb Threshold", 'mawiblah'); ?></h3>
    <p>
        <?php esc_html_e('Minimum time (in seconds) that must pass before the same subscriber is contacted again. If a subscriber received an email more recently than this threshold, the send is skipped for that subscriber.', 'mawiblah'); ?>
    </p>
    <p><?php esc_html_e('Default: 2592000 (30 days).', 'mawiblah'); ?></p>

    <h3><?php esc_html_e('Time Between Emails', 'mawiblah'); ?></h3>
    <p>
        <?php esc_html_e('Delay in seconds between each individual email send during a campaign. Useful for spreading load and avoiding rate limits on the mail server.', 'mawiblah'); ?>
    </p>
    <p><?php esc_html_e('Default: 1 second.', 'mawiblah'); ?></p>

    <h3><?php esc_html_e('Debug', 'mawiblah'); ?></h3>
    <table class="widefat striped" style="max-width:700px">
        <thead>
            <tr>
                <th><?php esc_html_e('Setting', 'mawiblah'); ?></th>
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
                <td><?php esc_html_e('Comma-separated IP addresses. When set, debug output is shown only to those IPs.', 'mawiblah'); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Email sending', 'mawiblah'); ?></td>
                <td><?php esc_html_e('Set to "Don\'t send emails" to suppress all outgoing mail — useful during development and testing. No emails are sent but all other logic runs normally.', 'mawiblah'); ?></td>
            </tr>
        </tbody>
    </table>

    <h3><?php esc_html_e('reCAPTCHA v3 (Subscription Form)', 'mawiblah'); ?></h3>
    <p>
        <?php esc_html_e('Optional Google reCAPTCHA v3 protection for the subscription form. When disabled, only the honeypot field is active. To enable:', 'mawiblah'); ?>
    </p>
    <ol>
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
        <li><?php esc_html_e('Copy the Site Key (public) and Secret Key (private) into the fields below.', 'mawiblah'); ?></li>
        <li><?php esc_html_e('Set "Enable reCAPTCHA v3" to Enabled and save.', 'mawiblah'); ?></li>
    </ol>
    <table class="widefat striped" style="max-width:700px">
        <thead>
            <tr>
                <th><?php esc_html_e('Setting', 'mawiblah'); ?></th>
                <th><?php esc_html_e('Description', 'mawiblah'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php esc_html_e('Enable reCAPTCHA v3', 'mawiblah'); ?></td>
                <td><?php esc_html_e('Disabled / Enabled. When enabled, the subscription form verifies each submission with Google. Submissions with a score below 0.5 are rejected.', 'mawiblah'); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Site Key (public)', 'mawiblah'); ?></td>
                <td><?php esc_html_e('The public key used in the browser to load the reCAPTCHA widget.', 'mawiblah'); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Secret Key (private)', 'mawiblah'); ?></td>
                <td><?php esc_html_e('The private key used server-side to verify tokens with Google. Never expose this publicly.', 'mawiblah'); ?></td>
            </tr>
        </tbody>
    </table>

</div>
