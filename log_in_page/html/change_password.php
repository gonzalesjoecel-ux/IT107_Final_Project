<?php
/** @var PDO $pdo (from db.php via includes/auth.php) */
/*
 * change_password.php â€” the last step of Forgot Password (handout section 11).
 *
 * It opens only with $_SESSION['password_reset'], written after a correct
 * reset OTP. The grant is one-time: it is deleted before the success redirect,
 * so reloading or pressing Back cannot reuse the page.
 */
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/validation.php';

$grant = $_SESSION['password_reset'] ?? null;

if (!is_array($grant)
    || empty($grant['user_id'])
    || empty($grant['token'])
    || empty($grant['verified_at'])
    || time() - (int)$grant['verified_at'] > 600) {

    unset($_SESSION['password_reset']);
    flash('err', 'Your password reset session has expired. Please start again from Forgot Password.');
    redirect(url('forgot.php'));
}

$userId = (int)$grant['user_id'];
$errorMsg = '';

$s = $pdo->prepare('SELECT id, username, fullname, email, role FROM users WHERE id = ? LIMIT 1');
$s->execute([$userId]);
$account = $s->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    unset($_SESSION['password_reset']);
    flash('err', 'This account is no longer available. Please contact the administrator.');
    redirect(url('forgot.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new     = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (!csrf_check($_POST['csrf_token'] ?? '')) {

        $errorMsg = 'The form has expired. Refresh the page and try again.';

    } elseif (($e = v_password($new)) !== null) {

        $errorMsg = $e;

    } elseif ($confirm === '' || !hash_equals($new, $confirm)) {

        /* Exact wording required by the handout. */
        $errorMsg = 'Mismatch Password.';

    } else {

        try {
            $s = $pdo->prepare(
                'SELECT password_hash FROM password_history
                 WHERE user_id = ? ORDER BY id DESC LIMIT 3'
            );
            $s->execute([$userId]);
            $recent = $s->fetchAll(PDO::FETCH_COLUMN);

            $reused = false;
            foreach ($recent as $oldHash) {
                if (password_verify($new, (string)$oldHash)) {
                    $reused = true;
                    break;
                }
            }

            if ($reused) {

                $errorMsg = 'You cannot reuse a recent password.';

            } else {

                $hash = password_hash($new, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                $pdo->prepare(
                    'UPDATE users
                     SET password_hash = ?, failed_attempts = 0, locked_until = NULL,
                         lockout_level = 0, last_failed_at = NULL
                     WHERE id = ?'
                )->execute([$hash, $userId]);

                $pdo->prepare(
                    'INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)'
                )->execute([$userId, $hash]);

                $pdo->commit();

                log_event($pdo, $userId, 'password_changed', 'via forgot password');

                /* Warn the real owner (handout section 9.1). */
                send_password_changed_alert($pdo, $account);

                /* Use up the grant so Back or reload cannot replay it. */
                unset($_SESSION['password_reset'], $_SESSION['pending_otp'], $_SESSION['recovery'],
                      $_SESSION['login_fail_count'], $_SESSION['last_login_name']);

                redirect(url('login.php?changed=1'));
            }

        } catch (Throwable $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('change_password: ' . $ex->getMessage());
            $errorMsg = 'There was a problem changing your password. Please try again.';
        }
    }
}

$pageJs = '
(function () {
  var input = document.getElementById("cp-password");
  var box   = document.getElementById("cp-strength");
  var label = document.getElementById("cp-strength-label");
  var MIN = ' . PASSWORD_MIN . ';
  var MAX = ' . PASSWORD_MAX . ';

  function strengthKind(value) {
    var classes = 0;
    if (/[a-z]/.test(value)) { classes++; }
    if (/[A-Z]/.test(value)) { classes++; }
    if (/\d/.test(value)) { classes++; }
    if (/[^A-Za-z0-9]/.test(value)) { classes++; }
    if (value.length >= 12 && classes >= 3) { return "strong"; }
    if (value.length >= MIN && classes >= 2) { return "medium"; }
    return "weak";
  }

  function paint() {
    if (!input || !box || !label) { return; }
    if (!input.value) {
      box.hidden = true;
      box.setAttribute("data-strength", "weak");
      label.textContent = "Weak";
      return;
    }
    var kind = strengthKind(input.value);
    box.hidden = false;
    box.setAttribute("data-strength", kind);
    label.textContent = kind.charAt(0).toUpperCase() + kind.slice(1);
  }

  if (input) {
    input.addEventListener("input", paint);
    paint();
  }
})();
';

render_public_header('Change password', 'login');
?>

<section class="auth" id="home">

  <?php render_auth_hero("Choose a new password. Your reset link works only once."); ?>

  <div class="auth__panel">
    <div class="auth__card">

      <?php render_auth_brand(); ?>

      <div class="auth__view">
        <h1 class="auth__title">Change Password</h1>
        <p class="auth__sub">
          Set a new password for <strong><?= e($account['username']) ?></strong>.
          Use <?= PASSWORD_MIN ?>â€“<?= PASSWORD_MAX ?> characters and a mix of letters, numbers or symbols.
          You cannot reuse any of your last three passwords.
        </p>

        <?php if ($errorMsg !== ''): ?>
          <div class="site-alert site-alert--err" role="alert"><?= e($errorMsg) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= e(url('change_password.php')) ?>" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

          <div class="site-field">
            <label for="cp-password">Enter Password <span class="site-field__req">*</span></label>
            <div class="site-input-wrap">
              <input class="site-input" type="password" id="cp-password" name="password" required
                     maxlength="<?= PASSWORD_MAX ?>" autocomplete="new-password" />
              <button class="site-eye" type="button" data-toggle-password="#cp-password" aria-pressed="false">Show</button>
            </div>
            <div class="site-strength" id="cp-strength" data-strength="weak" hidden>
              <div class="site-strength__bar"><span></span></div>
              <div class="site-strength__label">Strength: <strong id="cp-strength-label">Weak</strong></div>
            </div>
          </div>

          <div class="site-field">
            <label for="cp-confirm">Re-enter Password <span class="site-field__req">*</span></label>
            <div class="site-input-wrap">
              <input class="site-input" type="password" id="cp-confirm" name="confirm" required
                     maxlength="<?= PASSWORD_MAX ?>" autocomplete="new-password" />
              <button class="site-eye" type="button" data-toggle-password="#cp-confirm" aria-pressed="false">Show</button>
            </div>
          </div>

          <div class="auth__stack">
            <button class="site-btn site-btn--primary site-btn--block" type="submit">Change Password</button>
          </div>
        </form>

        <p class="auth__foot">
          <a class="site-textlink" href="<?= e(url('forgot.php?restart=1')) ?>">Start over</a>
        </p>
      </div>

    </div>
  </div>

</section>

<?php render_public_footer($pageJs); ?>
