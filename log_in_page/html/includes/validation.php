<?php
/*
 * validation.php — server-side validation for the registration form.
 *
 * Every rule from handout sections 4 and 5 lives here, and every message
 * names the actual mistake (section 4.10). The browser validates the same
 * rules in js/signup.js; this file is the authority.
 */

require_once __DIR__ . '/seed.php';

/* ---------- small helpers ---------- */

function v_value($value): string
{
    return trim((string)$value);
}

function v_length(string $value): int
{
    return mb_strlen($value, 'UTF-8');
}

function v_has_double_space(string $value): bool
{
    return str_contains($value, '  ');
}

function v_is_title_word(string $word): bool
{
    return (bool)preg_match('/^[A-Z][a-z]*$/', $word);
}

/* Names: first, middle, family (handout section 4.5) */
function v_name(?string $value, string $label, bool $required = true, int $max = NAME_MAX): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return $required ? "$label is required." : null;
    }
    if (v_length($value) < 2) {
        return "$label must be at least 2 characters.";
    }
    if (v_length($value) > $max) {
        return "$label must not exceed $max characters.";
    }
    if (preg_match('/\d/', $value)) {
        return "Numbers are not allowed in $label.";
    }
    if (preg_match('/[^A-Za-z ]/', $value)) {
        return "Special characters are not allowed in $label.";
    }
    if (v_has_double_space($value)) {
        return 'Double space is not allowed.';
    }
    if ($value === mb_strtoupper($value, 'UTF-8') && preg_match('/[A-Z]/', $value)) {
        return 'Do not use all capital letters.';
    }
    if (preg_match('/([A-Za-z])\1{2}/i', $value)) {
        return 'Three consecutive identical letters are not allowed.';
    }
    foreach (explode(' ', $value) as $word) {
        if (!v_is_title_word($word)) {
            return 'First letter of your name must be capital.';
        }
    }

    return null;
}

/* Name extension: Jr., Sr., or a Roman numeral typed under "Other". */
function v_name_extension(?string $value): ?string
{
    $value = v_value($value);

    if ($value === '' || in_array($value, ['Jr.', 'Sr.'], true)) {
        return null;
    }
    if (in_array($value, ['I', 'II', 'III', 'IV', 'IX', 'X'], true)) {
        return null;
    }

    return 'Name extension must be Jr., Sr., or a Roman numeral (I, II, III, IV, IX, X).';
}

/* ID Number: xxxx-xxxx (handout section 4.4) */
function v_id_number(?string $value): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return 'ID Number is required.';
    }
    if (!preg_match('/^\d{4}-\d{4}$/', $value)) {
        return 'ID Number must follow the format xxxx-xxxx.';
    }

    return null;
}

/*
 * Birthdate and age (handout section 4.6).
 * Returns [normalised birthdate, error message or null, age or null].
 */
function v_birthdate(?string $value): array
{
    $value = v_value($value);

    if ($value === '') {
        return ['', 'Birthdate is required.', null];
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$date
        || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d') !== $value
    ) {
        return ['', 'Please enter a valid birthdate.', null];
    }

    $today = new DateTime('today');
    if ($date > $today) {
        return [$value, 'Birthdate cannot be in the future.', null];
    }

    $age = $today->diff($date)->y;
    if ($age < 18) {
        return [$value, 'You must be at least 18 years old to register.', $age];
    }

    return [$value, null, $age];
}

function v_age(?string $birthdate): ?int
{
    [, $error, $age] = v_birthdate($birthdate);

    return $error === null ? $age : null;
}

/* Address: Purok / Street (handout section 4.7) */
function v_street(?string $value): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return 'Purok / Street is required.';
    }
    if (v_length($value) < 3) {
        return 'Purok / Street must be at least 3 characters.';
    }
    if (v_length($value) > 50) {
        return 'Purok / Street must not exceed 50 characters.';
    }
    if (preg_match('/[^A-Za-z0-9 ]/', $value)) {
        return 'Purok / Street may contain letters, digits and spaces only.';
    }
    if (v_has_double_space($value)) {
        return 'Double space is not allowed.';
    }

    return null;
}

/* Address: Barangay, Municipality / City, Province, Country */
function v_place(
    ?string $value,
    string $label,
    bool $required = true,
    int $min = 2,
    int $max = 50,
    bool $allowParticles = true
): ?string {
    $value = v_value($value);

    if ($value === '') {
        return $required ? "$label is required." : null;
    }
    if (v_length($value) < $min) {
        return "$label must be at least $min characters.";
    }
    if (v_length($value) > $max) {
        return "$label must not exceed $max characters.";
    }
    if (preg_match('/[^A-Za-z ]/', $value)) {
        return "$label may contain letters and spaces only.";
    }
    if (v_has_double_space($value)) {
        return 'Double space is not allowed.';
    }
    if ($value === mb_strtoupper($value, 'UTF-8') && preg_match('/[A-Z]/', $value)) {
        return 'Do not use all capital letters.';
    }

    $particles = ['de', 'del', 'dela', 'la', 'las', 'los', 'ng', 'sa', 'ug', 'of'];
    foreach (explode(' ', $value) as $word) {
        if ($allowParticles && in_array(mb_strtolower($word, 'UTF-8'), $particles, true)) {
            continue;
        }
        if (!v_is_title_word($word)) {
            return "$label must be in Title Case (for example, Cagayan de Oro).";
        }
    }

    return null;
}

/* ZIP Code: exactly four digits (handout section 4.7) */
function v_zip(?string $value): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return 'ZIP Code is required.';
    }
    if (!preg_match('/^\d{4}$/', $value)) {
        return 'ZIP Code must be exactly 4 digits.';
    }

    return null;
}

/* Email (handout section 4.8) */
function v_email(?string $value): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return 'Email is required.';
    }
    if (v_length($value) > 100) {
        return 'Email must not exceed 100 characters.';
    }
    if (preg_match('/\s/', $value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }

    return null;
}

/* Username: letters and digits, starts with a letter (handout section 4.9) */
function v_username(?string $value): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return 'Username is required.';
    }
    if (!preg_match('/^[A-Za-z]/', $value)) {
        return 'Username must start with a letter.';
    }
    if (v_length($value) < USERNAME_MIN) {
        return 'Username must be at least ' . USERNAME_MIN . ' characters.';
    }
    if (v_length($value) > USERNAME_MAX) {
        return 'Username must not exceed ' . USERNAME_MAX . ' characters.';
    }
    if (!preg_match('/^[A-Za-z0-9]+$/', $value)) {
        return 'Username may contain letters and digits only.';
    }

    return null;
}

/*
 * Password strength (handout section 4.9). These are our documented rules:
 *   Weak   - shorter than the minimum, or only one character class.
 *   Medium - minimum length or more, with at least two of:
 *            lowercase, uppercase, digit, symbol.
 *   Strong - 12 characters or more, with at least three of those classes.
 */
function password_strength(string $password): array
{
    $length = strlen($password);
    $classes = 0;
    foreach (['/[a-z]/', '/[A-Z]/', '/\d/', '/[^A-Za-z0-9]/'] as $pattern) {
        if (preg_match($pattern, $password)) {
            $classes++;
        }
    }

    if ($length >= 12 && $classes >= 3) {
        return ['strong', 'Strong'];
    }
    if ($length >= PASSWORD_MIN && $classes >= 2) {
        return ['medium', 'Medium'];
    }

    return ['weak', 'Weak'];
}

function v_password(string $password): ?string
{
    $length = strlen($password);

    if ($length === 0) {
        return 'Password is required.';
    }
    if ($length < PASSWORD_MIN) {
        return 'Password must be at least ' . PASSWORD_MIN . ' characters.';
    }
    if ($length > PASSWORD_MAX) {
        return 'Password must not exceed ' . PASSWORD_MAX . ' characters.';
    }
    if (password_strength($password)[0] === 'weak') {
        return 'Password is too weak. Use at least ' . PASSWORD_MIN
             . ' characters with a mix of uppercase and lowercase letters, numbers or symbols.';
    }

    return null;
}

function v_confirm_password(string $password, string $confirm): ?string
{
    if ($confirm === '') {
        return 'Please re-enter your password.';
    }
    if (!hash_equals($password, $confirm)) {
        return 'Passwords do not match.';
    }

    return null;
}

/* Authentication answer (handout section 5) */
function v_answer(?string $value): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return 'Answer is required.';
    }
    if (v_length($value) < 2 || v_length($value) > 50) {
        return 'Answer must be between 2 and 50 characters.';
    }
    if (preg_match('/[^A-Za-z ]/', $value)) {
        return 'Answer may contain letters and spaces only.';
    }
    if (v_has_double_space($value)) {
        return 'Double space is not allowed.';
    }

    return null;
}

/* ---------- system-specific fields (handout section 4.2) ---------- */

function v_recovery_mobile(?string $value): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return 'Recovery mobile number is required.';
    }
    if (!preg_match('/^09\d{9}$/', $value)) {
        return 'Recovery mobile number must be 11 digits and start with 09.';
    }

    return null;
}

function v_account_type(?string $value): ?string
{
    if (!in_array(v_value($value), ACCOUNT_TYPES, true)) {
        return 'Please select a valid account type.';
    }

    return null;
}

function v_vault_name(?string $value): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return 'Vault display name is required.';
    }
    if (v_length($value) < 3 || v_length($value) > 30) {
        return 'Vault display name must be 3-30 characters.';
    }
    if (preg_match('/[^A-Za-z ]/', $value)) {
        return 'Vault display name may contain letters and spaces only.';
    }
    if (v_has_double_space($value)) {
        return 'Double space is not allowed.';
    }
    foreach (explode(' ', $value) as $word) {
        if (!v_is_title_word($word)) {
            return 'Vault display name must be in Title Case.';
        }
    }

    return null;
}

function v_organization_name(?string $value, bool $required): ?string
{
    $value = v_value($value);

    if ($value === '') {
        return $required ? 'Organization name is required for organization accounts.' : null;
    }
    if (v_length($value) < 2 || v_length($value) > 50) {
        return 'Organization name must be 2-50 characters.';
    }
    if (preg_match('/[^A-Za-z0-9. ]/', $value)) {
        return 'Organization name may contain letters, digits, spaces and periods only.';
    }
    if (v_has_double_space($value)) {
        return 'Double space is not allowed.';
    }
    foreach (explode(' ', $value) as $word) {
        if (!preg_match('/^[A-Z][A-Za-z0-9.]*$/', $word)) {
            return 'Organization name must be in Title Case.';
        }
    }

    return null;
}
