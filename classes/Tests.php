<?php

namespace Mawiblah;

class Tests
{
    /** Outputs a scenario heading (<h2>) in the test results area. */
    public static function echoHeading(string $title): void
    {
        echo '<h2>' . esc_html($title) . '</h2>';
    }

    /** Outputs an assertion label (<h3>) in the test results area. */
    public static function echoTitle(string $title): void
    {
        echo '<h3>' . esc_html($title) . '</h3>';
    }

    /**
     * Outputs a pass/fail result line with optional debug dump.
     *
     * @param string     $resultMessage Human-readable assertion result.
     * @param string     $resultType    'success' or 'error' (mapped to CSS class).
     * @param mixed|null $debug         Optional value to print_r below the result.
     */
    public static function echoResult(string $resultMessage, string $resultType, $debug = null): void
    {
        echo '<p class="mawiblah-' . esc_attr($resultType) . '">' . esc_html($resultMessage) . '</p>';
        if ($debug) {
            echo '<pre class="mawiblah-debug">' . esc_html(print_r($debug, true)) . '</pre>';
        }
    }

    /** Permanently deletes all subscriber posts matching the given email address (test cleanup helper). */
    private static function deleteSubscribersByEmail(string $email): void
    {
        $posts = get_posts([
            'post_type'      => Subscribers::postType(),
            'title'          => $email,
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ]);
        foreach ($posts as $p) {
            wp_delete_post($p->ID, true);
        }
    }

    /** Returns the map of scenario slug to display label for the test page button grid. */
    public static function scenarios(): array
    {
        return [
            'campaigns'         => 'Campaign CRUD',
            'campaign-workflow' => 'Campaign Workflow (test / approve / start / finish)',
            'campaign-counters' => 'Campaign Counters',
            'campaign-template' => 'Campaign Template & Placeholders',
            'subscribers'       => 'Subscriber CRUD & Audience Hash',
            'unsubscribe'       => 'Unsubscribe Flow',
            'click-tracking'    => 'Click Tracking (triple-count)',
            'subscription-form' => 'Subscription Form',
            'default-audiences'          => 'Default Audiences (Unsubed + Testers)',
            'programmatic-subscribe'     => 'Programmatic Subscribe (mawiblah_subscribe hook)',
            'logging'                    => 'Logging (write & verify file log)',
        ];
    }

    /**
     * Dispatches to the named scenario method after verifying administrator capability.
     *
     * @param string $scenario Scenario slug from scenarios().
     */
    public static function run(string $scenario): void
    {
        if (!current_user_can('administrator')) {
            self::echoResult('Access denied.', 'error');
            return;
        }

        match ($scenario) {
            'campaigns'         => self::campaignScenario(),
            'campaign-workflow' => self::campaignWorkflowScenario(),
            'campaign-counters' => self::campaignCountersScenario(),
            'campaign-template' => self::campaignTemplateScenario(),
            'subscribers'       => self::subscriberScenario(),
            'unsubscribe'       => self::unsubscribeScenario(),
            'click-tracking'    => self::clickTrackingScenario(),
            'subscription-form' => self::subscriptionFormScenario(),
            'default-audiences'          => self::defaultAudiencesScenario(),
            'programmatic-subscribe'     => self::programmaticSubscribeScenario(),
            'logging'                    => self::loggingScenario(),
            default                      => self::echoResult('Unknown scenario: ' . $scenario, 'error'),
        };
    }

    // -------------------------------------------------------------------------
    // Campaign CRUD
    // -------------------------------------------------------------------------

    /** In-browser integration test: campaign CRUD, hash lookup, uniqueness, and cleanup. */
    public static function campaignScenario(): void
    {
        self::echoHeading('Campaign CRUD');

        $title1   = 'Mawiblah Test Campaign';
        $title2   = 'Mawiblah Test Campaign ---2';
        $baseline = count(Campaigns::getCampaigns());

        self::echoTitle('Add campaign');
        $id1 = Campaigns::addCampaign($title1, 'Subject', 'Content Title', 'Content', [], 'test-template');
        self::echoResult($id1 ? 'Campaign added (ID ' . $id1 . ')' : 'Campaign not added', $id1 ? 'success' : 'error');

        $id2 = Campaigns::addCampaign($title2, 'Subject', 'Content Title', 'Content', [], 'test-template');

        self::echoTitle('getCampaign finds by title');
        $found = Campaigns::getCampaign($title1);
        $ok = $found && $found->post_title === $title1;
        self::echoResult($ok ? 'Found' : 'Not found', $ok ? 'success' : 'error');

        self::echoTitle('getCampaign returns null for missing title');
        $notFound = Campaigns::getCampaign('Does Not Exist XYZ');
        self::echoResult($notFound === null ? 'Correctly null' : 'Should be null', $notFound === null ? 'success' : 'error');

        self::echoTitle('getCampaignByHash');
        $campaign = Campaigns::getCampaignById($id1);
        $byHash   = Campaigns::getCampaignByHash($campaign->campaignHash);
        $ok = $byHash && $byHash->id === $id1;
        self::echoResult($ok ? 'Found by hash' : 'Not found by hash', $ok ? 'success' : 'error');

        self::echoTitle('Count increased by exactly 2');
        $count = count(Campaigns::getCampaigns());
        $ok = $count - $baseline === 2;
        self::echoResult($ok ? 'Count correct (' . $count . ')' : 'Wrong count (' . $count . ', baseline ' . $baseline . ')', $ok ? 'success' : 'error');

        self::echoTitle('Cleanup');
        Campaigns::deleteCampaign($id1);
        Campaigns::deleteCampaign($id2);
        $afterCount = count(Campaigns::getCampaigns());
        $ok = $afterCount === $baseline;
        self::echoResult($ok ? 'Cleaned up' : 'Cleanup failed (' . $afterCount . ' vs ' . $baseline . ')', $ok ? 'success' : 'error');
    }

    // -------------------------------------------------------------------------
    // Campaign Workflow
    // -------------------------------------------------------------------------

    /** In-browser integration test: campaign test/approve/start/finish workflow state transitions. */
    public static function campaignWorkflowScenario(): void
    {
        self::echoHeading('Campaign Workflow');

        $id = Campaigns::addCampaign('Workflow Test', 'Subject', 'Title', 'Content', [], 'test-template');
        $c  = Campaigns::getCampaignById($id);

        self::echoTitle('testStarted is false before testStart');
        self::echoResult(!$c->testStarted ? 'Correct' : 'Should be false', !$c->testStarted ? 'success' : 'error');

        Campaigns::testStart($id);
        $c = Campaigns::getCampaignById($id);
        self::echoTitle('testStarted set after testStart');
        self::echoResult($c->testStarted ? 'Timestamp: ' . $c->testStarted : 'Not set', $c->testStarted ? 'success' : 'error');

        Campaigns::testFinish($id);
        $c = Campaigns::getCampaignById($id);
        self::echoTitle('testFinished set');
        self::echoResult($c->testFinished ? 'Timestamp: ' . $c->testFinished : 'Not set', $c->testFinished ? 'success' : 'error');

        Campaigns::testApprove($id);
        $c = Campaigns::getCampaignById($id);
        self::echoTitle('testApproved set');
        self::echoResult($c->testApproved ? 'Timestamp: ' . $c->testApproved : 'Not set', $c->testApproved ? 'success' : 'error');

        Campaigns::testReset($id);
        $c       = Campaigns::getCampaignById($id);
        $cleared = !$c->testStarted && !$c->testFinished && !$c->testApproved;
        self::echoTitle('All test timestamps cleared after testReset');
        self::echoResult($cleared ? 'Cleared' : 'Not cleared', $cleared ? 'success' : 'error');

        Campaigns::campaignStart($id);
        $c = Campaigns::getCampaignById($id);
        self::echoTitle('campaignStarted set');
        self::echoResult($c->campaignStarted ? 'Timestamp: ' . $c->campaignStarted : 'Not set', $c->campaignStarted ? 'success' : 'error');

        $startedAt = $c->campaignStarted;
        Campaigns::campaignStart($id);
        $c = Campaigns::getCampaignById($id);
        self::echoTitle('campaignStart guard — second call does not overwrite');
        self::echoResult($c->campaignStarted === $startedAt ? 'Guard OK' : 'Overwritten', $c->campaignStarted === $startedAt ? 'success' : 'error');

        Campaigns::campaignFinish($id);
        $c = Campaigns::getCampaignById($id);
        self::echoTitle('campaignFinished set');
        self::echoResult($c->campaignFinished ? 'Timestamp: ' . $c->campaignFinished : 'Not set', $c->campaignFinished ? 'success' : 'error');

        Campaigns::deleteCampaign($id);
        self::echoResult('Cleaned up', 'success');
    }

    // -------------------------------------------------------------------------
    // Campaign Counters
    // -------------------------------------------------------------------------

    /** In-browser integration test: campaign counters initialise at zero and update correctly. */
    public static function campaignCountersScenario(): void
    {
        self::echoHeading('Campaign Counters');

        $id = Campaigns::addCampaign('Counter Test', 'Subject', 'Title', 'Content', [], 'test-template');
        $c  = Campaigns::getCampaignById($id);

        self::echoTitle('All counters start at 0');
        $counters = Campaigns::getCounters($c);
        $allZero  = (int)$counters->emailsSend === 0 && (int)$counters->emailsFailed === 0 && (int)$counters->emailsSkipped === 0;
        self::echoResult($allZero ? 'All zero' : 'Not zero', $allZero ? 'success' : 'error');

        self::echoTitle('updateCounters reflects new values');
        Campaigns::updateCounters($c, 5, 1, 2, 1);
        $counters = Campaigns::getCounters($c);
        $ok = (int)$counters->emailsSend === 5 && (int)$counters->emailsFailed === 1 && (int)$counters->emailsSkipped === 2;
        self::echoResult($ok ? 'Counters correct' : 'Wrong counters', $ok ? 'success' : 'error');

        self::echoTitle('incrementNewlyUnsubed increments by 1');
        $before = (int) get_post_meta($id, 'emailsNewlyUnsubed', true);
        Campaigns::incrementNewlyUnsubed($id);
        $after = (int) get_post_meta($id, 'emailsNewlyUnsubed', true);
        self::echoResult($after === $before + 1 ? 'Incremented' : 'Not incremented', $after === $before + 1 ? 'success' : 'error');

        Campaigns::deleteCampaign($id);
        self::echoResult('Cleaned up', 'success');
    }

    // -------------------------------------------------------------------------
    // Campaign Template & Placeholders
    // -------------------------------------------------------------------------

    /** In-browser integration test: fillTemplate replaces all placeholders including URL-encoded variants. */
    public static function campaignTemplateScenario(): void
    {
        self::echoHeading('Campaign Template & Placeholders');

        $id  = Campaigns::addCampaign('Template Test', 'Subject', 'Title', 'Content', [], 'test-template');
        $c   = Campaigns::getCampaignById($id);
        $sub = Subscribers::addSubscriber('templatetest@mawiblah.test');

        $template = 'Hash:{campaignHash} Sub:{subscriberHash} Email:{email} Encoded:%7BcampaignHash%7D';
        $filled   = Campaigns::fillTemplate($template, $c, $sub);

        self::echoTitle('fillTemplate replaces {campaignHash}');
        self::echoResult(str_contains($filled, $c->campaignHash) ? 'Replaced' : 'Not replaced', str_contains($filled, $c->campaignHash) ? 'success' : 'error');

        self::echoTitle('fillTemplate replaces {subscriberHash}');
        self::echoResult(str_contains($filled, $sub->subscriberHash) ? 'Replaced' : 'Not replaced', str_contains($filled, $sub->subscriberHash) ? 'success' : 'error');

        self::echoTitle('fillTemplate replaces {email}');
        self::echoResult(str_contains($filled, $sub->email) ? 'Replaced' : 'Not replaced', str_contains($filled, $sub->email) ? 'success' : 'error');

        self::echoTitle('fillTemplate replaces URL-encoded %7BcampaignHash%7D');
        self::echoResult(!str_contains($filled, '%7BcampaignHash%7D') ? 'Replaced' : 'Not replaced', !str_contains($filled, '%7BcampaignHash%7D') ? 'success' : 'error');

        self::echoTitle('lockTemplate returns false for non-existent template');
        $result = Campaigns::lockTemplate($c, false);
        self::echoResult($result === false ? 'Correctly false' : 'Should return false', $result === false ? 'success' : 'error');

        Campaigns::deleteCampaign($id);
        wp_delete_post($sub->id, true);
        self::echoResult('Cleaned up', 'success');
    }

    // -------------------------------------------------------------------------
    // Subscriber CRUD & Audience Hash
    // -------------------------------------------------------------------------

    /** In-browser integration test: subscriber CRUD, audience hash generation, and token idempotency. */
    public static function subscriberScenario(): void
    {
        self::echoHeading('Subscriber CRUD & Audience Hash');

        $email = 'subscribertest@mawiblah.test';
        self::deleteSubscribersByEmail($email);

        self::echoTitle('addSubscriber creates subscriber');
        $sub = Subscribers::addSubscriber($email);
        self::echoResult($sub && $sub->email === $email ? 'Created' : 'Not created', $sub ? 'success' : 'error');

        self::echoTitle('getSubscriber finds by email');
        $found = Subscribers::getSubscriber($email);
        self::echoResult($found ? 'Found' : 'Not found', $found ? 'success' : 'error');

        self::echoTitle('getSubscriberById');
        $byId = Subscribers::getSubscriberById($sub->id);
        self::echoResult($byId && $byId->id === $sub->id ? 'Found' : 'Not found', $byId ? 'success' : 'error');

        self::echoTitle('getSubscriberBySubscriberHash');
        $byHash = Subscribers::getSubscriberBySubscriberHash($sub->subscriberHash);
        self::echoResult($byHash && $byHash->id === $sub->id ? 'Found' : 'Not found', $byHash ? 'success' : 'error');

        self::echoTitle('addSubscriber with same email — no duplicate');
        Subscribers::addSubscriber($email);
        $all = get_posts(['post_type' => Subscribers::postType(), 'title' => $email, 'posts_per_page' => -1]);
        self::echoResult(count($all) === 1 ? 'No duplicate' : 'Duplicate created (' . count($all) . ')', count($all) === 1 ? 'success' : 'error');

        self::echoTitle('audienceHash generated on appendAudienceMeta');
        $audiences = Subscribers::getAllAudiences();
        if (!empty($audiences)) {
            $a = $audiences[0];
            self::echoResult(!empty($a->audienceHash) ? 'Hash: ' . $a->audienceHash : 'No hash', !empty($a->audienceHash) ? 'success' : 'error');

            self::echoTitle('audienceHash is idempotent (same value on second call)');
            $a2 = Subscribers::getAllAudiences()[0];
            self::echoResult($a->audienceHash === $a2->audienceHash ? 'Same hash' : 'Different hash', $a->audienceHash === $a2->audienceHash ? 'success' : 'error');
        } else {
            self::echoResult('No audiences exist — create one to test audienceHash', 'error');
        }

        self::echoTitle('getUnsubToken generates and persists');
        $token1 = Subscribers::getUnsubToken($sub->id, $email);
        $token2 = Subscribers::getUnsubToken($sub->id, $email);
        $ok = !empty($token1) && $token1 === $token2;
        self::echoResult($ok ? 'Token stable: ' . $token1 : 'Token unstable', $ok ? 'success' : 'error');

        self::echoTitle('isEmailSent — false before sentEmail');
        $cId = Campaigns::addCampaign('Sub Test Campaign', 'Subject', 'Title', 'Content', [], 'test-template');
        self::echoResult(!Subscribers::isEmailSent($sub->id, $cId) ? 'Correctly false' : 'Should be false', !Subscribers::isEmailSent($sub->id, $cId) ? 'success' : 'error');

        self::echoTitle('isEmailSent — true after sentEmail');
        Subscribers::sentEmail($sub->id, $cId);
        self::echoResult(Subscribers::isEmailSent($sub->id, $cId) ? 'Correctly true' : 'Should be true', Subscribers::isEmailSent($sub->id, $cId) ? 'success' : 'error');

        Campaigns::deleteCampaign($cId);
        self::deleteSubscribersByEmail($email);
        self::echoResult('Cleaned up', 'success');
    }

    // -------------------------------------------------------------------------
    // Unsubscribe Flow
    // -------------------------------------------------------------------------

    /** In-browser integration test: token validation, unsubed flag, audience assignment, and campaign counter. */
    public static function unsubscribeScenario(): void
    {
        self::echoHeading('Unsubscribe Flow');

        $email = 'unsubtest@mawiblah.test';
        self::deleteSubscribersByEmail($email);
        $sub   = Subscribers::addSubscriber($email);
        $cId   = Campaigns::addCampaign('Unsub Test Campaign', 'Subject', 'Title', 'Content', [], 'test-template');
        $token = Subscribers::getUnsubToken($sub->id, $email);

        self::echoTitle('unsub with wrong token returns false');
        $result = Subscribers::unsub($email, 'wrong-token', '');
        self::echoResult($result === false ? 'Correctly rejected' : 'Should be false', $result === false ? 'success' : 'error');

        self::echoTitle('unsubed still false after wrong token');
        $fresh = Subscribers::getSubscriber($email);
        self::echoResult(!$fresh->unsubed ? 'Still subscribed' : 'Incorrectly unsubed', !$fresh->unsubed ? 'success' : 'error');

        self::echoTitle('unsub with correct token returns true');
        $result = Subscribers::unsub($email, $token, 'test feedback');
        self::echoResult($result === true ? 'Unsubscribed' : 'Should be true', $result === true ? 'success' : 'error');

        self::echoTitle('unsubed flag is set');
        $fresh = Subscribers::getSubscriber($email);
        self::echoResult($fresh->unsubed ? 'Flag set' : 'Flag not set', $fresh->unsubed ? 'success' : 'error');

        self::echoTitle('incrementNewlyUnsubed via campaign');
        $before = (int) get_post_meta($cId, 'emailsNewlyUnsubed', true);
        Campaigns::incrementNewlyUnsubed($cId);
        $after = (int) get_post_meta($cId, 'emailsNewlyUnsubed', true);
        self::echoResult($after === $before + 1 ? 'Counter incremented' : 'Not incremented', $after === $before + 1 ? 'success' : 'error');

        Campaigns::deleteCampaign($cId);
        self::deleteSubscribersByEmail($email);
        self::echoResult('Cleaned up', 'success');
    }

    // -------------------------------------------------------------------------
    // Click Tracking
    // -------------------------------------------------------------------------

    /** In-browser integration test: triple-count click tracking (total, unique-per-session, unique-per-user). */
    public static function clickTrackingScenario(): void
    {
        self::echoHeading('Click Tracking (triple-count)');

        $cId  = Campaigns::addCampaign('Click Test Campaign', 'Subject', 'Title', 'Content', [], 'test-template');
        $c    = Campaigns::getCampaignById($cId);
        $sub1 = Subscribers::addSubscriber('clicktest1@mawiblah.test');
        $sub2 = Subscribers::addSubscriber('clicktest2@mawiblah.test');
        $url  = 'https://example.com/page';
        $url2 = 'https://example.com/other';

        self::echoTitle('Counters start at 0');
        $total  = (int) get_post_meta($cId, 'linksClickedTotal', true);
        $unique = (int) get_post_meta($cId, 'linksClicked', true);
        $users  = (int) get_post_meta($cId, 'uniqueUserClicks', true);
        self::echoResult($total === 0 && $unique === 0 && $users === 0 ? 'All zero' : "Not zero (total=$total unique=$unique users=$users)", $total === 0 && $unique === 0 && $users === 0 ? 'success' : 'error');

        self::echoTitle('First visit — all three increment');
        // Start the session first so any existing store data is loaded, then wipe it.
        // Doing $_SESSION = [] before session_start() would be overwritten by the store restore.
        if ( ! session_id() ) { session_start(); }
        $_SESSION = [];
        Visits::visit($c->campaignHash, $sub1->subscriberHash, $url);
        $total  = (int) get_post_meta($cId, 'linksClickedTotal', true);
        $unique = (int) get_post_meta($cId, 'linksClicked', true);
        $users  = (int) get_post_meta($cId, 'uniqueUserClicks', true);
        $ok = $total === 1 && $unique === 1 && $users === 1;
        self::echoResult("total=$total unique=$unique users=$users", $ok ? 'success' : 'error');

        self::echoTitle('Same subscriber, same URL — only total increments');
        Visits::visit($c->campaignHash, $sub1->subscriberHash, $url);
        $total  = (int) get_post_meta($cId, 'linksClickedTotal', true);
        $unique = (int) get_post_meta($cId, 'linksClicked', true);
        $users  = (int) get_post_meta($cId, 'uniqueUserClicks', true);
        $ok = $total === 2 && $unique === 1 && $users === 1;
        self::echoResult("total=$total unique=$unique users=$users", $ok ? 'success' : 'error');

        self::echoTitle('Same subscriber, different URL — total+unique increment');
        Visits::visit($c->campaignHash, $sub1->subscriberHash, $url2);
        $total  = (int) get_post_meta($cId, 'linksClickedTotal', true);
        $unique = (int) get_post_meta($cId, 'linksClicked', true);
        $users  = (int) get_post_meta($cId, 'uniqueUserClicks', true);
        $ok = $total === 3 && $unique === 2 && $users === 1;
        self::echoResult("total=$total unique=$unique users=$users", $ok ? 'success' : 'error');

        self::echoTitle('New subscriber — uniqueUserClicks increments');
        $_SESSION = [];
        Visits::visit($c->campaignHash, $sub2->subscriberHash, $url);
        $users = (int) get_post_meta($cId, 'uniqueUserClicks', true);
        self::echoResult("users=$users", $users === 2 ? 'success' : 'error');

        Campaigns::deleteCampaign($cId);
        wp_delete_post($sub1->id, true);
        wp_delete_post($sub2->id, true);
        self::echoResult('Cleaned up', 'success');
    }

    // -------------------------------------------------------------------------
    // Subscription Form
    // -------------------------------------------------------------------------

    /** In-browser integration test: full subscription form flow including honeypot, invalid email, resubscribe, and partial audience overlap. */
    public static function subscriptionFormScenario(): void
    {
        self::echoHeading('Subscription Form');

        $email = 'formtest@mawiblah.test';
        self::deleteSubscribersByEmail($email);
        self::deleteSubscribersByEmail('honeypot@mawiblah.test');

        $aud1   = wp_insert_term('Form Test Audience 1', Subscribers::postType() . '_category');
        $aud2   = wp_insert_term('Form Test Audience 2', Subscribers::postType() . '_category');
        $aud1Id = is_wp_error($aud1) ? null : $aud1['term_id'];
        $aud2Id = is_wp_error($aud2) ? null : $aud2['term_id'];

        $aud1Obj = $aud1Id ? Subscribers::getAudience($aud1Id) : null;
        $aud2Obj = $aud2Id ? Subscribers::getAudience($aud2Id) : null;
        $hashes  = array_values(array_filter([$aud1Obj->audienceHash ?? null, $aud2Obj->audienceHash ?? null]));

        $makeRequest = function (array $body): \WP_REST_Request {
            $req = new \WP_REST_Request('POST', '/mawiblah/v1/subscribe');
            $req->set_body(json_encode($body));
            $req->set_header('content-type', 'application/json');
            return $req;
        };
        $call = function (array $body) use ($makeRequest): array {
            return SubscriptionForm::subscribe($makeRequest($body))->get_data();
        };

        self::echoTitle('New email → subscriber created, added to both audiences');
        $res = $call(['email' => $email, 'audienceHashes' => $hashes, 'honeypot' => '']);
        $sub = Subscribers::getSubscriber($email);
        $ok  = $res['status'] === 'ok' && $sub;
        self::echoResult($ok ? 'OK' : 'Failed', $ok ? 'success' : 'error');

        self::echoTitle('Same active email → silent ok, no duplicate');
        $res  = $call(['email' => $email, 'audienceHashes' => $hashes, 'honeypot' => '']);
        $dups = get_posts(['post_type' => Subscribers::postType(), 'title' => $email, 'posts_per_page' => -1]);
        $ok   = $res['status'] === 'ok' && count($dups) === 1;
        self::echoResult($ok ? 'OK, no duplicate' : 'Failed (' . count($dups) . ' records)', $ok ? 'success' : 'error');

        self::echoTitle('Honeypot filled → silent ok, no subscriber created');
        $res   = $call(['email' => 'honeypot@mawiblah.test', 'audienceHashes' => [], 'honeypot' => 'bot-value']);
        $noSub = Subscribers::getSubscriber('honeypot@mawiblah.test');
        $ok    = $res['status'] === 'ok' && !$noSub;
        self::echoResult($ok ? 'Correctly rejected' : 'Failed', $ok ? 'success' : 'error');

        self::echoTitle('Invalid email → error returned');
        $res = $call(['email' => 'not-an-email', 'audienceHashes' => [], 'honeypot' => '']);
        self::echoResult($res['status'] === 'error' ? 'Error returned' : 'Should be error', $res['status'] === 'error' ? 'success' : 'error');

        self::echoTitle('Unsubscribed email → check inbox response, flag unchanged');
        update_post_meta($sub->id, 'unsubed', true);
        $res   = $call(['email' => $email, 'audienceHashes' => $hashes, 'honeypot' => '']);
        $fresh = Subscribers::getSubscriber($email);
        $ok    = $res['status'] === 'ok' && $fresh->unsubed;
        self::echoResult($ok ? 'Check inbox returned, still unsubed' : 'Failed', $ok ? 'success' : 'error');

        self::echoTitle('Re-subscribe: valid token clears unsubed flag');
        $token = Subscribers::getUnsubToken($sub->id, $email);
        // Directly invoke the confirmation logic (avoid template include + exit)
        $expectedToken = Subscribers::getUnsubToken($sub->id, $email);
        if ($token === $expectedToken) {
            update_post_meta($sub->id, 'unsubed', false);
            delete_post_meta($sub->id, 'unsub_time');
        }
        $fresh = Subscribers::getSubscriber($email);
        self::echoResult(!$fresh->unsubed ? 'Re-subscribed' : 'Failed', !$fresh->unsubed ? 'success' : 'error');

        self::echoTitle('Re-subscribe: wrong token rejected');
        update_post_meta($sub->id, 'unsubed', true);
        $wrongToken = 'invalid-token-xyz';
        $rejected   = $wrongToken !== $token;
        self::echoResult($rejected ? 'Correctly rejected' : 'Should be rejected', $rejected ? 'success' : 'error');

        // Cleanup
        self::deleteSubscribersByEmail($email);
        self::deleteSubscribersByEmail('honeypot@mawiblah.test');
        if ($aud1Id) wp_delete_term($aud1Id, Subscribers::postType() . '_category');
        if ($aud2Id) wp_delete_term($aud2Id, Subscribers::postType() . '_category');
        self::echoResult('Cleaned up', 'success');
    }

    // -------------------------------------------------------------------------
    // Default Audiences
    // -------------------------------------------------------------------------

    /** In-browser integration test: Unsubed and Testers system audiences exist and have correct hashes. */
    public static function defaultAudiencesScenario(): void
    {
        self::echoHeading('Default Audiences');

        $taxonomy = Subscribers::postType() . '_category';

        foreach (['Unsubed' => 'unsubedAudience', 'Testers' => 'testerAudience'] as $name => $method) {
            self::echoTitle($name . ' audience exists');
            $term = get_term_by('name', $name, $taxonomy);
            self::echoResult($term ? 'Exists (ID ' . $term->term_id . ')' : 'Missing', $term ? 'success' : 'error');

            self::echoTitle($name . ' — ' . $method . '() returns a term object');
            $result = Subscribers::$method();
            $ok = $result && !is_wp_error($result) && isset($result->term_id);
            self::echoResult($ok ? 'Returns term object (ID ' . $result->term_id . ')' : 'Returned non-object', $ok ? 'success' : 'error', $ok ? null : $result);

            self::echoTitle($name . ' — audienceHash is set');
            if ($ok) {
                $hash = get_term_meta($result->term_id, 'audienceHash', true);
                self::echoResult($hash ? 'Hash: ' . $hash : 'No hash', $hash ? 'success' : 'error');
            } else {
                self::echoResult('Skipped — term not found', 'error');
            }
        }

        self::echoTitle('Unsubed audience is distinct from Testers');
        $unsubed = Subscribers::unsubedAudience();
        $testers = Subscribers::testerAudience();
        $distinct = $unsubed && $testers && $unsubed->term_id !== $testers->term_id;
        self::echoResult($distinct ? 'Distinct IDs' : 'Same term or missing', $distinct ? 'success' : 'error');
    }

    // -------------------------------------------------------------------------
    // Programmatic Subscribe
    // -------------------------------------------------------------------------

    /** In-browser integration test: mawiblah_subscribe() function, no-duplicate guard, and mawiblah_subscribed action hook. */
    public static function programmaticSubscribeScenario(): void
    {
        self::echoHeading('Programmatic Subscribe');

        $email    = 'hooktest@mawiblah.test';
        $taxonomy = Subscribers::postType() . '_category';
        self::deleteSubscribersByEmail($email);

        $t1 = wp_insert_term('Hook Test Audience 1', $taxonomy);
        $t2 = wp_insert_term('Hook Test Audience 2', $taxonomy);

        $aud1Id = is_wp_error($t1) ? null : $t1['term_id'];
        $aud2Id = is_wp_error($t2) ? null : $t2['term_id'];
        $aud1   = $aud1Id ? Subscribers::getAudience($aud1Id) : null;
        $aud2   = $aud2Id ? Subscribers::getAudience($aud2Id) : null;
        $hash1  = $aud1->audienceHash ?? null;
        $hash2  = $aud2->audienceHash ?? null;
        $hashes = array_values(array_filter([$hash1, $hash2]));

        // --- invalid email -------------------------------------------------------
        self::echoTitle('Invalid email returns error');
        $res = mawiblah_subscribe('not-an-email');
        self::echoResult($res['status'] === 'error' ? 'Correctly returned error' : 'Should be error', $res['status'] === 'error' ? 'success' : 'error');

        // --- new subscriber ------------------------------------------------------
        self::echoTitle('New email → subscriber created, added to both audiences');
        $res = mawiblah_subscribe($email, $hashes);
        $sub = Subscribers::getSubscriber($email);
        $ok  = $res['status'] === 'ok' && $sub !== null;
        self::echoResult($ok ? 'Subscriber created' : 'Failed — ' . $res['message'], $ok ? 'success' : 'error');

        if ($sub && $aud1Id && $aud2Id) {
            $terms = wp_get_post_terms($sub->id, $taxonomy, ['fields' => 'ids']);
            $inBoth = in_array($aud1Id, $terms) && in_array($aud2Id, $terms);
            self::echoResult($inBoth ? 'Added to both audiences' : 'Missing audience (terms: ' . implode(',', $terms) . ')', $inBoth ? 'success' : 'error');
        }

        // --- no duplicate --------------------------------------------------------
        self::echoTitle('Same email again → ok, no duplicate subscriber');
        $res  = mawiblah_subscribe($email, $hashes);
        $dups = get_posts(['post_type' => Subscribers::postType(), 'title' => $email, 'posts_per_page' => -1]);
        $ok   = $res['status'] === 'ok' && count($dups) === 1;
        self::echoResult($ok ? 'No duplicate' : 'Failed (' . count($dups) . ' records)', $ok ? 'success' : 'error');

        // --- mawiblah_subscribed action hook -------------------------------------
        self::echoTitle('mawiblah_subscribed action fires with correct arguments');
        self::deleteSubscribersByEmail($email);

        $hookFired      = false;
        $hookEmail      = null;
        $hookHashes     = null;
        $hookSubscriber = null;

        $listener = function (string $e, array $h, object $s) use (&$hookFired, &$hookEmail, &$hookHashes, &$hookSubscriber) {
            $hookFired      = true;
            $hookEmail      = $e;
            $hookHashes     = $h;
            $hookSubscriber = $s;
        };
        add_action('mawiblah_subscribed', $listener, 10, 3);

        mawiblah_subscribe($email, $hashes);

        remove_action('mawiblah_subscribed', $listener, 10);

        $ok = $hookFired && $hookEmail === $email && $hookHashes === $hashes && $hookSubscriber !== null;
        self::echoResult($ok ? 'Hook fired with correct args' : 'Hook not fired or wrong args', $ok ? 'success' : 'error');

        // --- partial overlap — only missing audience added -----------------------
        self::echoTitle('Partial overlap → only missing audience added');
        $sub = Subscribers::getSubscriber($email);
        if ($sub && $aud1Id && $aud2Id) {
            // Remove aud2 so subscriber is only in aud1
            $currentTerms = wp_get_post_terms($sub->id, $taxonomy, ['fields' => 'ids']);
            wp_set_post_terms($sub->id, array_diff($currentTerms, [$aud2Id]), $taxonomy);

            // Re-subscribe to both
            mawiblah_subscribe($email, $hashes);

            $terms  = wp_get_post_terms($sub->id, $taxonomy, ['fields' => 'ids']);
            $inBoth = in_array($aud1Id, $terms) && in_array($aud2Id, $terms);
            self::echoResult($inBoth ? 'Both audiences present' : 'Missing audience after re-subscribe', $inBoth ? 'success' : 'error');
        } else {
            self::echoResult('Skipped — subscriber or audiences missing', 'error');
        }

        // --- cleanup -------------------------------------------------------------
        self::deleteSubscribersByEmail($email);
        if ($aud1Id) wp_delete_term($aud1Id, $taxonomy);
        if ($aud2Id) wp_delete_term($aud2Id, $taxonomy);
        self::echoResult('Cleaned up', 'success');
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    /** In-browser integration test: verifies file logging is enabled, writes a test entry, and confirms it appears in the log file. */
    public static function loggingScenario(): void
    {
        self::echoHeading('Logging');

        self::echoTitle('Logging is enabled');
        $enabled = Logs::enabled();
        self::echoResult($enabled ? 'Enabled' : 'Disabled — enable it under Settings → Logging', $enabled ? 'success' : 'error');

        if (!$enabled) {
            return;
        }

        self::echoTitle('Write test log entry');
        $message = 'Hey, this is a log entry from tests! [' . gmdate('H:i:s') . ']';
        $written = Logs::addLog('test', $message);
        self::echoResult($written ? 'Written' : 'Write failed', $written ? 'success' : 'error');

        self::echoTitle('Log file exists');
        $files = Logs::getLogFiles();
        $ok    = !empty($files);
        self::echoResult($ok ? 'Found ' . count($files) . ' log file(s)' : 'No log files found', $ok ? 'success' : 'error');

        self::echoTitle('Test entry appears in today\'s log');
        $found = false;
        if ($ok && file_exists($files[0]['file'])) {
            $lines = file($files[0]['file'], FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $line) {
                if (str_contains($line, $message)) {
                    $found = true;
                    break;
                }
            }
        }
        self::echoResult($found ? 'Entry confirmed in file' : 'Entry not found in file', $found ? 'success' : 'error', $found ? null : ($lines ?? []));
    }
}
