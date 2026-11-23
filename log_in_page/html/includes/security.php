<?php
/*
 * security.php — lockout tiers, IP blocking and alert emails
 * (handout sections 8.3 and 9).
 */

require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/mailer.php';

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function client_agent(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/* Which lockout tier does this failure count reach? */
function lockout_tier_for(int $attempts): ?array
{
    if ($attempts >= 9) {
        return ['level' => 3, 'seconds' => LOCKOUT_TIER_3];
    }
    if ($attempts === 6) {
        return ['level' => 2, 'seconds' => LOCKOUT_TIER_2];
    }
    if ($attempts === 3) {
        return ['level' => 1, 'seconds' => LOCKOUT_TIER_1];
    }
    return null;
}

/* Seconds left on an account lock, or 0 when the account is not locked. */
function user_lock_seconds(array $user): int
{
    if (empty($user['locked_until'])) {
        return 0;
    }
    $until = strtotime((string)$user['locked_until']);
    return $until !== false && $until > time() ? $until - time() : 0;
}

/* Seconds left on an IP block, or 0 when this address is not blocked. */
function ip_block_seconds(PDO $pdo): int
{
    $block = ip_block_state($pdo, client_ip());
    if (!$block) {
        return 0;
    }
    $until = strtotime((string)$block['blocked_until']);
    return $until !== false && $until > time() ? $until - time() : 0;
}

/*
 * One failed sign-in or recovery answer.
 * Applies the tiered lockout and emails the owner once per tier.
 * Returns ['attempts' => n, 'seconds' => n, 'level' => n, 'locked' => bool].
 */
function apply_failed_attempt(PDO $pdo, array $user, string $detail = 'wrong password'): array
{
    $userId    = (int)$user['id'];
    $oldLevel  = (int)($user['lockout_level'] ?? 0);
    $attempts  = (int)($user['failed_attempts'] ?? 0) + 1;
    $tier      = lockout_tier_for($attempts);
    $seconds   = $tier['seconds'] ?? 0;
    $level     = $tier ? max($oldLevel, $tier['level']) : $oldLevel;

    if ($tier) {
        $pdo->prepare(
            'UPDATE users
             SET failed_attempts = ?, last_failed_at = NOW(), lockout_level = ?,
                 locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND)
             WHERE id = ?'
        )->execute([$attempts, $level, $seconds, $userId]);
    } else {
        $pdo->prepare(
            'UPDATE users SET failed_attempts = ?, last_failed_at = NOW() WHERE id = ?'
        )->execute([$attempts, $userId]);
    }

    log_event($pdo, $userId, 'login_fail', $detail);

    if ($tier && $tier['level'] > $oldLevel) {
        send_security_alert($pdo, $user, 'Failed sign-in attempts on your account', $attempts);
        log_event(
            $pdo,
            $userId,
            'account_locked',
            'Tier ' . $tier['level'] . ' after ' . $attempts . ' failures (' . $seconds . ' seconds)'
        );
    }

    record_ip_failure($pdo, client_ip());

    return [
        'attempts' => $attempts,
        'seconds'  => $seconds,
        'level'    => $level,
        'locked'   => $seconds > 0,
    ];
}

function ip_block_state(PDO $pdo, string $ip): ?array
{
    $s = $pdo->prepare(
        'SELECT * FROM ip_blocks
         WHERE ip_address = ? AND blocked_until > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $s->execute([$ip]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/*
 * Count failures from this IP across every account in the last 10 minutes.
 * When the IP block threshold is reached, create a 10-minute block.
 */
function record_ip_failure(PDO $pdo, string $ip): void
{
    if ($ip === '' || ip_block_state($pdo, $ip)) {
        return;
    }

    try {
        $s = $pdo->prepare(
            'SELECT COUNT(*) FROM activity_log
             WHERE event = ? AND ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $s->execute(['login_fail', $ip, IP_BLOCK_MINUTES]);
        $count = (int)$s->fetchColumn();

        if ($count >= IP_BLOCK_THRESHOLD) {
            $pdo->prepare(
                'INSERT INTO ip_blocks (ip_address, reason, failed_attempts, blocked_at, blocked_until)
                 VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE))'
            )->execute([
                $ip,
                'Too many sign-in failures across accounts',
                $count,
                IP_BLOCK_MINUTES,
            ]);
            log_event($pdo, null, 'ip_blocked', 'IP blocked after ' . $count . ' failures');
        }
    } catch (Throwable $ex) {
        error_log('record_ip_failure: ' . $ex->getMessage());
    }
}

function absolute_url(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . url($path);
}

/*
 * Email the real owner. The caller also logs the lockout/password event.
 * Returns true when the alert was handed to SMTP or the local outbox.
 */
function send_security_alert(PDO $pdo, array $user, string $title, int $attempts): bool
{
    if (empty($user['email'])) {
        return false;
    }

    $time = date('F j, Y g:i A');
    $ip   = client_ip();
    $agent = client_agent();
    $reset = absolute_url('forgot.php');

    $result = send_app_mail(
        (string)$user['email'],
        'Encryptica — ' . $title,
        alert_email_html((string)($user['fullname'] ?? ''), $title, $attempts, $ip, $agent, $time, $reset),
        alert_email_text((string)($user['fullname'] ?? ''), $title, $attempts, $ip, $agent, $time, $reset)
    );

    if ($result['ok']) {
        log_event($pdo, (int)($user['id'] ?? 0) ?: null, 'alert_email_sent', $title);
    } else {
        error_log('security alert failed: ' . $result['error']);
    }

    return $result['ok'];
}

/* "Your password was changed" email (handout section 9.1). */
function send_password_changed_alert(PDO $pdo, array $user): bool
{
    if (empty($user['email'])) {
        return false;
    }

    $time  = date('F j, Y g:i A');
    $ip    = client_ip();
    $agent = client_agent();
    $reset = absolute_url('forgot.php');

    $result = send_app_mail(
        (string)$user['email'],
        'Encryptica — Your password was changed',
        password_changed_email_html((string)($user['fullname'] ?? ''), $time, $ip, $agent, $reset),
        password_changed_email_text((string)($user['fullname'] ?? ''), $time, $ip, $agent, $reset)
    );

    if ($result['ok']) {
        log_event($pdo, (int)($user['id'] ?? 0) ?: null, 'alert_email_sent', 'Your password was changed');
    } else {
        error_log('password changed alert failed: ' . $result['error']);
    }

    return $result['ok'];
}
