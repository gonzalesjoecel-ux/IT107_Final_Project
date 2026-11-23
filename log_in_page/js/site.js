/* site.js — small helpers for the public pages */
(function () {

  /* Mobile menu */
  var toggle = document.querySelector('[data-nav-toggle]');
  var menu = document.getElementById('nav-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var open = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    menu.addEventListener('click', function (e) {
      if (e.target.closest('a')) {
        menu.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* On the Home page the lock fades out while you scroll down to the other sections */
  var lock = document.querySelector('.bg__lock');
  if (lock && document.body.classList.contains('page--home')) {
    var ticking = false;
    var update = function () {
      var p = Math.min(1, window.scrollY / (window.innerHeight * 0.75));
      lock.style.opacity = (0.95 * (1 - p)).toFixed(3);
      ticking = false;
    };
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }, { passive: true });
    update();
  }

  /* Show / hide password */
  document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-toggle-pw'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  });

  /* Password strength bar (sign up page) */
  document.querySelectorAll('[data-meter]').forEach(function (input) {
    var meter = document.getElementById(input.getAttribute('data-meter'));
    if (!meter) return;
    var text = meter.querySelector('.meter__text');
    var labels = ['Use letters and numbers', 'Weak', 'Fair', 'Good', 'Strong'];

    input.addEventListener('input', function () {
      var v = input.value, score = 0;
      if (v.length >= 8) score++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
      if (/\d/.test(v) && /[A-Za-z]/.test(v)) score++;
      if (v.length >= 12 || /[^A-Za-z0-9]/.test(v)) score++;
      if (v.length === 0) score = 0;
      meter.setAttribute('data-level', String(score));
      if (text) text.textContent = labels[score];
    });
  });

  /* "Confirm password" live check */
  document.querySelectorAll('[data-match]').forEach(function (input) {
    var other = document.getElementById(input.getAttribute('data-match'));
    var fld = input.closest('.fld');
    var msg = fld ? fld.querySelector('.fld__msg') : null;
    if (!other || !fld || !msg) return;

    function check() {
      var bad = input.value !== '' && input.value !== other.value;
      fld.classList.toggle('fld--invalid', bad);
      msg.textContent = bad ? 'The two passwords do not match.' : '';
    }
    input.addEventListener('input', check);
    other.addEventListener('input', check);
  });

})();