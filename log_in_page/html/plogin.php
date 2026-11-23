<?php
/** @var PDO $pdo (from db.php via includes/auth.php) */
/* login.php â€” the public Home page: nav bar, log-in form, About and Security sections */
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';      // db, session, helpers (no login needed here)
require_once __DIR__ . '/includes/activity.php';
require __DIR__ . '/includes/layout.php';    // public layout: nav bar + background

/* Already logged in? Go straight to the dashboard. */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    redirect(url('user_dashboard.php'));
}

$errorMsg = '';
$passFieldInvalid = false;
$justRegistered = isset($_GET['registered']);
$justExpired    = isset($_GET['expired']);
$wasDisabled    = isset($_GET['disabled']);

const MAX_ATTEMPTS = 5; // number of failed attempts before the account is locked
const LOCK_MINUTES = 1; // lock duration in minutes

/*
 * Called after a CORRECT password.
 * Starts the session and sends the user to their dashboard
 * (admin -> admin_dashboard.php, user -> user_dashboard.php).
 * Only returns (with an error message) if the account is disabled.
 */
function finish_login(PDO $pdo, array $user): ?string
{
    if ((int)$user['is_active'] !== 1) {
        log_event($pdo, (int)$user['id'], 'login_fail', 'account disabled');
        return 'This account has been disabled. Please contact the administrator.';
    }

    $pdo->prepare('UPDATE users SET last_login = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $user['id']]);

    log_event($pdo, (int)$user['id'], 'login_ok');

    unset($_SESSION['last_login_name']);
    setcookie('plogin_lock_user', '', time() - 3600, '/');

    /* New session id after login (stops session fixation) */
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['fullname']      = $user['fullname'];
    $_SESSION['prev_login']    = $user['last_login'];   // shown on the dashboard
    $_SESSION['last_activity'] = time();

    redirect($user['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php');
    return null; // never reached
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $enteredName = trim($_POST['username'] ?? '');
    $passwordRaw = $_POST['password'] ?? '';

    if (!csrf_check($_POST['csrf_token'] ?? '')) {

        $errorMsg = 'The form has expired. Refresh the page and try again.';

    } elseif ($enteredName === '' || $passwordRaw === '') {

        $errorMsg = 'Please fill in both fields.';

    } else {

        try {

            /*
             * Start transaction so only one login request
             * can check/update the account at a time.
             */
            $pdo->beginTransaction();

            /*
             * FOR UPDATE locks this user's row until the
             * transaction is finished.
             */
            $stmt = $pdo->prepare('
                SELECT
                    id,
                    username,
                    fullname,
                    password_hash,
                    failed_attempts,
                    locked_until,
                    role,
                    is_active,
                    last_login
                FROM users
                WHERE username = ? OR email = ?
                LIMIT 1
                FOR UPDATE
            ');

            $stmt->execute([$enteredName, $enteredName]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                $pdo->commit();

                log_event($pdo, null, 'login_fail', 'unknown user');

                /* Same message as a wrong password, so nobody can find out which usernames exist */
                $errorMsg = 'Incorrect username or password.';

            } else {

                /*
                 * Remember who was attempted, so a later reload
                 * (GET) can still show the countdown.
                 */
                $_SESSION['last_login_name'] = $enteredName;

                $now = new DateTime();

                /*
                 * CHECK IF ACCOUNT IS CURRENTLY LOCKED
                 */
                if (!empty($user['locked_until'])) {

                    $lockedUntil = new DateTime($user['locked_until']);

                    if ($now < $lockedUntil) {

                        $secondsLeft =
                            max(0, $lockedUntil->getTimestamp() - $now->getTimestamp());

                        $pdo->commit();

                        log_event($pdo, (int)$user['id'], 'login_fail', 'while locked');

                        $errorMsg =
                            "Account is locked due to too many failed attempts. Please wait {$secondsLeft} seconds.";

                        $passFieldInvalid = true;

                    } else {

                        /*
                         * Lock period is finished.
                         * Reset attempts so user can try again.
                         */
                        $stmt = $pdo->prepare('
                            UPDATE users
                            SET failed_attempts = 0,
                                locked_until = NULL
                            WHERE id = ?
                        ');

                        $stmt->execute([$user['id']]);

                        $user['failed_attempts'] = 0;
                        $user['locked_until'] = null;

                        /*
                         * Now check password normally.
                         */
                        if (password_verify($passwordRaw, $user['password_hash'])) {

                            $pdo->commit();

                            $loginError = finish_login($pdo, $user);

                            $errorMsg = (string)$loginError;
                            $passFieldInvalid = true;

                        } else {

                            /*
                             * First wrong attempt after lock expired.
                             */
                            $newAttempts = 1;

                            $stmt = $pdo->prepare('
                                UPDATE users
                                SET failed_attempts = ?
                                WHERE id = ?
                            ');

                            $stmt->execute([
                                $newAttempts,
                                $user['id']
                            ]);

                            $pdo->commit();

                            log_event($pdo, (int)$user['id'], 'login_fail', 'wrong password');

                            $remaining = MAX_ATTEMPTS - $newAttempts;

                            $errorMsg =
                                "Incorrect password. {$remaining} attempt(s) remaining.";

                            $passFieldInvalid = true;
                        }
                    }

                } else {

                    /*
                     * ACCOUNT IS NOT LOCKED
                     */

                    if (password_verify($passwordRaw, $user['password_hash'])) {

                        /*
                         * Correct login.
                         * Reset attempts.
                         */
                        $stmt = $pdo->prepare('
                            UPDATE users
                            SET failed_attempts = 0,
                                locked_until = NULL
                            WHERE id = ?
                        ');

                        $stmt->execute([$user['id']]);

                        $pdo->commit();

                        $loginError = finish_login($pdo, $user);

                        $errorMsg = (string)$loginError;
                        $passFieldInvalid = true;

                    } else {

                        /*
                         * WRONG PASSWORD
                         */
                        $newAttempts = (int)$user['failed_attempts'] + 1;

                        /*
                         * If this is the 5th wrong attempt,
                         * lock the account.
                         */
                        if ($newAttempts >= MAX_ATTEMPTS) {

                            $stmt = $pdo->prepare('
                                UPDATE users
                                SET failed_attempts = ?,
                                    locked_until = DATE_ADD(NOW(), INTERVAL 1 MINUTE)
                                WHERE id = ?
                            ');

                            $stmt->execute([
                                MAX_ATTEMPTS,
                                $user['id']
                            ]);

                            /*
                             * Calculate countdown.
                             */
                            $secondsLeft = LOCK_MINUTES * 60;

                            /*
                             * Keep $user in sync with the DB so the
                             * countdown block below still sees the lock
                             * within this same request.
                             */
                            $user['locked_until'] =
                                date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60);

                            /*
                             * Remember the locked account in a cookie too,
                             * so the countdown survives session changes
                             * in the same browser.
                             */
                            setcookie('plogin_lock_user', $user['username'],
                                time() + LOCK_MINUTES * 60, '/');

                            $pdo->commit();

                            log_event($pdo, (int)$user['id'], 'login_fail', 'wrong password');
                            log_event($pdo, (int)$user['id'], 'account_locked', MAX_ATTEMPTS . ' wrong attempts');

                            $errorMsg =
                                "Account is locked due to too many failed attempts. Please wait {$secondsLeft} seconds.";

                            $passFieldInvalid = true;

                        } else {

                            /*
                             * Not yet at the limit.
                             */
                            $stmt = $pdo->prepare('
                                UPDATE users
                                SET failed_attempts = ?
                                WHERE id = ?
                            ');

                            $stmt->execute([
                                $newAttempts,
                                $user['id']
                            ]);

                            $pdo->commit();

                            log_event($pdo, (int)$user['id'], 'login_fail', 'wrong password');

                            $remaining = MAX_ATTEMPTS - $newAttempts;

                            $errorMsg =
                                "Incorrect password. {$remaining} attempt(s) remaining.";

                            $passFieldInvalid = true;
                        }
                    }
                }
            }

        } catch (Exception $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('login: ' . $ex->getMessage());

            $errorMsg = 'There was a problem logging in. Please try again.';
        }
    }
}

/*
 * Compute lock state so the page can start the countdown.
 *
 * On a POST the failing account is already loaded into $user.
 * On a fresh page load the account is re-fetched from the last
 * attempted username kept in the session, so the countdown
 * survives a reload.
 */
$lockCheckName = $_SESSION['last_login_name'] ?? $_COOKIE['plogin_lock_user'] ?? null;

if (!isset($user) && $lockCheckName) {

    $stmt = $pdo->prepare('
        SELECT id, username, locked_until
        FROM users
        WHERE username = ? OR email = ?
        LIMIT 1
    ');

    $stmt->execute([
        $lockCheckName,
        $lockCheckName
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$lockSecondsLeft = 0;

if (isset($user) && $user && !empty($user['locked_until'])) {

    $lockedUntil = new DateTime($user['locked_until']);
    $now = new DateTime();

    if ($now < $lockedUntil) {
        $lockSecondsLeft = max(0, $lockedUntil->getTimestamp() - $now->getTimestamp());
    }
}

/* Small script for this page only: lock countdown + "Forgot password?" note */
$pageJs = '
(function () {
  var seconds   = ' . (int)$lockSecondsLeft . ';
  var errorBox  = document.getElementById("login-error");
  var form      = document.getElementById("login-form");
  var forgotBtn = document.getElementById("forgot-password");
  var forgotMsg = document.getElementById("forgot-note");

  forgotBtn.addEventListener("click", function () { forgotMsg.hidden = !forgotMsg.hidden; });

  function setDisabled(state) {
    form.querySelectorAll("input, button").forEach(function (el) { el.disabled = state; });
  }

  if (seconds > 0) {
    setDisabled(true);

    (function tick() {
      if (seconds <= 0) {
        errorBox.textContent = "Lock period finished. You may try logging in again.";
        errorBox.className = "site-alert site-alert--ok";
        setDisabled(false);
        return;
      }
      errorBox.textContent = "Account locked. Please wait " + seconds + " seconds.";
      errorBox.className = "site-alert site-alert--err";
      errorBox.hidden = false;
      seconds--;
      setTimeout(tick, 1000);
    })();
  }
})();
';

render_public_header('Log in', 'login');
?>

<!-- ===================== HOME: tagline + log-in form ===================== -->
<section class="site-hero" id="home">

  <div class="site-hero__copy">
    <?php render_public_lock(); ?>
    <h1>Your data.<br>Locked down.</h1>
    <p class="site-hero__lead">
      Encryptica keeps your notes, pictures and documents encrypted before they are saved.
      Log in to open your vault.
    </p>
    <ul class="site-badges">
      <li>AES-256 encryption</li>
      <li>Brute-force protection</li>
      <li>Auto log-out</li>
    </ul>
  </div>

  <div class="site-card">
    <h2>Welcome back</h2>
    <p class="site-card__sub">Log in to your Encryptica account.</p>

    <form id="login-form" method="POST" action="<?= e(url('plogin.php')) ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

      <div id="login-error" class="site-alert site-alert--err" role="alert"<?= $errorMsg ? '' : ' hidden' ?>><?= e($errorMsg) ?></div>

      <?php if ($justRegistered && !$errorMsg): ?>
        <div class="site-alert site-alert--ok" role="status">Account created! You can now log in.</div>
      <?php endif; ?>
      <?php if ($justExpired && !$errorMsg): ?>
        <div class="site-alert site-alert--info" role="status">You were logged out because you were inactive for 8 minutes. Please log in again.</div>
      <?php endif; ?>
      <?php if ($wasDisabled && !$errorMsg): ?>
        <div class="site-alert site-alert--err" role="alert">This account has been disabled. Please contact the administrator.</div>
      <?php endif; ?>

      <div class="site-field">
        <label for="login-name">Username or email <span class="site-field__req">*</span></label>
        <input class="site-input" type="text" id="login-name" name="username" required
               placeholder="Enter your username" autocomplete="username" />
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

      <div class="site-row">
        <span></span>
        <button class="site-textlink" type="button" id="forgot-password">Forgot password?</button>
      </div>
      <div id="forgot-note" class="site-alert site-alert--info" hidden>
        Please contact the administrator to reset your password.
      </div>

      <button class="site-btn site-btn--primary site-btn--block" type="submit">Log in</button>
    </form>

    <p class="site-card__foot">
      Don't have an account? <a class="site-textlink" href="<?= e(url('signup.php')) ?>">Sign up</a>
    </p>
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
        <h3>Lock after 5 wrong tries</h3>
        <p>Too many wrong passwords lock the account for a short time to stop guessing.</p>
      </li>
      <li>
        <h3>Automatic log-out</h3>
        <p>After 15 minutes with no activity you are logged out, so a shared computer stays safe.</p>
      </li>
    </ol>
  </div>
</section>

<?php render_public_footer($pageJs); ?>