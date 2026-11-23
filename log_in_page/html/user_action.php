<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/* admin/user_action.php â€” unlock / disable / enable a user (handler only, admin only) */
require __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/activity.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users.php');
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
    flash('err', 'The form has expired. Please try again.');
    redirect('users.php');
}

$id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$back   = 'user_view.php?id=' . (int)$id;

if (!$id || !in_array($action, ['unlock', 'disable', 'enable', 'reset'], true)) {
    flash('err', 'Invalid request.');
    redirect('users.php');
}

/* Safety: an admin can't disable their own account */
if ($action === 'disable' && $id === (int)$user['id']) {
    flash('err', 'You cannot disable your own account.');
    redirect($back);
}

/* Random temporary password, easy to read out loud (no 0/O, 1/l/I) */
function make_temp_password(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    do {
        $pw = '';
        for ($i = 0; $i < 12; $i++) {
            $pw .= $chars[random_int(0, strlen($chars) - 1)];
        }
    } while (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw));
    return $pw;
}

if ($action === 'reset' && $id === (int)$user['id']) {
    flash('err', 'Use the Profile page to change your own password.');
    redirect($back);
}

try {
    $s = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $s->execute([$id]);
    if (!$s->fetch()) {
        flash('err', 'User not found.');
        redirect('users.php');
    }

    $by = 'by @' . $user['username'];

    if ($action === 'unlock') {
        $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$id]);
        log_event($pdo, $id, 'admin_unlock', $by, (int)$user['id']);
        flash('ok', 'The account has been unlocked.');

    } elseif ($action === 'disable') {
        $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$id]);
        log_event($pdo, $id, 'admin_disable', $by, (int)$user['id']);
        flash('ok', 'The account has been disabled. The user can no longer log in.');

    } elseif ($action === 'reset') {
        $temp = make_temp_password();
        $sql  = 'UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL'
              . (has_reset_column($pdo) ? ', reset_requested_at = NULL' : '') . ' WHERE id = ?';
        $pdo->prepare($sql)->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
        log_event($pdo, $id, 'admin_reset', $by, (int)$user['id']);
        flash('ok', 'Password reset successfully. Temporary password: ' . $temp . '  (Give this to the user and ask them to change it on the Profile page after logging in. It will not be shown again.)');

    } else {
        $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$id]);
        log_event($pdo, $id, 'admin_enable', $by, (int)$user['id']);
        flash('ok', 'The account has been re-enabled.');
    }

} catch (Throwable $ex) {
    error_log('admin/user_action: ' . $ex->getMessage());
    flash('err', 'Something went wrong. Please try again.');
}

redirect($back);