<?php
/**
 * WebP Converter — corrections/webp-conversion
 *
 * Converts all JPG/PNG attachments in wp-content/uploads to WebP,
 * updates all database references, keeps originals on disk until
 * the user explicitly deletes them. Fully reversible via log.
 *
 * Source of truth (backup scan 2026-08-27): 1,644 JPG/PNG files, 340.9 MB.
 */

if (!defined('ABSPATH')) exit;

define('MM_WEBP_QUALITY', 82);
define('MM_WEBP_BATCH', 25); // attachments per run (each has ~5-8 thumbnail files)

function mm_webp_log_path() {
    $u = wp_upload_dir();
    return trailingslashit($u['basedir']) . 'mm-webp-conversion-log.json';
}

function mm_webp_read_log() {
    $p = mm_webp_log_path();
    if (!file_exists($p)) return array('entries' => array());
    $data = json_decode(file_get_contents($p), true);
    return is_array($data) && isset($data['entries']) ? $data : array('entries' => array());
}

function mm_webp_write_log($log) {
    file_put_contents(mm_webp_log_path(), wp_json_encode($log));
}

/** Progress: total convertible attachments vs done. */
function mm_webp_status() {
    global $wpdb;
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = 'attachment' AND post_mime_type IN ('image/jpeg','image/png')"
    );
    $done = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_mm_webp_done'
         WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/jpeg','image/png')"
    );
    return array('total' => $total, 'done' => $done);
}

/** Convert one image file to .webp next to it. Returns new absolute path or false. */
function mm_webp_convert_file($abs) {
    if (!file_exists($abs)) return false;
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $dest = preg_replace('/\.(jpe?g|png)$/i', '.webp', $abs);
    if ($dest === $abs) return false;
    if (file_exists($dest)) return $dest; // already converted

    if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('image');

    if ($ext === 'png') {
        $im = @imagecreatefrompng($abs);
        if (!$im) return false;
        @imagepalettetotruecolor($im);
        imagealphablending($im, true);
        imagesavealpha($im, true);
    } else {
        $im = @imagecreatefromjpeg($abs);
        if (!$im) return false;
    }
    $ok = @imagewebp($im, $dest, MM_WEBP_QUALITY);
    imagedestroy($im);
    if (!$ok || !file_exists($dest) || filesize($dest) === 0) {
        @unlink($dest);
        return false;
    }
    return $dest;
}

/** Replace one relative upload path (e.g. 2026/02/foo.jpg -> .webp) everywhere in the DB. */
function mm_webp_replace_db($old_rel, $new_rel) {
    global $wpdb;
    // Plain form (post content, most meta)
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
        $old_rel, $new_rel, '%' . $wpdb->esc_like($old_rel) . '%'
    ));
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)
         WHERE meta_key = '_elementor_data' AND meta_value LIKE %s",
        $old_rel, $new_rel, '%' . $wpdb->esc_like($old_rel) . '%'
    ));
    // JSON-escaped form used inside Elementor data (2026\/02\/foo.jpg)
    $old_esc = str_replace('/', '\\/', $old_rel);
    $new_esc = str_replace('/', '\\/', $new_rel);
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)
         WHERE meta_key = '_elementor_data' AND meta_value LIKE %s",
        $old_esc, $new_esc, '%' . $wpdb->esc_like($old_esc) . '%'
    ));
}

/** Convert one batch of attachments. Returns status message. */
function mm_webp_convert_batch() {
    global $wpdb;
    if (!function_exists('imagewebp')) {
        return 'ERROR: PHP GD has no WebP support on this server (imagewebp missing).';
    }
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_mm_webp_done'
         WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/jpeg','image/png')
           AND m.meta_id IS NULL
         ORDER BY p.ID ASC LIMIT %d",
        MM_WEBP_BATCH
    ));
    if (empty($ids)) {
        $s = mm_webp_status();
        return "All done — {$s['done']} of {$s['total']} images converted. Verify the site, then use Delete Originals to free space.";
    }

    $log = mm_webp_read_log();
    $u = wp_upload_dir();
    $basedir = trailingslashit($u['basedir']);
    $converted_files = 0;
    $failed = array();

    foreach ($ids as $id) {
        $rel_main = get_post_meta($id, '_wp_attached_file', true);
        $meta = wp_get_attachment_metadata($id);
        $mime = get_post_mime_type($id);

        // Collect all files of this attachment: main + every thumbnail size
        $rels = array();
        if ($rel_main) $rels[] = $rel_main;
        $dir_rel = $rel_main ? trailingslashit(dirname($rel_main)) : '';
        if (is_array($meta) && !empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $size) {
                if (!empty($size['file'])) $rels[] = ($dir_rel === './' ? '' : $dir_rel) . $size['file'];
            }
        }
        $rels = array_unique($rels);

        $entry = array('id' => $id, 'files' => array(),
            'meta_backup' => array('attached_file' => $rel_main, 'metadata' => $meta, 'mime' => $mime));
        $all_ok = true;

        foreach ($rels as $rel) {
            if (!preg_match('/\.(jpe?g|png)$/i', $rel)) continue;
            $abs = $basedir . $rel;
            $dest = mm_webp_convert_file($abs);
            if ($dest === false) {
                if (file_exists($abs)) { $all_ok = false; $failed[] = $rel; }
                continue; // source missing on disk: skip silently, nothing to convert
            }
            $new_rel = preg_replace('/\.(jpe?g|png)$/i', '.webp', $rel);
            mm_webp_replace_db($rel, $new_rel);
            $entry['files'][] = array('old' => $rel, 'new' => $new_rel);
            $converted_files++;
        }

        // Update attachment record via proper WP APIs (safe serialization)
        if (!empty($entry['files'])) {
            $new_main = preg_replace('/\.(jpe?g|png)$/i', '.webp', $rel_main);
            update_post_meta($id, '_wp_attached_file', $new_main);
            if (is_array($meta)) {
                if (!empty($meta['file'])) $meta['file'] = preg_replace('/\.(jpe?g|png)$/i', '.webp', $meta['file']);
                if (!empty($meta['sizes'])) {
                    foreach ($meta['sizes'] as $k => $size) {
                        if (!empty($size['file'])) {
                            $meta['sizes'][$k]['file'] = preg_replace('/\.(jpe?g|png)$/i', '.webp', $size['file']);
                            $meta['sizes'][$k]['mime-type'] = 'image/webp';
                        }
                    }
                }
                wp_update_attachment_metadata($id, $meta);
            }
            $wpdb->update($wpdb->posts, array('post_mime_type' => 'image/webp'), array('ID' => $id));
            $log['entries'][] = $entry;
        }

        // Mark processed even if some files failed, so the batch always advances.
        // Failed file names are reported below for manual review.
        update_post_meta($id, '_mm_webp_done', $all_ok ? '1' : 'partial');
    }

    mm_webp_write_log($log);

    // Clear Elementor CSS cache so new URLs render
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_elementor_css', '_elementor_element_cache')");

    $s = mm_webp_status();
    $msg = "Batch done: {$converted_files} files converted. Progress: {$s['done']} / {$s['total']} attachments.";
    if ($failed) $msg .= ' FAILED (kept as original): ' . implode(', ', array_slice($failed, 0, 10));
    return $msg;
}

/** Delete original JPG/PNG files that have a converted WebP. Frees disk space. */
function mm_webp_delete_originals() {
    $log = mm_webp_read_log();
    if (empty($log['entries'])) return 'Nothing to delete — no conversion log found.';
    $u = wp_upload_dir();
    $basedir = trailingslashit($u['basedir']);
    $deleted = 0; $freed = 0;
    foreach ($log['entries'] as $entry) {
        foreach ($entry['files'] as $f) {
            $abs = $basedir . $f['old'];
            $webp = $basedir . $f['new'];
            if (file_exists($abs) && file_exists($webp) && filesize($webp) > 0) {
                $freed += filesize($abs);
                if (@unlink($abs)) $deleted++;
            }
        }
    }
    return sprintf('Deleted %d original files, freed %.1f MB. Originals remain safe in your .wpress backup.', $deleted, $freed / 1048576);
}

/** Rollback: restore DB references and attachment records, delete .webp files. Only valid while originals still exist. */
function mm_webp_rollback() {
    global $wpdb;
    $log = mm_webp_read_log();
    if (empty($log['entries'])) return 'Nothing to roll back — no conversion log found.';
    $u = wp_upload_dir();
    $basedir = trailingslashit($u['basedir']);
    $missing = 0;
    foreach ($log['entries'] as $entry) {
        foreach ($entry['files'] as $f) {
            if (!file_exists($basedir . $f['old'])) { $missing++; }
        }
    }
    if ($missing > 0) {
        return "ERROR: $missing original files are missing (already deleted?). Rollback requires originals on disk — restore from backup instead.";
    }
    foreach ($log['entries'] as $entry) {
        foreach ($entry['files'] as $f) {
            mm_webp_replace_db($f['new'], $f['old']); // reverse URL replacement
            @unlink($basedir . $f['new']);
        }
        $b = $entry['meta_backup'];
        update_post_meta($entry['id'], '_wp_attached_file', $b['attached_file']);
        if (is_array($b['metadata'])) wp_update_attachment_metadata($entry['id'], $b['metadata']);
        $wpdb->update($wpdb->posts, array('post_mime_type' => $b['mime']), array('ID' => $entry['id']));
        delete_post_meta($entry['id'], '_mm_webp_done');
    }
    @unlink(mm_webp_log_path());
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_elementor_css', '_elementor_element_cache')");
    return 'Rollback complete: all URLs restored, WebP files removed, attachment records restored.';
}
