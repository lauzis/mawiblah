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

    /**
     * The v2 widget's id for this form.
     *
     * Google renders every `.g-recaptcha` on the page in order, so the id is the
     * index of this form's widget among them. Without it a page with two forms
     * would read the first widget's answer for all of them.
     */
    function getWidgetId(wrapper) {
        var widget = wrapper.querySelector('.g-recaptcha');
        var all = Array.prototype.slice.call(document.querySelectorAll('.g-recaptcha'));

        return Math.max(0, all.indexOf(widget));
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
        var version   = wrapper.dataset.recaptchaVersion || 'v3';

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

        if (!siteKey || typeof grecaptcha === 'undefined') {
            doSubmit('');
            return;
        }

        // v2 has already asked the visitor: the answer is sitting in the widget,
        // and an empty one means they have not ticked it yet.
        if (version === 'v2') {
            var answer = '';

            try {
                answer = grecaptcha.getResponse(getWidgetId(wrapper)) || '';
            } catch (e) {
                answer = '';
            }

            if (!answer) {
                clearLoading();
                showMessage(wrapper, 'error', mawiblahSubscribeFormData.recaptchaMessage);
                return;
            }

            doSubmit(answer);
            return;
        }

        grecaptcha.ready(function () {
            grecaptcha.execute(siteKey, { action: 'subscribe' }).then(function (token) {
                doSubmit(token);
            }).catch(function () {
                clearLoading();
                showMessage(wrapper, 'error', wrapper.dataset.errorMessage || mawiblahSubscribeFormData.errorMessage);
            });
        });
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
