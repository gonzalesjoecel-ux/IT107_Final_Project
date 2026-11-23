<?php
/*
 * captcha.php (helpers) — image CAPTCHA for handout section 7.
 *
 * The image itself is drawn by html/captcha.php. The answer is kept in the
 * session, compared case-insensitively, and thrown away after one attempt.
 * Each protected form has its own slot so two forms on one page (log-in and
 * forgot password) cannot overwrite each other's codes.
 */

const CAPTCHA_LENGTH = 6;
const CAPTCHA_TTL    = 600; // seconds a code stays valid (10 minutes)

/* Random code without look-alike characters (no I, O, 0 or 1). */
function captcha_new_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < CAPTCHA_LENGTH; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

/* Keep only the slots this project uses. */
function captcha_slot(string $slot): string
{
    return in_array($slot, ['signup', 'login', 'forgot'], true) ? $slot : 'default';
}

/* Remember the answer until the next check (or until it expires). */
function captcha_store(string $code, string $slot = 'default'): void
{
    $_SESSION['captcha'][captcha_slot($slot)] = [
        'code' => strtoupper($code),
        'at'   => time(),
    ];
}

/*
 * Compare one answer with the stored code. The code is always cleared, so
 * every attempt needs a freshly loaded image. Returns false when the code is
 * missing, expired, or different.
 */
function captcha_check(?string $input, string $slot = 'default'): bool
{
    $key = captcha_slot($slot);
    $row = $_SESSION['captcha'][$key] ?? null;
    unset($_SESSION['captcha'][$key]);

    if (!is_array($row) || ($row['code'] ?? '') === '' || (int)($row['at'] ?? 0) === 0) {
        return false;
    }
    if ((time() - (int)$row['at']) > CAPTCHA_TTL) {
        return false;
    }

    return hash_equals((string)$row['code'], strtoupper(trim((string)$input)));
}

/* Markup shared by the three protected public forms. */
function render_captcha(string $id, string $slot, string $error = ''): void
{
    $imageId = $id . '-img';
    $src = url('captcha.php') . '?slot=' . rawurlencode(captcha_slot($slot)) . '&t=' . bin2hex(random_bytes(6));
    ?>
    <div class="site-field site-captcha">
      <label for="<?= e($id) ?>">CAPTCHA verification <span class="site-field__req">*</span></label>
      <div class="site-captcha__row">
        <img class="site-captcha__img" id="<?= e($imageId) ?>" src="<?= e($src) ?>"
             alt="CAPTCHA image" width="180" height="60" data-captcha-src="<?= e(url('captcha.php')) ?>" />
        <button class="site-btn site-captcha__refresh" type="button"
                data-captcha-refresh="<?= e($imageId) ?>">New code</button>
      </div>
      <input class="site-input<?= $error !== '' ? ' site-input--invalid' : '' ?>" type="text"
             id="<?= e($id) ?>" name="captcha" maxlength="6" required autocomplete="off"
             spellcheck="false" placeholder="Enter the characters shown" />
      <div class="site-field__msg" data-msg-for="captcha"><?= e($error) ?></div>
    </div>
    <?php
}
