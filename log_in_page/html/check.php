<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/*
 * check.php — one-page health check. Open it in the browser while logged in:
 *   http://localhost/IT107/log_in_page/html/check.php
 * Shows what is missing. DELETE THIS FILE when everything is OK.
 */
require __DIR__ . '/includes/auth.php';

$rows = [];
function row(string $label, bool $ok, string $hint = ''): void {
    global $rows;
    $rows[] = [$label, $ok, $hint];
}

$notes = [];
function note(string $label, string $hint = ''): void {
    global $notes;
    $notes[] = [$label, $hint];
}

/* 1. PHP + extensions */
row('PHP version ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>='), 'PHP 7.4 or higher is required');
foreach (['openssl', 'mbstring', 'fileinfo', 'pdo_mysql', 'gd'] as $ext) {
    row("PHP extension: $ext", extension_loaded($ext), "Enable extension=$ext in php.ini, then restart Apache");
}

/* 2. Files */
$files = [
    'login.php', 'logout.php', 'signup.php', 'privacy.php', 'username_check.php',
    'captcha.php', 'layout.php', 'forgot.php', 'verify_otp.php', 'change_password.php',
    'includes/auth.php', 'includes/crypto.php', 'includes/upload.php', 'includes/activity.php',
    'includes/layout.php', 'includes/seed.php', 'includes/validation.php', 'includes/captcha.php',
    'includes/otp.php', 'includes/mailer.php', 'includes/security.php',
    '../lib/PHPMailer/PHPMailer.php', '../lib/PHPMailer/SMTP.php',
    'user_dashboard.php', 'profile.php', 'profile_update.php', 'item_add.php', 'item_delete.php', 'item_file.php',
    'admin_dashboard.php', 'users.php', 'user_view.php', 'user_action.php', 'activity.php',
    '../css/app.css', '../js/signup.js', '../css/img/Loginbg.jpg',
];
foreach ($files as $f) {
    row("File: $f", is_file(__DIR__ . '/' . $f), 'Missing or in the wrong folder.');
}

/* 2b. Old files that are no longer used (info only, does not fail the check) */
foreach (['index.php', 'includes/nav.php', 'includes/helpers.php', 'includes/public.php',
          '../css/dashboard.css', '../css/site.css', '../css/img/login-bg.jpg', '../js/site.js', '../js/binary_rain.js'] as $old) {
    if (is_file(__DIR__ . '/' . $old)) {
        note("Old file still here: $old", 'Legacy file that is no longer used. You may delete it if you are sure.');
    }
}

/* 3. Functions (only if the files exist) */
foreach (['includes/crypto.php', 'includes/upload.php', 'includes/activity.php', 'includes/seed.php', 'includes/validation.php', 'includes/captcha.php', 'includes/otp.php', 'includes/mailer.php', 'includes/security.php', 'layout.php'] as $inc) {
    if (is_file(__DIR__ . '/' . $inc)) {
        require_once __DIR__ . '/' . $inc;
    }
}
$needed = [
    'auth.php'     => ['url', 'e', 'redirect', 'csrf_token', 'csrf_check', 'flash', 'take_flash', 'require_admin', 'has_reset_column', 'finish_login'],
    'crypto.php'   => ['storage_dir', 'app_key', 'encrypt_raw', 'decrypt_raw', 'encrypt_text', 'decrypt_text'],
    'upload.php'   => ['save_upload', 'has_upload', 'stored_file_path', 'human_size', 'allowed_file_types'],
    'activity.php' => ['log_event', 'event_info'],
    'validation.php' => ['v_name', 'v_id_number', 'v_birthdate', 'v_email', 'v_username', 'v_password', 'v_answer'],
    'captcha.php'    => ['captcha_new_code', 'captcha_store', 'captcha_check', 'render_captcha'],
    'otp.php'        => ['otp_new_code', 'otp_issue', 'otp_verify', 'otp_status', 'pending_otp_set', 'pending_otp_get'],
    'security.php'   => ['client_ip', 'apply_failed_attempt', 'ip_block_state', 'send_security_alert', 'send_password_changed_alert'],
    'mailer.php'     => ['send_app_mail'],
    'layout.php'   => ['icon', 'theme_boot_script', 'theme_toggle_button', 'site_theme_js', 'site_background_js',
                       'render_header', 'render_footer', 'user_status', 'render_feed', 'render_pager',
                       'render_public_header', 'render_auth_brand', 'render_public_lock', 'render_auth_hero', 'render_public_footer'],
];
foreach ($needed as $file => $fns) {
    foreach ($fns as $fn) {
        row("Function $fn()  [$file]", function_exists($fn), "The $file file is outdated. Replace it with the latest version.");
    }
}
row('Class UploadException  [upload.php]', class_exists('UploadException', false), 'upload.php is outdated or missing.');
$settingsOk = defined('USERNAME_MIN') && USERNAME_MIN === 6
    && defined('USERNAME_MAX') && USERNAME_MAX === 20
    && defined('PASSWORD_MIN') && PASSWORD_MIN === 8
    && defined('PASSWORD_MAX') && PASSWORD_MAX === 64
    && defined('NAME_MAX') && NAME_MAX === 50
    && defined('FORGOT_AFTER_FAILURES') && FORGOT_AFTER_FAILURES === 2
    && defined('LOCKOUT_TIER_1') && LOCKOUT_TIER_1 === 15
    && defined('LOCKOUT_TIER_2') && LOCKOUT_TIER_2 === 30
    && defined('LOCKOUT_TIER_3') && LOCKOUT_TIER_3 === 60
    && defined('OTP_TTL_MINUTES') && OTP_TTL_MINUTES === 5
    && defined('IDLE_TIMEOUT_MINUTES') && IDLE_TIMEOUT_MINUTES === 15
    && defined('IP_BLOCK_THRESHOLD') && IP_BLOCK_THRESHOLD === 10
    && defined('IP_BLOCK_MINUTES') && IP_BLOCK_MINUTES === 10;

row('Shared security settings (class values, November 2026)', $settingsOk,
    'includes/seed.php does not match the values given by the instructor.');
row('Authentication question bank: ' . (defined('AUTH_QUESTION_BANK') ? count(AUTH_QUESTION_BANK) : 0) . ' questions',
    defined('AUTH_QUESTION_BANK') && count(AUTH_QUESTION_BANK) >= 3,
    'The question bank is missing from includes/seed.php.');

/* 4. Encryption round-trip + storage folder */
try {
    if (function_exists('encrypt_text') && function_exists('decrypt_text')) {
        $ok = decrypt_text(encrypt_text('test', 'user:1'), 'user:1') === 'test';
        row('Encrypt/decrypt test', $ok, 'Encryption is not working.');
    }
    if (function_exists('storage_dir')) {
        $dir = storage_dir();
        row('Storage folder: ' . realpath($dir), is_dir($dir) && is_writable($dir), 'The folder is not writable.');
    }
} catch (Throwable $ex) {
    row('Encryption / storage', false, $ex->getMessage());
}

/* 5. Database */
foreach ([
    'Table items (basic columns)'  => ['SELECT id, user_id, title, content_enc, created_at FROM items LIMIT 1', 'Run items_setup.sql in phpMyAdmin.'],
    'Table items (upload columns)' => ['SELECT file_name, file_stored, file_mime, file_size FROM items LIMIT 1', 'Run items_upload_update.sql in phpMyAdmin.'],
    'Table users (role, is_active, last_login)' => ['SELECT role, is_active, last_login, failed_attempts, locked_until FROM users LIMIT 1', 'Run admin_setup.sql in phpMyAdmin.'],
    'Column users.reset_requested_at (Forgot Password)' => ['SELECT reset_requested_at FROM users LIMIT 1', 'Run STEP 2 at the end of admin_setup.sql.'],
    'Columns users (registration fields)' => ['SELECT id_number, first_name, middle_name, family_name, name_extension, birthdate, privacy_consent, consent_timestamp FROM users LIMIT 1', 'Run security_project_update.sql in phpMyAdmin.'],
    'Columns users (address and seed lockout)' => ['SELECT house_street, barangay, municipality_city, province, country, postal_code, lockout_level, last_failed_at FROM users LIMIT 1', 'Run security_project_update.sql in phpMyAdmin.'],
    'Columns users (verification and system fields)' => ['SELECT is_verified, recovery_mobile, account_type, vault_display_name, organization_name FROM users LIMIT 1', 'Run security_project_update.sql in phpMyAdmin.'],
    'Table password_history'       => ['SELECT id, user_id, password_hash, created_at FROM password_history LIMIT 1', 'Run security_project_update.sql in phpMyAdmin.'],
    'Table user_security_answers'  => ['SELECT id, user_id, question_code, answer_hash FROM user_security_answers LIMIT 1', 'Run security_project_update.sql in phpMyAdmin.'],
    'Table otp_codes'              => ['SELECT id, user_id, identifier, purpose, otp_hash, expires_at FROM otp_codes LIMIT 1', 'Run security_project_update.sql in phpMyAdmin.'],
    'Table security_flags'         => ['SELECT id, pattern_type, target_type, target_value, status FROM security_flags LIMIT 1', 'Run security_project_update.sql in phpMyAdmin.'],
    'Table ip_blocks'              => ['SELECT id, ip_address, failed_attempts, blocked_until FROM ip_blocks LIMIT 1', 'Run security_project_update.sql in phpMyAdmin.'],
    'Table activity_log'           => ['SELECT id, user_id, actor_id, event, detail, ip, created_at FROM activity_log LIMIT 1', 'Run admin_setup.sql in phpMyAdmin.'],
] as $label => [$sql, $hint]) {
    try {
        $pdo->query($sql);
        row($label, true);
    } catch (Throwable $ex) {
        row($label, false, $hint);
    }
}

row('Your role: ' . $user['role'], true);

/* 6. Upload limits (info) */
$up   = ini_get('upload_max_filesize');
$post = ini_get('post_max_size');
row("php.ini: file_uploads = " . (ini_get('file_uploads') ? 'On' : 'Off'), (bool)ini_get('file_uploads'), 'Enable file_uploads in php.ini.');
row("php.ini: upload_max_filesize = $up, post_max_size = $post", true, 'Both values should be at least 5M.');

$failed = count(array_filter($rows, fn($r) => !$r[1]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Check — Encryptica</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #05070f; color: #e8ecf6; margin: 0; padding: 32px 16px; }
    main { max-width: 760px; margin: 0 auto; }
    h1 { margin: 0 0 6px; }
    .sum { padding: 12px 16px; border-radius: 10px; margin: 16px 0 24px; font-weight: 600; }
    .sum--ok  { background: rgba(45,212,191,.12); color: #2dd4bf; }
    .sum--bad { background: rgba(248,113,113,.12); color: #fca5a5; }
    ul { list-style: none; padding: 0; margin: 0; }
    li { padding: 10px 0; border-top: 1px solid #1c2540; display: flex; gap: 12px; }
    .tag { flex-shrink: 0; width: 46px; font-weight: 700; }
    .ok { color: #2dd4bf; } .bad { color: #f87171; }
    .hint { color: #8a94b3; font-size: .88rem; }
  </style>
</head>
<body>
<main>
  <h1>System check</h1>
  <div class="sum <?= $failed ? 'sum--bad' : 'sum--ok' ?>">
    <?= $failed ? e($failed . ' issue(s) need attention. Review the red items below.') : 'All checks passed. You can now delete check.php.' ?>
  </div>

  <ul>
    <?php foreach ($rows as [$label, $ok, $hint]): ?>
      <li>
        <span class="tag <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'OK' : 'FAIL' ?></span>
        <div>
          <?= e($label) ?>
          <?php if (!$ok && $hint): ?><div class="hint"><?= e($hint) ?></div><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($notes): ?>
    <h2>Information</h2>
    <ul>
      <?php foreach ($notes as [$label, $hint]): ?>
        <li>
          <span class="tag" style="color:#8a94b3">INFO</span>
          <div>
            <?= e($label) ?>
            <?php if ($hint): ?><div class="hint"><?= e($hint) ?></div><?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</main>
</body>
</html>