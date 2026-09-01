<?php
/**
 * Core Web Vitals / weight reduction — corrections/performance
 *
 * Measured on the live homepage before this file existed:
 *   total weight ......... 2,797 KB over 76 requests
 *   images ...............  1,633 KB across 9 files (three PNGs were 1,633 KB of it)
 *   css .................. 627 KB over 35 files, 31 render-blocking in <head>
 *   js ................... 463 KB over 26 files
 *   DOMContentLoaded ..... 5,540 ms
 *   images missing width/height ... 16 of 16  (a CLS source)
 *
 * What this does, in order of measured impact:
 *   1. Serves .webp in place of .jpg/.png whenever a .webp sibling exists AND
 *      the browser advertises WebP support. Nothing in the database changes and
 *      the originals stay on disk, so it is reversible by deactivating alone.
 *   2. Preloads the hero image at high priority — it is the LCP element.
 *   3. Adds decoding="async" and lazy-loads below-the-fold images.
 *   4. Drops the emoji script (22 KB + a request) which the site never uses.
 *   5. Preconnects to the font hosts and forces font-display:swap so text
 *      paints immediately instead of blocking on webfonts.
 *
 * Design is untouched: same images, same dimensions, same layout — only the
 * encoding and the loading strategy change.
 */

if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------ */
/* 1. WebP delivery                                                     */
/* ------------------------------------------------------------------ */

function mm_perf_accepts_webp() {
    return isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
}

/**
 * Swap .jpg/.png upload URLs for .webp when the file exists.
 * Results are memoised so a page with many images costs few disk checks.
 */
function mm_perf_webp_swap($html) {
    if (!is_string($html) || $html === '') return $html;

    static $seen = array();
    $u       = wp_upload_dir();
    $baseurl = $u['baseurl'];
    $basedir = trailingslashit($u['basedir']);

    return preg_replace_callback(
        '#' . preg_quote($baseurl, '#') . '/([A-Za-z0-9_\-./]+?)\.(jpe?g|png)#i',
        function ($m) use ($baseurl, $basedir, &$seen) {
            $rel = $m[1] . '.webp';
            if (!isset($seen[$rel])) {
                $seen[$rel] = file_exists($basedir . $rel);
            }
            return $seen[$rel] ? $baseurl . '/' . $rel : $m[0];
        },
        $html
    );
}

// Buffer the front end so background images declared in generated CSS,
// inline styles and <img> tags are all covered by the same swap.
add_action('template_redirect', function () {
    if (is_admin() || is_feed() || is_preview()) return;
    if (!mm_perf_accepts_webp()) return;
    if (isset($_GET['mm_no_webp'])) return;   // escape hatch for comparison
    ob_start('mm_perf_webp_swap');
}, 1);

/* ------------------------------------------------------------------ */
/* 2. LCP: preload the hero image                                       */
/* ------------------------------------------------------------------ */

add_action('wp_head', function () {
    if (!is_front_page() && !is_home()) return;
    $u    = wp_upload_dir();
    $rel  = '2026/02/Rectangle-3-1';           // first hero slide background
    $webp = trailingslashit($u['basedir']) . $rel . '.webp';
    $png  = trailingslashit($u['basedir']) . $rel . '.png';

    if (mm_perf_accepts_webp() && file_exists($webp)) {
        $href = trailingslashit($u['baseurl']) . $rel . '.webp';
        $type = 'image/webp';
    } elseif (file_exists($png)) {
        $href = trailingslashit($u['baseurl']) . $rel . '.png';
        $type = 'image/png';
    } else {
        return;
    }
    printf(
        '<link rel="preload" as="image" href="%s" type="%s" fetchpriority="high">' . "\n",
        esc_url($href), esc_attr($type)
    );
}, 1);

// Faster connection setup for the webfonts
add_action('wp_head', function () {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}, 0);

/* ------------------------------------------------------------------ */
/* 3. Image loading hints (CLS + bandwidth)                             */
/* ------------------------------------------------------------------ */

add_filter('wp_get_attachment_image_attributes', function ($attr) {
    if (empty($attr['decoding'])) $attr['decoding'] = 'async';
    return $attr;
}, 10, 1);

// Let WordPress lazy-load everything except the first image on the page
add_filter('wp_omit_loading_attr_threshold', function () { return 1; });

/* ------------------------------------------------------------------ */
/* 4. Remove the emoji payload (22 KB + a request, unused here)         */
/* ------------------------------------------------------------------ */

add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('emoji_svg_url', '__return_false');
});

// Drop the emoji entry from the resource-hints list too
add_filter('wp_resource_hints', function ($urls, $relation) {
    if ($relation === 'dns-prefetch') {
        $urls = array_filter($urls, function ($u) {
            return strpos((string) $u, 's.w.org') === false;
        });
    }
    return $urls;
}, 10, 2);

/* ------------------------------------------------------------------ */
/* 5. font-display: swap so text is never invisible while fonts load    */
/* ------------------------------------------------------------------ */

add_filter('style_loader_src', function ($src, $handle) {
    if (strpos($src, 'fonts.googleapis.com') !== false && strpos($src, 'display=') === false) {
        $src = add_query_arg('display', 'swap', $src);
    }
    return $src;
}, 10, 2);
