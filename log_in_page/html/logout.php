<?php
/* logout.php â€” POST only, with CSRF token. Logs the event, then destroys the session. */
require __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/includes/activity.php';

date_default_timezone_set('Asia/Manila');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

/* Only accept POST with a valid CSRF token */
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')
) {
    header('Location: ' . (empty($_SESSION['user_id']) ? 'login.php' : 'user_dashboard.php'));
    exit;
}

if (!empty($_SESSION['user_id'])) {
    log_event($pdo, (int)$_SESSION['user_id'], 'logout');
}

/* Fully destroy the session + its cookie */
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: login.php');
exit;