<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\RestRoutes;
use Mawiblah\Settings;
use Mawiblah\ShortCodes;
use Mawiblah\Subscribers;
use Mawiblah\Templates;
use WP_UnitTestCase;

/**
 * Covers the templates the plugin ships: that they are discoverable, that the default
 * newsletter really does list the latest articles, and that a render leaves no variable
 * behind — including a faked send that inspects the letter wp_mail() was handed.
 */
class EmailTemplateTest extends WP_UnitTestCase
{
    private const DEFAULT_TEMPLATE = 'mawiblah-newsletter-template';
    private const ALL_VARS_TEMPLATE = 'mawiblah-all-variables-test';

    private int $campaignId = 0;
    private ?object $subscriber = null;
    private array $postIds = [];
    private $loopbackStub = null;

    public function setUp(): void
    {
        parent::setUp();

        ShortCodes::register();
        $this->loopbackStub = self::addTemplateLoopbackStub();

        $this->campaignId = Campaigns::addCampaign(
            'PHPUnit All Variables',
            'Diagnostic subject',
            'Diagnostic content title',
            'Diagnostic campaign body',
            [],
            self::ALL_VARS_TEMPLATE
        );

        $this->subscriber = Subscribers::addSubscriber('alltokens@mawiblah.test');

        foreach (['Newest Article One', 'Newest Article Two', 'Newest Article Three'] as $title) {
            $this->postIds[] = self::factory()->post->create([
                'post_title'  => $title,
                'post_status' => 'publish',
            ]);
        }
    }

    public function tearDown(): void
    {
        if ($this->loopbackStub) {
            remove_filter('pre_http_request', $this->loopbackStub, 10);
            $this->loopbackStub = null;
        }

        self::removeArchivedTemplate($this->campaignId, self::ALL_VARS_TEMPLATE);

        foreach ($this->postIds as $postId) {
            wp_delete_post($postId, true);
        }
        $this->postIds = [];

        if ($this->subscriber) {
            wp_delete_post($this->subscriber->id, true);
            $this->subscriber = null;
        }

        if ($this->campaignId) {
            Campaigns::deleteCampaign($this->campaignId);
            $this->campaignId = 0;
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Discovery
    // -------------------------------------------------------------------------

    public function test_default_newsletter_template_is_shipped_and_discoverable(): void
    {
        $source = Templates::getEmailTemplateByName(self::DEFAULT_TEMPLATE);

        $this->assertIsString($source, 'The shipped default newsletter template was not found.');
        $this->assertNotSame('', trim($source));
        $this->assertTrue(Templates::validateEmailTemplate(self::DEFAULT_TEMPLATE));
        $this->assertArrayHasKey(self::DEFAULT_TEMPLATE, Templates::getArrayOfEmailTemplates());
    }

    public function test_default_newsletter_template_lists_the_latest_three_articles(): void
    {
        $source = Templates::getEmailTemplateByName(self::DEFAULT_TEMPLATE);

        $this->assertStringContainsString(
            '[mawiblah_newest_articles count="3"]',
            $source,
            'The default newsletter no longer renders the latest 3 articles.'
        );
    }

    public function test_default_newsletter_template_renders_the_three_newest_posts(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        update_post_meta($this->campaignId, 'template', self::DEFAULT_TEMPLATE);
        $campaign->template = self::DEFAULT_TEMPLATE;

        $locked = Campaigns::lockTemplate($campaign, false);
        self::removeArchivedTemplate($this->campaignId, self::DEFAULT_TEMPLATE);

        $this->assertIsString($locked, 'lockTemplate() could not render the default newsletter.');
        foreach (['Newest Article One', 'Newest Article Two', 'Newest Article Three'] as $title) {
            $this->assertStringContainsString($title, $locked);
        }
    }

    public function test_all_variables_template_is_shipped_and_discoverable(): void
    {
        $source = Templates::getEmailTemplateByName(self::ALL_VARS_TEMPLATE);

        $this->assertIsString($source, 'The all-variables diagnostic template was not found.');
        $this->assertTrue(Templates::validateEmailTemplate(self::ALL_VARS_TEMPLATE));
        $this->assertArrayHasKey(self::ALL_VARS_TEMPLATE, Templates::getArrayOfEmailTemplates());
    }

    /**
     * The diagnostic template is only worth having while it is complete: a shortcode added
     * to ShortCodes::register() without a marker here would never be exercised again.
     */
    public function test_all_variables_template_exercises_every_registered_mawiblah_shortcode(): void
    {
        $source = Templates::getEmailTemplateByName(self::ALL_VARS_TEMPLATE);

        foreach ($GLOBALS['shortcode_tags'] as $tag => $callback) {
            if (!is_array($callback) || ($callback[0] ?? null) !== ShortCodes::class) {
                continue;
            }
            $this->assertStringContainsString(
                '[' . $tag,
                $source,
                sprintf('Shortcode [%s] is registered but missing from the diagnostic template.', $tag)
            );
        }
    }

    public function test_all_variables_template_declares_every_static_placeholder(): void
    {
        $source = Templates::getEmailTemplateByName(self::ALL_VARS_TEMPLATE);

        foreach (self::staticPlaceholders() as $placeholder) {
            $this->assertStringContainsString($placeholder, $source);
        }
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function test_render_leaves_no_variable_unreplaced(): void
    {
        $rendered  = $this->renderAllVariablesTemplate();
        $leftovers = $this->leftoverMarkers($rendered);

        $this->assertSame(
            [],
            $leftovers,
            'These variables were not replaced: ' . implode(', ', array_keys($leftovers))
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\[mawiblah_[a-z_]+/',
            $rendered,
            'A [mawiblah_…] shortcode survived the render.'
        );
    }

    public function test_rendered_unsubscribe_link_carries_the_real_hashes(): void
    {
        $rendered = $this->renderAllVariablesTemplate();
        $campaign = Campaigns::getCampaignById($this->campaignId);

        $this->assertStringContainsString($campaign->campaignHash, $rendered);
        $this->assertStringContainsString($this->subscriber->subscriberHash, $rendered);
        $this->assertStringContainsString('unsubscribe=' . $this->subscriber->email, $rendered);
        $this->assertStringNotContainsString('unsubscribe=%7Bemail%7D', $rendered);
    }

    // -------------------------------------------------------------------------
    // Faked send
    // -------------------------------------------------------------------------

    /**
     * Drives one real send through RestRoutes and reads the letter off the pre_wp_mail
     * filter instead of delivering it — the closest thing to opening the received email.
     */
    public function test_faked_send_delivers_a_letter_with_every_variable_replaced(): void
    {
        update_option('_mawiblah-dont-send-emails', 'send-emails');
        $this->assertTrue(Settings::sendEmails(), 'Email sending could not be enabled for the faked send.');

        $captured = [];
        $capture  = static function ($shortCircuit, $atts) use (&$captured) {
            $captured = $atts;
            return true;
        };
        add_filter('pre_wp_mail', $capture, 10, 2);

        $request = new \WP_REST_Request('POST', '/mawiblah/v1/send-email');
        $request->set_param('campaignPostId', $this->campaignId);
        $request->set_param('subscriberId', $this->subscriber->id);
        $request->set_param('email', $this->subscriber->email);
        $request->set_param('lastItem', false);

        $response = RestRoutes::sendEmail($request);
        remove_filter('pre_wp_mail', $capture, 10);

        $data = $response->get_data();
        $this->assertSame('ok', $data['status'], $data['message'] ?? '');
        $this->assertNotEmpty($captured, 'wp_mail() was never reached — nothing was sent.');

        $campaign   = Campaigns::getCampaignById($this->campaignId);
        $unsubToken = Subscribers::getUnsubToken($this->subscriber->id, $this->subscriber->email);

        $this->assertSame($this->subscriber->email, $captured['to']);
        $this->assertSame($campaign->subject, $captured['subject']);

        $leftovers = $this->leftoverMarkers($captured['message']);
        $this->assertSame(
            [],
            $leftovers,
            'The sent letter still carries these variables: ' . implode(', ', array_keys($leftovers))
        );

        $headers = implode("\n", (array) $captured['headers']);
        $this->assertStringContainsString('Content-Type: text/html', $headers);
        $this->assertStringContainsString('List-Unsubscribe-Post: List-Unsubscribe=One-Click', $headers);
        $this->assertStringContainsString($this->subscriber->subscriberHash, $headers);
        $this->assertStringContainsString($unsubToken, $headers);
        $this->assertStringContainsString($campaign->campaignHash, $headers);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** The six placeholders Campaigns::fillTemplate() replaces by str_replace. */
    private static function staticPlaceholders(): array
    {
        return [
            '{campaignHash}',
            '{subscriberHash}',
            '{email}',
            '%7BcampaignHash%7D',
            '%7BsubscriberHash%7D',
            '%7Bemail%7D',
        ];
    }

    /** Runs the shipped diagnostic template through the two real render passes. */
    private function renderAllVariablesTemplate(): string
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        $locked   = Campaigns::lockTemplate($campaign, false);

        $this->assertIsString($locked, 'lockTemplate() could not render the diagnostic template.');

        return Campaigns::fillTemplate($locked, $campaign, $this->subscriber);
    }

    /**
     * Maps every marker in the diagnostic template to its token, then returns the ones whose
     * token is still present in the rendered letter — so a failure names the variable.
     *
     * @return array<string, string> Marker name to unreplaced token.
     */
    private function leftoverMarkers(string $rendered): array
    {
        $source = Templates::getEmailTemplateByName(self::ALL_VARS_TEMPLATE);
        preg_match_all('/data-mawiblah-marker="([^"]+)">(.*?)<\/li>/s', (string) $source, $matches, PREG_SET_ORDER);

        $this->assertNotEmpty($matches, 'The diagnostic template no longer carries any markers.');

        $leftovers = [];
        foreach ($matches as [, $marker, $token]) {
            $token = trim($token);
            if ($token !== '' && str_contains($rendered, $token)) {
                $leftovers[$marker] = $token;
            }
        }

        return $leftovers;
    }

    /**
     * Answers the template loopback in-process. Templates::getTemplateByNameViaRest() posts to
     * the site's own REST route, which has no HTTP server behind it under PHPUnit; this serves
     * the same controller directly so the real lockTemplate() path can be exercised.
     */
    private static function addTemplateLoopbackStub(): callable
    {
        $stub = static function ($preempt, $args, $url) {
            if (!str_contains((string) $url, 'mawiblah/v1/get-html-template')) {
                return $preempt;
            }

            $body    = json_decode((string) ($args['body'] ?? ''), true);
            $request = new \WP_REST_Request('POST', '/mawiblah/v1/get-html-template');
            $request->set_param('template', $body['template'] ?? '');

            $response = RestRoutes::getHtmlTemplate($request);

            return [
                'headers'  => [],
                'body'     => wp_json_encode($response->get_data()),
                'response' => ['code' => $response->get_status(), 'message' => ''],
                'cookies'  => [],
                'filename' => null,
            ];
        };

        add_filter('pre_http_request', $stub, 10, 3);

        return $stub;
    }

    /** Removes the snapshot Templates::copyTemplate() writes, so a run leaves no files behind. */
    private static function removeArchivedTemplate(int $campaignId, string $templateName): void
    {
        if (!$campaignId) {
            return;
        }

        $dir  = MAWIBLAH_PLUGIN_DIR . '/email_templates/archived';
        $file = $dir . '/' . $campaignId . '_' . $templateName . '.html';

        if (file_exists($file)) {
            unlink($file);
        }
        if (is_dir($dir) && count((array) scandir($dir)) === 2) {
            rmdir($dir);
        }
    }
}
