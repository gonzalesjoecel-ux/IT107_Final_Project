<?php
/*
 * auth.php — shared by every logged-in page.
 * Does: DB connection, session, security headers, login check,
 *       idle timeout, loads $user (with role), and small helper functions.
 */
require __DIR__ . '/../../../db/db.php';

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/seed.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/activity.php';

const IDLE_TIMEOUT = IDLE_TIMEOUT_MINUTES * 60; // idle timeout (15 minutes)

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'");

/* ---------- URLs ---------- */

/* Web path of the "html" folder, e.g. /IT107/log_in_page/html */
$__script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (preg_match('#^(.*/html)(/|$)#', $__script, $__m)) {
    define('HTML_URL', $__m[1]);
} else {
    define('HTML_URL', dirname(dirname($__script)));
}
define('CSS_URL', dirname(HTML_URL) . '/css');
define('JS_URL', dirname(HTML_URL) . '/js');

/* url('user_dashboard.php') -> /IT107/log_in_page/html/user_dashboard.php */
function url(string $path): string {
    return HTML_URL . '/' . ltrim($path, '/');
}

/* ---------- helpers ---------- */

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/* One-time message shown on the next page. $type = 'ok' or 'err' */
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function take_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/*
 * Optional column from STEP 2 of admin_Setup.sql ("Forgot password").
 * Pages use this so the app still works on a database that has not run it yet.
 */
function has_reset_column(PDO $pdo): bool {
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    try {
        $pdo->query('SELECT reset_requested_at FROM users LIMIT 1');
        $has = true;
    } catch (Throwable $ex) {
        $has = false;
    }
    return $has;
}

/* ---------- guard: must be logged in ---------- */
/*
 * A public page (login / sign up) sets PUBLIC_PAGE before requiring this file,
 * so it skips the login check. It still gets $pdo, the session and the helpers.
 */
if (!defined('PUBLIC_PAGE') || !PUBLIC_PAGE) {

    if (empty($_SESSION['user_id'])) {
        redirect(url('login.php'));
    }

    /* Load the current user from the DB (don't trust the session alone) */
    try {
        $stmt = $pdo->prepare('SELECT id, username, fullname, email, role, is_active, last_login FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        error_log('auth: ' . $ex->getMessage());
        http_response_code(500);
        exit('Database is not ready. Run db/admin_setup.sql in phpMyAdmin first.');
    }

    /* Account removed or disabled by an admin -> kick out */
    if (!$user || (int)$user['is_active'] !== 1) {
        $_SESSION = [];
        session_destroy();
        redirect(url('login.php' . ($user ? '?disabled=1' : '')));
    }

    /* Idle timeout applies to regular users only; admin sessions do not expire. */
    if (($user['role'] ?? '') !== 'admin'
        && isset($_SESSION['last_activity'])
        && (time() - $_SESSION['last_activity']) > IDLE_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        redirect(url('login.php?expired=1'));
    }
    $_SESSION['last_activity'] = time();
}

/*
 * Called only after the password AND the OTP are both correct.
 * Creates the real session and sends the user to their dashboard.
 */
function finish_login(PDO $pdo, array $user): ?string
{
    if ((int)$user['is_active'] !== 1) {
        log_event($pdo, (int)$user['id'], 'login_fail', 'account disabled');
        return 'This account has been disabled. Please contact the administrator.';
    }
    if ((int)($user['is_verified'] ?? 1) !== 1) {
        return 'Please verify your email address before logging in.';
    }

    $pdo->prepare('UPDATE users SET last_login = ?, failed_attempts = 0, locked_until = NULL, lockout_level = 0 WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $user['id']]);

    log_event($pdo, (int)$user['id'], 'login_ok');

    unset($_SESSION['last_login_name'], $_SESSION['pending_otp'], $_SESSION['login_fail_count']);
    setcookie('plogin_lock_user', '', time() - 3600, '/');

    /* New session id after login (stops session fixation) */
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['fullname']      = $user['fullname'];
    $_SESSION['prev_login']    = $user['last_login'] ?? null;
    $_SESSION['last_activity'] = time();

    redirect($user['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php');
    return null; // never reached
}

/* Only admins may open admin pages. Put require_admin() at the top of each one. */
function require_admin(): void {
    global $user;
    if ($user['role'] !== 'admin') {
        flash('err', 'This page is for administrators only.');
        redirect(url('user_dashboard.php'));
    }
}