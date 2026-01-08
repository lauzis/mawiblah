<?php

namespace Mawiblah;

class Visits
{

    // http://gudlenieks.test/tmp-test/?utm_source=email&utm_medium=email&utm_campaign=monthly-email&campaign=5839ddf15d7eafa3befa98544d2c1e9d&subscriber=f5f96025b4aa949ad6d1b20e207c66c9
    public static function init()
    {
        if (isset($_GET['campaign']) && isset($_GET['subscriber']) && !isset($_GET['unsubscribe'])) {

            $currentUrl = Helpers::getCurrentUrlPath();
            $campaignHash = sanitize_text_field($_GET['campaign']);
            $subscriberHash = sanitize_text_field($_GET['subscriber']);
            self::visit($campaignHash, $subscriberHash, $currentUrl);
        }
    }

    public static function visit(string $campaignHash, string $subscriberHash, $currentUrl): void
    {
        if (!session_id()) {
            session_start();
        }

        Campaigns::linkCLicked($campaignHash, $currentUrl);
        Subscribers::linksClicked($subscriberHash);

        $_SESSION['campaignHash'] = $campaignHash;
        $_SESSION['subscriberHash'] = $subscriberHash;
        $_SESSION[$currentUrl] = true;
    }
}
