<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/* admin/activity.php — full activity log with filter (admin only) */
require __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/activity.php';
require __DIR__ . '/includes/layout.php';

$allEvents = [
    'register', 'login_ok', 'login_fail', 'account_locked', 'logout',
    'item_add', 'item_delete', 'profile_update',
    'admin_unlock', 'admin_disable', 'admin_enable',
    'reset_request', 'admin_reset', 'password_change',
];

$event   = $_GET['event'] ?? '';
$event   = in_array($event, $allEvents, true) ? $event : '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where  = $event !== '' ? 'WHERE l.event = ?' : '';
$params = $event !== '' ? [$event] : [];

$rows  = [];
$total = 0;
$pages = 1;
$err   = '';

try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM activity_log l $where");
    $s->execute($params);
    $total = (int)$s->fetchColumn();

    $pages  = max(1, (int)ceil($total / $perPage));
    $page   = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $s = $pdo->prepare(
        "SELECT l.event, l.detail, l.ip, l.created_at, l.user_id, u.username
         FROM activity_log l LEFT JOIN users u ON u.id = l.user_id
         $where ORDER BY l.id DESC LIMIT $perPage OFFSET $offset"
    );
    $s->execute($params);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    error_log('admin/activity: ' . $ex->getMessage());
    $err = "Activity can't be loaded. Has the database setup been run?";
}

render_header('Activity', 'a-activity');
?>

<div class="page-head">
  <h1>Activity log</h1>
  <p>
    <?= (int)$total ?> event<?= $total === 1 ? '' : 's' ?>
    <?= $event !== '' ? ' (' . e(event_info($event)[0]) . ')' : '' ?>.
  </p>
</div>

<?php if ($err): ?>
  <div class="dash-flash dash-flash--err" role="alert"><?= e($err) ?></div>
<?php endif; ?>

<section class="dash-card">
  <form class="toolbar" method="GET" action="activity.php">
    <select class="toolbar__select" name="event" aria-label="Filter by event">
      <option value="">All events</option>
      <?php foreach ($allEvents as $ev): ?>
        <option value="<?= e($ev) ?>"<?= $ev === $event ? ' selected' : '' ?>><?= e(event_info($ev)[0]) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="dash-btn dash-btn--primary dash-btn--sm" type="submit">Filter</button>
    <?php if ($event !== ''): ?>
      <a class="dash-btn dash-btn--sm" href="activity.php">Clear</a>
    <?php endif; ?>
  </form>

  <?php render_feed($rows); ?>
  <?php render_pager($page, $pages, 'activity.php', $event !== '' ? ['event' => $event] : []); ?>
</section>

<?php render_footer(); ?>
