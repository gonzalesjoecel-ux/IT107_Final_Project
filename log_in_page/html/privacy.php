<?php
/* privacy.php — Privacy Notice required by RA 10173 (handout section 4.3). */
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';

render_public_header('Privacy Notice', 'privacy');
?>

<section class="legal">
  <h1>Privacy Notice</h1>
  <p class="legal__lead">
    Encryptica is a secure personal vault. This notice explains what personal data we collect,
    why we collect it, how it is protected, and how long we keep it, in accordance with the
    Data Privacy Act of 2012 (Republic Act No. 10173).
  </p>

  <h2>What we collect</h2>
  <ul>
    <li><strong>Identity and contact details:</strong> ID Number, first name, middle name, family name, name extension, birthdate, email address, and recovery mobile number.</li>
    <li><strong>Address:</strong> purok or street, barangay, municipality or city, province, country, and ZIP code.</li>
    <li><strong>Account credentials:</strong> username, a hashed password, and hashed answers to your three authentication questions.</li>
    <li><strong>Vault profile:</strong> account type, vault display name, and organization name when the account is for an organization.</li>
    <li><strong>Security records:</strong> sign-in activity, failed attempts, lockouts, one-time password events, and consent timestamps.</li>
    <li><strong>Vault content:</strong> the notes and files you choose to store, which are encrypted before they are saved.</li>
  </ul>

  <h2>Why we collect it</h2>
  <ul>
    <li>To create and secure your account, verify your identity, and enforce the registration rules.</li>
    <li>To authenticate you at log-in, send one-time passwords, and warn you about suspicious sign-in attempts.</li>
    <li>To recover your account through your authentication questions and email verification.</li>
    <li>To detect and block attacks such as password guessing, IP abuse, and automated registrations.</li>
    <li>To comply with the Data Privacy Act and the academic requirements of IT 107.</li>
  </ul>

  <h2>How it is protected</h2>
  <ul>
    <li>Passwords, security answers, and one-time passwords are stored as one-way hashes, never as plain text.</li>
    <li>Vault notes and files are encrypted with AES-256 before they are written to the database or disk.</li>
    <li>Every database query uses prepared statements to prevent SQL injection.</li>
    <li>All output is escaped to prevent cross-site scripting, and every form carries a CSRF token.</li>
    <li>Accounts are protected by progressive lockout, IP blocking, session hardening, and an idle timeout of <?= IDLE_TIMEOUT_MINUTES ?> minutes.</li>
    <li>Only administrators can view the security monitoring dashboard, and they can see activity records, not the contents of your vault.</li>
  </ul>

  <h2>How long we keep it</h2>
  <ul>
    <li>Account data is kept while your account is active.</li>
    <li>When an account is deleted, personal data is removed within 30 days, except records that must be kept for security or legal reasons.</li>
    <li>Security activity logs are kept for 90 days.</li>
  </ul>

  <h2>Your rights</h2>
  <p>
    Under RA 10173 you have the right to be informed, to object, to access, to rectify, to erase or block,
    to damages, and to data portability. You may also file a complaint with the National Privacy Commission.
  </p>

  <h2>Who to contact</h2>
  <p>
    For questions, access requests, or complaints about your personal data, contact the project's
    Data Protection Officer at <a class="site-textlink" href="mailto:privacy@encryptica.local">privacy@encryptica.local</a>.
  </p>

  <p class="legal__back">
    <a class="site-textlink" href="<?= e(url('signup.php')) ?>">&larr; Back to registration</a>
  </p>
</section>

<?php render_public_footer(); ?>
