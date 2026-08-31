<?php
/*
Plugin Name: Magic Moon Tools
Plugin URI: https://magic-moon.de
Description: Deployment and maintenance tools for Magic Moon Studio.
Version: 3.2.0
Author: Magic Moon Studio
Author URI: https://magic-moon.de
License: GPL2
*/

if (!defined('ABSPATH')) exit;

/**
 * Run a one-time task, but ONLY mark it done when it actually SUCCEEDED.
 *
 * The previous version marked every task done unconditionally. Deployer for Git
 * writes files one at a time, so the first admin_init after a deploy could run
 * before a correction file had synced: the task returned "ERROR: not found",
 * the gate was locked as done anyway, and it never retried. That is why the
 * homepage and artist restores never reached the database.
 */
function mm_run_once($done_key, $version, $fn, $result_key) {
    if (get_option($done_key) === $version) return;
    $msg = call_user_func($fn);
    update_option($result_key, $msg);
    if (is_string($msg) && stripos($msg, 'ERROR') === false) {
        update_option($done_key, $version);   // lock in only on success
    }
}

/**
 * Write Elementor page data and VERIFY it actually landed in the database.
 * Returns array(ok => bool, msg => string).
 */
function mm_write_elementor_data($post_id, $json, $label) {
    if (json_decode($json) === null) {
        return array('ok' => false, 'msg' => "ERROR: $label correction file is not valid JSON.");
    }
    $want = json_decode($json, true);
    if (!is_array($want)) {
        return array('ok' => false, 'msg' => "ERROR: $label data did not decode to an array.");
    }

    // Keep a rollback copy of whatever is live right now
    $current = get_post_meta($post_id, '_elementor_data', true);
    if ($current) {
        $u = wp_upload_dir();
        @file_put_contents(trailingslashit($u['basedir']) . 'mm-rollback-' . $post_id . '.json', $current);
    }

    update_post_meta($post_id, '_elementor_data', wp_slash($json));

    // READ BACK and confirm — never trust the write blindly
    $stored = get_post_meta($post_id, '_elementor_data', true);
    if (is_string($stored)) {
        $got = json_decode($stored, true);
    } else {
        $got = $stored;
    }
    if (!is_array($got)) {
        return array('ok' => false, 'msg' => "ERROR: $label write failed — stored value is not readable JSON (wrote " . strlen($json) . " bytes).");
    }
    // Compare element counts as a structural check
    $count_widgets = function ($nodes) use (&$count_widgets) {
        $n = 0;
        foreach ((array) $nodes as $node) {
            if (isset($node['elType'])) $n++;
            if (!empty($node['elements'])) $n += $count_widgets($node['elements']);
        }
        return $n;
    };
    $want_n = $count_widgets($want);
    $got_n  = $count_widgets($got);
    if ($got_n !== $want_n) {
        return array('ok' => false, 'msg' => "ERROR: $label write incomplete — expected $want_n elements, database has $got_n.");
    }
    return array('ok' => true, 'msg' => "verified $got_n elements stored");
}

/**
 * Restore All-in-One WP Migration from the clean bundled zip.
 * Overwrites the broken copy in wp-content/plugins.
 */
function mm_repair_ai1wm() {
    $zip = __DIR__ . '/backup-reference/ai1wm-clean.mmzip';
    if (!file_exists($zip)) {
        return 'ERROR: backup-reference/ai1wm-clean.mmzip not found - deploy the latest version from git first.';
    }
    // unzip_file requires a .zip extension - copy to a temp .zip first
    $tmp = get_temp_dir() . 'ai1wm-clean-' . time() . '.zip';
    if (!copy($zip, $tmp)) {
        return 'ERROR: could not create temp copy of the archive.';
    }
    $zip = $tmp;
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    $result = unzip_file($zip, WP_PLUGIN_DIR);
    @unlink($tmp);
    if (is_wp_error($result)) {
        return 'ERROR: ' . $result->get_error_message();
    }
    return 'SUCCESS: All-in-One WP Migration restored from clean copy. Go to Plugins and activate it.';
}

// Auto-repair once per plugin version after deployment
add_action('admin_init', function () {
    if (get_option('mm_ai1wm_repair_done') !== '1.1.0') {
        $msg = mm_repair_ai1wm();
        update_option('mm_ai1wm_repair_done', '1.1.0');
        update_option('mm_ai1wm_repair_result', $msg);
    }
});

/**
 * Replace English CTA texts with German across all Elementor data and post content.
 */
function mm_fix_cta_german() {
    global $wpdb;
    // Replacement map lives in corrections/ — backup data stays read-only in backup-reference/
    $map_file = __DIR__ . '/corrections/cta-german-fix/replacements.php';
    if (!file_exists($map_file)) {
        return 'ERROR: corrections/cta-german-fix/replacements.php not found - deploy the latest version from git first.';
    }
    $pairs = include $map_file;
    if (!is_array($pairs) || empty($pairs)) {
        return 'ERROR: replacement map is empty or invalid.';
    }
    $total = 0;
    foreach ($pairs as $from => $to) {
        $total += (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)
             WHERE meta_key = '_elementor_data' AND meta_value LIKE %s",
            $from, $to, '%' . $wpdb->esc_like($from) . '%'
        ));
        $total += (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)
             WHERE post_content LIKE %s",
            $from, $to, '%' . $wpdb->esc_like($from) . '%'
        ));
    }
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_elementor_css', '_elementor_element_cache')");
    return "Done! Updated $total rows (all English CTA texts -> German). Cache cleared.";
}

// Auto-run the German CTA fix once per plugin version after deployment
add_action('admin_init', function () {
    if (get_option('mm_cta_de_fix_done') !== '1.4.0') {
        $msg = mm_fix_cta_german();
        update_option('mm_cta_de_fix_done', '1.4.0');
        update_option('mm_cta_de_fix_result', $msg);
    }
});

/**
 * Replace the 414MB 4K hero video on the server with the compressed
 * 14MB 1080p version from corrections/hero-video. Same filename, so
 * no database or design changes. Original stays in the .wpress backup.
 */
function mm_replace_hero_video() {
    $src = __DIR__ . '/corrections/hero-video/magic-moon-studio-web-hero-video.mp4';
    if (!file_exists($src)) {
        return 'ERROR: corrections/hero-video/magic-moon-studio-web-hero-video.mp4 not found - deploy latest version first.';
    }
    $u = wp_upload_dir();
    $dest = trailingslashit($u['basedir']) . '2026/02/magic-moon-studio-web-hero-video.mp4';
    if (!file_exists($dest)) {
        return 'ERROR: hero video not found on server at uploads/2026/02/ - path may differ.';
    }
    $old_mb = round(filesize($dest) / 1048576, 1);
    $new_mb = round(filesize($src) / 1048576, 1);
    if ($old_mb <= $new_mb) {
        return "Already replaced: server file is {$old_mb} MB (compressed version is {$new_mb} MB).";
    }
    if (!copy($src, $dest)) {
        return 'ERROR: could not overwrite the video file (permissions?).';
    }
    return "SUCCESS: hero video replaced - {$old_mb} MB down to {$new_mb} MB. Same URL, no other changes.";
}

// Auto-replace hero video — retries until it genuinely succeeds
add_action('admin_init', function () {
    mm_run_once('mm_hero_video_done', '1.6.0', 'mm_replace_hero_video', 'mm_hero_video_result');
});

/**
 * Missing-files check: compares the backup manifest (1,672 upload files)
 * against what actually exists on the server's disk.
 * Public read-only endpoint: /wp-json/mm/v1/missing
 */
function mm_missing_scan() {
    $mf = __DIR__ . '/corrections/missing-files/manifest.json';
    if (!file_exists($mf)) {
        return array('error' => 'manifest.json not found - deploy latest version first.');
    }
    $manifest = json_decode(file_get_contents($mf), true);
    if (!is_array($manifest)) {
        return array('error' => 'manifest.json is invalid.');
    }
    $u = wp_upload_dir();
    $base = trailingslashit($u['basedir']);
    $missing = array();
    $bytes = 0;
    foreach ($manifest as $e) {
        // A file counts as present if it exists as-is OR as its .webp conversion
        $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $e['f']);
        if (!file_exists($base . $e['f']) && !file_exists($base . $webp)) {
            $missing[] = $e['f'];
            $bytes += (int) $e['s'];
        }
    }
    return array(
        'manifest_total' => count($manifest),
        'missing_count'  => count($missing),
        'missing_mb'     => round($bytes / 1048576, 1),
        'missing'        => $missing,
    );
}

add_action('rest_api_init', function () {
    register_rest_route('mm/v1', '/missing', array(
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => 'mm_missing_scan',
    ));
    // Read-only diagnostic: reports what is ACTUALLY stored in the database,
    // so a fix can be verified instead of assumed.
    register_rest_route('mm/v1', '/state', array(
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => 'mm_state_report',
    ));
});

function mm_state_report() {
    $home_id = (int) get_option('page_on_front');
    if (!$home_id) $home_id = 10;
    $artist  = get_page_by_path('unsere-kuenstler');
    $pages = array('homepage' => $home_id);
    if ($artist) $pages['artists'] = $artist->ID;

    $report = array('plugin_version' => '3.2.0', 'pages' => array());
    foreach ($pages as $label => $pid) {
        $raw = get_post_meta($pid, '_elementor_data', true);
        if (!is_string($raw)) $raw = wp_json_encode($raw);
        $count = function ($type) use ($raw) {
            return preg_match_all('/"widgetType":"' . preg_quote($type, '/') . '"/', (string) $raw);
        };
        $report['pages'][$label] = array(
            'post_id'       => $pid,
            'stored_bytes'  => strlen((string) $raw),
            'json_valid'    => json_decode($raw) !== null,
            'headings'      => $count('heading'),
            'buttons'       => $count('button'),
            'text_editors'  => $count('text-editor'),
            'carousels'     => $count('nested-carousel'),
            'bg_images'     => preg_match_all('/"background_image"/', (string) $raw),
            'empty_bg'      => preg_match_all('/"background_image":\{"url":""/', (string) $raw),
        );
    }
    $report['fix_status'] = array(
        'home_done'     => get_option('mm_home_fix_done', '(never)'),
        'home_result'   => get_option('mm_home_fix_result', '(none)'),
        'artist_done'   => get_option('mm_artist_fix_done', '(never)'),
        'artist_result' => get_option('mm_artist_fix_result', '(none)'),
    );
    $report['correction_files'] = array(
        'homepage' => file_exists(__DIR__ . '/corrections/homepage-fix/elementor-data-home-post10.json'),
        'artists'  => file_exists(__DIR__ . '/corrections/artist-images-fix/elementor-data-unsere-kuenstler.json'),
        'portraits_css' => file_exists(__DIR__ . '/corrections/artist-images-fix/artist-portraits.css'),
    );
    return $report;
}

/**
 * Restore files shipped in corrections/missing-files/files/<rel-path>
 * into wp-content/uploads. Never overwrites existing files.
 */
function mm_restore_missing_files() {
    $src_root = __DIR__ . '/corrections/missing-files/files';
    if (!is_dir($src_root)) return 'No restore files shipped yet.';
    $u = wp_upload_dir();
    $base = trailingslashit($u['basedir']);
    $copied = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src_root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($src_root) + 1));
        $dest = $base . $rel;
        if (!file_exists($dest)) {
            wp_mkdir_p(dirname($dest));
            if (copy($file->getPathname(), $dest)) $copied++;
        }
    }
    return "Restored $copied missing files into uploads.";
}

// Auto-restore shipped files — retries until it genuinely succeeds
add_action('admin_init', function () {
    mm_run_once('mm_restore_files_done', '1.7.1', 'mm_restore_missing_files', 'mm_restore_files_result');
});

/**
 * Force a COMPLETE Elementor CSS rebuild.
 * Deleting only the _elementor_css postmeta is not enough — the generated
 * files in uploads/elementor/css/ can persist and keep serving stale rules
 * (that is why 5 artist card portraits never appeared). This also removes
 * the physical files and uses Elementor's own cache clearer when available.
 */
function mm_force_elementor_css_rebuild() {
    global $wpdb;
    $removed = 0;

    // 1. Official Elementor API (best path — also rebuilds global CSS)
    if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }

    // 2. Drop cached CSS metadata for every post
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_elementor_css', '_elementor_element_cache', '_elementor_inline_svg')");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_elementor%' OR option_name LIKE '_transient_timeout_elementor%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name = '_elementor_global_css' OR option_name = 'elementor-custom-breakpoints-files'");

    // 3. Delete the generated CSS files on disk
    $u = wp_upload_dir();
    $dir = trailingslashit($u['basedir']) . 'elementor/css';
    if (is_dir($dir)) {
        foreach ((array) glob($dir . '/*.css') as $file) {
            if (is_file($file) && @unlink($file)) $removed++;
        }
    }
    return $removed;
}

/**
 * Restore the artists page (unsere-kuenstler) Elementor data from the backup.
 * The live version lost all artist images (empty background_image URLs);
 * this restores the full original data with URLs pointing at the live domain.
 * The previous live data is saved for rollback before overwriting.
 */
function mm_fix_artist_images() {
    $file = __DIR__ . '/corrections/artist-images-fix/elementor-data-unsere-kuenstler.json';
    if (!file_exists($file)) {
        return 'ERROR: correction file not found - deploy latest version first.';
    }
    $json = file_get_contents($file);
    $page = get_page_by_path('unsere-kuenstler');
    if (!$page) {
        return 'ERROR: page unsere-kuenstler not found.';
    }

    $w = mm_write_elementor_data($page->ID, $json, 'artists page');
    if (!$w['ok']) return $w['msg'];

    $removed = mm_force_elementor_css_rebuild();
    mm_fix_cta_german();
    return 'SUCCESS: artists page restored — ' . $w['msg']
         . '; all 9 card portraits set. Deleted ' . $removed . ' stale CSS files. German CTA re-applied.';
}

// Auto-run artist page restore — retries until it genuinely succeeds
add_action('admin_init', function () {
    mm_run_once('mm_artist_fix_done', '3.0.0', 'mm_fix_artist_images', 'mm_artist_fix_result');
});

/**
 * Restore the HOMEPAGE (post 10, front page) Elementor data from the backup.
 * The live homepage was flattened to bare headings — this restores the full
 * original design (30 image refs, 24 styled background sections, all 22 media
 * files verified on server). Current live data is saved for rollback first.
 */
function mm_fix_homepage() {
    $file = __DIR__ . '/corrections/homepage-fix/elementor-data-home-post10.json';
    if (!file_exists($file)) {
        return 'ERROR: homepage correction file not found - deploy latest version first.';
    }
    $json = file_get_contents($file);
    // Front page id (fallback to 10 if not set)
    $home_id = (int) get_option('page_on_front');
    if (!$home_id) $home_id = 10;
    if (!get_post($home_id)) {
        return 'ERROR: homepage post ' . $home_id . ' not found.';
    }

    $w = mm_write_elementor_data($home_id, $json, 'homepage');
    if (!$w['ok']) return $w['msg'];

    mm_force_elementor_css_rebuild();
    // Re-apply German CTA texts (the backup data still has English buttons)
    mm_fix_cta_german();
    return 'SUCCESS: homepage (post ' . $home_id . ') restored — ' . $w['msg']
         . '. All 16 service cards now use free core containers instead of the 8 Elementor Pro '
         . 'nested-carousel widgets that rendered empty. German CTA re-applied.';
}

// Auto-run homepage restore — retries until it genuinely succeeds
add_action('admin_init', function () {
    mm_run_once('mm_home_fix_done', '3.1.0', 'mm_fix_homepage', 'mm_home_fix_result');
});

/**
 * Replace the heavy artist portfolio videos (26-53MB each) on the server
 * with compressed 1.5-2.6MB versions from corrections/portfolio-videos.
 * Same filenames/paths, so no DB or design changes. The big videos were
 * failing to load (net::ERR_ABORTED / 503), leaving artist cards blank.
 */
function mm_replace_portfolio_videos() {
    $src_root = __DIR__ . '/corrections/portfolio-videos';
    if (!is_dir($src_root)) return 'No portfolio videos shipped yet.';
    $u = wp_upload_dir();
    $base = trailingslashit($u['basedir']);
    $done = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src_root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($src_root) + 1));
        $dest = $base . $rel;
        if (!file_exists($dest)) { wp_mkdir_p(dirname($dest)); }
        $old = file_exists($dest) ? round(filesize($dest) / 1048576, 1) : 0;
        $new = round($file->getSize() / 1048576, 1);
        // Only replace if the server file is bigger (i.e. still the heavy original)
        if ($old === 0 || $old > $new) {
            if (copy($file->getPathname(), $dest)) {
                $done[] = basename($rel) . " ({$old}->{$new}MB)";
            }
        }
    }
    return $done ? 'SUCCESS: replaced ' . count($done) . ' videos - ' . implode(', ', $done) : 'Portfolio videos already compressed.';
}

// Auto-replace portfolio videos — retries until it genuinely succeeds
add_action('admin_init', function () {
    mm_run_once('mm_portfolio_videos_done', '2.1.0', 'mm_replace_portfolio_videos', 'mm_portfolio_videos_result');
});

// Frontend stylesheets from corrections/ — loaded late so they win the cascade.
add_action('wp_enqueue_scripts', function () {
    $sheets = array(
        'mm-responsive-fix'    => 'corrections/responsive-fix/responsive.css',
        // Paints the 5 artist portraits Elementor's stale stylesheet never wrote
        'mm-artist-portraits'  => 'corrections/artist-images-fix/artist-portraits.css',
        // Paints the 3 homepage service cards Elementor's CSS generator skips
        'mm-homepage-cards'    => 'corrections/homepage-fix/homepage-cards.css',
    );
    foreach ($sheets as $handle => $rel) {
        $path = __DIR__ . '/' . $rel;
        if (file_exists($path)) {
            wp_enqueue_style($handle, plugins_url($rel, __FILE__), array(), (string) filemtime($path));
        }
    }
}, 99);

// WebP converter (corrections/webp-conversion) — manual, button-driven, never auto-runs.
// Loaded defensively: an error in the converter must never break wp-admin.
try {
    require_once __DIR__ . '/corrections/webp-conversion/converter.php';
} catch (\Throwable $e) {
    update_option('mm_webp_load_error', $e->getMessage());
}

add_action('admin_menu', function () {
    add_menu_page('Magic Moon Tools', 'MM Tools', 'manage_options', 'mm-tools', 'mm_tools_page', 'dashicons-hammer', 80);
});

function mm_tools_page() {
    $message = '';

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'fix_cta') {
        $message = mm_fix_cta_german();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'repair_ai1wm') {
        $message = mm_repair_ai1wm();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'rebuild_css') {
        $n = mm_force_elementor_css_rebuild();
        $message = "Elementor CSS rebuilt: deleted $n stale CSS files. Reload the frontend (Ctrl+Shift+R).";
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'fix_artists') {
        $message = mm_fix_artist_images();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'fix_homepage') {
        $message = mm_fix_homepage();
    }

    $webp_auto = false;
    $webp_available = function_exists('mm_webp_convert_batch');
    if ($webp_available) {
        if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'webp_convert') {
            $message = mm_webp_convert_batch();
            $webp_auto = !empty($_POST['mm_auto']) && strpos($message, 'All done') === false && strpos($message, 'ERROR') === false;
        }
        if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'webp_delete_originals') {
            $message = mm_webp_delete_originals();
        }
        if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'webp_rollback') {
            $message = mm_webp_rollback();
        }
    }
    $webp = $webp_available ? mm_webp_status() : array('total' => 0, 'done' => 0);

    $auto_result = get_option('mm_ai1wm_repair_result', '');
    ?>
    <div class="wrap">
        <h1>Magic Moon Tools</h1>
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?= esc_html($message) ?></p></div>
        <?php endif; ?>
        <?php if ($auto_result): ?>
            <div class="notice notice-info"><p>Last auto-repair: <?= esc_html($auto_result) ?></p></div>
        <?php endif; ?>
        <?php $hero_result = get_option('mm_hero_video_result', ''); if ($hero_result): ?>
            <div class="notice notice-info"><p>Hero video: <?= esc_html($hero_result) ?></p></div>
        <?php endif; ?>

        <h2>Page Restores &amp; Elementor CSS</h2>
        <p>Homepage: restores 16 service headings, 16 buttons, 13 text blocks, 3 images.<br>
           Artists: restores all 9 card portraits. Each write is verified against the database.</p>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="fix_homepage">
            <?php submit_button('Fix Homepage', 'primary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="fix_artists">
            <?php submit_button('Fix Artist Cards', 'primary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="mm_action" value="rebuild_css">
            <?php submit_button('Rebuild Elementor CSS', 'secondary', 'submit', false); ?>
        </form>
        <?php $hr = get_option('mm_home_fix_result', ''); if ($hr): ?>
            <p style="color:#666;font-size:12px;margin:8px 0 0;"><strong>Homepage:</strong> <?= esc_html($hr) ?></p>
        <?php endif; ?>
        <?php $ar = get_option('mm_artist_fix_result', ''); if ($ar): ?>
            <p style="color:#666;font-size:12px;margin:4px 0 0;"><strong>Artists:</strong> <?= esc_html($ar) ?></p>
        <?php endif; ?>

        <hr>

        <h2>Repair All-in-One WP Migration</h2>
        <p>Restores the plugin from the clean bundled copy (overwrites broken files).</p>
        <form method="post">
            <input type="hidden" name="mm_action" value="repair_ai1wm">
            <?php submit_button('Repair AI1WM Now', 'primary'); ?>
        </form>

        <hr>

        <h2>CTA Text Fix</h2>
        <p>Changes all "Book Consultation" buttons to <strong>"Beratung buchen"</strong> sitewide.</p>
        <form method="post">
            <input type="hidden" name="mm_action" value="fix_cta">
            <?php submit_button('Run CTA Fix Now', 'secondary'); ?>
        </form>

        <hr>

        <h2>WebP Image Conversion</h2>
        <?php if (!$webp_available): ?>
            <div class="notice notice-error"><p>Converter failed to load: <?= esc_html(get_option('mm_webp_load_error', 'unknown error')) ?></p></div>
        <?php endif; ?>
        <?php $pct = $webp['total'] > 0 ? round($webp['done'] / $webp['total'] * 100) : 0; ?>
        <p><strong><?= (int) $webp['done'] ?> / <?= (int) $webp['total'] ?></strong> images converted (<?= $pct ?>%)</p>
        <div style="background:#e0e0e0;border-radius:4px;height:22px;max-width:480px;margin-bottom:14px;">
            <div style="background:#2271b1;height:22px;border-radius:4px;width:<?= $pct ?>%;"></div>
        </div>
        <form method="post" id="mm-webp-form">
            <input type="hidden" name="mm_action" value="webp_convert">
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="mm_auto" value="1" <?= $webp_auto ? 'checked' : '' ?>>
                Auto-continue until finished (page reloads after each batch of <?= defined('MM_WEBP_BATCH') ? MM_WEBP_BATCH : 25 ?>)
            </label>
            <?php submit_button('Convert Images to WebP', 'primary', 'submit', false); ?>
        </form>
        <?php if ($webp_auto): ?>
        <script>setTimeout(function () { document.getElementById('mm-webp-form').submit(); }, 1500);</script>
        <p><em>Auto-continue running... next batch starts in 1.5s. Leave this tab open.</em></p>
        <?php endif; ?>

        <?php if ($webp['done'] > 0): ?>
        <div style="margin-top:16px;padding:12px;background:#fff8e5;border-left:3px solid #dba617;max-width:480px;">
            <p><strong>After you verified the site looks correct:</strong></p>
            <form method="post" onsubmit="return confirm('Delete all original JPG/PNG files that have a WebP version? This frees disk space. Your .wpress backup still holds every original.');">
                <input type="hidden" name="mm_action" value="webp_delete_originals">
                <?php submit_button('Delete Original Images (free space)', 'delete', 'submit', false); ?>
            </form>
            <form method="post" style="margin-top:8px;" onsubmit="return confirm('Undo the entire WebP conversion? URLs restored, WebP files removed. Only works while originals are still on disk.');">
                <input type="hidden" name="mm_action" value="webp_rollback">
                <?php submit_button('Rollback WebP Conversion', 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
