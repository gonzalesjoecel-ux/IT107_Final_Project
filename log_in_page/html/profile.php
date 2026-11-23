<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/* profile.php — shows the edit-profile form. Saving is done by profile_update.php */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';

$flash = take_flash();

/* Values typed before an error (if any) */
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

$val = [
    'fullname' => $old['fullname'] ?? $user['fullname'],
    'username' => $old['username'] ?? $user['username'],
    'email'    => $old['email']    ?? $user['email'],
];
?>
<?php render_header('Edit profile', 'profile'); ?>

<div class="page-narrow">

  <p><a class="dash-link" href="<?= e(url('user_dashboard.php')) ?>">&larr; Back to dashboard</a></p>

  <div class="page-head">
    <h1>Edit profile</h1>
    <p>Update your name, username, and email.</p>
  </div>

  <?php if ($flash): ?>
    <div class="dash-flash dash-flash--<?= e($flash['type']) ?>" role="status">
      <?= e($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <section class="dash-card">
    <form method="POST" action="profile_update.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

      <div class="dash-field">
        <label for="fullname">Full name</label>
        <input type="text" id="fullname" name="fullname" maxlength="100" required
               value="<?= e($val['fullname']) ?>" />
      </div>

      <div class="dash-field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" maxlength="30" required
               value="<?= e($val['username']) ?>" autocomplete="username" />
        <span class="dash-hint">3-30 characters; letters, numbers, _ or . only.</span>
      </div>

      <div class="dash-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="150" required
               value="<?= e($val['email']) ?>" autocomplete="email" />
      </div>

      <div class="dash-actions">
        <a class="dash-btn" href="<?= e(url('user_dashboard.php')) ?>">Cancel</a>
        <button class="dash-btn dash-btn--primary" type="submit">Save changes</button>
      </div>
    </form>
  </section>

  <section class="dash-card">
    <h2>Change password</h2>
    <p class="dash-sub">Use this if your password was reset by an administrator, or if you want to change it.</p>

    <form method="POST" action="profile_update.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <input type="hidden" name="action" value="password" />

      <div class="dash-field">
        <label for="pw-current">Current password</label>
        <input type="password" id="pw-current" name="current" required autocomplete="current-password" />
      </div>

      <div class="dash-field">
        <label for="pw-new">New password</label>
        <input type="password" id="pw-new" name="new" required autocomplete="new-password" />
        <span class="dash-hint">8+ characters, including letters and numbers.</span>
      </div>

      <div class="dash-field">
        <label for="pw-confirm">Confirm new password</label>
        <input type="password" id="pw-confirm" name="confirm" required autocomplete="new-password" />
      </div>

      <div class="dash-actions">
        <button class="dash-btn dash-btn--primary" type="submit">Change password</button>
      </div>
    </form>
  </section>

</div>

<?php render_footer(); ?>