<?php
/** @var PDO $pdo (from db.php via includes/auth.php) */
/*
 * verify_otp.php â€” the second step of log-in, registration and password reset
 * (handout section 6).
 *
 * The page cannot be skipped:
 *   - it needs a pending verification in the session;
 *   - Change Password also needs the one-time grant written after a correct OTP.
 */
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/layout.php';

/* Already logged in? Go straight to the dashboard. */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    redirect(url('user_dashboard.php'));
}

$pending = pending_otp_get();

if (!$pending) {
    flash('err', 'Please log in again to request a verification code.');
    redirect(url('login.php'));
}

$identifier = (string)$pending['identifier'];
$purpose    = (string)$pending['purpose'];
$errorMsg   = '';
$noticeMsg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_check($_POST['csrf_token'] ?? '')) {

        $errorMsg = 'The form has expired. Refresh the page and try again.';

    } elseif (($_POST['action'] ?? '') === 'resend') {

        $issue = otp_issue(
            $pdo,
            (int)$pending['user_id'],
            $identifier,
            $purpose,
            (string)$pending['email'],
            (string)$pending['name']
        );

        if ($issue['ok']) {
            $noticeMsg = 'A new six-digit code was sent to your email address.';
        } else {
            $errorMsg = $issue['error'];
        }

    } else {

        $code = trim((string)($_POST['otp'] ?? ''));

        if (!preg_match('/^\d{6}$/', $code)) {
            $errorMsg = 'Please enter the six-digit code from your email.';
        } else {

            $result = otp_verify($pdo, $identifier, $purpose, $code);

            if (!$result['ok']) {
                $errorMsg = $result['error'];
                if (($result['attempts_left'] ?? 0) > 0) {
                    $errorMsg .= ' ' . (int)$result['attempts_left'] . ' attempt(s) left for this code.';
                }
            } else {

                $userId = (int)$result['user_id'];

                if ($purpose === 'registration') {

                    $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$userId]);
                    log_event($pdo, $userId, 'email_verified', 'registration code accepted');
                    pending_otp_clear();
                    redirect(url('login.php?verified=1'));

                } elseif ($purpose === 'forgot_password') {

                    /* One-time grant for change_password.php. Expires in 10 minutes. */
                    $_SESSION['password_reset'] = [
                        'user_id'     => $userId,
                        'token'       => bin2hex(random_bytes(24)),
                        'verified_at' => time(),
                    ];
                    pending_otp_clear();
                    redirect(url('change_password.php'));

                } else {

                    $s = $pdo->prepare(
                        'SELECT id, username, fullname, email, role, is_active, is_verified, last_login
                         FROM users WHERE id = ? LIMIT 1'
                    );
                    $s->execute([$userId]);
                    $account = $s->fetch(PDO::FETCH_ASSOC);

                    if (!$account) {
                        pending_otp_clear();
                        $errorMsg = 'This account is no longer available. Please contact the administrator.';
                    } else {
                        pending_otp_clear();
                        $loginError = finish_login($pdo, $account);
                        $errorMsg = (string)$loginError;
                    }
                }
            }
        }
    }
}

$status = otp_status($pdo, $identifier, $purpose);
$cooldown      = (int)$status['cooldown'];
$resendsLeft   = (int)$status['resends_left'];
$attempts      = (int)$status['attempts'];
$attemptsLeft  = max(0, OTP_MAX_ATTEMPTS - $attempts);

/* Never print the whole email address. */
$email = (string)$pending['email'];
$at = strpos($email, '@');
$maskedEmail = $at !== false
    ? substr($email, 0, 1) . str_repeat('â€¢', max(1, $at - 1)) . substr($email, $at)
    : $email;

$title = match ($purpose) {
    'registration'    => 'Verify your account',
    'forgot_password' => 'Password reset code',
    default           => 'Your log-in code',
};

$pageJs = '
(function () {
  var seconds = ' . $cooldown . ';
  var resend  = document.getElementById("otp-resend");
  var label   = document.getElementById("otp-resend-label");
  var original = label ? label.textContent : "Resend OTP";

  function tick() {
    if (!resend || !label) { return; }
    if (seconds <= 0) {
      resend.disabled = false;
      label.textContent = original;
      return;
    }
    resend.disabled = true;
    label.textContent = "Resend OTP in " + seconds + "s";
    seconds--;
    setTimeout(tick, 1000);
  }

  if (seconds > 0) {
    tick();
  }
})();
';

render_public_header($title, 'login');
?>

<section class="auth" id="home">

  <?php render_auth_hero("One last step. Enter the code we sent to your email address."); ?>

  <div class="auth__panel">
    <div class="auth__card">

      <?php render_auth_brand(); ?>

      <div class="auth__view">
        <h1 class="auth__title"><?= e($title) ?></h1>
        <?php if ($purpose === 'forgot_password'): ?>
        <p class="auth__sub">
         We sent a six-digit code to the email address registered on this account.
         It expires in <?= OTP_TTL_MINUTES ?> minutes.
       </p>
<?php else: ?>
  <p class="auth__sub">
    We sent a six-digit code to <strong><?= e($email) ?></strong>.
    It expires in <?= OTP_TTL_MINUTES ?> minutes.
  </p>
<?php endif; ?>

        <?php if ($errorMsg !== ''): ?>
          <div class="site-alert site-alert--err" role="alert"><?= e($errorMsg) ?></div>
        <?php endif; ?>
        <?php if ($noticeMsg !== ''): ?>
          <div class="site-alert site-alert--ok" role="status"><?= e($noticeMsg) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= e(url('verify_otp.php')) ?>" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="verify" />

          <div class="site-field">
            <label for="otp-code">Six-digit code <span class="site-field__req">*</span></label>
            <input class="site-input" type="text" id="otp-code" name="otp" required
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   autocomplete="one-time-code" placeholder="000000" autofocus />
            <div class="site-field__msg">
              <?= $attemptsLeft > 0
                    ? e($attemptsLeft . ' attempt(s) left for this code.')
                    : 'This code can no longer be used. Request a new one.' ?>
            </div>
          </div>

          <div class="auth__stack">
            <button class="site-btn site-btn--primary site-btn--block" type="submit">Verify code</button>
          </div>
        </form>

        <form method="POST" action="<?= e(url('verify_otp.php')) ?>" class="auth__resend">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="resend" />
          <button class="site-btn site-btn--block" id="otp-resend" type="submit">
            <span id="otp-resend-label">Resend OTP</span>
          </button>
          <p class="auth__fineprint">
            <?= $resendsLeft ?> resend(s) left in the next 15 minutes.
          </p>
        </form>

        <p class="auth__foot">
          <a class="site-textlink" href="<?= e(url('login.php')) ?>">Back to log-in</a>
        </p>
      </div>

    </div>
  </div>

</section>

<?php render_public_footer($pageJs); ?>
