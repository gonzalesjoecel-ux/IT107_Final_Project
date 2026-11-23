<?php
/*
 * upload.php — checks an uploaded picture/document, encrypts it,
 * and saves it in encryptica_storage/uploads/.
 * Used by item_add.php.
 */

const MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB

class UploadException extends Exception {} // message is safe to show to the user

/*
 * Allowed file types.
 *   mime  = what we send back when downloading/showing it
 *   check = what the file's real content must look like (null = skip check)
 */
function allowed_file_types(): array {
    $jpg = ['mime' => 'image/jpeg', 'check' => ['image/jpeg']];

    return [
        'jpg'  => $jpg,
        'jpeg' => $jpg,
        'png'  => ['mime' => 'image/png',  'check' => ['image/png']],
        'gif'  => ['mime' => 'image/gif',  'check' => ['image/gif']],
        'webp' => ['mime' => 'image/webp', 'check' => ['image/webp']],
        'pdf'  => ['mime' => 'application/octet-stream', 'check' => ['application/pdf']],
        'txt'  => ['mime' => 'application/octet-stream', 'check' => ['text/plain']],
        'csv'  => ['mime' => 'application/octet-stream', 'check' => ['text/plain', 'text/csv', 'application/csv']],
        'doc'  => ['mime' => 'application/octet-stream', 'check' => null],
        'docx' => ['mime' => 'application/octet-stream', 'check' => null],
        'xls'  => ['mime' => 'application/octet-stream', 'check' => null],
        'xlsx' => ['mime' => 'application/octet-stream', 'check' => null],
        'ppt'  => ['mime' => 'application/octet-stream', 'check' => null],
        'pptx' => ['mime' => 'application/octet-stream', 'check' => null],
    ];
}

function human_size(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
    return $bytes . ' B';
}

function has_upload(): bool {
    return isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE;
}

/*
 * Validates + encrypts + saves the file.
 * Returns: ['name' => ..., 'stored' => ..., 'mime' => ..., 'size' => ...]
 * Throws UploadException with a friendly message if something is wrong.
 */
function save_upload(int $userId): array {
    $f = $_FILES['attachment'];

    switch ($f['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new UploadException('File too large. Max 5 MB only.');
        default:
            throw new UploadException('File upload failed. Please try again.');
    }

    if (!is_uploaded_file($f['tmp_name'])) {
        throw new UploadException('Invalid file upload.');
    }
    if ($f['size'] > MAX_FILE_BYTES || $f['size'] <= 0) {
        throw new UploadException('File too large. Max 5 MB only.');
    }

    /* Original name: keep only the file name, clean it up */
    $name = basename(str_replace('\\', '/', (string)$f['name']));
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    $name = mb_substr($name, 0, 150);

    $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $types = allowed_file_types();

    if (!isset($types[$ext])) {
        throw new UploadException('File type not allowed. Allowed: picture (jpg, png, gif, webp) and documents (pdf, txt, csv, doc, docx, xls, xlsx, ppt, pptx).');
    }

    /* Check the real content, not just the name */
    $rule = $types[$ext];
    if ($rule['check'] !== null) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real  = $finfo->file($f['tmp_name']);

        if (!in_array($real, $rule['check'], true)) {
            throw new UploadException('The file you attached is not recognizable in .' . $ext . ' extension.');
        }
        if (strpos($rule['mime'], 'image/') === 0 && @getimagesize($f['tmp_name']) === false) {
            throw new UploadException('Invalid Picture.');
        }
    }

    $data = file_get_contents($f['tmp_name']);
    if ($data === false) {
        throw new UploadException('File cannot be read.');
    }

    /* Encrypt and save under a random name */
    $dir = storage_dir() . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create uploads folder: ' . $dir);
    }

    $stored = bin2hex(random_bytes(16)) . '.enc';

    if (file_put_contents($dir . '/' . $stored, encrypt_raw($data, 'file:' . $userId), LOCK_EX) === false) {
        throw new RuntimeException('Cannot write file: ' . $dir . '/' . $stored);
    }

    return [
        'name'   => $name,
        'stored' => $stored,
        'mime'   => $rule['mime'],
        'size'   => (int)$f['size'],
    ];
}

/* Path of an already-saved encrypted file */
function stored_file_path(string $stored): string {
    return storage_dir() . '/uploads/' . basename($stored);
}