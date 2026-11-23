<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/* user_dashboard.php — shows the page. Adding/deleting is done by item_add.php / item_delete.php */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/crypto.php';
require __DIR__ . '/includes/upload.php';
require __DIR__ . '/includes/layout.php';

$flash = take_flash();

$displayName = $user['fullname'] ?: $user['username'];
$firstName   = explode(' ', trim($displayName))[0];
$hour        = (int)date('G');
$greeting    = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

/* Load this user's items and decrypt them */
$items      = [];
$itemsError = '';
$fileCount  = 0;
$totalBytes = 0;

try {
    $stmt = $pdo->prepare('SELECT id, title, content_enc, file_name, file_mime, file_size, created_at FROM items WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$user['id']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $plain = decrypt_text($row['content_enc'], 'user:' . $user['id']);

        if ($row['file_name']) {
            $fileCount++;
            $totalBytes += (int)$row['file_size'];
        }

        $items[] = [
            'id'      => $row['id'],
            'title'   => $row['title'],
            'content' => $plain ?? '[Unable to decrypt this item. The key is incorrect or missing.]',
            'file'    => $row['file_name'] ? [
                'name'     => $row['file_name'],
                'size'     => human_size((int)$row['file_size']),
                'is_image' => strpos((string)$row['file_mime'], 'image/') === 0,
            ] : null,
            'date'    => date('M j, Y g:i A', strtotime($row['created_at'])),
        ];
    }
} catch (Throwable $ex) {
    error_log('dashboard: ' . $ex->getMessage());
    $itemsError = 'Could not load items. Have you run items_setup.sql and items_upload_update.sql in phpMyAdmin?';
}

/* The login before this one (saved by login.php) */
$prevLogin = $_SESSION['prev_login'] ?? null;
$lastLoginText = $prevLogin ? date('M j, g:i A', strtotime($prevLogin)) : 'First login';

render_header('Dashboard', 'dashboard');
?>

<section class="dash-hero">
  <div class="page-head">
    <h1><?= e($greeting) ?>, <?= e($firstName) ?></h1>
    <p>Your items and files are encrypted (AES-256) before being saved.</p>
  </div>
</section>

<?php if ($flash): ?>
  <div class="dash-flash dash-flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<?php if ($itemsError): ?>
  <div class="dash-flash dash-flash--err" role="alert"><?= e($itemsError) ?></div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__icon stat__icon--purple"><?= icon('vault') ?></div>
    <div class="stat__label">Items</div>
    <div class="stat__value"><?= count($items) ?></div>
  </div>
  <div class="stat">
    <div class="stat__icon stat__icon--teal"><?= icon('file') ?></div>
    <div class="stat__label">Files</div>
    <div class="stat__value"><?= $fileCount ?></div>
  </div>
  <div class="stat">
    <div class="stat__icon stat__icon--pink"><?= icon('storage') ?></div>
    <div class="stat__label">Storage used</div>
    <div class="stat__value"><?= e(human_size($totalBytes)) ?></div>
  </div>
  <div class="stat">
    <div class="stat__icon stat__icon--teal"><?= icon('clock') ?></div>
    <div class="stat__label">Last login</div>
    <div class="stat__value stat__value--sm"><?= e($lastLoginText) ?></div>
  </div>
</div>

<div class="dash-grid">

  <!-- VAULT -->
  <section class="dash-card dash-card--vault" aria-labelledby="vault-title">
    <div class="dash-card__head">
      <div>
        <h2 id="vault-title">Your vault</h2>
        <p class="dash-sub">Encrypted files and notes you save will appear here.</p>
      </div>
      <?php if ($items): ?>
        <button class="dash-btn dash-btn--primary dash-btn--sm" type="button" data-open-add>Add item</button>
      <?php endif; ?>
    </div>

    <?php if (!$items): ?>
      <div class="dash-empty">
        <svg viewBox="0 0 200 220" fill="none" aria-hidden="true">
          <rect x="40" y="90" width="120" height="100" rx="14" stroke="var(--teal)" stroke-width="10" />
          <path d="M62 90V64a38 38 0 0176 0v26" stroke="var(--purple)" stroke-width="10" stroke-linecap="round" />
          <circle cx="100" cy="135" r="14" fill="var(--pink)" />
        </svg>
        <strong>Nothing encrypted yet</strong>
        <p>Add your first item to start protecting it with Encryptica.</p>
        <button class="dash-btn dash-btn--primary" type="button" data-open-add>Add item</button>
      </div>
    <?php else: ?>
      <ul class="dash-items">
        <?php foreach ($items as $item): ?>
          <li class="dash-item">
            <div class="dash-item__top">
              <div>
                <div class="dash-item__title"><?= e($item['title']) ?></div>
                <div class="dash-item__date"><?= e($item['date']) ?></div>
              </div>

              <form method="POST" action="item_delete.php"
                    onsubmit="return confirm('Are you sure you want to delete this item?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>" />
                <button class="dash-btn dash-btn--danger dash-btn--sm" type="submit">Delete</button>
              </form>
            </div>

            <?php if ($item['file']): ?>
              <?php $fileUrl = 'item_file.php?id=' . (int)$item['id']; ?>

              <?php if ($item['file']['is_image']): ?>
                <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener">
                  <img class="dash-item__thumb" src="<?= e($fileUrl) ?>" alt="<?= e($item['file']['name']) ?>" loading="lazy" />
                </a>
              <?php endif; ?>

              <div class="dash-file">
                <div>
                  <div class="dash-file__name"><?= e($item['file']['name']) ?></div>
                  <div class="dash-file__meta"><?= e($item['file']['size']) ?></div>
                </div>
                <a class="dash-btn dash-btn--sm" href="<?= e($fileUrl) ?>&amp;dl=1">Download</a>
              </div>
            <?php endif; ?>

            <?php if ($item['content'] !== ''): ?>
              <details>
                <summary>Show content</summary>
                <pre class="dash-item__body"><?= e($item['content']) ?></pre>
              </details>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <!-- ACCOUNT -->
  <section class="dash-card dash-card--account" aria-labelledby="acct-title">
    <h2 id="acct-title">Account</h2>
    <p class="dash-sub">Your profile details.</p>
    <dl class="dash-dl">
      <div><dt>Full name</dt><dd><?= e($displayName) ?></dd></div>
      <div><dt>Username</dt><dd>@<?= e($user['username']) ?></dd></div>
      <div><dt>Email</dt><dd><?= e($user['email'] ?? '—') ?></dd></div>
    </dl>
    <p><a class="dash-btn dash-btn--sm" href="profile.php">Edit profile</a></p>
  </section>

  <!-- SECURITY -->
  <section class="dash-card dash-card--wide" aria-labelledby="sec-title">
    <h2 id="sec-title">Security</h2>
    <p class="dash-sub">How your account is being protected right now.</p>

    <div class="dash-rows">
      <div class="dash-row">
        <div>
          <div class="dash-row__title">Item encryption</div>
          <div class="dash-row__desc">Your notes and files are encrypted with AES-256 before they are saved.</div>
        </div>
        <span class="pill pill--ok">Active</span>
      </div>
      <div class="dash-row">
        <div>
          <div class="dash-row__title">Brute-force protection</div>
          <div class="dash-row__desc">Your account locks briefly after 5 wrong password attempts.</div>
        </div>
        <span class="pill pill--ok">Active</span>
      </div>
      <div class="dash-row">
        <div>
          <div class="dash-row__title">Password storage</div>
          <div class="dash-row__desc">Your password is hashed, never stored as plain text.</div>
        </div>
        <span class="pill pill--ok">Active</span>
      </div>
      <div class="dash-row">
        <div>
          <div class="dash-row__title">Auto log out</div>
          <div class="dash-row__desc">You'll be logged out after <?= IDLE_TIMEOUT / 60 ?> minutes of no activity.</div>
        </div>
        <span class="pill pill--ok">Active</span>
      </div>
    </div>
  </section>

</div>

<!-- ADD ITEM POPUP -->
<dialog class="dash-dialog" id="add-dialog" aria-labelledby="add-title">
  <h2 id="add-title">Add item</h2>
  <p class="dash-sub">The content and file are encrypted before they are saved.</p>

  <form method="POST" action="item_add.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

    <div class="dash-field">
      <label for="item-title">Title</label>
      <input type="text" id="item-title" name="title" maxlength="120" required />
    </div>

    <div class="dash-field">
      <label for="item-content">Content</label>
      <textarea id="item-content" name="content" maxlength="5000"></textarea>
      <span class="dash-hint">Maximum 5000 characters. Optional if a file is attached.</span>
    </div>

    <div class="dash-field">
      <label for="item-file">Picture or document</label>
      <input type="file" id="item-file" name="attachment"
             accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx" />
      <span class="dash-hint">Optional. jpg, png, gif, webp, pdf, txt, csv, Word, Excel, PowerPoint. Max 5 MB.</span>
    </div>

    <div class="dash-actions">
      <button class="dash-btn" type="button" id="add-cancel">Cancel</button>
      <button class="dash-btn dash-btn--primary" type="submit">Save item</button>
    </div>
  </form>
</dialog>

<script>
(function () {
  var dlg = document.getElementById('add-dialog');
  document.querySelectorAll('[data-open-add]').forEach(function (btn) {
    btn.addEventListener('click', function () { dlg.showModal(); });
  });
  document.getElementById('add-cancel').addEventListener('click', function () { dlg.close(); });
})();
</script>

<?php render_footer(); ?>