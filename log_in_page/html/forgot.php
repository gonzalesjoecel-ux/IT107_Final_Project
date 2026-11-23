<?php
/** @var PDO $pdo (from db.php via includes/auth.php) */
/*
 * forgot.php â€” recovery flow (handout section 10).
 *
 *   step 1: username + CAPTCHA
 *   step 2: choose one of the account's three questions and answer it twice
 *   step 3: verify_otp.php sends the reset OTP
 *   step 4: change_password.php opens only with the one-time grant
 *
 * A username that does not exist follows exactly the same screens and the
 * same messages as a real one.
 */
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity.php';
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    redirect(url('user_dashboard.php'));
}

$errorMsg  = '';
$captchaErr = '';
$lockSecondsLeft = 0;
$failureCount = 0;

if (isset($_GET['restart'])) {
    unset($_SESSION['recovery']);
}

$recovery = $_SESSION['recovery'] ?? null;
if (!is_array($recovery) || empty($recovery['started']) || time() - (int)$recovery['started'] > 900) {
    $recovery = null;
    unset($_SESSION['recovery']);
}

$step = $recovery ? 'question' : 'identify';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string)($_POST['action'] ?? '');

    if (!csrf_check($_POST['csrf_token'] ?? '')) {

        $errorMsg = 'The form has expired. Refresh the page and try again.';

    } elseif ($action === 'identify') {

        if (!captcha_check($_POST['captcha'] ?? '', 'forgot')) {

            $captchaErr = 'The CAPTCHA code is incorrect. Please try again.';
            $errorMsg   = $captchaErr;

        } else {

            $identifier = trim((string)($_POST['identifier'] ?? ''));

            if ($identifier === '') {

                $errorMsg = 'Please enter your username or email.';

            } else {

                $user = null;
                try {
                    $s = $pdo->prepare(
                        'SELECT id, username, fullname, email, failed_attempts, locked_until, lockout_level, is_active
                         FROM users WHERE username = ? OR email = ? LIMIT 1'
                    );
                    $s->execute([$identifier, $identifier]);
                    $user = $s->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (Throwable $ex) {
                    error_log('forgot lookup: ' . $ex->getMessage());
                }

                $questions = [];
                $username  = $identifier;

                if ($user) {
                    $username = (string)$user['username'];
                    try {
                        $s = $pdo->prepare(
                            'SELECT question_code FROM user_security_answers
                             WHERE user_id = ? ORDER BY id ASC'
                        );
                        $s->execute([(int)$user['id']]);
                        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $code) {
                            if (isset(AUTH_QUESTION_BANK[$code])) {
                                $questions[$code] = AUTH_QUESTION_BANK[$code];
                            }
                        }
                    } catch (Throwable $ex) {
                        error_log('forgot questions: ' . $ex->getMessage());
                    }
                }

                /* Same neutral questions for a real and a fake account. */
                if (count($questions) === 0) {
                    foreach (AUTH_QUESTION_FALLBACK as $code) {
                        $questions[$code] = AUTH_QUESTION_BANK[$code];
                    }
                }

                $_SESSION['recovery'] = [
                    'user_id'    => $user ? (int)$user['id'] : null,
                    'identifier' => $identifier,
                    'username'   => $username,
                    'email'      => $user ? (string)$user['email'] : '',
                    'name'       => $user ? (string)$user['fullname'] : '',
                    'questions'  => $questions,
                    'started'    => time(),
                ];

                redirect(url('forgot.php?step=question'));
            }
        }

    } elseif ($action === 'answer') {

        if (!$recovery) {

            $errorMsg = 'Your recovery session has expired. Please start again.';
            $step = 'identify';

        } else {

            $questions = (array)$recovery['questions'];
            $code      = (string)($_POST['question'] ?? '');
            $answer    = v_value($_POST['answer'] ?? '');
            $confirm   = v_value($_POST['answer_confirm'] ?? '');

            if (!isset($questions[$code])) {

                $errorMsg = 'Please choose one of your security questions.';

            } elseif ($e = v_answer($answer)) {

                $errorMsg = $e;

            } elseif (mb_strtolower($answer, 'UTF-8') !== mb_strtolower($confirm, 'UTF-8')) {

                $errorMsg = 'The answers do not match.';

            } else {

                $user = null;
                $answerHash = null;

                if (!empty($recovery['user_id'])) {
                    try {
                        $s = $pdo->prepare(
                            'SELECT id, username, fullname, email, failed_attempts, locked_until, lockout_level
                             FROM users WHERE id = ? LIMIT 1'
                        );
                        $s->execute([(int)$recovery['user_id']]);
                        $user = $s->fetch(PDO::FETCH_ASSOC) ?: null;

                        if ($user) {
                            $s = $pdo->prepare(
                                'SELECT answer_hash FROM user_security_answers
                                 WHERE user_id = ? AND question_code = ? LIMIT 1'
                            );
                            $s->execute([(int)$user['id'], $code]);
                            $answerHash = $s->fetchColumn() ?: null;
                        }
                    } catch (Throwable $ex) {
                        error_log('forgot answer lookup: ' . $ex->getMessage());
                    }
                }

                if ($user) {
                    $lockSecondsLeft = user_lock_seconds($user);
                    $failureCount = (int)$user['failed_attempts'];
                }

                if ($lockSecondsLeft > 0) {

                    $errorMsg = 'This account is locked due to too many failed attempts. Please wait '
                              . $lockSecondsLeft . ' seconds.';

                } else {

                    $lowerAnswer = mb_strtolower($answer, 'UTF-8');
                    $correct = $answerHash !== null
                        && password_verify($lowerAnswer, (string)$answerHash);

                    if (!$correct) {

                        if ($user) {
                            $result = apply_failed_attempt($pdo, $user, 'wrong security answer');
                            $failureCount = $result['attempts'];
                            if ($result['locked']) {
                                $lockSecondsLeft = $result['seconds'];
                                $errorMsg = 'This account is locked due to too many failed attempts. Please wait '
                                          . $lockSecondsLeft . ' seconds.';
                            } else {
                                $errorMsg = 'The answer is incorrect.';
                            }
                        } else {
                            log_event($pdo, null, 'login_fail', 'unknown user (recovery)');
                            record_ip_failure($pdo, client_ip());
                            $errorMsg = 'The answer is incorrect.';
                        }

                    } else {

                        $issue = otp_issue(
                            $pdo,
                            (int)$user['id'],
                            (string)$user['username'],
                            'forgot_password',
                            (string)$user['email'],
                            (string)$user['fullname']
                        );

                        if (!$issue['ok']) {
                            $errorMsg = $issue['error'];
                        } else {
                            pending_otp_set([
                                'user_id'    => (int)$user['id'],
                                'identifier' => (string)$user['username'],
                                'purpose'    => 'forgot_password',
                                'email'      => (string)$user['email'],
                                'name'       => (string)$user['fullname'],
                            ]);
                            redirect(url('verify_otp.php'));
                        }
                    }
                }
            }
        }
    }
}

$flash = take_flash();

$pageJs = '
(function () {
  var seconds = ' . (int)$lockSecondsLeft . ';
  var box = document.getElementById("forgot-error");
  var form = document.getElementById("forgot-answer-form");

  if (seconds > 0 && form) {
    form.querySelectorAll("input, button").forEach(function (el) { el.disabled = true; });
    (function tick() {
      if (seconds <= 0) {
        form.querySelectorAll("input, button").forEach(function (el) { el.disabled = false; });
        if (box) {
          box.textContent = "You may try again now.";
          box.className = "site-alert site-alert--ok";
          box.hidden = false;
        }
        return;
      }
      if (box) {
        box.textContent = "Account locked. Please wait " + seconds + " seconds.";
        box.className = "site-alert site-alert--err";
        box.hidden = false;
      }
      seconds--;
      setTimeout(tick, 1000);
    })();
  }
})();
';

render_public_header('Forgot password', 'login');
?>

<section class="auth" id="home">

  <?php render_auth_hero("Recover your account with your security question and a one-time email code."); ?>

  <div class="auth__panel">
    <div class="auth__card">

      <?php render_auth_brand(); ?>

      <?php if ($step === 'identify'): ?>

        <div class="auth__view">
          <h1 class="auth__title">Forgot password?</h1>
          <p class="auth__sub">
            Enter your username or email address and solve the CAPTCHA. We will then ask one of
            your registered security questions.
          </p>

          <?php if ($flash): ?>
            <div class="site-alert site-alert--<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($flash['msg']) ?></div>
          <?php endif; ?>
          <?php if ($errorMsg !== ''): ?>
            <div class="site-alert site-alert--err" role="alert"><?= e($errorMsg) ?></div>
          <?php endif; ?>

          <form method="POST" action="<?= e(url('forgot.php')) ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="identify" />

            <div class="site-field">
              <label for="forgot-id">Username or email <span class="site-field__req">*</span></label>
              <input class="site-input" type="text" id="forgot-id" name="identifier" required
                     placeholder="Enter username or email" autocomplete="username" />
            </div>

            <?php render_captcha('forgot-captcha', 'forgot', $captchaErr); ?>

            <div class="auth__stack">
              <button class="site-btn site-btn--primary site-btn--block" type="submit">Continue</button>
              <a class="site-btn site-btn--block" href="<?= e(url('login.php')) ?>">Back to log-in</a>
            </div>
          </form>
        </div>

      <?php else: ?>

        <div class="auth__view">
          <h1 class="auth__title">Choose the following question</h1>
          <p class="auth__sub">
            Choose one of the security questions you registered. Your answer is checked against
            the stored hash; the answer must be typed twice.
          </p>

          <?php if ($flash): ?>
            <div class="site-alert site-alert--<?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($flash['msg']) ?></div>
          <?php endif; ?>
          <div id="forgot-error" class="site-alert site-alert--err" role="alert"<?= $errorMsg !== '' ? '' : ' hidden' ?>><?= e($errorMsg) ?></div>

          <form id="forgot-answer-form" method="POST" action="<?= e(url('forgot.php')) ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="answer" />

            <fieldset class="site-choice">
              <legend>Security question <span class="site-field__req">*</span></legend>
              <?php foreach ($recovery['questions'] as $qcode => $qtext): ?>
                <label class="site-choice__item">
                  <input type="radio" name="question" value="<?= e($qcode) ?>" required
                         <?= (($_POST['question'] ?? '') === $qcode) ? 'checked' : '' ?> />
                  <span><?= e($qtext) ?></span>
                </label>
              <?php endforeach; ?>
            </fieldset>

            <div class="site-field">
              <label for="forgot-answer">Your Answer <span class="site-field__req">*</span></label>
              <input class="site-input" type="text" id="forgot-answer" name="answer" required
                     maxlength="50" autocomplete="off" placeholder="Letters and spaces only" />
            </div>

            <div class="site-field">
              <label for="forgot-answer-confirm">Re-enter Answer <span class="site-field__req">*</span></label>
              <input class="site-input" type="text" id="forgot-answer-confirm" name="answer_confirm" required
                     maxlength="50" autocomplete="off" placeholder="Type the same answer again" />
            </div>

            <div class="auth__stack">
              <button class="site-btn site-btn--primary site-btn--block" type="submit">Send reset code</button>
              <a class="site-btn site-btn--block" href="<?= e(url('forgot.php?restart=1')) ?>">Start over</a>
            </div>
          </form>
        </div>

      <?php endif; ?>

    </div>
  </div>

</section>

<?php render_public_footer($pageJs); ?>
