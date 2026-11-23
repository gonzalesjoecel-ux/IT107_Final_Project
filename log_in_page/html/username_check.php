<?php
/*
 * username_check.php — live username availability check (handout section 4.9).
 * Returns JSON only; the registration server check remains the authority.
 */
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seed.php';
require_once __DIR__ . '/includes/validation.php';

header('Content-Type: application/json; charset=utf-8');

$username = trim((string)($_GET['username'] ?? ''));

$error = v_username($username);
if ($error !== null) {
    echo json_encode(['available' => false, 'message' => $error]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $taken = (bool)$stmt->fetch();
} catch (Throwable $ex) {
    error_log('username_check: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['available' => false, 'message' => 'Unable to check the username right now.']);
    exit;
}

echo json_encode([
    'available' => !$taken,
    'message'   => $taken ? 'This username is already taken.' : 'Username is available.',
]);
