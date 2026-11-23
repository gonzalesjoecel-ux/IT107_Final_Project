<?php
/** @var array $user (from includes/auth.php) */
/* nav.php — top bar. Needs $user from auth.php */
$navName    = $user['fullname'] ?: $user['username'];
$navInitial = mb_strtoupper(mb_substr($navName, 0, 1));
?>
<header class="dash-nav">
  <a class="dash-brand" href="dashboard.php">Encryptica<span>.</span></a>

  <div class="dash-user">
    <a class="dash-user__link" href="profile.php" title="Edit profile">
      <div class="dash-avatar" aria-hidden="true"><?= e($navInitial) ?></div>
      <div class="dash-user__text">
        <div class="dash-user__name"><?= e($navName) ?></div>
        <div class="dash-user__sub">@<?= e($user['username']) ?></div>
      </div>
    </a>

    <form method="POST" action="logout.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <button class="dash-btn" type="submit">Log out</button>
    </form>
  </div>
</header>