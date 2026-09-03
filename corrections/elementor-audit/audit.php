<?php
/**
 * Elementor dependency audit — corrections/elementor-audit
 *
 * PURPOSE
 * Phase 1 of replacing Elementor with our own rendering. Before a single line
 * of renderer is written we need to know exactly what the site actually uses,
 * from the real database rather than from spot checks:
 *
 *   - every widget type on every page, with counts and which posts use it
 *   - which plugin owns each widget type (Elementor core, ElementsKit,
 *     King Addons, Premium Addons, or ours)
 *   - every container/element setting key in use, so the renderer knows which
 *     properties it has to honour
 *   - where the site's structure lives: page data vs header/footer/popup
 *     templates in elementor_library
 *
 * The result decides two things: what our renderer must cover to reach 100%,
 * and which of the four Elementor addon plugins are load-bearing versus dead
 * weight that can simply be switched off.
 *
 * READ ONLY. This module never writes to the database. It is exposed as
 * /wp-json/mm/v1/elementor-audit (manage_options only) and as a report on the
 * MM Tools screen.
 *
 * WHY SERVER SIDE
 * Crawling 257 rendered pages from a browser misses anything Elementor decides
 * not to output, and cannot see settings at all. Reading _elementor_data
 * directly sees the source of truth, including widgets that currently render
 * as nothing because their Pro plugin is absent.
 */

if (!defined('ABSPATH')) exit;

/** Map a widget type to the plugin that renders it. */
function mm_audit_widget_owner($type) {
    $t = strtolower((string) $type);

    // ours
    if (strpos($t, 'mm-') === 0 || strpos($t, 'mm_') === 0) return 'magic-moon (ours)';

    // addon prefixes seen on this site
    if (strpos($t, 'ekit') === 0 || strpos($t, 'elementskit') !== false) return 'elementskit-lite';
    if (strpos($t, 'king-addons') !== false || strpos($t, 'kng') === 0)  return 'king-addons';
    if (strpos($t, 'premium-') === 0 || strpos($t, 'premium_') === 0)    return 'premium-addons';
    if (strpos($t, 'polylang') !== false)                                 return 'connect-polylang-elementor';
    if (strpos($t, 'wpcf7') !== false || $t === 'shortcode')              return 'core/shortcode';

    // Elementor Pro widget types, which render as nothing without Pro
    $pro = array('nested-carousel','slides','posts','portfolio','form','login','nav-menu',
                 'animated-headline','price-list','price-table','flip-box','call-to-action',
                 'media-carousel','testimonial-carousel','reviews','table-of-contents',
                 'lottie','countdown','share-buttons','blockquote','sitemap','search-form',
                 'theme-post-content','theme-post-title','theme-site-logo','loop-grid');
    if (in_array($t, $pro, true)) return 'ELEMENTOR PRO (absent)';

    return 'elementor (free)';
}

/** Walk an Elementor tree collecting widget types and setting keys. */
function mm_audit_walk($nodes, array &$widgets, array &$settingKeys, array &$containers, $post_id) {
    foreach ((array) $nodes as $node) {
        if (!is_array($node)) continue;

        $elType = isset($node['elType']) ? $node['elType'] : '';

        if ($elType === 'widget') {
            $type = isset($node['widgetType']) ? $node['widgetType'] : '(unknown)';
            if (!isset($widgets[$type])) {
                $widgets[$type] = array('count' => 0, 'posts' => array(), 'owner' => mm_audit_widget_owner($type));
            }
            $widgets[$type]['count']++;
            if (count($widgets[$type]['posts']) < 12 && !in_array($post_id, $widgets[$type]['posts'], true)) {
                $widgets[$type]['posts'][] = $post_id;
            }
        } elseif ($elType !== '') {
            $key = $elType . (isset($node['isInner']) && $node['isInner'] ? ' (inner)' : '');
            $containers[$key] = isset($containers[$key]) ? $containers[$key] + 1 : 1;
        }

        if (!empty($node['settings']) && is_array($node['settings'])) {
            foreach (array_keys($node['settings']) as $k) {
                $settingKeys[$k] = isset($settingKeys[$k]) ? $settingKeys[$k] + 1 : 1;
            }
        }

        if (!empty($node['elements'])) {
            mm_audit_walk($node['elements'], $widgets, $settingKeys, $containers, $post_id);
        }
    }
}

/**
 * Build the full audit. Returns an array; safe to call repeatedly.
 */
function mm_elementor_audit() {
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT pm.post_id, pm.meta_value, p.post_type, p.post_title, p.post_status
           FROM {$wpdb->postmeta} pm
           JOIN {$wpdb->posts} p ON p.ID = pm.post_id
          WHERE pm.meta_key = '_elementor_data'
            AND p.post_status IN ('publish','draft','private')"
    );

    $widgets = array(); $settingKeys = array(); $containers = array();
    $byPostType = array(); $bad = array(); $totalBytes = 0; $pages = 0;

    foreach ((array) $rows as $r) {
        $pages++;
        $totalBytes += strlen((string) $r->meta_value);
        $pt = $r->post_type;
        $byPostType[$pt] = isset($byPostType[$pt]) ? $byPostType[$pt] + 1 : 1;

        $data = json_decode($r->meta_value, true);
        if (!is_array($data)) { $bad[] = (int) $r->post_id; continue; }

        mm_audit_walk($data, $widgets, $settingKeys, $containers, (int) $r->post_id);
    }

    // order widgets by how much work they represent
    uasort($widgets, function ($a, $b) { return $b['count'] - $a['count']; });
    arsort($settingKeys);
    arsort($containers);

    // group widget types by owning plugin
    $byOwner = array();
    foreach ($widgets as $type => $info) {
        $o = $info['owner'];
        if (!isset($byOwner[$o])) $byOwner[$o] = array('types' => 0, 'instances' => 0, 'list' => array());
        $byOwner[$o]['types']++;
        $byOwner[$o]['instances'] += $info['count'];
        if (count($byOwner[$o]['list']) < 25) $byOwner[$o]['list'][] = $type . ' x' . $info['count'];
    }
    uasort($byOwner, function ($a, $b) { return $b['instances'] - $a['instances']; });

    return array(
        'scanned' => array(
            'rows_with_elementor_data' => $pages,
            'by_post_type'             => $byPostType,
            'total_data_mb'            => round($totalBytes / 1048576, 2),
            'unreadable_json_posts'    => $bad,
        ),
        'widget_types_total'  => count($widgets),
        'by_owner'            => $byOwner,
        'widgets'             => $widgets,
        'container_types'     => $containers,
        'setting_keys_total'  => count($settingKeys),
        'setting_keys_top'    => array_slice($settingKeys, 0, 60, true),
    );
}

/** REST endpoint, administrators only. */
add_action('rest_api_init', function () {
    register_rest_route('mm/v1', '/elementor-audit', array(
        'methods'             => 'GET',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback'            => function () { return rest_ensure_response(mm_elementor_audit()); },
    ));
});

/**
 * Compact summary for the admin screen.
 */
function mm_elementor_audit_summary() {
    $a = mm_elementor_audit();
    $lines = array();
    $lines[] = sprintf('%d posts carry Elementor data (%s MB total) across: %s',
        $a['scanned']['rows_with_elementor_data'],
        $a['scanned']['total_data_mb'],
        implode(', ', array_map(function ($k, $v) { return "$k=$v"; },
            array_keys($a['scanned']['by_post_type']), $a['scanned']['by_post_type'])));
    $lines[] = sprintf('%d distinct widget types, %d distinct setting keys',
        $a['widget_types_total'], $a['setting_keys_total']);
    foreach ($a['by_owner'] as $owner => $o) {
        $lines[] = sprintf('%s: %d types, %d instances', $owner, $o['types'], $o['instances']);
    }
    if ($a['scanned']['unreadable_json_posts']) {
        $lines[] = 'UNREADABLE JSON on posts: ' . implode(', ', $a['scanned']['unreadable_json_posts']);
    }
    return $lines;
}
