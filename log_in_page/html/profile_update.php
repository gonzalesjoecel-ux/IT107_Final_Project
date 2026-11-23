<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/* profile_update.php â€” saves profile changes (handler only, no HTML) */
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
    flash('err', 'The form has expired. Please try again.');
    redirect('profile.php');
}

/* ---------- Change password (separate form on the profile page) ---------- */
if (($_POST['action'] ?? '') === 'password') {

    $current = (string)($_POST['current'] ?? '');
    $new     = (string)($_POST['new'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    $err     = null;

    try {
        $s = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $s->execute([$user['id']]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $err = 'Your current password is incorrect.';
        } elseif (($e = v_password($new)) !== null) {
            $err = $e;
        } elseif ($new !== $confirm) {
            $err = 'Mismatch Password.';
        } else {
            /* None of the last three passwords may be reused. */
            $s = $pdo->prepare(
                'SELECT password_hash FROM password_history
                 WHERE user_id = ? ORDER BY id DESC LIMIT 3'
            );
            $s->execute([$user['id']]);
            foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $oldHash) {
                if (password_verify($new, (string)$oldHash)) {
                    $err = 'You cannot reuse a recent password.';
                    break;
                }
            }
        }

        if ($err === null) {
            $hash = password_hash($new, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE users
                 SET password_hash = ?, failed_attempts = 0, locked_until = NULL,
                     lockout_level = 0, last_failed_at = NULL
                 WHERE id = ?'
            )->execute([$hash, $user['id']]);
            $pdo->prepare(
                'INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)'
            )->execute([$user['id'], $hash]);
            $pdo->commit();

            if (has_reset_column($pdo)) {
                $pdo->prepare('UPDATE users SET reset_requested_at = NULL WHERE id = ?')->execute([$user['id']]);
            }

            session_regenerate_id(true);
            log_event($pdo, (int)$user['id'], 'password_changed', 'from profile');
            send_password_changed_alert($pdo, $user);
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('profile_update(password): ' . $ex->getMessage());
        $err = 'Something went wrong. Please try again.';
    }

    flash($err ? 'err' : 'ok', $err ?? 'Successfully Change Password.');
    redirect('profile.php');
}

$fullname = trim($_POST['fullname'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');

/* Keep what they typed so the form isn't emptied on error */
$fail = function (string $msg) use ($fullname, $username, $email): void {
    $_SESSION['old'] = ['fullname' => $fullname, 'username' => $username, 'email' => $email];
    flash('err', $msg);
    redirect('profile.php');
};

if ($fullname === '' || mb_strlen($fullname) > 100) {
    $fail('Full name is required and must not exceed 100 characters.');
}
if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
    $fail('Username must be 3-30 characters; letters, numbers, _ and . are allowed (no spaces).');
}
if (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fail('Invalid email address.');
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
    $stmt->execute([$username, $user['id']]);
    if ($stmt->fetch()) {
        $fail('This username is already taken by another user.');
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$email, $user['id']]);
    if ($stmt->fetch()) {
        $fail('This email is already taken by another user.');
    }

    $stmt = $pdo->prepare('UPDATE users SET fullname = ?, username = ?, email = ? WHERE id = ?');
    $stmt->execute([$fullname, $username, $email, $user['id']]);

    $_SESSION['username'] = $username;
    $_SESSION['fullname'] = $fullname;

    flash('ok', 'Your profile has been updated.');
    log_event($pdo, (int)$user['id'], 'profile_update');

} catch (Throwable $ex) {
    error_log('profile_update: ' . $ex->getMessage());
    $fail('There was a problem saving your changes. Please try again.');
}

redirect('profile.php');