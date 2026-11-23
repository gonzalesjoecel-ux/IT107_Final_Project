<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/* item_delete.php â€” deletes one item and its file (handler only, no HTML) */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/crypto.php';
require __DIR__ . '/includes/upload.php';
require_once __DIR__ . '/includes/activity.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('user_dashboard.php');
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
    flash('err', 'The form has expired. Please try again.');
    redirect('user_dashboard.php');
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

if (!$id) {
    flash('err', 'Invalid item.');
    redirect('user_dashboard.php');
}

try {
    /* user_id check = users may delete only their own items */
    $stmt = $pdo->prepare('SELECT file_stored FROM items WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        flash('err', 'Item not found.');
        redirect('user_dashboard.php');
    }

    $stmt = $pdo->prepare('DELETE FROM items WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);

    if (!empty($row['file_stored'])) {
        @unlink(stored_file_path($row['file_stored']));
    }

    flash('ok', 'Item deleted.');
    log_event($pdo, (int)$user['id'], 'item_delete');

} catch (Throwable $ex) {
    error_log('item_delete: ' . $ex->getMessage());
    flash('err', 'There was a problem deleting the item.');
}

redirect('user_dashboard.php');