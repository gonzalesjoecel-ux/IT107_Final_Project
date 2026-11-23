<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/* admin/user_view.php?id=5 â€” details of one user + actions (admin only) */
require __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/activity.php';
require __DIR__ . '/includes/upload.php';   // for human_size()
require __DIR__ . '/includes/layout.php';

$flash = take_flash();
$id    = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);

if (!$id) {
    flash('err', 'Invalid user.');
    redirect('users.php');
}

try {
    $resetCol = has_reset_column($pdo) ? 'reset_requested_at' : 'NULL AS reset_requested_at';
    $s = $pdo->prepare("SELECT id, username, fullname, email, role, is_active, failed_attempts, locked_until, last_login, {$resetCol} FROM users WHERE id = ? LIMIT 1");
    $s->execute([$id]);
    $u = $s->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        flash('err', 'User not found.');
        redirect('users.php');
    }

    $s = $pdo->prepare('SELECT COUNT(*) c, COALESCE(SUM(file_size), 0) b, SUM(CASE WHEN file_stored IS NOT NULL THEN 1 ELSE 0 END) f FROM items WHERE user_id = ?');
    $s->execute([$id]);
    $st = $s->fetch(PDO::FETCH_ASSOC);

    $s = $pdo->prepare(
        'SELECT l.event, l.detail, l.ip, l.created_at, l.user_id, u.username
         FROM activity_log l LEFT JOIN users u ON u.id = l.user_id
         WHERE l.user_id = ? ORDER BY l.id DESC LIMIT 25'
    );
    $s->execute([$id]);
    $feed = $s->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    error_log('admin/user_view: ' . $ex->getMessage());
    flash('err', 'There was a problem loading the user.');
    redirect('users.php');
}

[$stText, $stTone] = user_status($u);
$isSelf   = (int)$u['id'] === (int)$user['id'];
$isLocked = $stText === 'Locked';
$name     = $u['fullname'] ?: $u['username'];

render_header($name, 'a-users');
?>

<p><a class="dash-link" href="users.php">&larr; Back to users</a></p>

<div class="page-head">
  <h1><?= e($name) ?></h1>
  <p>@<?= e($u['username']) ?> Â· <?= e($u['email']) ?></p>
</div>

<?php if ($flash): ?>
  <div class="dash-flash dash-flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<?php if (!empty($u['reset_requested_at'])): ?>
  <div class="dash-flash dash-flash--warn" role="status">
    This user requested a password reset (<?= e(when($u['reset_requested_at'], 'M j, g:i A')) ?>).
    Click <strong>Reset password</strong> below, then give the temporary password to the user.
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__icon stat__icon--purple"><?= icon('vault') ?></div>
    <div class="stat__label">Items</div>
    <div class="stat__value"><?= (int)$st['c'] ?></div>
  </div>
  <div class="stat">
    <div class="stat__icon stat__icon--teal"><?= icon('file') ?></div>
    <div class="stat__label">Files</div>
    <div class="stat__value"><?= (int)$st['f'] ?></div>
  </div>
  <div class="stat">
    <div class="stat__icon stat__icon--pink"><?= icon('storage') ?></div>
    <div class="stat__label">Storage used</div>
    <div class="stat__value"><?= e(human_size((int)$st['b'])) ?></div>
  </div>
  <div class="stat">
    <div class="stat__icon stat__icon--amber"><?= icon('alert') ?></div>
    <div class="stat__label">Failed attempts</div>
    <div class="stat__value"><?= (int)$u['failed_attempts'] ?></div>
  </div>
</div>

<div class="dash-grid">

  <section class="dash-card dash-card--account">
    <h2>Account</h2>
    <p class="dash-sub">User details.</p>
    <dl class="dash-dl">
      <div><dt>Status</dt><dd><span class="pill pill--<?= e($stTone) ?>"><?= e($stText) ?></span></dd></div>
      <div><dt>Role</dt><dd><?= e($u['role']) ?></dd></div>
      <div><dt>Last login</dt><dd><?= e(when($u['last_login'], 'M j, Y g:i A')) ?></dd></div>
      <?php if ($isLocked): ?>
        <div><dt>Locked until</dt><dd><?= e(when($u['locked_until'], 'g:i:s A')) ?></dd></div>
      <?php endif; ?>
    </dl>

    <div class="actions-stack">
      <?php if ($isLocked || (int)$u['failed_attempts'] > 0): ?>
        <form method="POST" action="user_action.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
          <input type="hidden" name="action" value="unlock" />
          <button class="dash-btn dash-btn--sm" type="submit">Unlock / reset attempts</button>
        </form>
      <?php endif; ?>

      <?php if (!$isSelf): ?>
        <form method="POST" action="user_action.php"
              onsubmit="return confirm('Reset the password for this user? A temporary password will be generated.');">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
          <input type="hidden" name="action" value="reset" />
          <button class="dash-btn <?= !empty($u['reset_requested_at']) ? 'dash-btn--primary' : '' ?> dash-btn--sm" type="submit">Reset password</button>
        </form>
      <?php endif; ?>

      <?php if ($isSelf): ?>
        <p class="dash-hint">You cannot disable your own account. Use the Profile page to change your password.</p>
      <?php elseif ((int)$u['is_active'] === 1): ?>
        <form method="POST" action="user_action.php"
              onsubmit="return confirm('Disable this user? They will no longer be able to log in.');">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
          <input type="hidden" name="action" value="disable" />
          <button class="dash-btn dash-btn--danger dash-btn--sm" type="submit">Disable account</button>
        </form>
      <?php else: ?>
        <form method="POST" action="user_action.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
          <input type="hidden" name="action" value="enable" />
          <button class="dash-btn dash-btn--primary dash-btn--sm" type="submit">Enable account</button>
        </form>
      <?php endif; ?>
    </div>

    <p class="dash-hint">Privacy: administrators can see counts only, not the contents of user items or files.</p>
  </section>

  <section class="dash-card dash-card--vault">
    <h2>Recent activity</h2>
    <p class="dash-sub">The 25 most recent events for this user.</p>
    <?php render_feed($feed, false); ?>
  </section>

</div>

<?php render_footer(); ?>