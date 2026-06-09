<?php
/**
 * @var array  $audienceHashes  Audience hashes to subscribe to
 * @var bool   $recaptcha       Whether reCAPTCHA v3 is active
 * @var string $siteKey         reCAPTCHA site key
 * @var string $label          Email field label
 * @var string $placeholder    Email input placeholder
 * @var string $buttonText     Submit button text
 * @var string $successMessage Custom success message (empty = use server message)
 * @var string $errorMessage   Custom error message (empty = use server message)
 */
?>
<div class="mawiblah-subscribe-form"
    <?php if ($recaptcha):      ?>data-recaptcha-site-key="<?= esc_attr($siteKey) ?>"<?php endif; ?>
    <?php if ($successMessage): ?>data-success-message="<?= esc_attr($successMessage) ?>"<?php endif; ?>
    <?php if ($errorMessage):   ?>data-error-message="<?= esc_attr($errorMessage) ?>"<?php endif; ?>
>

    <form class="mawiblah-subscribe-form__form">

        <?php /* Honeypot — hidden via inline style so it survives theme CSS resets */ ?>
        <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true" />

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

        <div class="mawiblah-subscribe-form__actions">
            <button class="mawiblah-subscribe-form__button" type="submit">
                <?= esc_html($buttonText) ?>
            </button>
        </div>

    </form>

    <div class="mawiblah-subscribe-form__message mawiblah-subscribe-form__message--success" hidden></div>
    <div class="mawiblah-subscribe-form__message mawiblah-subscribe-form__message--error" hidden></div>

</div>

<?php if ($recaptcha): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= esc_attr($siteKey) ?>"></script>
<?php endif; ?>
