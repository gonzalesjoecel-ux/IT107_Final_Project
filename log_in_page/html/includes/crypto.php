<?php
/*
 * crypto.php — encrypt/decrypt text and files with AES-256-GCM.
 *
 * Secret key + uploaded files are kept OUTSIDE htdocs, in:
 *     C:\xampp\encryptica_storage\
 * (so nobody can open them from the browser).
 * DON'T delete or share app.key: without it, saved items can't be decrypted.
 */

function storage_dir(): string {
    $dir = __DIR__ . '/../../../../../encryptica_storage';

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create storage folder: ' . $dir);
    }
    return $dir;
}

function app_key(): string {
    $file = storage_dir() . '/app.key';

    /* Older version kept the key in IT107/db/ (reachable from the browser).
       Move it to the safe folder so existing items still decrypt. */
    $old = __DIR__ . '/../../../db/app.key';
    if (!is_file($file) && is_file($old)) {
        if (!@rename($old, $file)) {
            if (copy($old, $file)) {
                @unlink($old);
            }
        }
    }

    if (!is_file($file)) {
        if (file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX) === false) {
            throw new RuntimeException('Cannot create key file: ' . $file);
        }
    }

    $key = hex2bin(trim((string)file_get_contents($file)));
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('Invalid key file: ' . $file);
    }
    return $key;
}

/* ---------- raw bytes (used for files) ---------- */

/* $aad ties the data to its owner (e.g. "file:5") */
function encrypt_raw(string $plain, string $aad = ''): string {
    $iv     = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);

    if ($cipher === false) {
        throw new RuntimeException('Encryption failed');
    }
    return $iv . $tag . $cipher;
}

/* Returns null if it can't be decrypted */
function decrypt_raw(string $raw, string $aad = ''): ?string {
    if (strlen($raw) < 29) {
        return null;
    }

    $iv     = substr($raw, 0, 12);
    $tag    = substr($raw, 12, 16);
    $cipher = substr($raw, 28);

    $plain = openssl_decrypt($cipher, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag, $aad);
    return $plain === false ? null : $plain;
}

/* ---------- text (stored in the database as base64) ---------- */

function encrypt_text(string $plain, string $aad = ''): string {
    return base64_encode(encrypt_raw($plain, $aad));
}

function decrypt_text(string $blob, string $aad = ''): ?string {
    $raw = base64_decode($blob, true);
    if ($raw === false) {
        return null;
    }
    return decrypt_raw($raw, $aad);
}