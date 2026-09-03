<?php
/**
 * Magic Moon renderer — corrections/renderer/renderer.php
 *
 * Renders every page of this site from its stored _elementor_data with our own
 * PHP, HTML and CSS, so the front end no longer depends on Elementor,
 * ElementsKit, King Addons, Premium Addons or the Polylang-Elementor bridge.
 *
 * WHAT IT DOES ON A FRONT-END REQUEST (when mode is "on")
 *   1. template_redirect  render the queried page, the header template and the
 *                         footer template into memory (styles.php/widgets.php)
 *   2. wp_head            print the kit CSS and the per-page CSS
 *   3. wp_enqueue_scripts remove every Elementor / addon stylesheet and script,
 *                         load mm-renderer.css/js and the Google fonts in use
 *   4. wp_body_open       print our header; the_content prints the page body;
 *                         wp_footer prints our footer
 *
 * If rendering a page fails for any reason the page falls back to Elementor's
 * own output for that request and the error is recorded in the option
 * mm_render_last_error, so a gap in the renderer can never blank a page.
 *
 * MODES (option mm_render_mode)
 *   on       every visitor gets our rendering               (?mm_render=0 shows Elementor's for comparison)
 *   preview  visitors get Elementor; append ?mm_render=1 to see ours
 *   off      renderer inactive
 *
 * The editor and Elementor preview are always left alone.
 *
 * SOURCE OF TRUTH
 * Page structure and every setting come from _elementor_data exactly as stored
 * — the same data the reference backup holds. Nothing is redesigned; the
 * renderer reproduces what the settings describe.
 */

if (!defined('ABSPATH')) exit;

define('MM_RENDER_VERSION', '7.0.0');
define('MM_RENDER_DIR', __DIR__);
define('MM_RENDER_URL', plugins_url('', __FILE__));

require_once __DIR__ . '/styles.php';
require_once __DIR__ . '/widgets.php';

/* ------------------------------------------------------------------ */
/* Mode                                                                */
/* ------------------------------------------------------------------ */

function mm_render_mode() {
    $m = get_option('mm_render_mode', 'on');
    return in_array($m, array('on', 'preview', 'off'), true) ? $m : 'on';
}

function mm_render_active() {
    static $active = null;
    if ($active !== null) return $active;
    $active = false;
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) return $active;
    if (isset($_GET['elementor-preview']) || isset($_GET['elementor']) || isset($_GET['elementor_library'])) return $active;
    if (function_exists('is_preview') && is_preview()) return $active;
    if (isset($_GET['mm_render'])) { $active = ($_GET['mm_render'] === '1'); return $active; }
    $active = (mm_render_mode() === 'on');
    return $active;
}

/* ------------------------------------------------------------------ */
/* Core: render a post's Elementor data                                */
/* ------------------------------------------------------------------ */

function mm_render_new_ctx($post_id, $scope) {
    $ctx = new stdClass;
    $ctx->post_id = (int) $post_id;
    $ctx->scope   = $scope;        // selector prefix, e.g. ".mm-page-10"
    $ctx->css     = array();
    $ctx->raw     = '';
    $ctx->fonts   = array();
    $ctx->unknown = array();
    $ctx->widgets = 0;
    return $ctx;
}

/**
 * @return array('html','css','fonts','widgets','unknown') or WP_Error
 */
function mm_render_post($post_id, $kind = 'page') {
    $post_id = (int) $post_id;
    $raw = get_post_meta($post_id, '_elementor_data', true);
    if (!is_string($raw) || $raw === '') return new WP_Error('mm_no_data', "post $post_id has no _elementor_data");
    $data = json_decode($raw, true);
    if (!is_array($data)) return new WP_Error('mm_bad_json', "post $post_id _elementor_data is not valid JSON");

    $scope = ($kind === 'page') ? ".mm-page-$post_id" : ".mm-tpl-$post_id";
    $ctx = mm_render_new_ctx($post_id, $scope);

    $ps = get_post_meta($post_id, '_elementor_page_settings', true);
    if (is_string($ps)) $ps = maybe_unserialize($ps);
    if (!is_array($ps)) $ps = array();

    // page-level styles
    mm_css_background($ctx, $ps, 'background', $scope);
    mm_css_spacing($ctx, $ps, 'padding', 'padding', $scope);
    mm_css_spacing($ctx, $ps, 'margin', 'margin', $scope);
    if (!empty($ps['custom_css'])) $ctx->raw .= "\n" . str_replace('selector', $scope, $ps['custom_css']) . "\n";

    $inner = '';
    foreach ($data as $el) $inner .= mm_render_element($el, $ctx, 0);

    $cls = ($kind === 'page')
        ? "mm-page mm-page-$post_id elementor elementor-$post_id"
        : "mm-tpl mm-tpl-$post_id mm-tpl-$kind elementor elementor-$post_id";
    $html = '<div class="' . $cls . '" data-elementor-id="' . $post_id . '" data-mm-render="' . MM_RENDER_VERSION . '">' . $inner . '</div>';

    return array(
        'html'    => $html,
        'css'     => mm_css_compile($ctx) . $ctx->raw,
        'fonts'   => array_keys($ctx->fonts),
        'widgets' => $ctx->widgets,
        'unknown' => $ctx->unknown,
    );
}

function mm_render_element($el, &$ctx, $depth) {
    if (!is_array($el) || empty($el['elType'])) return '';
    $type = $el['elType'];
    if ($type === 'widget')    return mm_render_widget($el, $ctx, $depth);
    if ($type === 'container') return mm_render_container($el, $ctx, $depth);
    // legacy section/column (14 + 12 instances in the reference): treat as containers
    if ($type === 'section' || $type === 'column') return mm_render_container($el, $ctx, $depth, $type);
    return '';
}

function mm_render_container($el, &$ctx, $depth, $legacy = '') {
    $s  = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : array();
    $id = isset($el['id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $el['id']) : uniqid('c');
    $sel = $ctx->scope . " .elementor-element.elementor-element-$id";

    $boxed = (!isset($s['content_width']) || $s['content_width'] === 'boxed');
    if ($legacy === 'column') $boxed = false;
    if ($legacy === 'section' && isset($s['layout']) && $s['layout'] === 'full_width') $boxed = false;

    $classes = array('mm-con', 'e-con', 'e-flex', 'e-no-lazyload', 'elementor-element', "elementor-element-$id",
                     $depth === 0 ? 'e-parent' : 'e-child', $boxed ? 'e-con-boxed' : 'e-con-full');
    if ($legacy) $classes[] = 'mm-legacy-' . $legacy;
    if (!empty($s['css_classes'])) $classes[] = $s['css_classes'];
    if (!empty($s['_css_classes'])) $classes[] = $s['_css_classes'];
    if (!empty($s['hide_desktop'])) $classes[] = 'elementor-hidden-desktop';
    if (!empty($s['hide_tablet']))  $classes[] = 'elementor-hidden-tablet';
    if (!empty($s['hide_mobile']))  $classes[] = 'elementor-hidden-mobile';

    mm_css_container($ctx, $s, $sel);
    mm_css_custom($ctx, $s, $sel);
    if ($legacy === 'column') {
        $w = mm_css_dim(isset($s['_column_size']) ? array('size' => $s['_column_size'], 'unit' => '%') : null, '%');
        if ($w !== '') mm_css_add($ctx, '', $sel, '--width:' . $w);
        mm_css_add($ctx, '', $sel, '--flex-direction:column');
    }
    if ($legacy === 'section') mm_css_add($ctx, '', $sel, '--flex-direction:row');

    $children = '';
    if (!empty($el['elements']) && is_array($el['elements'])) {
        foreach ($el['elements'] as $child) $children .= mm_render_element($child, $ctx, $depth + 1);
    }

    $attrs = ' class="' . esc_attr(implode(' ', $classes)) . '" data-id="' . esc_attr($id) . '" data-element_type="container"';
    if (!empty($s['_element_id'])) $attrs .= ' id="' . esc_attr($s['_element_id']) . '"';

    $inner = $boxed ? '<div class="e-con-inner">' . $children . '</div>' : $children;

    if (!empty($s['link']['url'])) {
        return '<a' . mm_w_link_attrs($s['link']) . $attrs . '>' . $inner . '</a>';
    }
    return '<div' . $attrs . '>' . $inner . '</div>';
}

function mm_render_widget($el, &$ctx, $depth) {
    $s    = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : array();
    $type = isset($el['widgetType']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $el['widgetType']) : 'unknown';
    $id   = isset($el['id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $el['id']) : uniqid('w');
    $sel  = $ctx->scope . " .elementor-element.elementor-element-$id";
    $ctx->widgets++;

    $inner = mm_render_widget_inner($type, $s, $ctx, $sel);
    if ($inner === null) {
        // container-like widgets (nested carousel etc.): lay their children out as a card row
        $inner = '<div class="mm-cards"><div class="mm-cards__track">';
        if (!empty($el['elements']) && is_array($el['elements'])) {
            foreach ($el['elements'] as $child) $inner .= mm_render_element($child, $ctx, $depth + 1);
        }
        $inner .= '</div></div>';
    }
    if ($inner === '') return '';

    $classes = array('mm-w', "mm-w-$type", 'elementor-element', "elementor-element-$id", 'elementor-widget', "elementor-widget-$type");
    if (!empty($s['_css_classes'])) $classes[] = $s['_css_classes'];
    if (!empty($s['hide_desktop'])) $classes[] = 'elementor-hidden-desktop';
    if (!empty($s['hide_tablet']))  $classes[] = 'elementor-hidden-tablet';
    if (!empty($s['hide_mobile']))  $classes[] = 'elementor-hidden-mobile';
    if (!empty($s['_element_width'])) $classes[] = 'elementor-widget__width-' . $s['_element_width'];

    mm_css_widget_common($ctx, $s, $sel);
    mm_css_custom($ctx, $s, $sel);

    $attrs = ' class="' . esc_attr(implode(' ', $classes)) . '" data-id="' . esc_attr($id) . '" data-element_type="widget" data-widget_type="' . esc_attr($type) . '.default"';
    if (!empty($s['_element_id'])) $attrs .= ' id="' . esc_attr($s['_element_id']) . '"';
    return '<div' . $attrs . '><div class="elementor-widget-container">' . $inner . '</div></div>';
}

/* ------------------------------------------------------------------ */
/* Header / footer templates                                           */
/* ------------------------------------------------------------------ */

/** Find the header or footer template for the current language. */
function mm_render_find_template($type) {
    static $cache = array();
    if (isset($cache[$type])) return $cache[$type];

    $lang = function_exists('pll_current_language') ? pll_current_language('slug') : '';
    $candidates = get_posts(array(
        'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids',
        'meta_query' => array('relation' => 'OR',
            array('key' => 'elementskit_template_type', 'value' => $type),
            array('key' => '_elementor_template_type',  'value' => $type),
        ),
    ));
    $pick = 0;
    foreach ($candidates as $id) {
        if (!get_post_meta($id, '_elementor_data', true)) continue;
        $active = get_post_meta($id, 'elementskit_template_activation', true);
        if ($active === 'no') continue;
        $tl = function_exists('pll_get_post_language') ? pll_get_post_language($id, 'slug') : '';
        if ($lang && $tl && $tl !== $lang) continue;
        $pick = (int) $id; break;
    }
    if (!$pick) {
        // the ids observed on this site, by language
        $known = array('header' => array('de' => 72, 'en' => 2653), 'footer' => array('de' => 254, 'en' => 2662));
        $l = ($lang === 'en') ? 'en' : 'de';
        if (isset($known[$type][$l]) && get_post_meta($known[$type][$l], '_elementor_data', true)) $pick = $known[$type][$l];
    }
    $cache[$type] = $pick;
    return $pick;
}

/* ------------------------------------------------------------------ */
/* Request lifecycle                                                   */
/* ------------------------------------------------------------------ */

$GLOBALS['mm_render'] = array('page' => null, 'header' => null, 'footer' => null, 'fonts' => array(), 'fallback' => false);

add_action('template_redirect', function () {
    if (!mm_render_active()) return;
    $R = &$GLOBALS['mm_render'];

    try {
        foreach (array('header', 'footer') as $t) {
            $tid = mm_render_find_template($t);
            if ($tid) {
                $r = mm_render_post($tid, $t);
                if (!is_wp_error($r)) { $R[$t] = $r; $R['fonts'] = array_merge($R['fonts'], $r['fonts']); }
            }
        }
        if (is_singular()) {
            $pid = get_queried_object_id();
            if ($pid && get_post_meta($pid, '_elementor_data', true)) {
                $r = mm_render_post($pid, 'page');
                if (is_wp_error($r)) { $R['fallback'] = true; update_option('mm_render_last_error', $r->get_error_message() . ' @ ' . current_time('mysql')); }
                else { $R['page'] = $r; $R['fonts'] = array_merge($R['fonts'], $r['fonts']); }
            }
        }
    } catch (\Throwable $e) {
        $R['fallback'] = true; $R['page'] = null;
        update_option('mm_render_last_error', $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine() . ' @ ' . current_time('mysql'));
    }
}, 3);

/** True when this request is being served by our renderer (page or templates). */
function mm_render_serving() {
    if (!mm_render_active()) return false;
    $R = $GLOBALS['mm_render'];
    return !empty($R['page']) || !empty($R['header']) || !empty($R['footer']);
}

// Body of the page
add_filter('the_content', function ($content) {
    if (!mm_render_serving()) return $content;
    $R = $GLOBALS['mm_render'];
    if (empty($R['page']) || !is_singular() || !in_the_loop() || !is_main_query()) return $content;
    if (get_the_ID() !== get_queried_object_id()) return $content;
    return $R['page']['html'];
}, PHP_INT_MAX);

// Header and footer
add_filter('hello_elementor_header_footer', function ($show) { return mm_render_serving() ? false : $show; });
add_filter('hello_elementor_page_title', function ($show) {
    if (!mm_render_serving() || !is_singular()) return $show;
    $ps = get_post_meta(get_queried_object_id(), '_elementor_page_settings', true);
    if (is_string($ps)) $ps = maybe_unserialize($ps);
    return (is_array($ps) && !empty($ps['hide_title'])) ? false : $show;
});
add_action('wp_body_open', function () {
    if (!mm_render_serving()) return;
    $R = $GLOBALS['mm_render'];
    if (!empty($R['header'])) echo '<header class="mm-site-header">' . $R['header']['html'] . '</header>';
}, 5);
add_action('wp_footer', function () {
    if (!mm_render_serving()) return;
    $R = $GLOBALS['mm_render'];
    if (!empty($R['footer'])) echo '<footer class="mm-site-footer">' . $R['footer']['html'] . '</footer>';
}, 5);

// CSS
add_action('wp_head', function () {
    if (!mm_render_serving()) return;
    try {
        $R = $GLOBALS['mm_render'];
        $kit = mm_css_kit();
        $css = mm_css_compile($kit) . $kit->raw;
        foreach (array('header', 'footer', 'page') as $k) if (!empty($R[$k])) $css .= "\n" . $R[$k]['css'];
        // hide anything Elementor/ElementsKit still prints while they remain installed
        $css .= "\n.mm-rendered .ekit-template-content-markup,.mm-rendered .elementor-location-header,.mm-rendered .elementor-location-footer,.mm-rendered header.site-header,.mm-rendered footer.site-footer{display:none!important}";
        echo '<style id="mm-render-css">' . "\n" . $css . "\n</style>\n";
    } catch (\Throwable $e) {
        // never let a CSS problem take the page down
        update_option('mm_render_last_error', 'wp_head: ' . $e->getMessage() . ' @ ' . current_time('mysql'));
        echo '<!-- mm-render css failed: ' . esc_html($e->getMessage()) . ' -->' . "\n";
    }
}, 20);

add_filter('body_class', function ($c) {
    if (mm_render_serving()) { $c[] = 'mm-rendered'; if (is_singular()) { $c[] = 'elementor-page'; $c[] = 'elementor-page-' . get_queried_object_id(); } }
    return $c;
});

/* ------------------------------------------------------------------ */
/* Assets: remove Elementor's, load ours                               */
/* ------------------------------------------------------------------ */

function mm_render_asset_is_foreign($src) {
    $src = (string) $src;
    foreach (array('/plugins/elementor/', '/plugins/elementskit-lite/', '/plugins/king-addons/', '/plugins/premium-addons-for-elementor/',
                   '/plugins/connect-polylang-elementor/', '/uploads/elementor/css/', 'elementor-gf-') as $needle) {
        if (strpos($src, $needle) !== false) return true;
    }
    return false;
}

function mm_render_strip_assets() {
    if (!mm_render_serving()) return;
    global $wp_styles, $wp_scripts;
    foreach (array($wp_styles, $wp_scripts) as $col) {
        if (!is_object($col) || empty($col->registered)) continue;
        foreach ($col->registered as $handle => $dep) {
            $src = isset($dep->src) ? $dep->src : '';
            if (mm_render_asset_is_foreign($src) || strpos($handle, 'elementor') === 0 || strpos($handle, 'ekit') === 0 || strpos($handle, 'elementskit') === 0 || strpos($handle, 'king-addons') === 0 || strpos($handle, 'premium') === 0) {
                if ($col === $wp_styles) { wp_dequeue_style($handle); wp_deregister_style($handle); }
                else { wp_dequeue_script($handle); wp_deregister_script($handle); }
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'mm_render_strip_assets', 9999);
add_action('wp_print_styles', 'mm_render_strip_assets', 9999);
add_action('wp_print_scripts', 'mm_render_strip_assets', 9999);
add_action('wp_print_footer_scripts', 'mm_render_strip_assets', 1);

add_action('wp_enqueue_scripts', function () {
    if (!mm_render_serving()) return;
    $css = MM_RENDER_DIR . '/mm-renderer.css';
    $js  = MM_RENDER_DIR . '/mm-renderer.js';
    if (file_exists($css)) wp_enqueue_style('mm-renderer', MM_RENDER_URL . '/mm-renderer.css', array(), (string) filemtime($css));
    if (file_exists($js))  wp_enqueue_script('mm-renderer', MM_RENDER_URL . '/mm-renderer.js', array(), (string) filemtime($js), true);

    // Font Awesome: our own copy in uploads, otherwise Elementor's files while they still exist
    $fa = mm_render_fa_url();
    if ($fa) wp_enqueue_style('mm-fontawesome', $fa, array(), MM_RENDER_VERSION);

    // Google fonts actually used by the rendered pages (+ kit)
    $R = $GLOBALS['mm_render'];
    try { $kit = mm_css_kit(); } catch (\Throwable $e) { $kit = new stdClass; $kit->fonts = array(); }
    $fams = array_unique(array_merge($R['fonts'], array_keys($kit->fonts)));
    $fams = array_filter($fams, function ($f) { return $f && !in_array(strtolower($f), array('arial', 'helvetica', 'georgia', 'times new roman', 'verdana', 'tahoma', 'inherit', 'sans-serif', 'serif', 'system-ui'), true); });
    if ($fams) {
        $q = array();
        foreach ($fams as $f) $q[] = str_replace(' ', '+', $f) . ':300,400,500,600,700,800';
        wp_enqueue_style('mm-google-fonts', 'https://fonts.googleapis.com/css?family=' . implode('|', $q) . '&display=swap', array(), null);
    }
}, 9998);

/** URL of a self-hosted Font Awesome stylesheet (copied from Elementor's bundle), or Elementor's while present. */
function mm_render_fa_url() {
    $u = wp_upload_dir();
    $own = trailingslashit($u['basedir']) . 'mm-fonts/font-awesome/css/all.min.css';
    if (file_exists($own)) return trailingslashit($u['baseurl']) . 'mm-fonts/font-awesome/css/all.min.css';
    $el = WP_PLUGIN_DIR . '/elementor/assets/lib/font-awesome/css/all.min.css';
    if (file_exists($el)) return plugins_url('elementor/assets/lib/font-awesome/css/all.min.css');
    return '';
}

/**
 * Copy Elementor's bundled Font Awesome (CSS + webfonts) into uploads so the
 * icons keep working after Elementor is removed. Runs once.
 */
function mm_render_copy_fontawesome() {
    $src = WP_PLUGIN_DIR . '/elementor/assets/lib/font-awesome';
    if (!is_dir($src)) return 'ERROR: Elementor Font Awesome folder not found (already removed?) - icons will use whatever is in uploads/mm-fonts.';
    $u = wp_upload_dir();
    $dst = trailingslashit($u['basedir']) . 'mm-fonts/font-awesome';
    $n = 0;
    foreach (array('css', 'webfonts') as $sub) {
        if (!is_dir("$src/$sub")) continue;
        wp_mkdir_p("$dst/$sub");
        foreach ((array) glob("$src/$sub/*") as $f) {
            if (is_file($f) && @copy($f, "$dst/$sub/" . basename($f))) $n++;
        }
    }
    return file_exists("$dst/css/all.min.css") ? "SUCCESS: Font Awesome self-hosted ($n files copied to uploads/mm-fonts/font-awesome)." : "ERROR: copy incomplete ($n files).";
}

/* ------------------------------------------------------------------ */
/* Admin state                                                         */
/* ------------------------------------------------------------------ */

function mm_render_state() {
    $u = wp_upload_dir();
    return array(
        'version'    => MM_RENDER_VERSION,
        'mode'       => mm_render_mode(),
        'last_error' => get_option('mm_render_last_error', '(none)'),
        'fontawesome_self_hosted' => file_exists(trailingslashit($u['basedir']) . 'mm-fonts/font-awesome/css/all.min.css'),
        'header_template_de' => mm_render_find_template_for('header', 'de'),
        'footer_template_de' => mm_render_find_template_for('footer', 'de'),
    );
}
function mm_render_find_template_for($type, $lang) {
    $known = array('header' => array('de' => 72, 'en' => 2653), 'footer' => array('de' => 254, 'en' => 2662));
    return isset($known[$type][$lang]) ? $known[$type][$lang] : 0;
}

/** Dry-run one post: returns counts and unknown widget types, writes nothing. */
function mm_render_test($post_id) {
    $r = mm_render_post((int) $post_id, 'page');
    if (is_wp_error($r)) return 'ERROR: ' . $r->get_error_message();
    return sprintf('post %d: %d widgets rendered, %d bytes html, %d bytes css, fonts: %s%s',
        (int) $post_id, $r['widgets'], strlen($r['html']), strlen($r['css']), implode(', ', $r['fonts']) ?: 'none',
        $r['unknown'] ? ' | UNSUPPORTED: ' . implode(', ', array_keys($r['unknown'])) : ' | all widget types supported');
}
