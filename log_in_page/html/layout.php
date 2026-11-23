<?php
/*
 * layout.php — everything that draws the SCREEN.
 *
 *  Logged-in pages (user/ and admin/):
 *      render_header('Dashboard', 'dashboard');   // 2nd = which sidebar link is highlighted
 *      ... page content ...
 *      render_footer();
 *
 *  Login / Sign up pages (split screen):
 *      define('PUBLIC_PAGE', true);               // before requiring auth.php
 *      render_public_header('Log in', 'login');
 *      ... <section class="auth"> render_auth_hero(...) + the form panel </section> ...
 *      render_public_footer();                    // optional: render_public_footer($extraJs)
 *
 *  Light / dark mode lives here too (theme_boot_script, theme_toggle_button, site_theme_js)
 *  and its colors live in css/app.css (section 1).
 */

/* Cache-busting version for CSS/JS so browsers pick up changes. */
function asset_version(string $relative): int {
    $path = dirname(__DIR__) . '/' . ltrim($relative, '/');
    $time = @filemtime($path);
    return $time ?: 1;
}

/* Small line icons (24x24) */
function icon(string $name): string {
    $paths = [
        'home'     => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'vault'    => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5"/><path d="M16 4.5a3.5 3.5 0 010 7"/><path d="M18 14.8c2.4.6 4 2.2 4 5.2"/>',
        'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'shield'   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'logout'   => '<path d="M9 21H5V3h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'file'     => '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4"/>',
        'storage'  => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'alert'    => '<path d="M12 3l10 18H2z"/><path d="M12 10v5"/><path d="M12 18h.01"/>',
        'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M12 15v2"/><path d="M8 11V8a4 4 0 018 0"/>',
        'key'      => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9"/><path d="M16 7l3 3"/>',
        'check'    => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'     => '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/* ---------------------------------------------------------------------
 * LIGHT / DARK MODE
 * The choice is saved in the browser (localStorage "enc-theme"), so each
 * user keeps the mode he picked on this device. Default = dark.
 * ------------------------------------------------------------------- */

/* Goes in <head>, BEFORE the css, so the page never flashes the wrong color */
function theme_boot_script(): string {
    return '<script>(function(){var t="dark";try{var s=localStorage.getItem("enc-theme");if(s==="light"||s==="dark"){t=s}}catch(e){}document.documentElement.setAttribute("data-theme",t)})();</script>';
}

/* The sun / moon button. $wide = the long version with a text label (sidebar) */
function theme_toggle_button(bool $wide = false): string {
    return '<button type="button" class="theme-toggle' . ($wide ? ' theme-toggle--wide' : '') . '" data-theme-toggle aria-pressed="false" aria-label="Switch theme">'
         . '<span class="ti ti--sun">' . icon('sun') . '</span><span class="ti ti--moon">' . icon('moon') . '</span>'
         . ($wide ? '<span class="theme-toggle__label" data-theme-label>Light mode</span>' : '')
         . '</button>';
}

function site_theme_js(): string {
    return <<<'JS'
(function () {
  var KEY = 'enc-theme', root = document.documentElement;
  var buttons = document.querySelectorAll('[data-theme-toggle]');

  function sync() {
    var light = root.getAttribute('data-theme') === 'light';
    buttons.forEach(function (b) {
      var text = light ? 'Switch to dark mode' : 'Switch to light mode';
      b.setAttribute('aria-pressed', light ? 'true' : 'false');
      b.setAttribute('aria-label', text);
      b.title = text;
      var label = b.querySelector('[data-theme-label]');
      if (label) { label.textContent = light ? 'Dark mode' : 'Light mode'; }
    });
  }

  buttons.forEach(function (b) {
    b.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem(KEY, next); } catch (e) {}
      sync();
    });
  });

  sync();
})();
JS;
}

/* The moving binary numbers (login / signup hero). The lock is separate and never moves. */
function site_background_js(): string {
    return <<<'JS'
(function () {
  var host = document.getElementById('site-digits');
  if (!host) { return; }
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var COL_W = 26, LINE_H = 20;

  function build() {
    host.innerHTML = '';
    var w = host.clientWidth  || window.innerWidth;
    var h = host.clientHeight || window.innerHeight;
    var cols  = Math.ceil(w / COL_W) + 1;
    var lines = Math.ceil(h / LINE_H) + 2;

    for (var i = 0; i < cols; i++) {
      var txt = '';
      for (var j = 0; j < lines; j++) { txt += (Math.random() < 0.5 ? '0' : '1') + '\n'; }

      var col = document.createElement('div');
      col.className = 'site-digits__col';
      col.style.left = (i * COL_W) + 'px';
      col.style.opacity = (0.45 + Math.random() * 0.55).toFixed(2);
      col.textContent = txt + txt;                       /* twice = seamless loop */

      if (!reduce) {
        var dur = 55 + Math.random() * 75;               /* 55-130 seconds per loop = slow and calm */
        col.style.animationDuration = dur.toFixed(1) + 's';
        col.style.animationDelay = '-' + (Math.random() * dur).toFixed(1) + 's';
      } else {
        col.style.animation = 'none';
      }
      host.appendChild(col);
    }
  }

  build();
  var timer;
  window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(build, 250); });
})();
JS;
}

/* =====================================================================
 * LOGGED-IN PAGES (sidebar + content)
 * ===================================================================== */

function render_header(string $title, string $active = ''): void {
    global $user;

    $isAdmin = ($user['role'] ?? '') === 'admin';
    $name    = $user['fullname'] ?: $user['username'];
    $initial = mb_strtoupper(mb_substr($name, 0, 1));

    /* sidebar links: [label, icon, url, key] */
    $groups = [];
    if ($isAdmin) {
        $groups['Admin'] = [
            ['Overview', 'shield',   url('admin_dashboard.php'), 'a-overview'],
            ['Users',    'users',    url('users.php'),     'a-users'],
            ['Activity', 'activity', url('activity.php'),  'a-activity'],
        ];
        $groups['My account'] = [
            ['My vault', 'vault', url('user_dashboard.php'), 'dashboard'],
            ['Profile',  'user',  url('profile.php'),   'profile'],
        ];
    } else {
        $groups['Menu'] = [
            ['Dashboard', 'home', url('user_dashboard.php'), 'dashboard'],
            ['Profile',   'user', url('profile.php'),   'profile'],
        ];
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="color-scheme" content="dark light" />
  <title><?= e($title) ?> — Encryptica</title>

  <?= theme_boot_script() ?>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" />
  <link rel="stylesheet" href="<?= e(CSS_URL) ?>/app.css?v=<?= asset_version('css/app.css') ?>" />
</head>
<body>
<div class="app">

  <aside class="side">
    <a class="side__brand" href="<?= e(url($isAdmin ? 'admin/dashboard.php' : 'user/dashboard.php')) ?>">
      Encryptica<span>.</span>
    </a>

    <nav class="side__nav" aria-label="Main">
      <?php foreach ($groups as $label => $links): ?>
        <div class="side__label"><?= e($label) ?></div>
        <?php foreach ($links as [$text, $ico, $href, $key]): ?>
          <a class="side__link<?= $key === $active ? ' is-active' : '' ?>" href="<?= e($href) ?>"
             <?= $key === $active ? 'aria-current="page"' : '' ?>>
            <?= icon($ico) ?><span><?= e($text) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="side__foot">
      <div class="chip">
        <div class="chip__avatar" aria-hidden="true"><?= e($initial) ?></div>
        <div class="chip__text">
          <div class="chip__name"><?= e($name) ?></div>
          <div class="chip__sub"><?= $isAdmin ? 'Administrator' : '@' . e($user['username']) ?></div>
        </div>
      </div>

      <?= theme_toggle_button(true) ?>

      <?php if (!$isAdmin): ?>
        <div class="side__timer">Auto log out in <span id="idle-left">--:--</span></div>
      <?php endif; ?>

      <form method="POST" action="<?= e(url('logout.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
        <button class="dash-btn side__logout" type="submit"><?= icon('logout') ?> Log out</button>
      </form>
    </div>
  </aside>

  <div class="main">
    <div class="main__inner">
<?php
}

function render_footer(): void {
    global $user;
    $isAdmin = ($user['role'] ?? '') === 'admin';
    ?>
    </div>
  </div>
</div>

<script>
<?php if (!$isAdmin): ?>
(function () {
  /* Idle countdown in the sidebar */
  var left = <?= (int)IDLE_TIMEOUT ?>;
  var el = document.getElementById('idle-left');
  var loginUrl = <?= json_encode(url('login.php?expired=1')) ?>;

  function fmt(s) {
    var m = Math.floor(s / 60), r = s % 60;
    return (m < 10 ? '0' : '') + m + ':' + (r < 10 ? '0' : '') + r;
  }
  el.textContent = fmt(left);

  setInterval(function () {
    left--;
    if (left <= 0) { window.location.href = loginUrl; return; }
    el.textContent = fmt(left);
    /* Warn during the last minute (handout section 8.4). */
    el.parentElement.classList.toggle('is-warn', left <= 60);
    if (left === 60) {
      el.parentElement.textContent = 'Auto log out in 1 minute: ' + fmt(left);
      el.parentElement.appendChild(el);
    }
  }, 1000);
})();
<?php endif; ?>

<?= site_theme_js() ?>
</script>
</body>
</html>
<?php
}

/* ---------- small UI helpers used by the admin pages ---------- */

/* ['Active', 'ok'] / ['Locked', 'warn'] / ['Disabled', 'bad'] */
function user_status(array $u): array {
    if ((int)($u['is_active'] ?? 1) !== 1) {
        return ['Disabled', 'bad'];
    }
    if (!empty($u['locked_until']) && strtotime($u['locked_until']) > time()) {
        return ['Locked', 'warn'];
    }
    return ['Active', 'ok'];
}

function when(?string $dt, string $fmt = 'M j, g:i A'): string {
    return $dt ? date($fmt, strtotime($dt)) : '—';
}

/* A list of activity_log rows. Each row needs: event, detail, ip, created_at, user_id, username */
function render_feed(array $rows, bool $showUser = true): void {
    if (!$rows) {
        echo '<p class="muted">No activity yet.</p>';
        return;
    }

    echo '<ul class="feed">';
    foreach ($rows as $r) {
        [$label, $tone] = event_info((string)$r['event']);

        echo '<li class="feed__item"><span class="dot dot--' . e($tone) . '"></span><div>';
        echo '<div class="feed__text">';
        if ($showUser) {
            if (!empty($r['user_id']) && !empty($r['username'])) {
                echo '<a class="feed__user" href="' . e(url('user_view.php?id=' . (int)$r['user_id'])) . '">@' . e($r['username']) . '</a> ';
            } else {
                echo '<span class="feed__user">Unknown user</span> ';
            }
        }
        echo e($label);
        if (!empty($r['detail'])) {
            echo ' <span class="muted">(' . e($r['detail']) . ')</span>';
        }
        echo '</div><div class="feed__meta">' . e(when($r['created_at'], 'M j, Y g:i:s A'));
        if (!empty($r['ip'])) {
            echo ' · ' . e($r['ip']);
        }
        echo '</div></div></li>';
    }
    echo '</ul>';
}

/* Previous / Next buttons. $path like 'users.php', $params = other query values to keep */
function render_pager(int $page, int $pages, string $path, array $params = []): void {
    if ($pages <= 1) {
        return;
    }
    $link = function (int $p) use ($path, $params): string {
        return url($path) . '?' . http_build_query($params + ['page' => $p]);
    };

    echo '<nav class="pager" aria-label="Pages">';
    echo $page > 1
        ? '<a class="dash-btn dash-btn--sm" href="' . e($link($page - 1)) . '">&larr; Prev</a>'
        : '<span class="dash-btn dash-btn--sm is-disabled">&larr; Prev</span>';
    echo '<span class="pager__info">Page ' . $page . ' of ' . $pages . '</span>';
    echo $page < $pages
        ? '<a class="dash-btn dash-btn--sm" href="' . e($link($page + 1)) . '">Next &rarr;</a>'
        : '<span class="dash-btn dash-btn--sm is-disabled">Next &rarr;</span>';
    echo '</nav>';
}

/* =====================================================================
 * LOGIN / SIGN UP PAGES (split screen: hero on the left, form on the right)
 * ===================================================================== */

function render_public_header(string $title, string $active = ''): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="color-scheme" content="dark light" />
  <title><?= e($title) ?> — Encryptica</title>

  <?= theme_boot_script() ?>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" />
  <link rel="stylesheet" href="<?= e(CSS_URL) ?>/app.css?v=<?= asset_version('css/app.css') ?>" />
</head>
<body class="site">

<a class="site-masthead" href="<?= e(url('login.php')) ?>">Encryptica</a>

<nav class="auth__top" aria-label="Main">
  <?php
  $links = [['Home', url('login.php')]];
  if ($active === 'signup') {
      $links[] = ['Log-in', url('login.php')];
  } elseif ($active === 'login') {
      $links[] = ['Register', url('signup.php')];
  } else {
      $links[] = ['Register', url('signup.php')];
      $links[] = ['Log-in', url('login.php')];
  }
  ?>
  <?php foreach ($links as [$text, $href]): ?>
    <a class="auth__link" href="<?= e($href) ?>"><?= e($text) ?></a>
  <?php endforeach; ?>
  <?= theme_toggle_button() ?>
</nav>

<main>
<?php
}

/* "Encryptica ✳" shown above the form */
function render_auth_brand(): void {
    ?>
<div class="auth__brand">Encryptica
  <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2v6M12 16v6M2 12h6M16 12h6M5 5l4 4M15 15l4 4M19 5l-4 4M9 15l-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
</div>
<?php
}

/* The padlock. It never moves — only the numbers behind it do. */
function render_public_lock(): void {
    ?>
<svg class="site-lock" viewBox="0 0 200 220" fill="none" role="img" aria-label="Padlock">
  <rect class="lock__body" x="40" y="90" width="120" height="100" rx="14" stroke-width="5"/>
  <path class="lock__shackle" d="M62 90V64a38 38 0 0 1 76 0v26" stroke-width="5" stroke-linecap="round"/>
  <circle class="lock__key" cx="100" cy="130" r="12"/>
  <rect class="lock__key" x="94" y="138" width="12" height="26" rx="4"/>
</svg>
<?php
}

/* Left half: glow + grid + moving numbers, steady lock, one short sentence */
function render_auth_hero(string $tagline): void {
    ?>
  <div class="auth__hero">
    <div class="site-digits" id="site-digits" aria-hidden="true"></div>
    <div class="auth__hero-inner">
      <?php render_public_lock(); ?>
      <p class="auth__tagline"><?= e($tagline) ?></p>
    </div>
  </div>
<?php
}

function render_public_footer(string $extraJs = '', ?string $scriptUrl = null): void {
    ?>
</main>

<footer class="site-footer">
  &copy; <?= date('Y') ?> Encryptica. All rights reserved.
</footer>

<script>
(function () {
  /* ---- Show / hide password buttons ---- */
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.querySelector(btn.getAttribute('data-toggle-password'));
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  });

  /* ---- CAPTCHA refresh button ---- */
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-captcha-refresh]');
    if (!btn) { return; }
    var img = document.getElementById(btn.getAttribute('data-captcha-refresh'));
    if (!img) { return; }
    var base = img.getAttribute('data-captcha-src') || img.src.split('?')[0];
    var slot = 'default';
    try { slot = new URL(img.src).searchParams.get('slot') || 'default'; } catch (err) {}
    img.src = base + '?slot=' + encodeURIComponent(slot) + '&t=' + Date.now();
  });
})();

<?= site_background_js() ?>

<?= site_theme_js() ?>

<?= $extraJs ?>
</script>
<?php if ($scriptUrl !== null && $scriptUrl !== ''): ?>
<script src="<?= e($scriptUrl) ?>"></script>
<?php endif; ?>
</body>
</html>
<?php
}