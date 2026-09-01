<?php
/**
 * WebP generator — corrections/performance
 *
 * Creates a .webp beside every JPG/PNG in the media library, including each
 * registered thumbnail size. It writes FILES ONLY: no attachment record, no
 * post meta and no page data is touched, so nothing can break and removing the
 * .webp files (or deactivating the plugin) restores the previous behaviour
 * exactly. Delivery is handled separately by mm-performance.php, which swaps
 * the extension in the output when a .webp exists and the browser accepts it.
 *
 * This replaces the earlier converter's approach of rewriting every URL in the
 * database — same bandwidth saving, none of the risk.
 *
 * Button-driven and batched: image encoding is CPU-heavy and must never run
 * inside admin_init.
 */

if (!defined('ABSPATH')) exit;

if (!defined('MM_WEBP_Q'))     define('MM_WEBP_Q', 82);
if (!defined('MM_WEBP_BATCH2')) define('MM_WEBP_BATCH2', 30);   // attachments per click

/** Encode one file to .webp beside itself. Returns bytes saved, or -1 on failure. */
function mm_webp_make($abs) {
    if (!file_exists($abs)) return -1;
    $dest = preg_replace('/\.(jpe?g|png)$/i', '.webp', $abs);
    if ($dest === $abs) return -1;
    if (file_exists($dest)) return 0;                 // already done

    if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('image');

    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    if ($ext === 'png') {
        $im = @imagecreatefrompng($abs);
        if (!$im) return -1;
        @imagepalettetotruecolor($im);
        imagealphablending($im, true);
        imagesavealpha($im, true);
    } else {
        $im = @imagecreatefromjpeg($abs);
        if (!$im) return -1;
    }

    $ok = @imagewebp($im, $dest, MM_WEBP_Q);
    imagedestroy($im);

    if (!$ok || !file_exists($dest) || filesize($dest) === 0) {
        @unlink($dest);
        return -1;
    }
    // If WebP came out larger (rare, small graphics), drop it and keep the original
    if (filesize($dest) >= filesize($abs)) {
        @unlink($dest);
        return 0;
    }
    return filesize($abs) - filesize($dest);
}

/** Progress across the whole media library. */
function mm_webp_progress() {
    global $wpdb;
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type='attachment' AND post_mime_type IN ('image/jpeg','image/png')"
    );
    $done = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_mm_webp_made'
         WHERE p.post_type='attachment' AND p.post_mime_type IN ('image/jpeg','image/png')"
    );
    return array('total' => $total, 'done' => $done);
}

/** Convert one batch. Safe to click repeatedly. */
function mm_webp_generate_batch() {
    global $wpdb;

    if (!function_exists('imagewebp')) {
        return 'ERROR: this server\'s PHP GD has no WebP support (imagewebp missing).';
    }

    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_mm_webp_made'
         WHERE p.post_type='attachment'
           AND p.post_mime_type IN ('image/jpeg','image/png')
           AND m.meta_id IS NULL
         ORDER BY p.ID ASC LIMIT %d",
        MM_WEBP_BATCH2
    ));

    if (empty($ids)) {
        $p = mm_webp_progress();
        $saved = (int) get_option('mm_webp_saved_bytes', 0);
        return sprintf(
            'All done — %d of %d images have WebP versions. Total saved: %s MB. Originals untouched.',
            $p['done'], $p['total'], number_format($saved / 1048576, 1)
        );
    }

    $u = wp_upload_dir();
    $base = trailingslashit($u['basedir']);
    $files = 0; $saved = 0; $failed = 0;

    foreach ($ids as $id) {
        $main = get_post_meta($id, '_wp_attached_file', true);
        $meta = wp_get_attachment_metadata($id);

        $rels = array();
        if ($main) $rels[] = $main;
        $dir = $main ? trailingslashit(dirname($main)) : '';
        if ($dir === './') $dir = '';
        if (is_array($meta) && !empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $s) {
                if (!empty($s['file'])) $rels[] = $dir . $s['file'];
            }
        }

        foreach (array_unique($rels) as $rel) {
            if (!preg_match('/\.(jpe?g|png)$/i', $rel)) continue;
            $r = mm_webp_make($base . $rel);
            if ($r > 0) { $files++; $saved += $r; }
            elseif ($r < 0) { $failed++; }
        }
        update_post_meta($id, '_mm_webp_made', '1');
    }

    update_option('mm_webp_saved_bytes', (int) get_option('mm_webp_saved_bytes', 0) + $saved);

    $p = mm_webp_progress();
    return sprintf(
        'Converted %d files this batch, saving %s MB (%d could not be read). Progress: %d / %d attachments — click again to continue.',
        $files, number_format($saved / 1048576, 2), $failed, $p['done'], $p['total']
    );
}

/** Delete every generated .webp (full rollback of the WebP layer). */
function mm_webp_remove_all() {
    global $wpdb;
    $u = wp_upload_dir();
    $base = trailingslashit($u['basedir']);
    $removed = 0;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        if (strtolower($file->getExtension()) !== 'webp') continue;
        // only remove ones we generated (an original of the same name exists)
        $stem = preg_replace('/\.webp$/i', '', $file->getPathname());
        if (file_exists($stem . '.jpg') || file_exists($stem . '.jpeg') || file_exists($stem . '.png')) {
            if (@unlink($file->getPathname())) $removed++;
        }
    }
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_mm_webp_made'");
    update_option('mm_webp_saved_bytes', 0);
    return "Removed $removed generated .webp files. Originals were never modified, so the site is back to JPEG/PNG delivery.";
}
