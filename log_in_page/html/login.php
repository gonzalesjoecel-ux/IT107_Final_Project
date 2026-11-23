<?php
/** @var PDO $pdo (from db.php via includes/auth.php) */
/*
 * login.php â€” public Home page and the first step of log-in
 * (handout section 8).
 *
 *   1. username + password + CAPTCHA
 *   2. if the password is correct, email a log-in OTP
 *   3. verify_otp.php creates the session only after the OTP is correct
 *
 * Nothing here creates a session by itself.
 */
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';       // db, session, helpers
require_once __DIR__ . '/includes/activity.php';
require __DIR__ . '/includes/security.php';   // lockout tiers, IP blocks, alerts
require __DIR__ . '/includes/layout.php';

/* Already logged in? Go straight to the dashboard. */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    redirect(url('user_dashboard.php'));
}

$errorMsg         = '';
$captchaErr       = '';
$passFieldInvalid = false;
$lockSecondsLeft  = 0;
$lockReason       = 'account';
$showForgotLink   = false;
$failureCount     = 0;

$justRegistered = isset($_GET['registered']);
$justVerified   = isset($_GET['verified']);
$justChanged    = isset($_GET['changed']);
$justExpired    = isset($_GET['expired']);
$wasDisabled    = isset($_GET['disabled']);

$ipBlocked = ip_block_state($pdo, client_ip());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $enteredName = trim((string)($_POST['username'] ?? ''));
    $passwordRaw = (string)($_POST['password'] ?? '');

    if ($ipBlocked) {

        $lockReason      = 'ip';
        $lockSecondsLeft = ip_block_seconds($pdo);
        $errorMsg        = 'Too many failed attempts from this network. Please wait '
                         . $lockSecondsLeft . ' seconds before trying again.';

    } elseif (!csrf_check($_POST['csrf_token'] ?? '')) {

        $errorMsg = 'The form has expired. Refresh the page and try again.';

    } elseif (!captcha_check($_POST['captcha'] ?? '', 'login')) {

        /*
         * YOUR CALL (handout section 7): a wrong CAPTCHA is NOT counted as a
         * failed log-in. It proves nothing about the password, and counting it
         * would let a bot lock a real account by submitting bad CAPTCHA text.
         */
        $captchaErr = 'The CAPTCHA code is incorrect. Please try again.';
        $errorMsg   = $captchaErr;

    } elseif ($enteredName === '' || $passwordRaw === '') {

        $errorMsg = 'Please fill in both fields.';

    } else {

        try {
            $stmt = $pdo->prepare(
                'SELECT id, username, fullname, email, password_hash, failed_attempts,
                        locked_until, lockout_level, role, is_active, is_verified, last_login
                 FROM users
                 WHERE username = ? OR email = ?
                 LIMIT 1
                 FOR UPDATE'
            );

            $pdo->beginTransaction();
            $stmt->execute([$enteredName, $enteredName]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $pdo->commit();

            if (!$user) {

                /* Same message as a wrong password: no username enumeration. */
                log_event($pdo, null, 'login_fail', 'unknown user');
                record_ip_failure($pdo, client_ip());

                $_SESSION['login_fail_count'] = (int)($_SESSION['login_fail_count'] ?? 0) + 1;
                $failureCount = (int)$_SESSION['login_fail_count'];
                $showForgotLink = $failureCount >= FORGOT_AFTER_FAILURES;

                $errorMsg = 'Invalid username or password.';
                $passFieldInvalid = true;

            } else {

                $_SESSION['last_login_name'] = $enteredName;

                $failureCount = max(
                    (int)($user['failed_attempts'] ?? 0),
                    (int)($_SESSION['login_fail_count'] ?? 0)
                );

                $lockSecondsLeft = user_lock_seconds($user);

                if ($lockSecondsLeft > 0) {

                    $lockReason = 'account';
                    log_event($pdo, (int)$user['id'], 'login_fail', 'while locked');
                    $errorMsg = 'Account is locked due to too many failed attempts. Please wait '
                              . $lockSecondsLeft . ' seconds.';
                    $passFieldInvalid = true;
                    $showForgotLink = true;

                } elseif (!password_verify($passwordRaw, $user['password_hash'])) {

                    $result = apply_failed_attempt($pdo, $user, 'wrong password');

                    $_SESSION['login_fail_count'] = max(
                        (int)($_SESSION['login_fail_count'] ?? 0) + 1,
                        $result['attempts']
                    );
                    $failureCount   = (int)$_SESSION['login_fail_count'];
                    $showForgotLink = $failureCount >= FORGOT_AFTER_FAILURES;

                    if ($result['locked']) {
                        $lockReason      = 'account';
                        $lockSecondsLeft = $result['seconds'];
                        $errorMsg = 'Account is locked due to too many failed attempts. Please wait '
                                  . $lockSecondsLeft . ' seconds.';
                    } else {
                        $remaining = 3 - ($result['attempts'] % 3);
                        if ($remaining === 3) {
                            $remaining = 3;
                        }
                        $errorMsg = 'Invalid username or password. '
                                  . ($remaining === 1
                                        ? '1 attempt remains before a short lock.'
                                        : $remaining . ' attempts remain before a short lock.');
                    }

                    $passFieldInvalid = true;

                } else {

                    /* ---------- password is correct: OTP is required next ---------- */

                    if ((int)$user['is_active'] !== 1) {
                        $errorMsg = 'This account has been disabled. Please contact the administrator.';
                        $passFieldInvalid = true;
                    } elseif ((int)($user['is_verified'] ?? 1) !== 1) {

                        /* Unverified account: send a fresh registration code. */
                        $issue = otp_issue(
                            $pdo,
                            (int)$user['id'],
                            (string)$user['username'],
                            'registration',
                            (string)$user['email'],
                            (string)$user['fullname']
                        );

                        if (!$issue['ok']) {
                            $errorMsg = $issue['error'];
                            $passFieldInvalid = true;
                        } else {
                            pending_otp_set([
                                'user_id'    => (int)$user['id'],
                                'identifier' => (string)$user['username'],
                                'purpose'    => 'registration',
                                'email'      => (string)$user['email'],
                                'name'       => (string)$user['fullname'],
                            ]);
                            redirect(url('verify_otp.php'));
                        }

                    } else {

                        $issue = otp_issue(
                            $pdo,
                            (int)$user['id'],
                            (string)$user['username'],
                            'login',
                            (string)$user['email'],
                            (string)$user['fullname']
                        );

                        if (!$issue['ok']) {
                            $errorMsg = $issue['error'];
                            $passFieldInvalid = true;
                        } else {
                            pending_otp_set([
                                'user_id'    => (int)$user['id'],
                                'identifier' => (string)$user['username'],
                                'purpose'    => 'login',
                                'email'      => (string)$user['email'],
                                'name'       => (string)$user['fullname'],
                            ]);
                            redirect(url('verify_otp.php'));
                        }
                    }
                }
            }

        } catch (Throwable $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('login: ' . $ex->getMessage());
            $errorMsg = 'There was a problem logging in. Please try again.';
        }
    }

} else {

    /*
     * A reload keeps the countdown for the last account that was tried.
     * The lock itself is always read from the database, never from a cookie.
     */
    $lockCheckName = $_SESSION['last_login_name'] ?? null;
    if ($lockCheckName) {
        $s = $pdo->prepare(
            'SELECT id, failed_attempts, locked_until FROM users
             WHERE username = ? OR email = ? LIMIT 1'
        );
        $s->execute([$lockCheckName, $lockCheckName]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $lockSecondsLeft = user_lock_seconds($row);
            $failureCount = max(
                (int)$row['failed_attempts'],
                (int)($_SESSION['login_fail_count'] ?? 0)
            );
        }
    }

    if ($ipBlocked) {
        $lockReason      = 'ip';
        $lockSecondsLeft = max($lockSecondsLeft, ip_block_seconds($pdo));
    }

    $showForgotLink = $failureCount >= FORGOT_AFTER_FAILURES || $lockSecondsLeft > 0;
}

$lockPrefix = $lockReason === 'ip'
    ? 'Too many failed attempts from this network. Please wait'
    : 'Account is locked. Please wait';

$pageJs = '
(function () {
  var seconds = ' . (int)$lockSecondsLeft . ';
  var prefix  = ' . json_encode($lockPrefix, JSON_UNESCAPED_UNICODE) . ';
  var errorBox = document.getElementById("login-error");
  var form     = document.getElementById("login-form");
  var signup   = document.getElementById("signup-link");
  var locked   = seconds > 0;

  function setLocked(state) {
    locked = state;
    if (form) {
      form.querySelectorAll("input, button").forEach(function (el) { el.disabled = state; });
    }
    if (signup) {
      signup.classList.toggle("is-disabled", state);
      signup.setAttribute("aria-disabled", state ? "true" : "false");
    }
  }

  if (signup) {
    signup.addEventListener("click", function (ev) {
      if (locked) {
        ev.preventDefault();
        if (errorBox) {
          errorBox.hidden = false;
          errorBox.className = "site-alert site-alert--err";
          errorBox.textContent = "You cannot register while this sign-in is locked.";
        }
      }
    });
  }

  if (seconds > 0) {
    setLocked(true);

    (function tick() {
      if (seconds <= 0) {
        setLocked(false);
        if (errorBox) {
          errorBox.textContent = "You may try logging in again now.";
          errorBox.className = "site-alert site-alert--ok";
        }
        return;
      }
      if (errorBox) {
        errorBox.textContent = prefix + " " + seconds + " seconds.";
        errorBox.className = "site-alert site-alert--err";
        errorBox.hidden = false;
      }
      seconds--;
      setTimeout(tick, 1000);
    })();
  }
})();
';

render_public_header('Log in', 'login');
?>

<!-- ===================== HOME: hero (left) + log-in form (right) ===================== -->
<section class="auth" id="home">

  <?php render_auth_hero("Welcome back. Log in to keep your data locked behind Encryptica's encryption."); ?>

  <div class="auth__panel">
    <div class="auth__card" id="auth-card">

      <?php render_auth_brand(); ?>

      <div class="auth__view auth__view--login">
        <h1 class="auth__title">Log-in</h1>

        <form id="login-form" method="POST" action="<?= e(url('login.php')) ?>" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="login" />

          <div id="login-error" class="site-alert site-alert--err" role="alert"<?= $errorMsg ? '' : ' hidden' ?>><?= e($errorMsg) ?></div>

          <?php if ($justRegistered && !$errorMsg): ?>
            <div class="site-alert site-alert--info" role="status">Account created. Check your email for the six-digit code to verify your account.</div>
          <?php endif; ?>
          <?php if ($justVerified && !$errorMsg): ?>
            <div class="site-alert site-alert--ok" role="status">Your email address is verified. You can log in now.</div>
          <?php endif; ?>
          <?php if ($justChanged && !$errorMsg): ?>
            <div class="site-alert site-alert--ok" role="status">Successfully Change Password. You can log in with your new password.</div>
          <?php endif; ?>
          <?php if ($justExpired && !$errorMsg): ?>
            <div class="site-alert site-alert--info" role="status">You were logged out because you were inactive for <?= IDLE_TIMEOUT_MINUTES ?> minutes. Please log in again.</div>
          <?php endif; ?>
          <?php if ($wasDisabled && !$errorMsg): ?>
            <div class="site-alert site-alert--err" role="alert">This account has been disabled. Please contact the administrator.</div>
          <?php endif; ?>

          <div class="site-field">
            <label for="login-name">Username or email <span class="site-field__req">*</span></label>
            <input class="site-input" type="text" id="login-name" name="username" required maxlength="150"
                   placeholder="Enter username or email" autocomplete="username" />
          </div>

          <div class="site-field">
            <label for="login-password">Password <span class="site-field__req">*</span></label>
            <div class="site-input-wrap">
              <input class="site-input<?= $passFieldInvalid ? ' site-input--invalid' : '' ?>" type="password"
                     id="login-password" name="password" required placeholder="Enter your password"
                     autocomplete="current-password" aria-invalid="<?= $passFieldInvalid ? 'true' : 'false' ?>" />
              <button class="site-eye" type="button" data-toggle-password="#login-password" aria-pressed="false">Show</button>
            </div>
          </div>

          <?php render_captcha('login-captcha', 'login', $captchaErr); ?>

          <div class="auth__stack">
            <button class="site-btn site-btn--primary site-btn--block" type="submit">Log-in</button>
            <?php if ($showForgotLink): ?>
              <a class="site-btn site-btn--block" id="forgot-link" href="<?= e(url('forgot.php')) ?>">Forgot Password? Reset Here</a>
            <?php endif; ?>
          </div>

          <?php if (!$showForgotLink): ?>
            <p class="auth__forgot">
              <a class="site-textlink" id="forgot-password-link" href="<?= e(url('forgot.php')) ?>">Forgot password?</a>
            </p>
          <?php endif; ?>
        </form>

        <p class="auth__foot">
          Don't have an account?
          <a class="site-textlink" id="signup-link" href="<?= e(url('signup.php')) ?>">Please register here</a>
        </p>
      </div>

    </div>
  </div>

</section>

<!-- ===================== ABOUT ===================== -->
<section class="site-band" id="about">
  <div class="site-section">
    <h2>About Encryptica</h2>
    <p class="site-section__lead">
      A personal vault for the things you don't want anyone else to read. Save notes, pictures and documents,
      and open them again only after you log in.
    </p>

    <div class="site-cards">
      <article class="site-feature">
        <div class="stat__icon stat__icon--teal"><?= icon('vault') ?></div>
        <h3>Your private vault</h3>
        <p>Keep notes and files in one place. Everything is stored encrypted, never as plain text.</p>
      </article>
      <article class="site-feature">
        <div class="stat__icon stat__icon--purple"><?= icon('file') ?></div>
        <h3>Pictures and documents</h3>
        <p>Attach images, PDFs, Word, Excel and PowerPoint files (up to 5 MB each) and download them anytime.</p>
      </article>
      <article class="site-feature">
        <div class="stat__icon stat__icon--pink"><?= icon('users') ?></div>
        <h3>Built for teams too</h3>
        <p>Administrators can watch sign-ins and unlock accounts, but they only see counts, never your content.</p>
      </article>
    </div>
  </div>
</section>

<!-- ===================== SECURITY ===================== -->
<section class="site-band" id="security">
  <div class="site-section">
    <h2>How your account is protected</h2>
    <p class="site-section__lead">Security is on by default. You don't have to turn anything on.</p>

    <ol class="site-steps">
      <li>
        <h3>Encrypted before saving</h3>
        <p>Your notes and files are encrypted with AES-256-GCM before they reach the database or the disk.</p>
      </li>
      <li>
        <h3>Passwords are hashed</h3>
        <p>Your password is never stored as text, so even the administrator can't read it.</p>
      </li>
      <li>
        <h3>Password plus email code</h3>
        <p>After your password, a six-digit code is sent to your email. No session is created until that code is correct.</p>
      </li>
      <li>
        <h3>Progressive lockout</h3>
        <p>Three, six and nine wrong attempts each trigger a longer lock, and the owner is warned by email.</p>
      </li>
      <li>
        <h3>Automatic log-out</h3>
        <p>After <?= IDLE_TIMEOUT_MINUTES ?> minutes with no activity you are logged out, so a shared computer stays safe.</p>
      </li>
    </ol>
  </div>
</section>

<?php render_public_footer($pageJs); ?>
