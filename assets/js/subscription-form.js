(function () {
    'use strict';

    function getAudienceHashes(form) {
        return Array.from(form.querySelectorAll('.mawiblah-subscribe-form__audience'))
            .map(function (el) { return el.value; });
    }

    function showMessage(wrapper, type, text) {
        var success = wrapper.querySelector('.mawiblah-subscribe-form__message--success');
        var error   = wrapper.querySelector('.mawiblah-subscribe-form__message--error');
        success.hidden = true;
        error.hidden   = true;

        var target = type === 'success' ? success : error;
        target.textContent = text;
        target.hidden = false;
    }

    function handleSubmit(wrapper, form, event) {
        event.preventDefault();

        if (wrapper.classList.contains('mawiblah-subscribe-form--loading')) {
            return;
        }

        var email     = form.querySelector('.mawiblah-subscribe-form__input').value;
        var honeypot  = form.querySelector('input[name="website"]').value;
        var audiences = getAudienceHashes(form);
        var siteKey   = wrapper.dataset.recaptchaSiteKey || '';

        wrapper.classList.add('mawiblah-subscribe-form--loading');

        function clearLoading() {
            wrapper.classList.remove('mawiblah-subscribe-form--loading');
        }

        function doSubmit(recaptchaToken) {
            fetch(mawiblahSubscribeFormData.restUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email:          email,
                    audienceHashes: audiences,
                    honeypot:       honeypot,
                    recaptchaToken: recaptchaToken || '',
                }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                clearLoading();
                if (data.status === 'ok') {
                    wrapper.classList.add('mawiblah-subscribe-form--submitted');
                    showMessage(wrapper, 'success', wrapper.dataset.successMessage || data.message);
                } else {
                    showMessage(wrapper, 'error', wrapper.dataset.errorMessage || data.message);
                }
            })
            .catch(function () {
                clearLoading();
                showMessage(wrapper, 'error', wrapper.dataset.errorMessage || mawiblahSubscribeFormData.errorMessage);
            });
        }

        if (siteKey && typeof grecaptcha !== 'undefined') {
            grecaptcha.ready(function () {
                grecaptcha.execute(siteKey, { action: 'subscribe' }).then(function (token) {
                    doSubmit(token);
                }).catch(function () {
                    clearLoading();
                    showMessage(wrapper, 'error', wrapper.dataset.errorMessage || mawiblahSubscribeFormData.errorMessage);
                });
            });
        } else {
            doSubmit('');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.mawiblah-subscribe-form').forEach(function (wrapper) {
            var form = wrapper.querySelector('.mawiblah-subscribe-form__form');
            if (!form) return;
            form.addEventListener('submit', function (e) {
                handleSubmit(wrapper, form, e);
            });
        });
    });
}());
