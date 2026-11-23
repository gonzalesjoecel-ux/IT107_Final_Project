<?php
/*
 * includes/layout.php — kept only for backward compatibility.
 *
 * The real layout now lives in html/layout.php (one file used by every page:
 * logged-in sidebar pages AND the public login / sign-up screens).
 * Old pages still do: require __DIR__ . '/includes/layout.php';
 * so this file simply loads the current layout.
 */
require_once __DIR__ . '/../layout.php';
