<?php
/** @var PDO $pdo   (from db.php via includes/auth.php) */
/** @var array $user (from includes/auth.php) */
/*
 * admin_dashboard.php — SECURITY MONITORING DASHBOARD (handout section 13)
 *
 *   READ-ONLY.  This page only ever runs SELECT on the activity log:
 *   no edit, no delete, no "clear logs".  (The only write is log_event(), which INSERTs.)
 *
 *   13.1  admin only  ........ the check right after the helper functions
 *   13.2  what it shows ...... 1 summary cards   2 failed log-ins per hour (Chart.js)
 *                              3 top IP addresses   4 flags   5 recent events + filters
 *   13.3  auto-refresh ....... js/admin_dashboard.js fetches   admin_dashboard.php?data=1   (JSON)
 *                              The JSON mode checks for an admin too.
 *
 *   Everything shown comes from the log, and the log contains what ATTACKERS typed.
 *   So every value is escaped with e() (htmlspecialchars) in HTML,
 *   and written with textContent (never innerHTML) in JavaScript.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/activity.php';
require __DIR__ . '/includes/layout.php';

/* ------------------------------------------------------------------
 * 0. SETTINGS — change these if YOUR tables or event names are different
 * ---------------------------------------------------------------- */
const T_LOG    = 'activity_log';
const T_FLAGS  = 'security_flags';     // written by scripts/monitor.php
const T_BLOCKS = 'ip_blocks';          // IPs that are blocked for 10 minutes
const BLOCK_IP_COL    = 'ip_address';
const BLOCK_UNTIL_COL = 'blocked_until';

/* Event names. Old names (login_ok) and handout names (LOGIN_SUCCESS) are both matched. */
const EV_SUCCESS  = ['LOGIN_SUCCESS', 'login_ok'];
const EV_FAILED   = ['LOGIN_FAILED', 'login_fail', 'login_failed'];
const EV_OTP_SENT = ['OTP_SENT', 'otp_sent'];
const EV_OTP_FAIL = ['OTP_FAILED', 'otp_failed', 'otp_fail'];
const EV_ALERT    = ['ALERT_EMAIL_SENT', 'alert_email_sent'];

/* ------------------------------------------------------------------
 * 1. Small helpers
 * ---------------------------------------------------------------- */

function table_exists(PDO $pdo, string $table): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $s->execute([$table]);
    return (int)$s->fetchColumn() > 0;
}

/* Column names of the log table, so the page still works before you add the new columns */
function log_columns(PDO $pdo): array {
    $s = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
    $s->execute([T_LOG]);
    return array_map('strtolower', array_column($s->fetchAll(PDO::FETCH_ASSOC), 'column_name'));
}

function in_marks(array $values): string {
    return implode(',', array_fill(0, count($values), '?'));
}

function count_events(PDO $pdo, array $names, string $since): int {
    $s = $pdo->prepare('SELECT COUNT(*) FROM ' . T_LOG . ' WHERE event IN (' . in_marks($names) . ') AND created_at >= ?');
    $s->execute(array_merge($names, [$since]));
    return (int)$s->fetchColumn();
}

/* "Chrome 129 / Windows" from the raw User-Agent text */
function ua_label(?string $ua): string {
    if (!$ua) {
        return '—';
    }
    if     (preg_match('~Edg(?:e|A|iOS)?/(\d+)~', $ua, $m))   { $b = 'Edge ' . $m[1]; }
    elseif (preg_match('~OPR/(\d+)~', $ua, $m))               { $b = 'Opera ' . $m[1]; }
    elseif (preg_match('~(?:Chrome|CriOS)/(\d+)~', $ua, $m))  { $b = 'Chrome ' . $m[1]; }
    elseif (preg_match('~Firefox/(\d+)~', $ua, $m))           { $b = 'Firefox ' . $m[1]; }
    elseif (preg_match('~Version/(\d+).*Safari~', $ua, $m))   { $b = 'Safari ' . $m[1]; }
    else                                                      { $b = mb_substr(preg_split('~[/ ]~', $ua)[0], 0, 20); }

    if     (stripos($ua, 'Windows') !== false)   { $os = 'Windows'; }
    elseif (stripos($ua, 'Android') !== false)   { $os = 'Android'; }
    elseif (preg_match('~iPhone|iPad~', $ua))    { $os = 'iOS'; }
    elseif (stripos($ua, 'Mac OS X') !== false)  { $os = 'macOS'; }
    elseif (stripos($ua, 'Linux') !== false)     { $os = 'Linux'; }
    else                                         { $os = 'Unknown OS'; }

    return $b . ' / ' . $os;
}

/* Text and color for an event name: [label, tone]. Works for old and new names. */
function ev_info(string $event): array {
    $k = strtoupper(trim($event));
    $map = [
        'LOGIN_SUCCESS' => 'ok',   'LOGIN_OK' => 'ok',
        'LOGIN_FAILED'  => 'bad',  'LOGIN_FAIL' => 'bad',
        'OTP_SENT'      => 'info', 'OTP_FAILED' => 'bad', 'OTP_FAIL' => 'bad',
        'LOCKOUT_TIER_1' => 'warn', 'LOCKOUT_TIER_2' => 'warn', 'LOCKOUT_TIER_3' => 'warn', 'ACCOUNT_LOCKED' => 'warn',
        'IP_BLOCKED'    => 'bad',  'ALERT_EMAIL_SENT' => 'warn',
        'REGISTERED'    => 'info', 'REGISTER' => 'info',
        'ACCOUNT_VERIFIED' => 'ok', 'EMAIL_VERIFIED' => 'ok',
        'RESET_REQUESTED' => 'warn', 'RESET_REQUEST' => 'warn',
        'PASSWORD_CHANGED' => 'info', 'PASSWORD_CHANGE' => 'info',
        'CAPTCHA_FAILED' => 'bad', 'CSRF_REJECTED' => 'bad',
        'ADMIN_VIEWED_DASHBOARD' => 'muted', 'ADMIN_ACCESS_DENIED' => 'bad',
    ];
    return [str_replace('_', ' ', $k), $map[$k] ?? 'muted'];
}

/* Escape % and _ so they are searched as normal characters (we use ! as the escape character) */
function like_value(string $s): string {
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s) . '%';
}

/* ------------------------------------------------------------------
 * 2. Everything the dashboard shows, in ONE array (used by the page AND the JSON)
 * ---------------------------------------------------------------- */
function dashboard_payload(PDO $pdo, array $f): array {

    $now      = date('Y-m-d H:i:s');
    $today    = date('Y-m-d 00:00:00');
    $ago24    = date('Y-m-d H:i:s', time() - 86400);
    $warnings = [];

    $out = [
        'summary'  => ['success' => 0, 'failed' => 0, 'locked' => 0, 'blocked' => null,
                       'otp_sent' => 0, 'otp_failed' => 0, 'alerts' => 0],
        'hourly'   => ['labels' => [], 'values' => []],
        'top_ips'  => [],
        'flags'    => [],
        'events'   => [],
        'warnings' => [],
        'updated'  => date('H:i:s'),
    ];

    /* ---- 1. Today's summary cards ---- */
    try {
        $out['summary']['success']    = count_events($pdo, EV_SUCCESS,  $today);
        $out['summary']['failed']     = count_events($pdo, EV_FAILED,   $today);
        $out['summary']['otp_sent']   = count_events($pdo, EV_OTP_SENT, $today);
        $out['summary']['otp_failed'] = count_events($pdo, EV_OTP_FAIL, $today);
        $out['summary']['alerts']     = count_events($pdo, EV_ALERT,    $today);

        $q = $pdo->prepare('SELECT COUNT(*) FROM users WHERE locked_until IS NOT NULL AND locked_until > ?');
        $q->execute([$now]);
        $out['summary']['locked'] = (int)$q->fetchColumn();
    } catch (Throwable $ex) {
        error_log('dashboard summary: ' . $ex->getMessage());
        $warnings[] = 'The summary cards could not be loaded.';
    }

    /* ---- IPs blocked right now (needs the ip_blocks table) ---- */
    $blockedIps = [];
    try {
        if (table_exists($pdo, T_BLOCKS)) {
            $q = $pdo->prepare('SELECT ' . BLOCK_IP_COL . ' AS ip FROM ' . T_BLOCKS . ' WHERE ' . BLOCK_UNTIL_COL . ' > ?');
            $q->execute([$now]);
            $blockedIps = array_flip(array_column($q->fetchAll(PDO::FETCH_ASSOC), 'ip'));
            $out['summary']['blocked'] = count($blockedIps);
        } else {
            $warnings[] = 'Table ' . T_BLOCKS . ' was not found, so "IPs blocked now" cannot be counted. Run dashboard_update.sql.';
        }
    } catch (Throwable $ex) {
        error_log('dashboard blocks: ' . $ex->getMessage());
    }

    /* ---- 2. Failed log-ins per hour, last 24 hours ---- */
    try {
        $start  = strtotime(date('Y-m-d H:00:00')) - 23 * 3600;      // beginning of the hour 23 hours ago
        $values = array_fill(0, 24, 0);
        $q = $pdo->prepare('SELECT created_at FROM ' . T_LOG . ' WHERE event IN (' . in_marks(EV_FAILED) . ') AND created_at >= ?');
        $q->execute(array_merge(EV_FAILED, [date('Y-m-d H:i:s', $start)]));
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $i = intdiv(strtotime((string)$t) - $start, 3600);
            if ($i >= 0 && $i < 24) {
                $values[$i]++;
            }
        }
        $labels = [];
        for ($i = 0; $i < 24; $i++) {
            $labels[] = date('H:00', $start + $i * 3600);
        }
        $out['hourly'] = ['labels' => $labels, 'values' => $values];
    } catch (Throwable $ex) {
        error_log('dashboard hourly: ' . $ex->getMessage());
        $warnings[] = 'The hourly chart could not be loaded.';
    }

    /* ---- 3. Top 5 IP addresses by failures (24 h) ---- */
    try {
        $q = $pdo->prepare(
            'SELECT ip, COUNT(*) AS c FROM ' . T_LOG . '
             WHERE event IN (' . in_marks(EV_FAILED) . ') AND created_at >= ? AND ip IS NOT NULL AND ip <> \'\'
             GROUP BY ip ORDER BY c DESC LIMIT 5'
        );
        $q->execute(array_merge(EV_FAILED, [$ago24]));
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out['top_ips'][] = ['ip' => (string)$r['ip'], 'failures' => (int)$r['c'], 'blocked' => isset($blockedIps[$r['ip']])];
        }
    } catch (Throwable $ex) {
        error_log('dashboard top ips: ' . $ex->getMessage());
        $warnings[] = 'The top IP list could not be loaded.';
    }

    /* ---- 4. Flags written by the monitoring script ---- */
    try {
        if (table_exists($pdo, T_FLAGS)) {
            $q = $pdo->query('SELECT * FROM ' . T_FLAGS . ' ORDER BY id DESC LIMIT 10');
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out['flags'][] = [
                    'title'    => (string)($r['pattern'] ?? $r['flag_type'] ?? $r['type'] ?? 'Flag'),
                    'target'   => (string)($r['target'] ?? ''),
                    'details'  => (string)($r['details'] ?? $r['detail'] ?? ''),
                    'severity' => strtoupper((string)($r['severity'] ?? 'MED')),
                    'date'     => (string)($r['flag_date'] ?? $r['created_at'] ?? ''),
                ];
            }
        } else {
            $warnings[] = 'Table ' . T_FLAGS . ' was not found, so no flags can be shown. Run dashboard_update.sql.';
        }
    } catch (Throwable $ex) {
        error_log('dashboard flags: ' . $ex->getMessage());
    }

    /* ---- 5. Recent events (latest 50) with filters ---- */
    try {
        $cols  = log_columns($pdo);
        $typed = in_array('username_typed', $cols, true) ? 'l.username_typed' : 'NULL';
        $agent = in_array('user_agent', $cols, true)     ? 'l.user_agent'     : 'NULL';

        $where  = [];
        $params = [];

        if ($f['from'] !== '') { $where[] = 'l.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
        if ($f['to']   !== '') { $where[] = 'l.created_at <= ?'; $params[] = $f['to']   . ' 23:59:59'; }
        if ($f['type'] !== '') { $where[] = 'l.event = ?';       $params[] = $f['type']; }
        if ($f['ip']   !== '') { $where[] = "l.ip LIKE ? ESCAPE '!'"; $params[] = like_value($f['ip']); }
        if ($f['username'] !== '') {
            $where[] = '(' . ($typed !== 'NULL' ? "$typed LIKE ? ESCAPE '!' OR " : '') . "u.username LIKE ? ESCAPE '!')";
            if ($typed !== 'NULL') { $params[] = like_value($f['username']); }
            $params[] = like_value($f['username']);
        }

        $sql = "SELECT l.event, l.ip, l.created_at, $typed AS typed, $agent AS agent, u.username AS account
                FROM " . T_LOG . " l LEFT JOIN users u ON u.id = l.user_id"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY l.id DESC LIMIT 50';
        $q = $pdo->prepare($sql);
        $q->execute($params);

        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            [$label, $tone] = ev_info((string)$r['event']);
            $out['events'][] = [
                'time'     => date('M j  H:i:s', strtotime((string)$r['created_at'])),
                'event'    => $label,
                'tone'     => $tone,
                'username' => (string)(($r['typed'] !== null && $r['typed'] !== '') ? $r['typed'] : ($r['account'] ?? '')),
                'ip'       => (string)($r['ip'] ?? ''),
                'browser'  => ua_label($r['agent']),
            ];
        }

        if ($typed === 'NULL' || $agent === 'NULL') {
            $warnings[] = 'The log table has no username_typed / user_agent column yet, so the Username and Browser columns are incomplete. Run dashboard_update.sql.';
        }
    } catch (Throwable $ex) {
        error_log('dashboard events: ' . $ex->getMessage());
        $warnings[] = 'The recent events could not be loaded.';
    }

    $out['warnings'] = $warnings;
    return $out;
}

/* ------------------------------------------------------------------
 * 3. Who may open this?  (13.1)
 * ---------------------------------------------------------------- */
$isJson = isset($_GET['data']);

if (($user['role'] ?? '') !== 'admin') {

    log_event($pdo, (int)$user['id'], 'ADMIN_ACCESS_DENIED', 'tried to open ' . basename($_SERVER['SCRIPT_NAME']));
    http_response_code(403);

    if ($isJson) {
        exit;                                   // the data endpoint returns NOTHING to a non-admin
    }

    render_header('Access denied', '');
    ?>
    <div class="dash-card page-narrow">
      <h1>Access denied</h1>
      <p class="muted">Only administrators can open the security monitoring dashboard. This attempt was recorded.</p>
      <p><a class="dash-btn dash-btn--primary" href="<?= e(url('user_dashboard.php')) ?>">Back to my vault</a></p>
    </div>
    <?php
    render_footer();
    exit;
}

/* ------------------------------------------------------------------
 * 4. Filters (all optional, all validated)
 * ---------------------------------------------------------------- */
$validDate = static fn(string $d): bool => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;

$f = [
    'from'     => trim((string)($_GET['from'] ?? '')),
    'to'       => trim((string)($_GET['to'] ?? '')),
    'type'     => trim((string)($_GET['type'] ?? '')),
    'username' => mb_substr(trim((string)($_GET['username'] ?? '')), 0, 100),
    'ip'       => mb_substr(trim((string)($_GET['ip'] ?? '')), 0, 45),
];
if (!$validDate($f['from'])) { $f['from'] = ''; }
if (!$validDate($f['to']))   { $f['to'] = ''; }

/* Allowed event types = the ones that really exist in the log */
$types = [];
try {
    $types = $pdo->query('SELECT DISTINCT event FROM ' . T_LOG . ' ORDER BY event LIMIT 100')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $ex) {
    error_log('dashboard types: ' . $ex->getMessage());
}
if ($f['type'] !== '' && !in_array($f['type'], $types, true)) {
    $f['type'] = '';
}

/* ------------------------------------------------------------------
 * 5. JSON mode (the 60-second refresh) — admin only, nothing is cached
 * ---------------------------------------------------------------- */
if ($isJson) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(dashboard_payload($pdo, $f), JSON_UNESCAPED_UNICODE);
    exit;
}

/* ------------------------------------------------------------------
 * 6. The page
 * ---------------------------------------------------------------- */
log_event($pdo, (int)$user['id'], 'ADMIN_VIEWED_DASHBOARD', 'opened the dashboard');   // watching the logs is logged too

$p       = dashboard_payload($pdo, $f);
$s       = $p['summary'];
$dataUrl = url('admin_dashboard.php') . '?' . http_build_query(array_merge(array_filter($f, 'strlen'), ['data' => 1]));
$jsBase  = dirname(HTML_URL) . '/js';

render_header('Security monitoring', 'a-overview');
?>

<div class="page-head">
  <h1>Security monitoring</h1>
  <p>
    A read-only view of the activity log. It refreshes by itself every 60 seconds.
    Last update: <strong id="dash-updated"><?= e($p['updated']) ?></strong>
    <span class="pill pill--muted">Read-only</span>
  </p>
</div>

<?php foreach ($p['warnings'] as $w): ?>
  <div class="dash-flash dash-flash--warn" role="status"><?= e($w) ?></div>
<?php endforeach; ?>

<div id="dash-root" data-url="<?= e($dataUrl) ?>">

  <!-- 1. Today's summary cards -->
  <div class="stats stats--six">
    <div class="stat">
      <div class="stat__icon stat__icon--teal"><?= icon('check') ?></div>
      <div class="stat__label">Successful log-ins (today)</div>
      <div class="stat__value" data-stat="success"><?= (int)$s['success'] ?></div>
    </div>
    <div class="stat">
      <div class="stat__icon stat__icon--red"><?= icon('alert') ?></div>
      <div class="stat__label">Failed log-ins (today)</div>
      <div class="stat__value stat__value--bad" data-stat="failed"><?= (int)$s['failed'] ?></div>
    </div>
    <div class="stat">
      <div class="stat__icon stat__icon--amber"><?= icon('lock') ?></div>
      <div class="stat__label">Accounts locked now</div>
      <div class="stat__value stat__value--warn" data-stat="locked"><?= (int)$s['locked'] ?></div>
    </div>
    <div class="stat">
      <div class="stat__icon stat__icon--amber"><?= icon('shield') ?></div>
      <div class="stat__label">IPs blocked now</div>
      <div class="stat__value stat__value--warn" data-stat="blocked"><?= $s['blocked'] === null ? '—' : (int)$s['blocked'] ?></div>
    </div>
    <div class="stat">
      <div class="stat__icon stat__icon--purple"><?= icon('key') ?></div>
      <div class="stat__label">OTPs sent / failed (today)</div>
      <div class="stat__value"><span data-stat="otp_sent"><?= (int)$s['otp_sent'] ?></span> / <span data-stat="otp_failed"><?= (int)$s['otp_failed'] ?></span></div>
    </div>
    <div class="stat">
      <div class="stat__icon stat__icon--pink"><?= icon('activity') ?></div>
      <div class="stat__label">Alert emails sent (today)</div>
      <div class="stat__value" data-stat="alerts"><?= (int)$s['alerts'] ?></div>
    </div>
  </div>

  <div class="dash-grid">

    <!-- 2. Failed log-ins per hour -->
    <section class="dash-card dash-card--vault" aria-labelledby="chart-title">
      <h2 id="chart-title">Failed log-ins per hour — last 24 hours</h2>
      <p class="dash-sub">Each bar is one hour (Philippine time). A tall bar means somebody is guessing passwords.</p>
      <div class="chart-box">
        <canvas id="hourly-chart" role="img" aria-label="Bar chart of failed log-ins per hour"
                data-labels="<?= e(json_encode($p['hourly']['labels'])) ?>"
                data-values="<?= e(json_encode($p['hourly']['values'])) ?>"></canvas>
      </div>
    </section>

    <!-- 3. Top IP addresses -->
    <section class="dash-card dash-card--account" aria-labelledby="ips-title">
      <h2 id="ips-title">Top IP addresses by failures (24 h)</h2>
      <p class="dash-sub">The five IPs with the most failed log-ins.</p>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>IP address</th><th>Failures</th><th>Status</th></tr></thead>
          <tbody id="top-ips">
            <?php if (!$p['top_ips']): ?>
              <tr><td colspan="3" class="muted">No failed log-ins in the last 24 hours.</td></tr>
            <?php endif; ?>
            <?php foreach ($p['top_ips'] as $r): ?>
              <tr>
                <td class="mono"><?= e($r['ip']) ?></td>
                <td><?= (int)$r['failures'] ?></td>
                <td><?= $r['blocked'] ? '<span class="pill pill--warn">BLOCKED</span>' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 4. Flags from the monitoring script -->
    <section class="dash-card dash-card--wide" aria-labelledby="flags-title">
      <h2 id="flags-title">Flags from the monitoring script</h2>
      <p class="dash-sub">Written by <code>php scripts/monitor.php</code>. Newest first.</p>
      <ul class="feed" id="flags">
        <?php if (!$p['flags']): ?>
          <li class="muted">No flags yet. Run the monitoring script to scan the log.</li>
        <?php endif; ?>
        <?php foreach ($p['flags'] as $r): ?>
          <li class="feed__item">
            <span class="dot dot--<?= $r['severity'] === 'HIGH' ? 'bad' : 'warn' ?>"></span>
            <div>
              <div class="feed__text"><strong><?= e($r['title']) ?></strong> — <span class="mono"><?= e($r['target']) ?></span></div>
              <div class="feed__meta"><?= e($r['details']) ?> · <?= e($r['severity']) ?> · <?= e($r['date']) ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <!-- 5. Recent events with filters -->
    <section class="dash-card dash-card--wide" aria-labelledby="events-title">
      <h2 id="events-title">Recent events (latest 50)</h2>

      <form class="toolbar" method="GET" action="<?= e(url('admin_dashboard.php')) ?>">
        <input class="toolbar__search" type="date" name="from" value="<?= e($f['from']) ?>" aria-label="From date" />
        <input class="toolbar__search" type="date" name="to" value="<?= e($f['to']) ?>" aria-label="To date" />
        <select class="toolbar__select" name="type" aria-label="Event type">
          <option value="">Event type: all</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= e($t) ?>"<?= $t === $f['type'] ? ' selected' : '' ?>><?= e(ev_info((string)$t)[0]) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="toolbar__search" type="text" name="username" maxlength="100" value="<?= e($f['username']) ?>" placeholder="Username: any" aria-label="Username" />
        <input class="toolbar__search" type="text" name="ip" maxlength="45" value="<?= e($f['ip']) ?>" placeholder="IP: any" aria-label="IP address" />
        <button class="dash-btn dash-btn--primary dash-btn--sm" type="submit">Apply filter</button>
        <?php if (array_filter($f, 'strlen')): ?>
          <a class="dash-btn dash-btn--sm" href="<?= e(url('admin_dashboard.php')) ?>">Clear</a>
        <?php endif; ?>
      </form>

      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Time (PHT)</th><th>Event</th><th>Username</th><th>IP address</th><th>Browser</th></tr></thead>
          <tbody id="events">
            <?php if (!$p['events']): ?>
              <tr><td colspan="5" class="muted">No events match.</td></tr>
            <?php endif; ?>
            <?php foreach ($p['events'] as $r): ?>
              <tr>
                <td class="nowrap"><?= e($r['time']) ?></td>
                <td><span class="pill pill--<?= e($r['tone']) ?>"><?= e($r['event']) ?></span></td>
                <td class="mono"><?= e($r['username']) ?></td>
                <td class="mono"><?= e($r['ip']) ?></td>
                <td><?= e($r['browser']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</div>

<script src="<?= e($jsBase) ?>/chart.umd.js"></script>
<script src="<?= e($jsBase) ?>/admin_dashboard.js"></script>

<?php render_footer(); ?>