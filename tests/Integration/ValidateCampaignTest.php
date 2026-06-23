<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\Subscribers;
use WP_UnitTestCase;

/**
 * Tests for Campaigns::validateCampaign() covering the PR changes.
 *
 * The PR replaced print() calls with echo esc_html__() / echo esc_html(sprintf())
 * to produce translatable, escaped output. These tests verify that each validation
 * branch still outputs the expected message and returns false, and that a fully
 * valid set of inputs returns true.
 */
class ValidateCampaignTest extends WP_UnitTestCase
{
    /**
     * Template name that actually exists in the plugin's email_templates directory.
     * Used for tests that need to pass the template-validation check.
     */
    private const REAL_TEMPLATE = 'mawiblah-newsletter-template';

    /** @var int Term ID for a real audience created for the audiences-related tests. */
    private int $audienceTermId;

    public function setUp(): void
    {
        parent::setUp();
        $term = wp_insert_term('Test Audience PHPUnit', Subscribers::taxonomyName());
        $this->audienceTermId = $term['term_id'];
    }

    public function tearDown(): void
    {
        wp_delete_term($this->audienceTermId, Subscribers::taxonomyName());
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Empty-field early-exit branches
    // -----------------------------------------------------------------------

    public function test_returns_false_and_outputs_message_for_empty_title(): void
    {
        ob_start();
        $result = Campaigns::validateCampaign('', 'Subject', [1], 'template');
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Empty title', $output);
    }

    public function test_returns_false_and_outputs_message_for_empty_audiences(): void
    {
        ob_start();
        $result = Campaigns::validateCampaign('My Campaign', 'Subject', [], 'template');
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Empty audiences', $output);
    }

    public function test_returns_false_and_outputs_message_for_empty_template(): void
    {
        ob_start();
        $result = Campaigns::validateCampaign('My Campaign', 'Subject', [1], '');
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Empty template', $output);
    }

    public function test_returns_false_and_outputs_message_for_empty_subject(): void
    {
        ob_start();
        // title, audiences, template are all non-empty; subject is empty
        $result = Campaigns::validateCampaign('My Campaign', '', [1], 'some-template');
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Empty subject', $output);
    }

    // -----------------------------------------------------------------------
    // Template-not-found branch
    // -----------------------------------------------------------------------

    public function test_returns_false_and_outputs_message_for_nonexistent_template(): void
    {
        $templateName = 'nonexistent-template-phpunit-xyz';

        ob_start();
        $result = Campaigns::validateCampaign('My Campaign', 'Subject', [1], $templateName);
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Could not get template', $output);
        // The PR changed sprintf interpolation to include the template name in the message
        $this->assertStringContainsString($templateName, $output);
    }

    public function test_template_error_message_no_longer_contains_typo(): void
    {
        // Regression: the old message contained "Could nog get template" (typo).
        // The PR fixed this to "Could not get template".
        ob_start();
        Campaigns::validateCampaign('Title', 'Subject', [1], 'bad-template');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('nog', $output);
        $this->assertStringContainsString('not', $output);
    }

    // -----------------------------------------------------------------------
    // Invalid audiences branch (template passes, audience IDs do not exist)
    // -----------------------------------------------------------------------

    public function test_returns_false_and_outputs_message_for_invalid_audience_ids(): void
    {
        // Use term IDs that are extremely unlikely to exist in the test database
        $invalidIds = [999999901, 999999902];

        ob_start();
        $result = Campaigns::validateCampaign('My Campaign', 'Subject', $invalidIds, self::REAL_TEMPLATE);
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Audiences not found', $output);
    }

    // -----------------------------------------------------------------------
    // Valid inputs — happy path
    // -----------------------------------------------------------------------

    public function test_returns_true_and_produces_no_output_for_valid_inputs(): void
    {
        ob_start();
        $result = Campaigns::validateCampaign(
            'My Campaign',
            'My Subject',
            [$this->audienceTermId],
            self::REAL_TEMPLATE
        );
        $output = ob_get_clean();

        $this->assertTrue($result);
        $this->assertSame('', $output, 'No output should be produced for a valid campaign');
    }

    // -----------------------------------------------------------------------
    // Boundary / regression
    // -----------------------------------------------------------------------

    public function test_whitespace_only_title_is_not_caught_by_empty_check(): void
    {
        // PHP empty() returns false for a whitespace-only string, so the title
        // check does not fire. This pins the current behaviour so that a future
        // trim() addition would be a deliberate, noticed change.
        ob_start();
        $result = Campaigns::validateCampaign('   ', 'Subject', [1], 'some-template');
        $output = ob_get_clean();

        // Whitespace title passes the empty() guard; the next failing check fires
        $this->assertFalse($result);
        $this->assertStringNotContainsString('Empty title', $output);
    }

    public function test_single_nonexistent_audience_id_fails_validation(): void
    {
        ob_start();
        $result = Campaigns::validateCampaign('Title', 'Subject', [999999903], self::REAL_TEMPLATE);
        $output = ob_get_clean();

        $this->assertFalse($result);
        $this->assertStringContainsString('Audiences not found', $output);
    }
}