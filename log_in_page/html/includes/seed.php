<?php
/*
 * seed.php — shared security settings (updated class values, November 2026)
 *
 * The student-number formulas (D1 / D2) are no longer used. Every limit and
 * timer in the system is read from this file, so the project always matches
 * the values given by the instructor.
 */

/* Username: 6 to 20 characters */
const USERNAME_MIN = 6;
const USERNAME_MAX = 20;

/* Password: minimum 8, maximum 64 (handout section 4.9) */
const PASSWORD_MIN = 8;
const PASSWORD_MAX = 64;

/* Each name field: maximum 50 characters */
const NAME_MAX = 50;

/* Forgot Password link appears after this many consecutive failures */
const FORGOT_AFTER_FAILURES = 2;

/* Progressive lockout, in seconds */
const LOCKOUT_TIER_1 = 15;   // after 3 failures
const LOCKOUT_TIER_2 = 30;   // after 3 more failures
const LOCKOUT_TIER_3 = 60;   // after 3 more failures

/* OTP expiry: 5 minutes */
const OTP_TTL_MINUTES = 5;

/* Idle session timeout: 15 minutes */
const IDLE_TIMEOUT_MINUTES = 15;

/* IP block: 10 failures in 10 minutes, blocked for 10 minutes */
const IP_BLOCK_THRESHOLD = 10;
const IP_BLOCK_MINUTES = 10;

/* System-specific fields (handout section 4.2) */
const ACCOUNT_TYPES = ['Personal', 'Student', 'Professional', 'Organization'];

/* Question bank (handout section 5) */
const AUTH_QUESTION_BANK = [
    'Q1' => 'What was the name of your first pet?',
    'Q2' => 'Who was your best friend in elementary school?',
    'Q3' => 'Who was your favorite teacher in high school?',
    'Q4' => "What is your mother's maiden first name?",
    'Q5' => 'What street did you live on in Grade 1?',
    'Q6' => 'What was the first song you learned by heart?',
    'Q7' => 'What was the name of your elementary school?',
    'Q8' => 'What dish did you cook for the first time?',
];

/*
 * Neutral fallback shown when an account has no stored questions, so the
 * recovery page never reveals whether a username exists. Each user chooses
 * three different questions from AUTH_QUESTION_BANK during registration.
 */
const AUTH_QUESTION_FALLBACK = ['Q1', 'Q2', 'Q3'];
