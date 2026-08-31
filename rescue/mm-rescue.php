<?php
/**
 * Magic Moon Rescue v2 — must-use plugin.
 * Place in:  wp-content/mu-plugins/mm-rescue.php  (overwrite the old one)
 *
 * 1. Writes a diagnostic log the instant it loads (proves it ran + lists dropins)
 * 2. Disables ALL regular plugins so admin can load
 * 3. Neutralizes cache dropins that crash before plugins
 * 4. Resets 'hasan' password to  Magic2026!
 * 5. Captures any fatal error
 *
 * Remove this file once the site is fixed.
 */

if (!defined('ABSPATH')) exit;

$mm_log = WP_CONTENT_DIR . '/mm-rescue-log.txt';

/* Write a load marker immediately, plus environment snapshot ------------- */
$dropins = array();
foreach (array('advanced-cache.php', 'object-cache.php', 'db.php') as $d) {
    if (file_exists(WP_CONTENT_DIR . '/' . $d)) $dropins[] = $d;
}
@file_put_contents($mm_log,
    date('c') . "  RESCUE v2 LOADED  PHP=" . PHP_VERSION .
    "  dropins=[" . implode(',', $dropins) . "]\n",
    FILE_APPEND
);

/* Capture fatal errors -------------------------------------------------- */
register_shutdown_function(function () use ($mm_log) {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR), true)) {
        @file_put_contents($mm_log,
            date('c') . "  FATAL: {$e['message']}  in {$e['file']}:{$e['line']}\n",
            FILE_APPEND);
    }
});

/* Disable ALL regular plugins ------------------------------------------- */
add_filter('option_active_plugins', '__return_empty_array', PHP_INT_MAX);
add_filter('site_option_active_sitewide_plugins', '__return_empty_array', PHP_INT_MAX);

/* Neutralize cache so a crashing cache dropin can't run ------------------ */
if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);

/* Reset hasan password once --------------------------------------------- */
add_action('init', function () {
    if (get_option('mm_rescue_pw_done') === 'v2') return;
    $user = get_user_by('login', 'hasan');
    if ($user) {
        wp_set_password('Magic2026!', $user->ID);
        update_option('mm_rescue_pw_done', 'v2');
    }
});
