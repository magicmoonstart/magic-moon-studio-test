<?php
/*
Plugin Name: Magic Moon Tools
Plugin URI: https://magic-moon.de
Description: Deployment and maintenance tools for Magic Moon Studio.
Version: 1.7.1
Author: Magic Moon Studio
Author URI: https://magic-moon.de
License: GPL2
*/

if (!defined('ABSPATH')) exit;

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

// Auto-replace hero video once per plugin version after deployment
add_action('admin_init', function () {
    if (get_option('mm_hero_video_done') !== '1.6.0') {
        $msg = mm_replace_hero_video();
        update_option('mm_hero_video_done', '1.6.0');
        update_option('mm_hero_video_result', $msg);
    }
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
});

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

// Auto-restore shipped files once per plugin version after deployment
add_action('admin_init', function () {
    if (get_option('mm_restore_files_done') !== '1.7.1') {
        $msg = mm_restore_missing_files();
        update_option('mm_restore_files_done', '1.7.1');
        update_option('mm_restore_files_result', $msg);
    }
});

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
