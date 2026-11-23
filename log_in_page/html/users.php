<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/* admin/users.php â€” list + search all users (admin only) */
require __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/activity.php';
require __DIR__ . '/includes/upload.php';   // for human_size()
require __DIR__ . '/includes/layout.php';

$flash   = take_flash();
$q       = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where  = '';
$params = [];
if ($q !== '') {
    /* ! is the escape character so a typed % or _ is searched literally */
    $like   = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
    $where  = "WHERE (u.username LIKE ? ESCAPE '!' OR u.fullname LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')";
    $params = [$like, $like, $like];
}

$rows  = [];
$total = 0;
$err   = '';
$resetCol = has_reset_column($pdo) ? 'u.reset_requested_at' : 'NULL AS reset_requested_at';

try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
    $s->execute($params);
    $total = (int)$s->fetchColumn();

    $pages  = max(1, (int)ceil($total / $perPage));
    $page   = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $s = $pdo->prepare(
        "SELECT u.id, u.username, u.fullname, u.email, u.role, u.is_active, u.locked_until, u.last_login, {$resetCol},
                (SELECT COUNT(*) FROM items i WHERE i.user_id = u.id) AS item_count,
                (SELECT COALESCE(SUM(i.file_size), 0) FROM items i WHERE i.user_id = u.id) AS bytes
         FROM users u $where
         ORDER BY u.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $s->execute($params);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    error_log('admin/users: ' . $ex->getMessage());
    $err   = 'Could not load users. Have you run admin_setup.sql in phpMyAdmin?';
    $pages = 1;
}

render_header('Users', 'a-users');
?>

<div class="page-head">
  <h1>Users</h1>
  <p><?= $total ?> user<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?>.</p>
</div>

<?php if ($flash): ?>
  <div class="dash-flash dash-flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="dash-flash dash-flash--err" role="alert"><?= e($err) ?></div>
<?php endif; ?>

<section class="dash-card">
  <form class="toolbar" method="GET" action="users.php">
    <input class="toolbar__search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, username or email" />
    <button class="dash-btn dash-btn--primary dash-btn--sm" type="submit">Search</button>
    <?php if ($q !== ''): ?>
      <a class="dash-btn dash-btn--sm" href="users.php">Clear</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>User</th><th>Role</th><th>Status</th><th>Items</th><th>Storage</th><th>Last login</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="muted">No users found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
          <?php [$stText, $stTone] = user_status($r); $name = $r['fullname'] ?: $r['username']; ?>
          <tr>
            <td>
              <div class="who">
                <div class="chip__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></div>
                <div>
                  <div class="who__name"><?= e($name) ?><?= (int)$r['id'] === (int)$user['id'] ? ' <span class="muted">(you)</span>' : '' ?></div>
                  <div class="who__sub">@<?= e($r['username']) ?> Â· <?= e($r['email']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="pill pill--<?= $r['role'] === 'admin' ? 'info' : 'muted' ?>"><?= e($r['role']) ?></span></td>
            <td>
              <span class="pill pill--<?= e($stTone) ?>"><?= e($stText) ?></span>
              <?php if (!empty($r['reset_requested_at'])): ?><span class="pill pill--warn">Reset requested</span><?php endif; ?>
            </td>
            <td><?= (int)$r['item_count'] ?></td>
            <td><?= e(human_size((int)$r['bytes'])) ?></td>
            <td class="nowrap"><?= e(when($r['last_login'])) ?></td>
            <td><a class="dash-btn dash-btn--sm" href="user_view.php?id=<?= (int)$r['id'] ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php render_pager($page, $pages, 'users.php', $q !== '' ? ['q' => $q] : []); ?>
</section>

<?php render_footer(); ?>