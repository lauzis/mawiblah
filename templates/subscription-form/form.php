<?php
/**
 * @var array  $audienceHashes  Audience hashes to subscribe to
 * @var bool   $recaptcha        Whether reCAPTCHA is active
 * @var string $recaptchaVersion 'disabled', 'v2' or 'v3'
 * @var string $siteKey         reCAPTCHA site key
 * @var string $label          Email field label
 * @var string $placeholder    Email input placeholder
 * @var string $buttonText     Submit button text
 * @var string $successMessage Custom success message (empty = use server message)
 * @var string $errorMessage   Custom error message (empty = use server message)
 */
?>
<div class="mawiblah-subscribe-form"
    <?php if ($recaptcha):      ?>data-recaptcha-site-key="<?= esc_attr($siteKey) ?>" data-recaptcha-version="<?= esc_attr($recaptchaVersion) ?>"<?php endif; ?>
    <?php if ($successMessage): ?>data-success-message="<?= esc_attr($successMessage) ?>"<?php endif; ?>
    <?php if ($errorMessage):   ?>data-error-message="<?= esc_attr($errorMessage) ?>"<?php endif; ?>
>

    <form class="mawiblah-subscribe-form__form">

        <?php /* Honeypot — hidden via inline style so it survives theme CSS resets */ ?>
        <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true" />

        <input type="hidden" class="mawiblah-subscribe-form__signature" value="<?= esc_attr($audienceSignature ?? '') ?>" />
        <?php foreach ($audienceHashes as $hash): ?>
            <input type="hidden" class="mawiblah-subscribe-form__audience" value="<?= esc_attr($hash) ?>" />
        <?php endforeach; ?>

        <div class="mawiblah-subscribe-form__field">
            <label class="mawiblah-subscribe-form__label" for="mawiblah-email">
                <?= esc_html($label) ?>
            </label>
            <input
                class="mawiblah-subscribe-form__input"
                type="email"
                id="mawiblah-email"
                name="email"
                placeholder="<?= esc_attr($placeholder) ?>"
                required
            />
        </div>

        <?php if ($recaptcha && $recaptchaVersion === 'v2'): ?>
            <?php /* v2 asks the visitor a question, so it needs somewhere to ask it. */ ?>
            <div class="mawiblah-subscribe-form__field mawiblah-subscribe-form__recaptcha">
                <div class="g-recaptcha" data-sitekey="<?= esc_attr($siteKey) ?>"></div>
            </div>
        <?php endif; ?>

        <div class="mawiblah-subscribe-form__actions">
            <button class="mawiblah-subscribe-form__button" type="submit">
                <?= esc_html($buttonText) ?>
            </button>
        </div>

    </form>

    <div class="mawiblah-subscribe-form__message mawiblah-subscribe-form__message--success" hidden></div>
    <div class="mawiblah-subscribe-form__message mawiblah-subscribe-form__message--error" hidden></div>

</div>

<?php if ($recaptcha && $recaptchaVersion === 'v3'): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= esc_attr($siteKey) ?>"></script>
<?php elseif ($recaptcha && $recaptchaVersion === 'v2'): ?>
    <?php
    // The v2 widget is ~360 KB and a third-party cookie, and this form usually
    // sits at the foot of the page. Fetch Google's script once a widget nears
    // the viewport or the form is touched; api.js then renders every
    // .g-recaptcha on the page by itself. Printed once even with several forms.
    if (empty($GLOBALS['mawiblah_recaptcha_loader'])) :
        $GLOBALS['mawiblah_recaptcha_loader'] = true; ?>
    <script>
    (function () {
        var loaded = false;
        function load() {
            if (loaded) { return; }
            loaded = true;
            var s = document.createElement('script');
            s.src = 'https://www.google.com/recaptcha/api.js';
            s.async = true;
            document.head.appendChild(s);
        }
        function init() {
            var widgets = document.querySelectorAll('.g-recaptcha');
            if (!widgets.length) { return; }
            if (!('IntersectionObserver' in window)) { load(); return; }
            var io = new IntersectionObserver(function (entries) {
                if (entries.some(function (e) { return e.isIntersecting; })) { io.disconnect(); load(); }
            }, { rootMargin: '600px 0px' });
            widgets.forEach(function (w) {
                io.observe(w);
                var form = w.closest('form');
                if (form) { form.addEventListener('focusin', load, { once: true }); }
            });
        }
        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
    })();
    </script>
    <?php endif; ?>
<?php endif; ?>
