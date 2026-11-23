<?php
/*
 * config.sample.php — FAKE VALUES ONLY.
 *
 * Copy this file and fill in your own local values. Never commit the filled
 * copy (config.php or mail_config.php) to GitHub.
 *
 * The live mail settings used by this project live OUTSIDE the repository at
 * C:\xampp\encryptica_storage\mail_config.php so the SMTP App Password cannot
 * be pushed by accident.
 */
return [
    'db' => [
        'host'     => 'localhost',
        'dbname'   => 'encryptica_sample',
        'username' => 'sample_user',
        'password' => 'FAKE_DATABASE_PASSWORD',
        'charset'  => 'utf8mb4',
    ],
    'mail' => [
        'host'       => 'smtp.example.com',
        'port'       => 587,
        'secure'     => 'tls',
        'username'   => 'sample.account@example.com',
        'password'   => 'FAKE_16_CHARACTER_APP_PASSWORD',
        'from_email' => 'no-reply@example.com',
        'from_name'  => 'Encryptica',
    ],
];
