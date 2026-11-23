<?php
/*
 * otp.php — six-digit one-time passwords (handout section 6).
 *
 * Rules implemented here:
 *   - random_int only, six digits
 *   - stored hashed with a purpose, expiry and attempt counter
 *   - single use; requesting a new code cancels the previous one
 *   - three wrong entries cancel the code
 *   - resend cooldown (60s) and at most 3 resends per 15 minutes
 */

require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/mailer.php';

const OTP_MAX_ATTEMPTS   = 3;
const OTP_RESEND_WAIT    = 60;
const OTP_RESEND_LIMIT   = 3;   // resends allowed in the 15-minute window
const OTP_RESEND_WINDOW  = 15;  // minutes
const OTP_TOTAL_LIMIT    = 4;   // first message + 3 resends

function otp_new_code(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/* Cancel every unused code for this account and purpose. */
function otp_invalidate(PDO $pdo, string $identifier, string $purpose): void
{
    $pdo->prepare(
        'UPDATE otp_codes SET used_at = NOW()
         WHERE identifier = ? AND purpose = ? AND used_at IS NULL'
    )->execute([$identifier, $purpose]);
}

function otp_last_sent(PDO $pdo, string $identifier, string $purpose): ?array
{
    $s = $pdo->prepare(
        'SELECT id, created_at, expires_at, attempts FROM otp_codes
         WHERE identifier = ? AND purpose = ? ORDER BY id DESC LIMIT 1'
    );
    $s->execute([$identifier, $purpose]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/* How many codes were created for this account/purpose in the window. */
function otp_recent_count(PDO $pdo, string $identifier, string $purpose): int
{
    $s = $pdo->prepare(
        'SELECT COUNT(*) FROM otp_codes
         WHERE identifier = ? AND purpose = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
    );
    $s->execute([$identifier, $purpose, OTP_RESEND_WINDOW]);
    return (int)$s->fetchColumn();
}

/*
 * Resend state for the page: cooldown seconds, resends left, can_resend.
 * The first message is not a resend, so the limit counts total messages - 1.
 */
function otp_status(PDO $pdo, string $identifier, string $purpose): array
{
    $last = otp_last_sent($pdo, $identifier, $purpose);
    $cooldown = 0;
    if ($last) {
        $cooldown = max(0, OTP_RESEND_WAIT - (time() - strtotime($last['created_at'])));
    }

    $usedInWindow = otp_recent_count($pdo, $identifier, $purpose);
    $resendsLeft  = max(0, OTP_TOTAL_LIMIT - $usedInWindow);

    return [
        'cooldown'     => $cooldown,
        'resends_left' => $resendsLeft,
        'can_resend'   => $cooldown === 0 && $resendsLeft > 0,
        'attempts'     => $last ? (int)$last['attempts'] : 0,
    ];
}

/*
 * Create and email a new OTP. Returns:
 *   ['ok'=>true, 'expires'=>datetime, 'via'=>smtp|outbox]
 *   ['ok'=>false, 'error'=>message]
 */
function otp_issue(PDO $pdo, ?int $userId, string $identifier, string $purpose, string $email, string $name): array
{
    $identifier = mb_strtolower(trim($identifier), 'UTF-8');
    $status = otp_status($pdo, $identifier, $purpose);

    if ($status['cooldown'] > 0) {
        return ['ok' => false, 'error' => 'Please wait ' . $status['cooldown'] . ' seconds before requesting a new code.'];
    }
    if ($status['resends_left'] <= 0) {
        return ['ok' => false, 'error' => 'Too many codes were requested. Please wait 15 minutes and try again.'];
    }

    $code    = otp_new_code();
    $expires = date('Y-m-d H:i:s', time() + (OTP_TTL_MINUTES * 60));

    otp_invalidate($pdo, $identifier, $purpose);

    $pdo->prepare(
        'INSERT INTO otp_codes (user_id, identifier, purpose, otp_hash, attempts, created_at, expires_at, ip_address)
         VALUES (?, ?, ?, ?, 0, NOW(), ?, ?)'
    )->execute([
        $userId,
        $identifier,
        $purpose,
        password_hash($code, PASSWORD_DEFAULT),
        $expires,
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);
    $otpId = (int)$pdo->lastInsertId();

    $subject = 'Encryptica — ' . otp_purpose_title($purpose);
    $result = send_app_mail(
        $email,
        $subject,
        otp_email_html($name, $purpose, $code, OTP_TTL_MINUTES),
        otp_email_text($name, $purpose, $code, OTP_TTL_MINUTES)
    );

    if (!$result['ok']) {
        $pdo->prepare('UPDATE otp_codes SET used_at = NOW() WHERE id = ?')->execute([$otpId]);
        log_event($pdo, $userId, 'otp_failed', 'Could not send ' . $purpose . ' code');
        return ['ok' => false, 'error' => 'The code could not be emailed. Please try again later.'];
    }

    log_event($pdo, $userId, 'otp_sent', $purpose . ' code sent via ' . $result['via']);
    return ['ok' => true, 'expires' => $expires, 'via' => $result['via']];
}

/*
 * Check one OTP. Always consumes an attempt and, on success, marks the code
 * as used. Returns:
 *   ['ok'=>true, 'user_id'=>int]
 *   ['ok'=>false, 'error'=>message, 'attempts_left'=>int]
 */
function otp_verify(PDO $pdo, string $identifier, string $purpose, string $code): array
{
    $identifier = mb_strtolower(trim($identifier), 'UTF-8');
    $code = trim($code);

    $pdo->beginTransaction();
    $s = $pdo->prepare(
        'SELECT id, user_id, otp_hash, attempts, expires_at, used_at
         FROM otp_codes
         WHERE identifier = ? AND purpose = ? AND used_at IS NULL
         ORDER BY id DESC LIMIT 1 FOR UPDATE'
    );
    $s->execute([$identifier, $purpose]);
    $row = $s->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->commit();
        log_event($pdo, null, 'otp_failed', 'No active ' . $purpose . ' code');
        return ['ok' => false, 'error' => 'That code is no longer valid. Please request a new one.', 'attempts_left' => 0];
    }

    if (strtotime($row['expires_at']) <= time()) {
        $pdo->prepare('UPDATE otp_codes SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
        $pdo->commit();
        log_event($pdo, (int)$row['user_id'], 'otp_failed', 'Expired ' . $purpose . ' code');
        return ['ok' => false, 'error' => 'That code has expired. Please request a new one.', 'attempts_left' => 0];
    }

    $attempts = (int)$row['attempts'] + 1;

    if (!password_verify($code, $row['otp_hash'])) {
        $cancel = $attempts >= OTP_MAX_ATTEMPTS;
        $pdo->prepare(
            'UPDATE otp_codes SET attempts = ?, used_at = ' . ($cancel ? 'NOW()' : 'used_at') . ' WHERE id = ?'
        )->execute([$attempts, $row['id']]);
        $pdo->commit();

        log_event($pdo, (int)$row['user_id'], 'otp_failed', 'Wrong ' . $purpose . ' code');

        if ($cancel) {
            return [
                'ok' => false,
                'error' => 'Too many wrong codes. This code is cancelled; please request a new one.',
                'attempts_left' => 0,
            ];
        }
        return [
            'ok' => false,
            'error' => 'The code is incorrect. Please try again.',
            'attempts_left' => OTP_MAX_ATTEMPTS - $attempts,
        ];
    }

    $pdo->prepare('UPDATE otp_codes SET attempts = ?, used_at = NOW() WHERE id = ?')
        ->execute([$attempts, $row['id']]);
    $pdo->commit();

    return ['ok' => true, 'user_id' => (int)$row['user_id']];
}

/* Pending verification kept in the session between the form and the OTP page. */
function pending_otp_set(array $data): void
{
    $_SESSION['pending_otp'] = [
        'user_id'    => (int)$data['user_id'],
        'identifier' => (string)$data['identifier'],
        'purpose'    => (string)$data['purpose'],
        'email'      => (string)$data['email'],
        'name'       => (string)($data['name'] ?? ''),
        'started'    => time(),
    ];
}

function pending_otp_get(): ?array
{
    $pending = $_SESSION['pending_otp'] ?? null;
    if (!is_array($pending) || empty($pending['user_id']) || empty($pending['purpose'])) {
        return null;
    }
    // A pending verification cannot live forever.
    if (time() - (int)($pending['started'] ?? 0) > 3600) {
        unset($_SESSION['pending_otp']);
        return null;
    }
    return $pending;
}

function pending_otp_clear(): void
{
    unset($_SESSION['pending_otp']);
}
