<?php
/*
 * captcha.php — draws a fresh CAPTCHA image (handout section 7).
 *
 * The answer is generated here, stored in the session, and checked on the
 * server by captcha_check(). The image is never cached by the browser.
 */

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

require_once __DIR__ . '/includes/captcha.php';

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('The CAPTCHA image needs the PHP GD extension.');
}

$slot = captcha_slot((string)($_GET['slot'] ?? 'default'));
$code = captcha_new_code();
captcha_store($code, $slot);

/* Draw at 2x, then scale down for smoother characters. */
$smallW = 90;
$smallH = 30;
$im = imagecreatetruecolor($smallW, $smallH);

$bg = imagecolorallocate($im, 243, 241, 250);
imagefilledrectangle($im, 0, 0, $smallW, $smallH, $bg);

/* Background noise: thin lines and specks. */
for ($i = 0; $i < 7; $i++) {
    $tone = random_int(170, 220);
    $line = imagecolorallocate($im, $tone, $tone - 8, $tone + 10);
    imageline(
        $im,
        random_int(0, $smallW),
        random_int(0, $smallH),
        random_int(0, $smallW),
        random_int(0, $smallH),
        $line
    );
}
for ($i = 0; $i < 90; $i++) {
    $tone = random_int(140, 215);
    $dot = imagecolorallocate($im, $tone, $tone, $tone + 12);
    imagesetpixel($im, random_int(0, $smallW - 1), random_int(0, $smallH - 1), $dot);
}

/* Characters: random height, then a small tilt over the whole image. */
$ink = imagecolorallocate($im, 42, 30, 70);
$x = 6;
foreach (str_split($code) as $char) {
    $y = random_int(2, 9);
    imagestring($im, 5, $x, $y, $char, $ink);
    $x += 13;
}

/* Diagonal strokes on top of the letters. */
for ($i = 0; $i < 3; $i++) {
    $tone = random_int(150, 205);
    $line = imagecolorallocate($im, $tone, $tone - 10, $tone + 5);
    imageline($im, random_int(0, 30), random_int(0, $smallH), random_int(60, $smallW), random_int(0, $smallH), $line);
}

$rotated = imagerotate($im, (float)random_int(-4, 4), $bg);
if ($rotated !== false) {
    imagedestroy($im);
    $im = $rotated;
}

$finalW = 180;
$finalH = 60;
$big = imagescale($im, $finalW, $finalH, IMG_BICUBIC);
if ($big === false) {
    $big = $im;
}

$border = imagecolorallocate($big, 176, 166, 205);
imagerectangle($big, 0, 0, imagesx($big) - 1, imagesy($big) - 1, $border);

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

imagepng($big);

if ($big !== $im) {
    imagedestroy($im);
}
imagedestroy($big);
