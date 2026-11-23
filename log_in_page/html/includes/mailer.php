<?php
/*
 * mailer.php — sends OTP and security-alert emails.
 *
 * Uses PHPMailer when it is available and falls back to a local outbox file
 * under encryptica_storage/mail_outbox so the app never loses an OTP while
 * the SMTP server is offline. SMTP settings live outside htdocs in
 * encryptica_storage/mail_config.php.
 */

function mail_storage_dir(): string
{
    $dir = __DIR__ . '/../../../../../encryptica_storage';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create mail storage folder: ' . $dir);
    }
    return $dir;
}

function mail_config(): array
{
    $defaults = [
        'host'       => '127.0.0.1',
        'port'       => 1025,
        'secure'     => '',
        'username'   => '',
        'password'   => '',
        'from_email' => 'no-reply@encryptica.local',
        'from_name'  => 'Encryptica',
    ];

    $file = mail_storage_dir() . '/mail_config.php';
    if (!is_file($file)) {
        @file_put_contents($file, "<?php\nreturn " . var_export($defaults, true) . ";\n", LOCK_EX);
    }

    $loaded = is_file($file) ? require $file : [];
    return is_array($loaded) ? array_merge($defaults, $loaded) : $defaults;
}

/* Fallback: write a standard .eml file so the message is not lost. */
function mail_write_outbox(string $to, string $subject, string $html, string $text = ''): string
{
    $dir = mail_storage_dir() . '/mail_outbox';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return '';
    }

    $stamp = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $file  = $dir . '/' . $stamp . '.eml';
    $body  = $text !== '' ? $text : trim(strip_tags($html));

    $message = "To: {$to}\r\n"
             . 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8') . "\r\n"
             . 'Date: ' . date('r') . "\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n\r\n"
             . $html . "\r\n";

    if (@file_put_contents($file, $message, LOCK_EX) === false) {
        return '';
    }
    return $file;
}

/*
 * Returns ['ok' => bool, 'via' => 'smtp'|'smtp_fallback'|'outbox', 'error' => string].
 *
 * When the configured server is remote (for example Gmail) and it rejects the
 * message, the same message is retried against the local Mailpit inbox. That
 * keeps OTP delivery usable during development while a Gmail App Password is
 * still being set up.
 */
function send_app_mail(string $to, string $subject, string $html, string $text = ''): array
{
    $config = mail_config();

    $lib = __DIR__ . '/../../lib/PHPMailer';
    if (is_file($lib . '/PHPMailer.php')) {
        require_once $lib . '/Exception.php';
        require_once $lib . '/PHPMailer.php';
        require_once $lib . '/SMTP.php';

        $attempts = [[
            'host'       => (string)$config['host'],
            'port'       => (int)$config['port'],
            'secure'     => (string)$config['secure'],
            'username'   => trim((string)$config['username']),
            /* Gmail App Passwords are shown as "abcd efgh ijkl mnop"; spaces are ignored. */
            'password'   => preg_replace('/\s+/', '', (string)$config['password']),
            'from_email' => (string)$config['from_email'],
            'from_name'  => (string)$config['from_name'],
        ]];

        /* Local Mailpit fallback for a remote SMTP server. */
        if (!in_array(strtolower($attempts[0]['host']), ['127.0.0.1', 'localhost', '::1'], true)) {
            $attempts[] = [
                'host'       => '127.0.0.1',
                'port'       => 1025,
                'secure'     => '',
                'username'   => '',
                'password'   => '',
                'from_email' => 'no-reply@encryptica.local',
                'from_name'  => (string)$config['from_name'],
            ];
        }

        foreach ($attempts as $index => $settings) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $settings['host'];
                $mail->Port       = $settings['port'];
                $mail->Timeout    = 10;
                $mail->SMTPAuth   = $settings['username'] !== '';
                $mail->CharSet    = 'UTF-8';
                $mail->isHTML(true);

                if ($settings['secure'] !== '') {
                    $mail->SMTPSecure = $settings['secure'];
                }
                if ($mail->SMTPAuth) {
                    $mail->Username = $settings['username'];
                    $mail->Password = $settings['password'];
                }

                $mail->setFrom($settings['from_email'], $settings['from_name']);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body    = $html;
                $mail->AltBody = $text !== '' ? $text : trim(strip_tags($html));

                $mail->send();
                return ['ok' => true, 'via' => $index === 0 ? 'smtp' : 'smtp_fallback', 'error' => ''];
            } catch (Throwable $ex) {
                error_log('send_app_mail attempt ' . ($index + 1) . ': ' . $ex->getMessage());
            }
        }
    }

    $file = mail_write_outbox($to, $subject, $html, $text);
    return [
        'ok'    => $file !== '',
        'via'   => 'outbox',
        'error' => $file === '' ? 'SMTP failed and the outbox is not writable.' : '',
    ];
}

/* ---------- message bodies ---------- */

function otp_purpose_title(string $purpose): string
{
    return match ($purpose) {
        'registration'  => 'Verify your account',
        'login'         => 'Your log-in code',
        'forgot_password' => 'Password reset code',
        default         => 'Your verification code',
    };
}

function otp_email_html(string $name, string $purpose, string $code, int $minutes): string
{
    $title = otp_purpose_title($purpose);
    $safeName = htmlspecialchars($name !== '' ? $name : 'there', ENT_QUOTES, 'UTF-8');

    return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;padding:28px;color:#1f2937">'
        . '<h1 style="font-size:22px;margin:0 0 16px">Encryptica</h1>'
        . '<p>Hello ' . $safeName . ',</p>'
        . '<p><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '<p style="font-size:32px;letter-spacing:8px;font-weight:700;margin:22px 0;padding:16px;text-align:center;background:#f3f1fa;border-radius:10px">'
        . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>This code expires in <strong>' . $minutes . ' minutes</strong>.</p>'
        . '<p>If you did not request this code, ignore this email and consider changing your password.</p>'
        . '<p style="color:#6b7280;font-size:13px">Never share this code with anyone. Encryptica will never ask for your password.</p>'
        . '</div>';
}

function otp_email_text(string $name, string $purpose, string $code, int $minutes): string
{
    return otp_purpose_title($purpose) . "\n\n"
        . ($name !== '' ? "Hello {$name},\n\n" : '')
        . "Your code: {$code}\n"
        . "This code expires in {$minutes} minutes.\n\n"
        . "If you did not request this code, ignore this email and consider changing your password.\n";
}

function alert_email_html(string $name, string $title, int $attempts, string $ip, string $agent, string $time, string $resetUrl): string
{
    $safeName = htmlspecialchars($name !== '' ? $name : 'there', ENT_QUOTES, 'UTF-8');

    return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;padding:28px;color:#1f2937">'
        . '<h1 style="font-size:22px;margin:0 0 16px">Encryptica security alert</h1>'
        . '<p>Hello ' . $safeName . ',</p>'
        . '<p><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '<p><strong>Failed attempts:</strong> ' . $attempts . '<br>'
        . '<strong>Date and time:</strong> ' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . ' (Philippine time)<br>'
        . '<strong>IP address:</strong> ' . htmlspecialchars($ip !== '' ? $ip : 'unknown', ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Browser or device:</strong> ' . htmlspecialchars($agent !== '' ? $agent : 'unknown', ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>If this was not you, reset your password now: '
        . '<a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Forgot Password</a>.</p>'
        . '<p style="color:#6b7280;font-size:13px">This message never contains your password.</p>'
        . '</div>';
}

function alert_email_text(string $name, string $title, int $attempts, string $ip, string $agent, string $time, string $resetUrl): string
{
    return "Encryptica security alert\n\n"
        . ($name !== '' ? "Hello {$name},\n\n" : '')
        . "{$title}\n\n"
        . "Failed attempts: {$attempts}\n"
        . "Date and time: {$time} (Philippine time)\n"
        . "IP address: " . ($ip !== '' ? $ip : 'unknown') . "\n"
        . "Browser or device: " . ($agent !== '' ? $agent : 'unknown') . "\n\n"
        . "If this was not you, reset your password now: {$resetUrl}\n";
}

function password_changed_email_html(string $name, string $time, string $ip, string $agent, string $resetUrl): string
{
    $safeName = htmlspecialchars($name !== '' ? $name : 'there', ENT_QUOTES, 'UTF-8');

    return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;padding:28px;color:#1f2937">'
        . '<h1 style="font-size:22px;margin:0 0 16px">Encryptica security alert</h1>'
        . '<p>Hello ' . $safeName . ',</p>'
        . '<p><strong>Your password was changed.</strong></p>'
        . '<p><strong>Date and time:</strong> ' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . ' (Philippine time)<br>'
        . '<strong>IP address:</strong> ' . htmlspecialchars($ip !== '' ? $ip : 'unknown', ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Browser or device:</strong> ' . htmlspecialchars($agent !== '' ? $agent : 'unknown', ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>If you made this change, no further action is needed. If this was not you, reset your password now: '
        . '<a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Forgot Password</a>.</p>'
        . '<p style="color:#6b7280;font-size:13px">This message never contains your password.</p>'
        . '</div>';
}

function password_changed_email_text(string $name, string $time, string $ip, string $agent, string $resetUrl): string
{
    return "Encryptica security alert\n\n"
        . ($name !== '' ? "Hello {$name},\n\n" : '')
        . "Your password was changed.\n\n"
        . "Date and time: {$time} (Philippine time)\n"
        . "IP address: " . ($ip !== '' ? $ip : 'unknown') . "\n"
        . "Browser or device: " . ($agent !== '' ? $agent : 'unknown') . "\n\n"
        . "If this was not you, reset your password now: {$resetUrl}\n";
}
