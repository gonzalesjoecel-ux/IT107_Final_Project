<?php
/*
 * activity.php — writes one row to activity_log.
 * Logging must never break the app, so every error is swallowed.
 *
 *   log_event($pdo, $userId, 'item_add');
 *   log_event($pdo, $userId, 'admin_unlock', 'by admin', $adminId);
 */
/*
 * Logging must never break the app. The event name stays lowercase so the
 * dashboard, filters and health check keep working. On top of the basic row,
 * the handout's username_typed and user_agent columns are filled when they
 * exist in the database.
 */
function log_event(PDO $pdo, ?int $userId, string $event, string $detail = '', ?int $actorId = null, ?string $typed = null): void {
    static $cols = null;
    try {
        if ($cols === null) {
            $cols = [];
            foreach ($pdo->query('SHOW COLUMNS FROM activity_log') as $c) {
                $cols[strtolower((string)$c['Field'])] = true;
            }
        }

        /* Login uses "username"; Forgot Password uses "identifier". */
        if ($typed === null) {
            $typed = $_POST['username'] ?? $_POST['identifier'] ?? null;
            $typed = is_string($typed) ? $typed : null;
        }

        $row = [
            'user_id'    => $userId,
            'event'      => $event,
            'detail'     => $detail !== '' ? mb_substr($detail, 0, 255) : null,
            'ip'         => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if (isset($cols['actor_id']))       { $row['actor_id']       = $actorId; }
        if (isset($cols['username_typed'])) { $row['username_typed'] = $typed !== null ? mb_substr($typed, 0, 100) : null; }
        if (isset($cols['user_agent']))     { $row['user_agent']     = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255); }

        /* Column names are written by us; values are always bound with ?. */
        $sql = 'INSERT INTO activity_log (' . implode(', ', array_keys($row)) . ') VALUES ('
             . implode(', ', array_fill(0, count($row), '?')) . ')';
        $pdo->prepare($sql)->execute(array_values($row));
    } catch (Throwable $ex) {
        error_log('log_event: ' . $ex->getMessage());
    }
}

/* Text + color for each event (used by admin pages) */
function event_info(string $event): array {
    $map = [
        'login_ok'       => ['Logged in',            'ok'],
        'login_fail'     => ['Failed login',         'bad'],
        'account_locked' => ['Account locked',       'bad'],
        'logout'         => ['Logged out',           'muted'],
        'register'       => ['Registered an account', 'ok'],
        'item_add'       => ['Added an item',        'info'],
        'item_delete'    => ['Deleted an item',      'info'],
        'profile_update' => ['Updated profile',      'info'],
        'admin_unlock'   => ['Unlocked by admin',    'warn'],
        'admin_disable'  => ['Disabled by admin',    'warn'],
        'admin_enable'   => ['Enabled by admin',     'warn'],
        'reset_request'  => ['Requested a password reset', 'warn'],
        'admin_reset'    => ['Password reset by admin',    'warn'],
        'password_change'=> ['Changed password',     'info'],
    ];
    return $map[$event] ?? [$event, 'muted'];
}