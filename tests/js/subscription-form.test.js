'use strict';

// ---- Fixtures ---------------------------------------------------------------

function buildForm(options = {}) {
    document.body.innerHTML = `
        <div class="mawiblah-subscribe-form" data-recaptcha-site-key="${options.siteKey || ''}">
            <form class="mawiblah-subscribe-form__form">
                <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true" />
                ${(options.audiences || []).map(h => `<input type="hidden" class="mawiblah-subscribe-form__audience" value="${h}" />`).join('')}
                <div class="mawiblah-subscribe-form__field">
                    <label class="mawiblah-subscribe-form__label" for="mawiblah-email">Email</label>
                    <input class="mawiblah-subscribe-form__input" type="email" id="mawiblah-email" name="email" value="${options.email || ''}" />
                </div>
                <div class="mawiblah-subscribe-form__actions">
                    <button class="mawiblah-subscribe-form__button" type="submit">Subscribe</button>
                </div>
            </form>
            <div class="mawiblah-subscribe-form__message mawiblah-subscribe-form__message--success" hidden></div>
            <div class="mawiblah-subscribe-form__message mawiblah-subscribe-form__message--error" hidden></div>
        </div>
    `;

    window.mawiblahSubscribeFormData = {
        restUrl:      'https://example.com/wp-json/mawiblah/v1/subscribe',
        errorMessage: 'Something went wrong. Please try again.',
    };

    // Re-load the script logic after DOM is ready
    jest.resetModules();
    require('../../assets/js/subscription-form.js');

    // Fire DOMContentLoaded to bind listeners
    document.dispatchEvent(new Event('DOMContentLoaded'));

    return {
        wrapper: document.querySelector('.mawiblah-subscribe-form'),
        form:    document.querySelector('.mawiblah-subscribe-form__form'),
        button:  document.querySelector('.mawiblah-subscribe-form__button'),
        input:   document.querySelector('.mawiblah-subscribe-form__input'),
        success: document.querySelector('.mawiblah-subscribe-form__message--success'),
        error:   document.querySelector('.mawiblah-subscribe-form__message--error'),
        honeypot: document.querySelector('input[name="website"]'),
    };
}

function mockFetch(response) {
    global.fetch = jest.fn().mockResolvedValue({
        json: jest.fn().mockResolvedValue(response),
    });
}

// ---- Honeypot ---------------------------------------------------------------

describe('honeypot', () => {
    test('honeypot input has display:none inline style', () => {
        const { honeypot } = buildForm();
        expect(honeypot.style.display).toBe('none');
    });

    test('honeypot input has tabindex=-1', () => {
        const { honeypot } = buildForm();
        expect(honeypot.getAttribute('tabindex')).toBe('-1');
    });

    test('honeypot value is empty on a normal (human) form', () => {
        const { honeypot } = buildForm();
        expect(honeypot.value).toBe('');
    });
});

// ---- Submit payload ---------------------------------------------------------

describe('submit payload', () => {
    beforeEach(() => {
        mockFetch({ status: 'ok', message: 'You are now subscribed!' });
    });

    test('sends email in POST body', async () => {
        const { form, input } = buildForm({ email: 'test@example.com', audiences: ['hash1'] });
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await new Promise(r => setTimeout(r, 0));

        const body = JSON.parse(global.fetch.mock.calls[0][1].body);
        expect(body.email).toBe('test@example.com');
    });

    test('sends audienceHashes in POST body', async () => {
        const { form } = buildForm({ email: 'a@b.com', audiences: ['hash1', 'hash2'] });
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await new Promise(r => setTimeout(r, 0));

        const body = JSON.parse(global.fetch.mock.calls[0][1].body);
        expect(body.audienceHashes).toEqual(['hash1', 'hash2']);
    });

    test('sends empty honeypot value on normal submit', async () => {
        const { form } = buildForm({ email: 'a@b.com' });
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await new Promise(r => setTimeout(r, 0));

        const body = JSON.parse(global.fetch.mock.calls[0][1].body);
        expect(body.honeypot).toBe('');
    });

    test('does not include recaptcha token when grecaptcha is absent', async () => {
        delete global.grecaptcha;
        const { form } = buildForm({ email: 'a@b.com', siteKey: '6Lctest' });
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await new Promise(r => setTimeout(r, 0));

        const body = JSON.parse(global.fetch.mock.calls[0][1].body);
        expect(body.recaptchaToken).toBe('');
    });

    test('attaches recaptcha token when grecaptcha is available', async () => {
        global.grecaptcha = {
            ready: cb => cb(),
            execute: jest.fn().mockResolvedValue('test-token-xyz'),
        };
        const { form } = buildForm({ email: 'a@b.com', siteKey: '6Lctest' });
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await new Promise(r => setTimeout(r, 50));

        const body = JSON.parse(global.fetch.mock.calls[0][1].body);
        expect(body.recaptchaToken).toBe('test-token-xyz');
        delete global.grecaptcha;
    });
});

// ---- DOM state --------------------------------------------------------------

describe('DOM state', () => {
    test('adds loading class to wrapper while fetch is in flight', async () => {
        let resolveFetch;
        global.fetch = jest.fn().mockReturnValue(new Promise(r => { resolveFetch = r; }));
        const { wrapper, form } = buildForm({ email: 'a@b.com' });

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await new Promise(r => setTimeout(r, 0));

        expect(wrapper.classList.contains('mawiblah-subscribe-form--loading')).toBe(true);

        resolveFetch({ json: async () => ({ status: 'ok', message: 'OK' }) });
        await new Promise(r => setTimeout(r, 0));
    });

    test('removes loading class after fetch resolves', async () => {
        mockFetch({ status: 'ok', message: 'Done' });
        const { wrapper, form } = buildForm({ email: 'a@b.com' });

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await new Promise(r => setTimeout(r, 50));

        expect(wrapper.classList.contains('mawiblah-subscribe-form--loading')).toBe(false);
    });

    test('shows success message and adds submitted class on ok response', async () => {
        mockFetch({ status: 'ok', message: 'You are now subscribed!' });
        const { form, wrapper, success } = buildForm({ email: 'a@b.com' });

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await new Promise(r => setTimeout(r, 50));

        expect(wrapper.classList.contains('mawiblah-subscribe-form--submitted')).toBe(true);
        expect(success.hidden).toBe(false);
        expect(success.textContent).toBe('You are now subscribed!');
    });

    test('shows error message and keeps form visible on error response', async () => {
        mockFetch({ status: 'error', message: 'Invalid email address.' });
        const { form, wrapper, error } = buildForm({ email: 'bad' });

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await new Promise(r => setTimeout(r, 50));

        expect(wrapper.classList.contains('mawiblah-subscribe-form--submitted')).toBe(false);
        expect(error.hidden).toBe(false);
        expect(error.textContent).toBe('Invalid email address.');
    });
});
