/* ═══════════════════════════════════════════
   Kim Castle — Splash Page Scripts
   ═══════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {

  const video = document.getElementById('hero-video');
  const listenBtn = document.getElementById('listen-btn');
  const listenLabel = document.getElementById('listen-label');
  const dotRing = document.getElementById('dot-ring');

  let soundOn = false;

  // Video is muted by default — browser compliance
  video.muted = true;

  // LISTEN / SILENCE toggle
  listenBtn.addEventListener('click', function () {
    soundOn = !soundOn;

    if (soundOn) {
      video.muted = false;
      video.volume = 1;
      listenLabel.textContent = 'SILENCE';
      dotRing.style.animationPlayState = 'paused';
      dotRing.style.opacity = '0';
    } else {
      video.muted = true;
      listenLabel.textContent = 'LISTEN';
      dotRing.style.animationPlayState = 'running';
      dotRing.style.opacity = '0.25';
    }
  });

  /* ═══════════════════════════════════════════
     OPT-IN FORM → ACTIVECAMPAIGN

     This posts to a small serverless function which holds the
     ActiveCampaign API key and adds the contact to the "Kim Castle
     General" list via the API. Source lives in the separate
     kimcastle-subscribe-api project; see its README to deploy.

     Paste the deployed URL below, e.g.
       https://kimcastle-subscribe-api.vercel.app/api/subscribe

     The key is deliberately NOT here. Anything in this file is visible
     to every visitor via View Source, and an exposed key would grant
     read/write access to every list in the ActiveCampaign account.

     While this is blank the form reports sign-up as unavailable rather
     than pretending a submission worked.
     ═══════════════════════════════════════════ */
  const SUBSCRIBE_ENDPOINT = '/optin.php';

  /* Google reCAPTCHA v3 — invisible, no checkbox, so the page design is
     untouched. Paste the SITE key here; it is public and safe in this file.
     The SECRET key goes on the server, in /www/secrets/kimcastle-ac.php.
     Register the site at https://www.google.com/recaptcha/admin (choose v3).

     While this is blank the form still works, just without reCAPTCHA. */
  const RECAPTCHA_SITE_KEY = '6LfTsK8tAAAAAJfnKvOZ8LiVjxaoa9q87itNHL-Z';

  const optinForm = document.getElementById('optin-form');
  const optinLabel = document.getElementById('optin-label');
  const optinMessage = document.getElementById('optin-message');
  const btnIn = document.getElementById('btn-in');
  const firstNameInput = document.getElementById('first-name');
  const lastNameInput = document.getElementById('last-name');
  const emailInput = document.getElementById('email');
  const honeypotInput = document.getElementById('website-url');
  const formInputs = [firstNameInput, lastNameInput, emailInput];

  const SUBMIT_TIMEOUT_MS = 12000;

  /* Load reCAPTCHA only when a key is configured, so no third-party script
     is pulled in (and no cookie set) until it is actually in use. */
  if (RECAPTCHA_SITE_KEY) {
    const recaptchaScript = document.createElement('script');
    recaptchaScript.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(RECAPTCHA_SITE_KEY);
    recaptchaScript.async = true;
    document.head.appendChild(recaptchaScript);
  }

  /* Resolves with a token, or with '' if reCAPTCHA is unconfigured or fails
     to load. The server decides whether a missing token is fatal — that way
     a blocked script never silently disables the protection. */
  function recaptchaToken() {
    if (!RECAPTCHA_SITE_KEY || !window.grecaptcha || !window.grecaptcha.execute) {
      return Promise.resolve('');
    }
    return new Promise(function (resolve) {
      window.grecaptcha.ready(function () {
        window.grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'subscribe' })
          .then(resolve, function () { resolve(''); });
      });
    });
  }

  function showMessage(text, isError) {
    optinMessage.textContent = text;
    optinMessage.classList.toggle('is-error', Boolean(isError));
  }

  function clearMessage() {
    optinMessage.textContent = '';
    optinMessage.classList.remove('is-error');
  }

  // Clear the error state on a field as soon as the visitor corrects it
  formInputs.forEach(function (input) {
    input.addEventListener('input', function () {
      input.classList.remove('is-invalid');
      clearMessage();
    });
  });

  function validate() {
    formInputs.forEach(i => i.classList.remove('is-invalid'));

    const firstName = firstNameInput.value.trim();
    const lastName = lastNameInput.value.trim();
    const email = emailInput.value.trim();

    if (!firstName) {
      firstNameInput.classList.add('is-invalid');
      return { ok: false, message: 'Please add your first name.', focus: firstNameInput };
    }
    if (!lastName) {
      lastNameInput.classList.add('is-invalid');
      return { ok: false, message: 'Please add your last name.', focus: lastNameInput };
    }
    if (!email) {
      emailInput.classList.add('is-invalid');
      return { ok: false, message: 'Please add your email address.', focus: emailInput };
    }
    // Deliberately permissive: one @, a dot in the domain, no spaces.
    // Real deliverability is confirmed by ActiveCampaign's own opt-in email.
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
      emailInput.classList.add('is-invalid');
      return { ok: false, message: 'That email address doesn\'t look right.', focus: emailInput };
    }

    return { ok: true, data: { firstname: firstName, lastname: lastName, email: email } };
  }

  /* Posts to our own server, which relays to the ActiveCampaign API.
     Same origin, so no CORS workarounds — and unlike a cross-origin
     POST we can actually read the reply and report real failures.
     Resolves only on a genuine success; rejects with the server's own
     visitor-facing message when there is one. */
  function postSubscription(data) {
    const controller = new AbortController();
    const timer = setTimeout(function () { controller.abort(); }, SUBMIT_TIMEOUT_MS);

    return fetch(SUBSCRIBE_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
      signal: controller.signal
    })
      .then(function (response) {
        // A non-JSON reply means something upstream is misconfigured
        // (e.g. PHP not executing) — treat it as a failure, never a success.
        return response.json()
          .catch(function () { throw new Error('badresponse'); })
          .then(function (payload) {
            if (!response.ok || !payload || payload.ok !== true) {
              throw new Error((payload && payload.error) || 'rejected');
            }
            return payload;
          });
      })
      .catch(function (error) {
        if (error.name === 'AbortError') throw new Error('timeout');
        if (error instanceof TypeError) throw new Error('network');
        throw error;
      })
      .finally(function () { clearTimeout(timer); });
  }

  optinForm.addEventListener('submit', function (event) {
    event.preventDefault();

    const result = validate();
    if (!result.ok) {
      showMessage(result.message, true);
      if (result.focus) result.focus.focus();
      return;
    }

    if (!SUBSCRIBE_ENDPOINT) {
      showMessage('Sign-up is temporarily unavailable. Please try again later.', true);
      return;
    }

    btnIn.disabled = true;
    btnIn.textContent = 'Sending';
    clearMessage();

    recaptchaToken()
      .then(function (token) {
        const payload = {
          firstname: result.data.firstname,
          lastname: result.data.lastname,
          addr: result.data.email,   // see optin.php: 'email' as a field name is blocked upstream
          website: honeypotInput ? honeypotInput.value : ''
        };
        if (token) payload.recaptcha_token = token;
        return postSubscription(payload);
      })
      .then(function () {
        formInputs.forEach(i => i.value = '');
        optinLabel.textContent = 'You\'re in. Welcome.';
        optinForm.style.display = 'none';
        clearMessage();
      })
      .catch(function (error) {
        btnIn.disabled = false;
        btnIn.textContent = 'I\'m In';

        const generic = 'Something went wrong on our end. Please try again.';
        const opaque = ['timeout', 'network', 'badresponse', 'rejected'];
        // The server sends messages already written for visitors, so pass
        // those straight through; fall back to generic for transport faults.
        showMessage(opaque.indexOf(error.message) === -1 ? error.message : generic, true);
      });
  });

});
