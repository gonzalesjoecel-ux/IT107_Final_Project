<?php
/** @var PDO $pdo (from db.php via includes/auth.php) */
/*
 * signup.php â€” registration form (public page).
 * Implements handout sections 4 and 5: core fields, system fields,
 * three authentication questions chosen by the user, and RA 10173 privacy consent.
 * Browser validation lives in js/signup.js; these server checks are final.
 */
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';        // db, session, helpers
require_once __DIR__ . '/includes/activity.php';    // log_event()
require __DIR__ . '/includes/layout.php';      // public layout
require_once __DIR__ . '/includes/seed.php';   // shared limits, question bank
require_once __DIR__ . '/includes/validation.php';

/* Already logged in? Go straight to the dashboard. */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    redirect(url('user_dashboard.php'));
}

$errors = [];
$old = [
    'id_number'            => '',
    'first_name'           => '',
    'middle_name'          => '',
    'family_name'          => '',
    'name_extension'       => '',
    'name_extension_other' => '',
    'birthdate'            => '',
    'age'                  => '',
    'house_street'         => '',
    'barangay'             => '',
    'municipality_city'    => '',
    'province'             => '',
    'country'              => '',
    'postal_code'          => '',
    'email'                => '',
    'recovery_mobile'      => '',
    'username'             => '',
    'account_type'         => '',
    'vault_display_name'   => '',
    'organization_name'    => '',
    'question_1'           => '',
    'question_2'           => '',
    'question_3'           => '',
    'answer_1'             => '',
    'answer_2'             => '',
    'answer_3'             => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($old as $field => $_) {
        $old[$field] = v_value($_POST[$field] ?? '');
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');
    $privacy  = isset($_POST['privacy']) && $_POST['privacy'] === '1';

    /*
     * Honeypot (handout section 12.8). The field is hidden from people, so
     * only a bot fills it. Refuse silently: show the normal success page and
     * do not save anything.
     */
    if (v_value($_POST['website'] ?? '') !== '') {
        redirect(url('login.php?registered=1'));
    }

    if (!csrf_check($_POST['csrf_token'] ?? '')) {

        $errors['form'] = 'The form has expired. Refresh the page and try again.';

    } elseif (!captcha_check($_POST['captcha'] ?? '', 'signup')) {

        $errors['captcha'] = 'The CAPTCHA code is incorrect. Please try again.';

    } else {

        /* ---------- core fields ---------- */

        if ($e = v_id_number($old['id_number'])) {
            $errors['id_number'] = $e;
        }

        if ($e = v_name($old['first_name'], 'First Name')) {
            $errors['first_name'] = $e;
        }
        if ($e = v_name($old['middle_name'], 'Middle Name', false)) {
            $errors['middle_name'] = $e;
        }
        if ($e = v_name($old['family_name'], 'Family Name')) {
            $errors['family_name'] = $e;
        }

        if (!in_array($old['name_extension'], ['', 'Jr.', 'Sr.', 'Other'], true)) {
            $errors['name_extension'] = 'Please select a valid name extension.';
        } elseif ($old['name_extension'] === 'Other') {
            if ($old['name_extension_other'] === '') {
                $errors['name_extension'] = 'Please type your name extension.';
            } elseif ($e = v_name_extension($old['name_extension_other'])) {
                $errors['name_extension'] = $e;
            }
        }
        $extensionValue = $old['name_extension'] === 'Other'
            ? $old['name_extension_other']
            : $old['name_extension'];

        [$birthdate, $birthdateError, $age] = v_birthdate($old['birthdate']);
        $old['birthdate'] = $birthdate !== '' ? $birthdate : $old['birthdate'];
        $old['age'] = $age !== null ? (string)$age : '';
        if ($birthdateError) {
            $errors['birthdate'] = $birthdateError;
        }

        if ($e = v_street($old['house_street'])) {
            $errors['house_street'] = $e;
        }
        if ($e = v_place($old['barangay'], 'Barangay')) {
            $errors['barangay'] = $e;
        }
        if ($e = v_place($old['municipality_city'], 'Municipality / City')) {
            $errors['municipality_city'] = $e;
        }
        if ($e = v_place($old['province'], 'Province')) {
            $errors['province'] = $e;
        }
        if ($e = v_place($old['country'], 'Country')) {
            $errors['country'] = $e;
        }
        if ($e = v_zip($old['postal_code'])) {
            $errors['postal_code'] = $e;
        }

        if ($e = v_email($old['email'])) {
            $errors['email'] = $e;
        }
        if ($e = v_username($old['username'])) {
            $errors['username'] = $e;
        }

        if ($e = v_password($password)) {
            $errors['password'] = $e;
        } elseif ($e = v_confirm_password($password, $confirm)) {
            $errors['confirm'] = $e;
        }

        /*
         * ---------- three authentication questions ----------
         * The user chooses three DIFFERENT questions from the bank
         * (handout section 5, updated class rule).
         */
        $chosenQuestions = [];
        foreach ([1, 2, 3] as $index) {
            $codeField = 'question_' . $index;
            $code = $old[$codeField];

            if ($code === '' || !isset(AUTH_QUESTION_BANK[$code])) {
                $errors[$codeField] = 'Please choose a security question.';
            } elseif (in_array($code, $chosenQuestions, true)) {
                $errors[$codeField] = 'Please choose a different question.';
            } else {
                $chosenQuestions[$index] = $code;
            }

            if ($e = v_answer($old['answer_' . $index])) {
                $errors['answer_' . $index] = $e;
            }
        }

        /* ---------- system-specific fields ---------- */
        if ($e = v_recovery_mobile($old['recovery_mobile'])) {
            $errors['recovery_mobile'] = $e;
        }
        if ($e = v_account_type($old['account_type'])) {
            $errors['account_type'] = $e;
        }
        if ($e = v_vault_name($old['vault_display_name'])) {
            $errors['vault_display_name'] = $e;
        }
        if ($old['account_type'] === 'Organization') {
            if ($e = v_organization_name($old['organization_name'], true)) {
                $errors['organization_name'] = $e;
            }
        } else {
            $old['organization_name'] = '';
        }

        /* ---------- privacy consent (RA 10173) ---------- */
        if (!$privacy) {
            $errors['privacy'] = 'You must agree to the Privacy Notice to register.';
        }

        /* ---------- uniqueness ---------- */
        if (!$errors) {
            try {
                $check = $pdo->prepare('SELECT id FROM users WHERE id_number = ? LIMIT 1');
                $check->execute([$old['id_number']]);
                if ($check->fetch()) {
                    $errors['id_number'] = 'This ID Number is already registered.';
                }

                $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                $check->execute([$old['username']]);
                if ($check->fetch()) {
                    $errors['username'] = 'This username is already taken.';
                }

                $email = mb_strtolower($old['email'], 'UTF-8');
                $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $check->execute([$email]);
                if ($check->fetch()) {
                    $errors['email'] = 'This email is already registered.';
                }
            } catch (Throwable $ex) {
                error_log('signup check: ' . $ex->getMessage());
                $errors['form'] = 'There was a problem creating your account. Please try again.';
            }
        }

        /* ---------- save ---------- */
        if (!$errors) {
            $fullname = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
                $old['first_name'],
                $old['middle_name'],
                $old['family_name'],
                $extensionValue,
            ], static fn($part) => $part !== ''))));

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $email = mb_strtolower($old['email'], 'UTF-8');
            $consentAt = date('Y-m-d H:i:s');

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO users
                       (id_number, first_name, middle_name, family_name, name_extension, fullname,
                        birthdate, house_street, barangay, municipality_city, province, country,
                        postal_code, email, recovery_mobile, username, password_hash,
                        failed_attempts, privacy_consent, consent_timestamp, is_verified,
                        account_type, vault_display_name, organization_name)
                     VALUES
                       (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?, 0, ?, ?, ?)'
                );
                $stmt->execute([
                    $old['id_number'],
                    $old['first_name'],
                    $old['middle_name'] !== '' ? $old['middle_name'] : null,
                    $old['family_name'],
                    $extensionValue !== '' ? $extensionValue : null,
                    $fullname,
                    $old['birthdate'],
                    $old['house_street'],
                    $old['barangay'],
                    $old['municipality_city'],
                    $old['province'],
                    $old['country'],
                    $old['postal_code'],
                    $email,
                    $old['recovery_mobile'],
                    $old['username'],
                    $hash,
                    $consentAt,
                    $old['account_type'],
                    $old['vault_display_name'],
                    $old['organization_name'] !== '' ? $old['organization_name'] : null,
                ]);

                $userId = (int)$pdo->lastInsertId();

                /* Password history starts with the first password (section 12.7). */
                $stmt = $pdo->prepare('INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)');
                $stmt->execute([$userId, $hash]);

                /* Security answers are hashed like passwords, lowercased first (section 5). */
                $stmt = $pdo->prepare(
                    'INSERT INTO user_security_answers (user_id, question_code, answer_hash) VALUES (?, ?, ?)'
                );
                foreach ([1, 2, 3] as $index) {
                    $answer = mb_strtolower($old['answer_' . $index], 'UTF-8');
                    $stmt->execute([$userId, $chosenQuestions[$index], password_hash($answer, PASSWORD_DEFAULT)]);
                }

                log_event($pdo, $userId, 'register');
                $pdo->commit();

                /*
                 * Verify the email address before the account can log in
                 * (handout section 6.1). The user row exists already, so a mail
                 * failure does not lose the registration: the OTP page offers
                 * a resend.
                 */
                $issue = otp_issue(
                    $pdo,
                    $userId,
                    (string)$old['username'],
                    'registration',
                    $email,
                    $fullname
                );

                pending_otp_set([
                    'user_id'    => $userId,
                    'identifier' => (string)$old['username'],
                    'purpose'    => 'registration',
                    'email'      => $email,
                    'name'       => $fullname,
                ]);

                if (!$issue['ok']) {
                    flash('err', $issue['error']);
                }

                redirect(url('verify_otp.php'));

            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('signup: ' . $ex->getMessage());
                $errors['form'] = ($ex instanceof PDOException && $ex->getCode() === '23000')
                    ? 'This ID Number, username or email is already registered.'
                    : 'There was a problem creating your account. Please try again.';
            }
        }
    }
}

render_public_header('Sign up', 'signup');
?>

<section class="auth auth--signup" id="home">

  <?php render_auth_hero("Create your account and keep your notes, pictures and documents locked behind Encryptica's encryption."); ?>

  <div class="auth__panel">
    <div class="auth__card auth__card--wide">

      <?php render_auth_brand(); ?>

      <h1 class="auth__title">Create your account</h1>
      <p class="auth__sub">Fields marked <span class="site-field__req">*</span> are required.</p>

      <form id="signup-form" method="POST" action="<?= e(url('signup.php')) ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

        <!-- Honeypot: hidden from people, filled only by bots. -->
        <div class="site-hp" aria-hidden="true">
          <label for="su-website">Website</label>
          <input type="text" id="su-website" name="website" tabindex="-1" autocomplete="off" />
        </div>

        <?php if (isset($errors['form'])): ?>
          <div class="site-alert site-alert--err" role="alert"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <!-- ================= PERSONAL INFORMATION ================= -->
        <h2 class="auth__legend">Personal information</h2>

        <div class="site-field">
          <label for="su-id">ID Number <span class="site-field__req">*</span></label>
          <input class="site-input<?= isset($errors['id_number']) ? ' site-input--invalid' : '' ?>" type="text"
                 id="su-id" name="id_number" inputmode="numeric" maxlength="9" required
                 placeholder="xxxx-xxxx" value="<?= e($old['id_number']) ?>" autocomplete="off" />
          <div class="site-field__msg" data-msg-for="id_number"><?= e($errors['id_number'] ?? '') ?></div>
        </div>

        <div class="site-grid site-grid--2">
          <div class="site-field">
            <label for="su-first">First Name <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['first_name']) ? ' site-input--invalid' : '' ?>" type="text"
                   id="su-first" name="first_name" maxlength="<?= NAME_MAX ?>" required
                   value="<?= e($old['first_name']) ?>" placeholder="First name" autocomplete="given-name" />
            <div class="site-field__msg" data-msg-for="first_name"><?= e($errors['first_name'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-middle">Middle Name <span class="site-field__opt">optional</span></label>
            <input class="site-input<?= isset($errors['middle_name']) ? ' site-input--invalid' : '' ?>" type="text"
                   id="su-middle" name="middle_name" maxlength="<?= NAME_MAX ?>"
                   value="<?= e($old['middle_name']) ?>" placeholder="Middle name" autocomplete="additional-name" />
            <div class="site-field__msg" data-msg-for="middle_name"><?= e($errors['middle_name'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-family">Family Name <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['family_name']) ? ' site-input--invalid' : '' ?>" type="text"
                   id="su-family" name="family_name" maxlength="<?= NAME_MAX ?>" required
                   value="<?= e($old['family_name']) ?>" placeholder="Family name" autocomplete="family-name" />
            <div class="site-field__msg" data-msg-for="family_name"><?= e($errors['family_name'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-ext">Name Extension <span class="site-field__opt">optional</span></label>
            <select class="site-input<?= isset($errors['name_extension']) ? ' site-input--invalid' : '' ?>"
                    id="su-ext" name="name_extension">
              <option value=""<?= $old['name_extension'] === '' ? ' selected' : '' ?>>None</option>
              <option value="Jr."<?= $old['name_extension'] === 'Jr.' ? ' selected' : '' ?>>Jr.</option>
              <option value="Sr."<?= $old['name_extension'] === 'Sr.' ? ' selected' : '' ?>>Sr.</option>
              <option value="Other"<?= $old['name_extension'] === 'Other' ? ' selected' : '' ?>>Other</option>
            </select>
            <div class="site-extra" id="su-ext-other-wrap"<?= $old['name_extension'] === 'Other' ? '' : ' hidden' ?>>
              <input class="site-input<?= isset($errors['name_extension']) ? ' site-input--invalid' : '' ?>" type="text"
                     id="su-ext-other" name="name_extension_other" maxlength="10"
                     placeholder="I, II, III, IV, IX or X" value="<?= e($old['name_extension_other']) ?>" />
            </div>
            <div class="site-field__msg" data-msg-for="name_extension"><?= e($errors['name_extension'] ?? '') ?></div>
          </div>
        </div>

        <div class="site-grid site-grid--2">
          <div class="site-field">
            <label for="su-birthdate">Birthdate <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['birthdate']) ? ' site-input--invalid' : '' ?>" type="date"
                   id="su-birthdate" name="birthdate" required max="<?= date('Y-m-d') ?>"
                   value="<?= e($old['birthdate']) ?>" placeholder="YYYY-MM-DD" autocomplete="bday" />
            <div class="site-field__msg" data-msg-for="birthdate"><?= e($errors['birthdate'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-age">Age</label>
            <input class="site-input" type="text" id="su-age" name="age" readonly
                   value="<?= e($old['age']) ?>" placeholder="Calculated automatically" />
            <div class="site-hint">Calculated from your birthdate. You must be at least 18 years old.</div>
          </div>
        </div>

        <!-- ================= CONTACT DETAILS ================= -->
        <h2 class="auth__legend">Contact details</h2>

        <div class="site-grid site-grid--2">
          <div class="site-field">
            <label for="su-email">Email <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['email']) ? ' site-input--invalid' : '' ?>" type="email"
                   id="su-email" name="email" maxlength="100" required
                   value="<?= e($old['email']) ?>" placeholder="you@example.com" autocomplete="email" />
            <div class="site-field__msg" data-msg-for="email"><?= e($errors['email'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-mobile">Recovery Mobile Number <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['recovery_mobile']) ? ' site-input--invalid' : '' ?>" type="tel"
                   id="su-mobile" name="recovery_mobile" maxlength="11" required
                   value="<?= e($old['recovery_mobile']) ?>" placeholder="09xxxxxxxxx" autocomplete="tel" />
            <div class="site-field__msg" data-msg-for="recovery_mobile"><?= e($errors['recovery_mobile'] ?? '') ?></div>
          </div>
        </div>

        <!-- ================= ADDRESS ================= -->
        <h2 class="auth__legend">Address</h2>

        <div class="site-field">
          <label for="su-street">Purok / Street <span class="site-field__req">*</span></label>
          <input class="site-input<?= isset($errors['house_street']) ? ' site-input--invalid' : '' ?>" type="text"
                 id="su-street" name="house_street" maxlength="50" required
                 value="<?= e($old['house_street']) ?>" placeholder="Purok 5, Mabini Street" autocomplete="street-address" />
          <div class="site-field__msg" data-msg-for="house_street"><?= e($errors['house_street'] ?? '') ?></div>
        </div>

        <div class="site-grid site-grid--2">
          <div class="site-field">
            <label for="su-barangay">Barangay <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['barangay']) ? ' site-input--invalid' : '' ?>" type="text"
                   id="su-barangay" name="barangay" maxlength="50" required
                   value="<?= e($old['barangay']) ?>" placeholder="e.g. Barangay 1" autocomplete="address-level3" />
            <div class="site-field__msg" data-msg-for="barangay"><?= e($errors['barangay'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-city">Municipality / City <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['municipality_city']) ? ' site-input--invalid' : '' ?>" type="text"
                   id="su-city" name="municipality_city" maxlength="50" required
                   value="<?= e($old['municipality_city']) ?>" placeholder="e.g. Cebu City" autocomplete="address-level2" />
            <div class="site-field__msg" data-msg-for="municipality_city"><?= e($errors['municipality_city'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-province">Province <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['province']) ? ' site-input--invalid' : '' ?>" type="text"
                   id="su-province" name="province" maxlength="50" required
                   value="<?= e($old['province']) ?>" placeholder="e.g. Cebu" autocomplete="address-level1" />
            <div class="site-field__msg" data-msg-for="province"><?= e($errors['province'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-country">Country <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['country']) ? ' site-input--invalid' : '' ?>" type="text"
                   id="su-country" name="country" maxlength="50" required
                   value="<?= e($old['country']) ?>" placeholder="Philippines" autocomplete="country-name" />
            <div class="site-field__msg" data-msg-for="country"><?= e($errors['country'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-zip">ZIP Code <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['postal_code']) ? ' site-input--invalid' : '' ?>" type="text"
                   id="su-zip" name="postal_code" inputmode="numeric" maxlength="4" required
                   value="<?= e($old['postal_code']) ?>" placeholder="1234" autocomplete="postal-code" />
            <div class="site-field__msg" data-msg-for="postal_code"><?= e($errors['postal_code'] ?? '') ?></div>
          </div>
        </div>

        <!-- ================= ACCOUNT ACCESS ================= -->
        <h2 class="auth__legend">Account access</h2>

        <div class="site-field">
          <label for="su-username">Username <span class="site-field__req">*</span></label>
          <input class="site-input<?= isset($errors['username']) ? ' site-input--invalid' : '' ?>" type="text"
                 id="su-username" name="username" minlength="<?= USERNAME_MIN ?>" maxlength="<?= USERNAME_MAX ?>" required
                 value="<?= e($old['username']) ?>" placeholder="<?= USERNAME_MIN ?>-<?= USERNAME_MAX ?> letters and digits"
                 autocomplete="username" />
          <div class="site-field__msg" data-msg-for="username"><?= e($errors['username'] ?? '') ?></div>
          <div class="site-hint"><?= USERNAME_MIN ?>-<?= USERNAME_MAX ?> characters; letters and digits only, starting with a letter.</div>
        </div>

        <div class="site-grid site-grid--2">
          <div class="site-field">
            <label for="su-password">Password <span class="site-field__req">*</span></label>
            <div class="site-input-wrap">
              <input class="site-input<?= isset($errors['password']) ? ' site-input--invalid' : '' ?>" type="password"
                     id="su-password" name="password" required autocomplete="new-password" placeholder="Enter your password" />
              <button class="site-eye" type="button" data-toggle-password="#su-password" aria-pressed="false">Show</button>
            </div>
            <div class="site-strength" id="su-strength" data-strength="weak" hidden>
              <div class="site-strength__bar"><span></span></div>
              <div class="site-strength__label">Strength: <strong id="su-strength-label">Weak</strong></div>
            </div>
            <div class="site-field__msg" data-msg-for="password"><?= e($errors['password'] ?? '') ?></div>
            <div class="site-hint">At least <?= PASSWORD_MIN ?> characters, mixing letters, numbers or symbols.</div>
          </div>

          <div class="site-field">
            <label for="su-confirm">Re-enter Password <span class="site-field__req">*</span></label>
            <div class="site-input-wrap">
              <input class="site-input<?= isset($errors['confirm']) ? ' site-input--invalid' : '' ?>" type="password"
                     id="su-confirm" name="confirm" required autocomplete="new-password" placeholder="Re-enter your password" />
              <button class="site-eye" type="button" data-toggle-password="#su-confirm" aria-pressed="false">Show</button>
            </div>
            <div class="site-field__msg" data-msg-for="confirm"><?= e($errors['confirm'] ?? '') ?></div>
          </div>
        </div>

        <!-- ================= AUTHENTICATION QUESTIONS ================= -->
        <h2 class="auth__legend">Authentication questions</h2>
        <p class="auth__sub">
          Choose three different questions from the bank. Answers are case-insensitive and stored hashed,
          never as plain text.
        </p>

        <?php foreach ([1, 2, 3] as $index): ?>
          <?php $qField = 'question_' . $index; $aField = 'answer_' . $index; ?>
          <div class="auth__question">
            <div class="site-field">
              <label for="su-<?= e($qField) ?>">Question <?= $index ?> <span class="site-field__req">*</span></label>
              <select class="site-input<?= isset($errors[$qField]) ? ' site-input--invalid' : '' ?>"
                      id="su-<?= e($qField) ?>" name="<?= e($qField) ?>" required>
                <option value="">Select a security question</option>
                <?php foreach (AUTH_QUESTION_BANK as $code => $text): ?>
                  <option value="<?= e($code) ?>"<?= $old[$qField] === $code ? ' selected' : '' ?>><?= e($text) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="site-field__msg" data-msg-for="<?= e($qField) ?>"><?= e($errors[$qField] ?? '') ?></div>
            </div>

            <div class="site-field">
              <label for="su-<?= e($aField) ?>">Your Answer <span class="site-field__req">*</span></label>
              <input class="site-input<?= isset($errors[$aField]) ? ' site-input--invalid' : '' ?>" type="text"
                     id="su-<?= e($aField) ?>" name="<?= e($aField) ?>" maxlength="50" required
                     value="<?= e($old[$aField]) ?>" autocomplete="off" placeholder="Letters and spaces only" />
              <div class="site-field__msg" data-msg-for="<?= e($aField) ?>"><?= e($errors[$aField] ?? '') ?></div>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- ================= VAULT PROFILE (system fields) ================= -->
        <h2 class="auth__legend">Vault profile</h2>

        <div class="site-grid site-grid--2">
          <div class="site-field">
            <label for="su-account-type">Account Type <span class="site-field__req">*</span></label>
            <select class="site-input<?= isset($errors['account_type']) ? ' site-input--invalid' : '' ?>"
                    id="su-account-type" name="account_type" required>
              <option value=""<?= $old['account_type'] === '' ? ' selected' : '' ?>>Select an account type</option>
              <?php foreach (ACCOUNT_TYPES as $type): ?>
                <option value="<?= e($type) ?>"<?= $old['account_type'] === $type ? ' selected' : '' ?>><?= e($type) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="site-field__msg" data-msg-for="account_type"><?= e($errors['account_type'] ?? '') ?></div>
          </div>

          <div class="site-field">
            <label for="su-vault">Vault Display Name <span class="site-field__req">*</span></label>
            <input class="site-input<?= isset($errors['vault_display_name']) ? ' site-input--invalid' : '' ?>" type="text"
                   id="su-vault" name="vault_display_name" maxlength="30" required
                   value="<?= e($old['vault_display_name']) ?>" placeholder="My Personal Vault" />
            <div class="site-field__msg" data-msg-for="vault_display_name"><?= e($errors['vault_display_name'] ?? '') ?></div>
          </div>
        </div>

        <div class="site-field" id="su-org-wrap"<?= $old['account_type'] === 'Organization' ? '' : ' hidden' ?>>
          <label for="su-org">Organization Name <span class="site-field__req">*</span></label>
          <input class="site-input<?= isset($errors['organization_name']) ? ' site-input--invalid' : '' ?>" type="text"
                 id="su-org" name="organization_name" maxlength="50"
                 value="<?= e($old['organization_name']) ?>" placeholder="Organization name" />
          <div class="site-field__msg" data-msg-for="organization_name"><?= e($errors['organization_name'] ?? '') ?></div>
        </div>

        <!-- ================= CAPTCHA (handout section 7) ================= -->
        <?php render_captcha('su-captcha', 'signup', $errors['captcha'] ?? ''); ?>

        <!-- ================= PRIVACY CONSENT ================= -->
        <div class="site-field">
          <label class="site-check" for="su-privacy">
            <input type="checkbox" id="su-privacy" name="privacy" value="1" required
                   <?= isset($_POST['privacy']) && $_POST['privacy'] === '1' ? 'checked' : '' ?> />
            <span>
              I have read the <a class="site-textlink" href="<?= e(url('privacy.php')) ?>" target="_blank" rel="noopener">Privacy Notice</a>
              and agree to the collection and processing of my personal data in accordance with the
              Data Privacy Act of 2012 (RA 10173). <span class="site-field__req">*</span>
            </span>
          </label>
          <div class="site-field__msg" data-msg-for="privacy"><?= e($errors['privacy'] ?? '') ?></div>
        </div>

        <div class="auth__stack">
          <button class="site-btn site-btn--primary site-btn--block" type="submit">Create account</button>
        </div>
      </form>

      <p class="auth__foot">
        Already have an account? <a class="site-textlink" href="<?= e(url('login.php')) ?>">Log in</a>
      </p>

    </div>
  </div>

</section>

<?php render_public_footer('', JS_URL . '/signup.js'); ?>
