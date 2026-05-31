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

  // Form submission placeholder
  const btnIn = document.getElementById('btn-in');
  const formInputs = document.querySelectorAll('.optin-form input');
  const optinLabel = document.getElementById('optin-label');

  btnIn.addEventListener('click', function () {
    const firstName = document.getElementById('first-name').value.trim();
    const lastName = document.getElementById('last-name').value.trim();
    const email = document.getElementById('email').value.trim();

    if (firstName && lastName && email) {
      // Replace with your email service integration
      formInputs.forEach(i => i.value = '');
      optinLabel.textContent = 'You\'re in. Welcome.';
      btnIn.style.display = 'none';
    }
  });

});
