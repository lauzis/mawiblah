<?php

namespace Mawiblah;

class Visits
{

    // http://gudlenieks.test/tmp-test/?utm_source=email&utm_medium=email&utm_campaign=monthly-email&campaign=5839ddf15d7eafa3befa98544d2c1e9d&subscriberId=f5f96025b4aa949ad6d1b20e207c66c9
    public static function init()
    {
        if (isset($_GET['campaign']) && isset($_GET['subscriberId']) && !isset($_GET['unsubscribe'])) {

            $currentUrl = Helpers::getCurrentUrlPath();
            $campaignHash = sanitize_text_field($_GET['campaign']);
            $subscriberId = sanitize_text_field($_GET['subscriberId']);
            self::visit($campaignHash, $subscriberId, $currentUrl);
        }
    }

    public static function visit(string $campaignHash, string $subscriberId, $currentUrl): void
    {
        Campaigns::linkCLicked($campaignHash, $currentUrl);
        Subscribers::linksClicked($subscriberId);
        if (!session_id()) {
            session_start();
        }

        $_SESSION['campaignHash'] = $campaignHash;
        $_SESSION['subscriberId'] = $subscriberId;
        $_SESSION[$currentUrl] = true;
    }
}
