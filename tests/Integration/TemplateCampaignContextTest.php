<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\Templates;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * The campaign has to be held while a template's shortcodes run.
 *
 * Expanding them without it bakes the no-campaign fallbacks into the letter --
 * "Summary for the {month}" and a fixed sentence -- and the pass that does set
 * the campaign, in Campaigns::lockTemplate(), then finds nothing left to fill.
 */
class TemplateCampaignContextTest extends WP_UnitTestCase
{
    private const TEMPLATE = 'phpunit-campaign-context';

    private string $templateFile;
    private int $campaignId;

    public function setUp(): void
    {
        parent::setUp();

        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates';
        wp_mkdir_p($dir);

        $this->templateFile = $dir . '/' . self::TEMPLATE . '.html';
        file_put_contents($this->templateFile, '<h1>[mawiblah_title]</h1>[mawiblah_content]');

        $this->campaignId = Campaigns::addCampaign(
            'PHPUnit campaign',
            'Subject line',
            'Mēneša jaunumi.',
            'Esam sagatavojuši jaunus rakstus.',
            [],
            self::TEMPLATE
        );
    }

    public function tearDown(): void
    {
        if (file_exists($this->templateFile)) {
            unlink($this->templateFile);
        }

        Campaigns::deleteCampaign($this->campaignId);

        parent::tearDown();
    }

    public function test_the_letter_carries_the_campaign_title_and_content(): void
    {
        $html = Templates::renderWithCampaign(file_get_contents($this->templateFile), $this->campaignId);

        $this->assertStringContainsString('<h1>Mēneša jaunumi.</h1>', $html);
        $this->assertStringContainsString('Esam sagatavojuši jaunus rakstus.', $html);
        $this->assertStringNotContainsString('[mawiblah_title]', $html);
    }

    /** A preview of the template alone still shows what it looks like with nothing behind it. */
    public function test_without_a_campaign_the_fallbacks_are_used(): void
    {
        $html = Templates::renderWithCampaign(file_get_contents($this->templateFile));

        $this->assertStringNotContainsString('Mēneša jaunumi.', $html);
        $this->assertStringContainsString(gmdate('F'), $html, 'The no-campaign title falls back to the month.');
    }

    /** The campaign is released again, so the next render is not quietly inheriting it. */
    public function test_the_campaign_is_not_left_attached_afterwards(): void
    {
        Templates::renderWithCampaign(file_get_contents($this->templateFile), $this->campaignId);

        $this->assertStringNotContainsString(
            'Mēneša jaunumi.',
            Templates::renderWithCampaign(file_get_contents($this->templateFile))
        );
    }

    public function test_the_rest_route_expands_the_template_for_the_campaign_it_is_given(): void
    {
        $request = new WP_REST_Request('POST', '/mawiblah/v1/get-html-template');
        $request->set_param('template', self::TEMPLATE);
        $request->set_param('campaign', $this->campaignId);

        $data = \Mawiblah\RestRoutes::getHtmlTemplate($request)->get_data();

        $this->assertSame('ok', $data['status']);
        $this->assertStringContainsString('<h1>Mēneša jaunumi.</h1>', $data['template']);
        $this->assertStringContainsString('Esam sagatavojuši jaunus rakstus.', $data['template']);
    }

    public function test_the_rest_route_without_a_campaign_is_unchanged(): void
    {
        $request = new WP_REST_Request('POST', '/mawiblah/v1/get-html-template');
        $request->set_param('template', self::TEMPLATE);

        $data = \Mawiblah\RestRoutes::getHtmlTemplate($request)->get_data();

        $this->assertSame('ok', $data['status']);
        $this->assertStringNotContainsString('Mēneša jaunumi.', $data['template']);
    }

    public function test_a_missing_template_still_answers_404(): void
    {
        $request = new WP_REST_Request('POST', '/mawiblah/v1/get-html-template');
        $request->set_param('template', 'phpunit-no-such-template');

        $response = \Mawiblah\RestRoutes::getHtmlTemplate($request);

        $this->assertSame(404, $response->get_status());
    }
}
