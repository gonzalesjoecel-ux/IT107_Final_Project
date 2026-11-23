<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/*
 * item_file.php — decrypts and sends back an item's picture/document.
 *   item_file.php?id=5          -> picture shows in the browser
 *   item_file.php?id=5&dl=1     -> downloads the file
 * Only the owner can open it.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/crypto.php';
require __DIR__ . '/includes/upload.php';

function not_found(): void {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    not_found();
}

try {
    $stmt = $pdo->prepare(
        'SELECT file_name, file_stored, file_mime FROM items
         WHERE id = ? AND user_id = ? AND file_stored IS NOT NULL'
    );
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        not_found();
    }

    $path = stored_file_path($row['file_stored']);
    if (!is_file($path)) {
        not_found();
    }

    $data = decrypt_raw((string)file_get_contents($path), 'file:' . $user['id']);
    if ($data === null) {
        http_response_code(500);
        echo 'Cannot decrypt file';
        exit;
    }

} catch (Throwable $ex) {
    error_log('item_file: ' . $ex->getMessage());
    http_response_code(500);
    echo 'Error';
    exit;
}

$mime     = $row['file_mime'] ?: 'application/octet-stream';
$isImage  = strpos($mime, 'image/') === 0;
$inline   = $isImage && empty($_GET['dl']);
$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$row['file_name']);

/* Locked-down headers: the file can never run as a web page */
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($data));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
    . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode((string)$row['file_name']));

echo $data;
exit;