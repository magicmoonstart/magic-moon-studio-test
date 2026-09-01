<?php
/*
Plugin Name: Magic Moon Tools
Plugin URI: https://magic-moon.de
Description: Deployment and maintenance tools for Magic Moon Studio.
Version: 6.3.0
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
 * Apply the German CTA replacements to ONE post only.
 *
 * mm_fix_cta_german() runs 24 replacement pairs across the whole postmeta and
 * posts tables — roughly 48 full-table queries. Calling that from every page
 * restore meant a single admin_init could fire it four times and risk hitting
 * PHP's execution limit part-way through, leaving later fixes unrun. This
 * scoped version touches only the page that was just written.
 */
function mm_fix_cta_for_post($post_id) {
    $map_file = __DIR__ . '/corrections/cta-german-fix/replacements.php';
    if (!file_exists($map_file)) return 0;
    $pairs = include $map_file;
    if (!is_array($pairs) || empty($pairs)) return 0;

    $data = get_post_meta($post_id, '_elementor_data', true);
    if (!is_string($data) || $data === '') return 0;

    $changed = 0;
    foreach ($pairs as $from => $to) {
        if (strpos($data, $from) !== false) {
            $data = str_replace($from, $to, $data);
            $changed++;
        }
    }
    if ($changed) {
        update_post_meta($post_id, '_elementor_data', wp_slash($data));
    }
    return $changed;
}

/**
 * Apply the editorial text corrections in corrections/text-fixes.
 *
 * Sitewide find-and-replace across Elementor data and post content, for copy
 * changes requested after the backup was taken. Reports how many rows each
 * replacement touched so a change that matched nothing is visible rather than
 * silently doing nothing.
 */
function mm_apply_text_fixes() {
    global $wpdb;
    $map_file = __DIR__ . '/corrections/text-fixes/replacements.php';
    if (!file_exists($map_file)) {
        return 'ERROR: corrections/text-fixes/replacements.php not found - deploy latest version first.';
    }
    $pairs = include $map_file;
    if (!is_array($pairs) || empty($pairs)) {
        return 'ERROR: text-fix map is empty or invalid.';
    }

    $run = function ($from, $to) use ($wpdb) {
        $n  = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)
             WHERE meta_key = '_elementor_data' AND meta_value LIKE %s",
            $from, $to, '%' . $wpdb->esc_like($from) . '%'
        ));
        $n += (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)
             WHERE post_content LIKE %s",
            $from, $to, '%' . $wpdb->esc_like($from) . '%'
        ));
        return $n;
    };

    // The same rule has to be attempted in every encoding the database might
    // be holding it in:
    //   1) as written .......... post_content, and ASCII-only Elementor data
    //   2) fully JSON-escaped .. Elementor's own output: "ü", "<\/p>", "\""
    //   3) half-escaped ........ after corrections/json-normalize has run:
    //                            unicode and slashes are plain again, but a
    //                            double quote inside HTML attributes is still
    //                            stored as \" because JSON requires it.
    // Rules that contain HTML attributes only match in forms 2 and 3, and
    // which of the two applies depends on whether the normaliser has been run
    // yet — so all three are always attempted.
    $variants = function ($s) {
        $out = array($s);
        $full = trim(wp_json_encode($s), '"');
        $half = trim(json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '"');
        foreach (array($full, $half) as $v) {
            if (is_string($v) && $v !== '' && !in_array($v, $out, true)) $out[] = $v;
        }
        return $out;
    };

    $report = array();
    foreach ($pairs as $from => $to) {
        $fromForms = $variants($from);
        $n = 0;
        foreach ($fromForms as $i => $f) {
            // encode the replacement the same way as the pattern it answers
            if ($to === '') {
                $t = '';
            } elseif ($i === 0) {
                $t = $to;
            } elseif ($i === 1) {
                $t = trim(wp_json_encode($to), '"');
            } else {
                $t = trim(json_encode($to, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '"');
            }
            $n += $run($f, $t);
        }
        $report[] = mb_substr($from, 0, 44) . ($n ? " -> $n" : ' -> NO MATCH');
    }

    // Verify: count anything the rules were supposed to remove that is still
    // present, in any of its encodings. Reported per rule so a partial match
    // is visible instead of being hidden behind an overall "success".
    $leftover = array();
    foreach ($pairs as $from => $to) {
        $still = 0;
        foreach ($variants($from) as $f) {
            $still += (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta}
                  WHERE meta_key='_elementor_data' AND meta_value LIKE %s",
                '%' . $wpdb->esc_like($f) . '%'
            ));
            $still += (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s",
                '%' . $wpdb->esc_like($f) . '%'
            ));
        }
        if ($still > 0) {
            $leftover[] = mb_substr($from, 0, 34) . ' x' . $still;
        }
    }

    mm_force_elementor_css_rebuild();

    return 'SUCCESS: text fixes applied. ' . implode(' | ', $report)
         . ' || STILL PRESENT: ' . ($leftover ? implode(', ', $leftover) : 'nothing — all rules fully applied');
}

add_action('admin_init', function () {
    mm_run_once('mm_text_fixes_done', '6.3.0', 'mm_apply_text_fixes', 'mm_text_fixes_result');
});

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
    $home_en = get_page_by_path('home-en');
    $blogs   = get_page_by_path('blogs');
    $pages = array('homepage' => $home_id);
    if ($artist)  $pages['artists']  = $artist->ID;
    if ($home_en) $pages['home_en']  = $home_en->ID;
    if ($blogs)   $pages['blogs']    = $blogs->ID;

    $report = array('plugin_version' => '5.4.0', 'pages' => array());
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
        'home_done'      => get_option('mm_home_fix_done', '(never)'),
        'home_result'    => get_option('mm_home_fix_result', '(none)'),
        'home_en_done'   => get_option('mm_home_en_fix_done', '(never)'),
        'home_en_result' => get_option('mm_home_en_fix_result', '(none)'),
        'blogs_done'     => get_option('mm_blogs_fix_done', '(never)'),
        'blogs_result'   => get_option('mm_blogs_fix_result', '(none)'),
        'artist_done'    => get_option('mm_artist_fix_done', '(never)'),
        'artist_result'  => get_option('mm_artist_fix_result', '(none)'),
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
    mm_fix_cta_for_post($page->ID);
    return 'SUCCESS: artists page restored — ' . $w['msg']
         . '; all 9 card portraits set. Deleted ' . $removed . ' stale CSS files. German CTA re-applied.';
}

// Auto-run artist page restore — retries until it genuinely succeeds
add_action('admin_init', function () {
    mm_run_once('mm_artist_fix_done', '4.0.0', 'mm_fix_artist_images', 'mm_artist_fix_result');
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
    // Re-apply German CTA texts to this page only (cheap, scoped)
    mm_fix_cta_for_post($home_id);
    return 'SUCCESS: homepage (post ' . $home_id . ') restored — ' . $w['msg']
         . '. All 16 service cards now use free core containers instead of the 8 Elementor Pro '
         . 'nested-carousel widgets that rendered empty. German CTA re-applied.';
}

// Auto-run homepage restore — retries until it genuinely succeeds
add_action('admin_init', function () {
    mm_run_once('mm_home_fix_done', '4.1.0', 'mm_fix_homepage', 'mm_home_fix_result');
});

/**
 * Restore the ENGLISH homepage (post 2616). Same defects as the German one:
 * 1 Pro "slides" widget + 8 Pro "nested-carousel" widgets rendering empty.
 * The shipped data has both already converted to free equivalents.
 */
function mm_fix_homepage_en() {
    $file = __DIR__ . '/corrections/homepage-en-fix/elementor-data-home-en-post2616.json';
    if (!file_exists($file)) {
        return 'ERROR: English homepage correction file not found - deploy latest version first.';
    }
    $post_id = 2616;
    if (!get_post($post_id)) {
        // fall back to looking the page up by slug
        $p = get_page_by_path('home-en');
        if (!$p) return 'ERROR: English homepage (2616 / home-en) not found.';
        $post_id = $p->ID;
    }
    $w = mm_write_elementor_data($post_id, file_get_contents($file), 'English homepage');
    if (!$w['ok']) return $w['msg'];

    mm_force_elementor_css_rebuild();
    return 'SUCCESS: English homepage (post ' . $post_id . ') restored — ' . $w['msg']
         . '. 8 Pro carousels converted to free grids, Pro slides widget replaced with the built-in hero slider.';
}

add_action('admin_init', function () {
    mm_run_once('mm_home_en_fix_done', '4.1.0', 'mm_fix_homepage_en', 'mm_home_en_fix_result');
});

/**
 * Rebuild the BLOGS page (post 21). The reference uses the Pro "archive-posts"
 * widget; this replaces it with a heading plus the free [mm_blog_archive]
 * shortcode registered below, laid out 2-up like the reference.
 */
function mm_fix_blogs() {
    $file = __DIR__ . '/corrections/blogs-fix/elementor-data-blogs-post21.json';
    if (!file_exists($file)) {
        return 'ERROR: blogs correction file not found - deploy latest version first.';
    }
    $page = get_page_by_path('blogs');
    $post_id = $page ? $page->ID : 21;
    if (!get_post($post_id)) return 'ERROR: blogs page not found.';

    $w = mm_write_elementor_data($post_id, file_get_contents($file), 'blogs page');
    if (!$w['ok']) return $w['msg'];

    // Elementor only renders a page it considers "built with Elementor"
    if (get_post_meta($post_id, '_elementor_edit_mode', true) !== 'builder') {
        update_post_meta($post_id, '_elementor_edit_mode', 'builder');
    }

    /*
     * THE REASON THE FIRST ATTEMPT COULD NEVER WORK
     * This page was assigned as WordPress's "Posts page" (Settings > Reading).
     * For that page WordPress ignores the page's own content completely and
     * renders the theme's post archive instead — body class "blog", no
     * elementor-<id> wrapper. So no amount of Elementor data would ever show.
     * Releasing the assignment lets the page render its own content; the post
     * listing itself is provided by [mm_blog_archive]. The previous value is
     * stored so this can be put back.
     */
    $note = '';
    if ((int) get_option('page_for_posts') === (int) $post_id) {
        update_option('mm_prev_page_for_posts', (int) $post_id);
        update_option('page_for_posts', 0);
        $note = ' Released this page from Settings > Reading > "Posts page" (old value saved as mm_prev_page_for_posts) so WordPress renders its content instead of the theme archive.';
    }

    mm_force_elementor_css_rebuild();
    return 'SUCCESS: blogs page (post ' . $post_id . ') rebuilt — ' . $w['msg']
         . '. Pro archive-posts replaced with the free [mm_blog_archive] shortcode.' . $note;
}

add_action('admin_init', function () {
    mm_run_once('mm_blogs_fix_done', '4.1.0', 'mm_fix_blogs', 'mm_blogs_fix_result');
});

/**
 * Rebuild attachment metadata so responsive srcset variants come back.
 *
 * On pages like ueber-uns / job the full-size images all render, but the
 * -150x150 / -300x298 / -1024x506 variants were absent from srcset: the
 * thumbnail FILES exist on disk (restored earlier) while the attachment
 * metadata has no 'sizes' entries for them, so WordPress cannot advertise
 * them. This regenerates the metadata for such attachments.
 *
 * Batched and button-driven only — regeneration is CPU-heavy and must never
 * run inside admin_init.
 */
function mm_rebuild_image_metadata($limit = 40) {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_mm_meta_rebuilt'
         WHERE p.post_type = 'attachment'
           AND p.post_mime_type IN ('image/jpeg','image/png','image/webp')
           AND d.meta_id IS NULL
         ORDER BY p.ID ASC LIMIT %d",
        (int) $limit
    ));

    if (empty($ids)) {
        return 'All image metadata already rebuilt.';
    }

    $u = wp_upload_dir();
    $base = trailingslashit($u['basedir']);
    $fixed = 0; $skipped = 0;

    foreach ($ids as $id) {
        $rel = get_post_meta($id, '_wp_attached_file', true);
        $abs = $base . $rel;
        if (!$rel || !file_exists($abs)) { update_post_meta($id, '_mm_meta_rebuilt', 'missing'); $skipped++; continue; }

        $meta = wp_generate_attachment_metadata($id, $abs);
        if (is_array($meta) && !empty($meta['sizes'])) {
            wp_update_attachment_metadata($id, $meta);
            $fixed++;
        } else {
            $skipped++;
        }
        update_post_meta($id, '_mm_meta_rebuilt', '1');
    }

    $remaining = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_mm_meta_rebuilt'
         WHERE p.post_type = 'attachment'
           AND p.post_mime_type IN ('image/jpeg','image/png','image/webp')
           AND d.meta_id IS NULL"
    );

    return "Rebuilt metadata for $fixed images ($skipped skipped). $remaining still to go — click again to continue.";
}

/**
 * Restore the blog posts' featured images.
 *
 * The migration lost these: every post came across with featured_media = 0 and
 * the three attachment RECORDS were gone entirely (REST returned
 * rest_post_invalid_id), even though the image FILES were still on disk. The
 * backup contains exactly four _thumbnail_id rows, so this is the whole scope.
 * Attachment ids and file paths below are taken verbatim from the backup.
 */
function mm_fix_post_thumbnails() {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $u    = wp_upload_dir();
    $base = trailingslashit($u['basedir']);

    // backup: _wp_attached_file rows for the three attachments
    $files = array(
        371  => '2026/02/cards-services.png',
        374  => '2026/02/cards-services-2.png',
        5837 => '2026/02/Rectangle-41.png',
    );
    // backup: the only four _thumbnail_id rows (post id => attachment id)
    $thumbs = array(370 => 5837, 373 => 374, 2709 => 374, 2711 => 371);

    $resolved = array();   // original attachment id => id actually usable here
    $created = 0; $reused = 0; $problems = array();

    foreach ($files as $aid => $rel) {
        $abs = $base . $rel;
        if (!file_exists($abs)) { $problems[] = "file missing: $rel"; continue; }

        // already present under its original id?
        if (get_post($aid) && get_post_type($aid) === 'attachment') {
            $resolved[$aid] = $aid; $reused++; continue;
        }
        // or present under a different id for the same file?
        $existing = get_posts(array(
            'post_type' => 'attachment', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => array(array('key' => '_wp_attached_file', 'value' => $rel)),
        ));
        if (!empty($existing)) { $resolved[$aid] = (int) $existing[0]; $reused++; continue; }

        $type = wp_check_filetype(basename($abs));
        $new  = wp_insert_attachment(array(
            'import_id'      => $aid,                       // keep the backup's id where possible
            'post_mime_type' => $type['type'] ? $type['type'] : 'image/png',
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($abs)),
            'post_status'    => 'inherit',
            'guid'           => trailingslashit($u['baseurl']) . $rel,
        ), $abs);

        if (is_wp_error($new) || !$new) { $problems[] = "could not create attachment for $rel"; continue; }

        update_post_meta($new, '_wp_attached_file', $rel);
        $meta = wp_generate_attachment_metadata($new, $abs);
        if (is_array($meta)) { wp_update_attachment_metadata($new, $meta); }
        $resolved[$aid] = (int) $new;
        $created++;
    }

    $set = 0;
    foreach ($thumbs as $post_id => $aid) {
        if (!get_post($post_id)) { $problems[] = "post $post_id not found"; continue; }
        if (empty($resolved[$aid])) { $problems[] = "no attachment available for post $post_id"; continue; }
        update_post_meta($post_id, '_thumbnail_id', $resolved[$aid]);
        $set++;
    }

    $msg = "created $created attachment(s), reused $reused, featured image set on $set of " . count($thumbs) . " posts";
    if ($problems) { $msg .= '. Issues: ' . implode('; ', array_slice($problems, 0, 4)); }
    return ($set > 0 ? 'SUCCESS: ' : 'ERROR: ') . $msg;
}

add_action('admin_init', function () {
    mm_run_once('mm_thumbs_fix_done', '4.2.0', 'mm_fix_post_thumbnails', 'mm_thumbs_fix_result');
});

/**
 * [mm_blog_archive columns="2"] — free replacement for Elementor Pro's
 * archive-posts widget. Cards with featured image, title, excerpt and link.
 */
function mm_blog_archive_shortcode($atts) {
    $atts = shortcode_atts(array('columns' => '2', 'per_page' => '12'), $atts, 'mm_blog_archive');

    $q = new WP_Query(array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => (int) $atts['per_page'],
        'ignore_sticky_posts' => true,
    ));

    if (!$q->have_posts()) {
        wp_reset_postdata();
        return '<p class="mm-blog__empty">' . esc_html__('No posts yet.', 'default') . '</p>';
    }

    $cols = max(1, min(4, (int) $atts['columns']));
    $out  = '<div class="mm-blog" style="grid-template-columns:repeat(' . $cols . ',minmax(0,1fr))">';

    while ($q->have_posts()) {
        $q->the_post();
        $link  = get_permalink();
        $title = get_the_title();
        $thumb = get_the_post_thumbnail_url(get_the_ID(), 'large');
        $excerpt = wp_trim_words(get_the_excerpt(), 28, '…');

        $out .= '<article class="mm-blog__card">';
        if ($thumb) {
            $out .= '<a class="mm-blog__thumb" href="' . esc_url($link) . '"'
                  . ' style="background-image:url(' . esc_url($thumb) . ')"'
                  . ' aria-label="' . esc_attr($title) . '"></a>';
        }
        $out .= '<div class="mm-blog__body">';
        $out .= '<h3 class="mm-blog__title"><a href="' . esc_url($link) . '">' . esc_html($title) . '</a></h3>';
        if ($excerpt) {
            $out .= '<p class="mm-blog__excerpt">' . esc_html($excerpt) . '</p>';
        }
        $out .= '<a class="mm-blog__more" href="' . esc_url($link) . '">' . esc_html__('Read more', 'default') . '</a>';
        $out .= '</div></article>';
    }

    $out .= '</div>';
    wp_reset_postdata();
    return $out;
}
add_shortcode('mm_blog_archive', 'mm_blog_archive_shortcode');

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
        // Hero slider + card sliders + blog archive (free Pro replacements)
        'mm-slider'            => 'corrections/slider/mm-slider.css',
        // Consultation popup (free replacement for Pro Popup Builder)
        'mm-popup'             => 'corrections/popup/mm-popup.css',
    );
    foreach ($sheets as $handle => $rel) {
        $path = __DIR__ . '/' . $rel;
        if (file_exists($path)) {
            wp_enqueue_style($handle, plugins_url($rel, __FILE__), array(), (string) filemtime($path));
        }
    }

    // Slider behaviour: hero autoplay/arrows/dots + card sliding.
    // Deferred so it runs after Elementor has rendered the containers.
    foreach (array('mm-slider' => 'corrections/slider/mm-slider.js',
                   'mm-popup'  => 'corrections/popup/mm-popup.js') as $h => $rel) {
        $js = __DIR__ . '/' . $rel;
        if (file_exists($js)) {
            wp_enqueue_script($h, plugins_url($rel, __FILE__), array(), (string) filemtime($js), true);
        }
    }
}, 99);

/**
 * Copy the shipped .webp assets into uploads.
 * These are the three card backgrounds that made up the homepage's entire
 * 1,633 KB image payload; as WebP they total 142 KB. homepage-cards.css points
 * at them. Never overwrites an existing file.
 */
function mm_install_webp_assets() {
    $src_root = __DIR__ . '/corrections/webp-assets';
    if (!is_dir($src_root)) return 'No WebP assets shipped.';
    $u = wp_upload_dir();
    $base = trailingslashit($u['basedir']);
    $copied = 0; $already = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src_root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $rel  = str_replace('\\', '/', substr($file->getPathname(), strlen($src_root) + 1));
        $dest = $base . $rel;
        if (file_exists($dest)) { $already++; continue; }
        wp_mkdir_p(dirname($dest));
        if (copy($file->getPathname(), $dest)) $copied++;
    }
    return "SUCCESS: installed $copied WebP asset(s), $already already present.";
}

add_action('admin_init', function () {
    mm_run_once('mm_webp_assets_done', '5.0.0', 'mm_install_webp_assets', 'mm_webp_assets_result');
});

/**
 * Consultation popup — free replacement for Elementor Pro's Popup Builder.
 *
 * The reference renders elementor_library post 2136 as an "elementor-location-popup".
 * Popup Builder is a Pro feature, so on this install the template exists but is
 * never output — which is why the circular consultation widget was missing.
 *
 * Reference settings reproduced here: page_load trigger, 3s delay, fadeIn 1.2s,
 * front page only.
 */
function mm_render_consult_popup() {
    if (!is_front_page() && !is_home()) return;

    $u    = wp_upload_dir();
    $base = trailingslashit($u['baseurl']);
    $dir  = trailingslashit($u['basedir']);

    // Prefer .webp when it exists and the browser takes it (see mm-performance.php)
    $pick = function ($rel) use ($base, $dir) {
        $webp = preg_replace('/\.png$/i', '.webp', $rel);
        if (function_exists('mm_perf_accepts_webp') && mm_perf_accepts_webp() && file_exists($dir . $webp)) {
            return $base . $webp;
        }
        return file_exists($dir . $rel) ? $base . $rel : '';
    };

    $a = $pick('2026/02/Ellipse-5.png');
    $b = $pick('2026/02/Ellipse-6.png');
    if (!$a && !$b) return;   // nothing to show

    ?>
<div class="mm-consult" data-mm-delay="3000" role="dialog" aria-label="Consultation">
    <button class="mm-consult__close" type="button" aria-label="Close">&times;</button>
    <div class="mm-consult__photos">
        <?php if ($a): ?><img src="<?= esc_url($a) ?>" width="158" height="158" alt="" loading="lazy" decoding="async"><?php endif; ?>
        <?php if ($b): ?><img src="<?= esc_url($b) ?>" width="158" height="158" alt="" loading="lazy" decoding="async"><?php endif; ?>
    </div>
    <a class="mm-consult__btn" href="/contact/">consultation Now</a>
    <p class="mm-consult__text">Get professional consultation &mdash; we&rsquo;re ready to assist you.</p>
</div>
    <?php
}
add_action('wp_footer', 'mm_render_consult_popup', 20);

// Core Web Vitals layer: WebP delivery, LCP preload, lazy/async images,
// emoji removal, font-display swap. Loaded defensively.
try {
    require_once __DIR__ . '/corrections/performance/mm-performance.php';
    require_once __DIR__ . '/corrections/performance/webp-generator.php';
    // Rewrites Elementor data as plain UTF-8 so content edits match what you type
    require_once __DIR__ . '/corrections/json-normalize/normalizer.php';
    // Grouping menu labels ("Piercing") must not navigate anywhere
    require_once __DIR__ . '/corrections/menu-fix/menu-fix.php';
    // Copy a finished page layout onto its untranslated counterpart
    require_once __DIR__ . '/corrections/page-clone/page-clone.php';
    // Then put German copy on the German page
    require_once __DIR__ . '/corrections/translate-de/translate-de.php';
    // Delete whole misplaced sections on specific pages
    require_once __DIR__ . '/corrections/remove-blocks/remove-blocks.php';
    // Pages that exist but were never added to the menu
    require_once __DIR__ . '/corrections/menu-items/menu-items.php';
} catch (\Throwable $e) {
    update_option('mm_perf_load_error', $e->getMessage());
}

add_action('admin_init', function () {
    mm_run_once('mm_menu_static_done', '5.5.0', 'mm_fix_static_menu_items', 'mm_menu_static_result');
    // Order matters: the clone brings the layout and the photographs across,
    // then the translation replaces the English text it arrives with.
    mm_run_once('mm_page_clone_done', '5.7.0', 'mm_clone_page_layouts', 'mm_page_clone_result');
    mm_run_once('mm_translate_de_done', '6.2.0', 'mm_apply_de_translations', 'mm_translate_de_result');
    mm_run_once('mm_remove_blocks_done', '5.9.0', 'mm_remove_blocks', 'mm_remove_blocks_result');
    mm_run_once('mm_menu_items_done', '6.0.0', 'mm_add_missing_menu_items', 'mm_menu_items_result');
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

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'fix_homepage_en') {
        $message = mm_fix_homepage_en();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'fix_blogs') {
        $message = mm_fix_blogs();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'rebuild_img_meta') {
        $message = mm_rebuild_image_metadata(40);
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'fix_thumbs') {
        $message = mm_fix_post_thumbnails();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'text_fixes') {
        $message = mm_apply_text_fixes();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'json_normalize' && function_exists('mm_normalize_elementor_json')) {
        $message = mm_normalize_elementor_json();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'menu_static' && function_exists('mm_fix_static_menu_items')) {
        $message = mm_fix_static_menu_items();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'page_clone' && function_exists('mm_clone_page_layouts')) {
        $message = mm_clone_page_layouts();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'translate_de' && function_exists('mm_apply_de_translations')) {
        $message = mm_apply_de_translations();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'remove_blocks' && function_exists('mm_remove_blocks')) {
        $message = mm_remove_blocks();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'menu_items' && function_exists('mm_add_missing_menu_items')) {
        $message = mm_add_missing_menu_items();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'webp_generate' && function_exists('mm_webp_generate_batch')) {
        $message = mm_webp_generate_batch();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'webp_remove' && function_exists('mm_webp_remove_all')) {
        $message = mm_webp_remove_all();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'fix_all') {
        $parts = array(
            'Homepage DE: ' . mm_fix_homepage(),
            'Homepage EN: ' . mm_fix_homepage_en(),
            'Blogs: '       . mm_fix_blogs(),
            'Artists: '     . mm_fix_artist_images(),
            'Thumbs: '      . mm_fix_post_thumbnails(),
            'Text: '        . mm_apply_text_fixes(),
        );
        $message = implode(' || ', $parts);
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
            <input type="hidden" name="mm_action" value="fix_all">
            <?php submit_button('Fix Everything', 'primary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="fix_homepage">
            <?php submit_button('Fix Homepage (DE)', 'secondary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="fix_homepage_en">
            <?php submit_button('Fix Homepage (EN)', 'secondary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="fix_blogs">
            <?php submit_button('Fix Blogs', 'secondary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="fix_artists">
            <?php submit_button('Fix Artist Cards', 'primary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="rebuild_css">
            <?php submit_button('Rebuild Elementor CSS', 'secondary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="rebuild_img_meta">
            <?php submit_button('Rebuild Image Metadata (srcset)', 'secondary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="fix_thumbs">
            <?php submit_button('Restore Blog Featured Images', 'secondary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="mm_action" value="text_fixes">
            <?php submit_button('Apply Text Corrections', 'secondary', 'submit', false); ?>
        </form>
        <?php $hr = get_option('mm_home_fix_result', ''); if ($hr): ?>
            <p style="color:#666;font-size:12px;margin:8px 0 0;"><strong>Homepage:</strong> <?= esc_html($hr) ?></p>
        <?php endif; ?>
        <?php $ar = get_option('mm_artist_fix_result', ''); if ($ar): ?>
            <p style="color:#666;font-size:12px;margin:4px 0 0;"><strong>Artists:</strong> <?= esc_html($ar) ?></p>
        <?php endif; ?>

        <hr>

        <h2>Normalise Elementor Data (do this before text edits)</h2>
        <?php $jn = function_exists('mm_json_norm_progress') ? mm_json_norm_progress() : array('total'=>0,'done'=>0);
              $jpct = $jn['total'] ? round($jn['done']/$jn['total']*100) : 0;
              $jrem = function_exists('mm_json_norm_remaining') ? mm_json_norm_remaining() : -1; ?>
        <p>Elementor saves text as <code>Unterst\u00fctzung</code> and URLs as <code>https:\/\/…</code>.
           Byte-for-byte search &amp; replace therefore never matches what you actually type &mdash; which is why the
           German heading did not change while the English one did. This rewrites every row as plain UTF-8.
           Elementor reads both forms identically, so nothing on the site changes visually.<br>
           <strong><?= (int)$jn['done'] ?> / <?= (int)$jn['total'] ?></strong> rows processed (<?= $jpct ?>%)
           <?php if ($jrem >= 0): ?>&middot; rows still holding escaped sequences: <strong><?= $jrem ?></strong><?php endif; ?></p>
        <div style="background:#e0e0e0;border-radius:4px;height:20px;max-width:460px;margin-bottom:12px;">
            <div style="background:#2271b1;height:20px;border-radius:4px;width:<?= $jpct ?>%;"></div>
        </div>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="mm_action" value="json_normalize">
            <?php submit_button('Normalise Elementor Data', 'primary', 'submit', false); ?>
        </form>
        <p style="color:#666;font-size:12px;">Each click works for about 18 seconds and resumes where it stopped. Keep clicking until it says finished.</p>

        <hr>

        <h2>Menu grouping labels</h2>
        <p style="max-width:760px;">Labels that only exist to group sub-categories must not navigate anywhere.
           They keep their position, their text and their dropdown &mdash; they just stop being links,
           exactly like <code>Dienstleistungen</code>, <code>TATTOO DESIGN</code> and <code>SERVICES</code> already do.</p>
        <?php if (function_exists('mm_menu_static_state')): $ms = mm_menu_static_state(); ?>
        <table class="widefat striped" style="max-width:640px;margin-bottom:12px;">
            <thead><tr><th>ID</th><th>Label</th><th>URL</th><th>State</th></tr></thead>
            <tbody>
            <?php if (!$ms): ?>
                <tr><td colspan="4">No matching menu items found.</td></tr>
            <?php else: foreach ($ms as $row): ?>
                <tr>
                    <td><?= (int) $row['id'] ?></td>
                    <td><?= esc_html($row['label']) ?></td>
                    <td><code><?= esc_html($row['url'] === '' ? '(empty)' : $row['url']) ?></code></td>
                    <td><?= $row['static']
                            ? '<span style="color:#1a7f37;font-weight:600;">static</span>'
                            : '<span style="color:#b32d2e;font-weight:600;">still a link</span>' ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="mm_action" value="menu_static">
            <?php submit_button('Make Grouping Labels Static', 'secondary', 'submit', false); ?>
        </form>
        <p style="color:#666;font-size:12px;">Runs automatically once on deploy. This button re-applies it if a later menu edit reintroduces a URL.</p>

        <hr>

        <h2>Page layout clones</h2>
        <p style="max-width:760px;">Copies a finished page's Elementor layout onto a counterpart that never received
           its own content. The background photographs live inside that layout, so copying it reproduces
           the design and the images together. Titles, slugs, menus and language links are not touched,
           and the previous layout is saved to <code>uploads/mm-rollback-&lt;id&gt;.json</code> first.</p>
        <?php if (function_exists('mm_page_clone_state')): foreach (mm_page_clone_state() as $c): ?>
        <table class="widefat striped" style="max-width:860px;margin-bottom:12px;">
            <tbody>
                <tr><th style="width:150px;">Clone</th><td><?= esc_html($c['label']) ?></td></tr>
                <tr><th>Source <?= (int) $c['source'] ?></th>
                    <td><?= (int) $c['srcBytes'] ?> bytes &middot; images:
                        <code><?= esc_html($c['srcImgs'] ? implode(', ', $c['srcImgs']) : 'none') ?></code></td></tr>
                <tr><th>Target <?= (int) $c['target'] ?></th>
                    <td><?= (int) $c['tgtBytes'] ?> bytes &middot; images:
                        <code><?= esc_html($c['tgtImgs'] ? implode(', ', $c['tgtImgs']) : 'none') ?></code></td></tr>
                <tr><th>State</th><td><?= $c['match']
                        ? '<span style="color:#1a7f37;font-weight:600;">identical — clone applied</span>'
                        : '<span style="color:#b32d2e;font-weight:600;">different — not yet cloned</span>' ?></td></tr>
            </tbody>
        </table>
        <?php endforeach; endif; ?>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="mm_action" value="page_clone">
            <?php submit_button('Copy Page Layouts', 'secondary', 'submit', false); ?>
        </form>
        <p style="color:#666;font-size:12px;">Runs automatically once on deploy. Re-run only if the source page changes and you want the copy refreshed.</p>

        <h2>German page copy</h2>
        <p style="max-width:760px;">A cloned layout arrives with the source language's text. This writes the German copy over it,
           addressing each element by its Elementor id so nothing depends on matching English strings.
           Wording follows the studio's already-translated <code>/ohrlaeppchen/</code> page.
           <strong>Run the layout clone first</strong> &mdash; if any widget id is missing, nothing is written at all
           rather than leaving a half-translated page.</p>
        <?php if (function_exists('mm_de_translation_state')): ?>
        <table class="widefat striped" style="max-width:640px;margin-bottom:12px;">
            <thead><tr><th>Post</th><th>Widgets expected</th><th>Found in layout</th><th>Already German</th></tr></thead>
            <tbody>
            <?php foreach (mm_de_translation_state() as $row): ?>
                <tr>
                    <td><?= (int) $row['post'] ?></td>
                    <td><?= (int) $row['total'] ?></td>
                    <td><?= $row['found'] === $row['total']
                            ? '<span style="color:#1a7f37;font-weight:600;">' . (int) $row['found'] . '</span>'
                            : '<span style="color:#b32d2e;font-weight:600;">' . (int) $row['found'] . ' — clone not applied</span>' ?></td>
                    <td><?= $row['done'] === $row['total']
                            ? '<span style="color:#1a7f37;font-weight:600;">all ' . (int) $row['done'] . '</span>'
                            : (int) $row['done'] . ' of ' . (int) $row['total'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="mm_action" value="translate_de">
            <?php submit_button('Apply German Copy', 'secondary', 'submit', false); ?>
        </form>
        <p style="color:#666;font-size:12px;">Runs automatically once on deploy, immediately after the layout clone.</p>

        <hr>

        <h2>Remove misplaced sections</h2>
        <p style="max-width:760px;">Deletes whole sections &mdash; heading, body copy and that section's own CTA together &mdash;
           on the exact pages listed in <code>corrections/remove-blocks/blocks.php</code> and nowhere else.
           Scoped by post id, so the same wording appearing legitimately on another page is never touched.
           A page is skipped rather than left empty, and the previous layout is saved to
           <code>uploads/mm-rollback-&lt;id&gt;.json</code>.</p>
        <?php if (function_exists('mm_remove_blocks_state')): ?>
        <table class="widefat striped" style="max-width:860px;margin-bottom:12px;">
            <thead><tr><th>Post</th><th>Title</th><th>Widgets still matching</th></tr></thead>
            <tbody>
            <?php foreach (mm_remove_blocks_state() as $row): ?>
                <tr>
                    <td><?= (int) $row['post'] ?></td>
                    <td><?= esc_html($row['title']) ?></td>
                    <td><?php
                        if ($row['marked'] < 0) {
                            echo '<span style="color:#b32d2e;">unreadable Elementor data</span>';
                        } elseif ($row['marked'] === 0) {
                            echo '<span style="color:#1a7f37;font-weight:600;">0 &mdash; clean</span>';
                        } else {
                            echo '<span style="color:#b32d2e;font-weight:600;">' . (int) $row['marked'] . ' still present</span>';
                        }
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="mm_action" value="remove_blocks">
            <?php submit_button('Remove Listed Sections', 'secondary', 'submit', false); ?>
        </form>
        <p style="color:#666;font-size:12px;">Runs automatically once on deploy. Every listed post is reported, including ones that were already clean.</p>

        <hr>

        <h2>Missing menu entries</h2>
        <p style="max-width:760px;">Pages that exist, are published and are correctly linked by Polylang, but were never added to a menu &mdash;
           so they were reachable only by typing the URL. Entries are created under the named parent and positioned to
           match the English submenu, then the menu is renumbered so the order is deterministic.
           Re-running adds nothing if the entry is already there; it only re-asserts the position.</p>
        <?php if (function_exists('mm_menu_items_state')): ?>
        <table class="widefat striped" style="max-width:860px;margin-bottom:12px;">
            <thead><tr><th>Label</th><th>Page</th><th>Slug</th><th>Menu</th><th>State</th></tr></thead>
            <tbody>
            <?php foreach (mm_menu_items_state() as $row): ?>
                <tr>
                    <td><?= esc_html($row['label']) ?></td>
                    <td><?= (int) $row['page'] ?></td>
                    <td><code>/<?= esc_html($row['slug']) ?>/</code></td>
                    <td><?= (int) $row['menu'] ?></td>
                    <td><?= $row['present']
                            ? '<span style="color:#1a7f37;font-weight:600;">in menu (item ' . (int) $row['item'] . ')</span>'
                            : '<span style="color:#b32d2e;font-weight:600;">missing from menu</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <form method="post" style="display:inline-block;">
            <input type="hidden" name="mm_action" value="menu_items">
            <?php submit_button('Add Missing Menu Entries', 'secondary', 'submit', false); ?>
        </form>
        <p style="color:#666;font-size:12px;">Runs automatically once on deploy.</p>

        <hr>

        <h2>WebP &amp; Core Web Vitals</h2>
        <?php $wp = function_exists('mm_webp_progress') ? mm_webp_progress() : array('total'=>0,'done'=>0);
              $pct = $wp['total'] ? round($wp['done']/$wp['total']*100) : 0; ?>
        <p>Creates a <code>.webp</code> beside every JPG/PNG. Originals are never modified &mdash;
           delivery swaps the extension only when the file exists and the browser accepts WebP.<br>
           <strong><?= (int)$wp['done'] ?> / <?= (int)$wp['total'] ?></strong> images done (<?= $pct ?>%) &middot;
           saved so far: <strong><?= number_format(((int)get_option('mm_webp_saved_bytes',0))/1048576, 1) ?> MB</strong></p>
        <div style="background:#e0e0e0;border-radius:4px;height:20px;max-width:460px;margin-bottom:12px;">
            <div style="background:#2271b1;height:20px;border-radius:4px;width:<?= $pct ?>%;"></div>
        </div>
        <form method="post" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="mm_action" value="webp_generate">
            <?php submit_button('Generate WebP (next batch)', 'primary', 'submit', false); ?>
        </form>
        <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete all generated .webp files? Originals are untouched, the site simply goes back to JPEG/PNG.');">
            <input type="hidden" name="mm_action" value="webp_remove">
            <?php submit_button('Remove all WebP', 'delete', 'submit', false); ?>
        </form>

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
