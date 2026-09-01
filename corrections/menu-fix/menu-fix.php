<?php
/**
 * Navigation menu corrections — corrections/menu-fix
 *
 * THE PROBLEM
 * In the German header menu ("Dienstleistungen" dropdown) the "Piercing" entry
 * is a WordPress *custom link* menu item pointing at /piercing/. Clicking it
 * navigated away to that page instead of just opening its 17 sub-categories.
 *
 * Every other grouping header in the same menu already uses "#" as its URL:
 *
 *     Uber uns ............ #    (id 2417)
 *     Dienstleistungen .... #    (id 2611)
 *     TATTOO DESIGN ....... #
 *     SERVICES ............ #    (id 4591, EN)
 *     Piercing ............ #    (id 4612, EN)  <- already correct
 *     Piercing ............ /piercing/  (id 442, DE)  <- the one outlier
 *
 * So "#" is this site's own existing convention for "this label groups
 * children, it is not a destination". The English menu was already built that
 * way; only the German item was ever wrong.
 *
 * THE FIX
 * Set the URL of the listed grouping labels to "#". The menu item itself stays
 * exactly where it is, keeps its label, and keeps rendering its children — it
 * simply stops navigating. Nothing is deleted: the /piercing/ page still
 * exists and any other link to it still works.
 *
 * Matching is by label, so it covers both languages and re-asserts itself if a
 * future menu edit reintroduces a URL on one of them.
 */

if (!defined('ABSPATH')) exit;

/**
 * Menu labels that must behave as static category headers rather than links.
 * Compared case-insensitively against the menu item's own label.
 */
function mm_menu_static_labels() {
    return array(
        'Piercing',
    );
}

/**
 * Force the listed labels to be non-navigating grouping headers.
 * Only touches menu items of type "custom" — real page/post menu items are
 * never modified, so genuine page links can't be broken by this.
 */
function mm_fix_static_menu_items() {
    global $wpdb;

    $labels = array_map('mm_menu_lc', mm_menu_static_labels());

    $items = $wpdb->get_results(
        "SELECT ID, post_title FROM {$wpdb->posts}
          WHERE post_type = 'nav_menu_item' AND post_status = 'publish'"
    );
    if ($items === null) {
        return 'ERROR: could not read nav_menu_item posts.';
    }

    $changed = 0; $already = 0; $failed = 0;
    $report = array();

    foreach ($items as $item) {

        // Only custom links can be grouping headers; leave page links alone.
        if (get_post_meta($item->ID, '_menu_item_type', true) !== 'custom') {
            continue;
        }

        $label = trim((string) $item->post_title);
        if ($label === '' || !in_array(mm_menu_lc($label), $labels, true)) {
            continue;
        }

        $url = (string) get_post_meta($item->ID, '_menu_item_url', true);
        if ($url === '#') { $already++; continue; }

        update_post_meta($item->ID, '_menu_item_url', '#');

        // read back — never report success without confirming the write
        if ((string) get_post_meta($item->ID, '_menu_item_url', true) !== '#') {
            $failed++;
            $report[] = sprintf('id %d "%s": WRITE FAILED', $item->ID, $label);
            continue;
        }

        $changed++;
        $report[] = sprintf('id %d "%s": "%s" -> "#"', $item->ID, $label, $url);
    }

    // Menu markup can be held in the nav-menu object cache.
    wp_cache_delete('last_changed', 'posts');
    wp_cache_flush();

    if ($failed) {
        return 'ERROR: ' . $failed . ' menu item(s) could not be updated. '
             . implode(' | ', $report);
    }

    return sprintf(
        'SUCCESS: %d menu item(s) made static, %d already static. %s',
        $changed, $already,
        $report ? implode(' | ', $report) : 'Nothing needed changing.'
    );
}

/** Lowercase helper that is safe for umlauts whether or not mbstring is present. */
function mm_menu_lc($s) {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/**
 * Read-only check used by the admin screen so the current state is visible
 * without having to run anything.
 */
function mm_menu_static_state() {
    global $wpdb;
    $labels = array_map('mm_menu_lc', mm_menu_static_labels());
    $rows = array();

    $items = $wpdb->get_results(
        "SELECT ID, post_title FROM {$wpdb->posts}
          WHERE post_type = 'nav_menu_item' AND post_status = 'publish'"
    );
    foreach ((array) $items as $item) {
        if (get_post_meta($item->ID, '_menu_item_type', true) !== 'custom') continue;
        $label = trim((string) $item->post_title);
        if ($label === '' || !in_array(mm_menu_lc($label), $labels, true)) continue;

        $url = (string) get_post_meta($item->ID, '_menu_item_url', true);
        $rows[] = array(
            'id'     => (int) $item->ID,
            'label'  => $label,
            'url'    => $url,
            'static' => ($url === '#'),
        );
    }
    return $rows;
}
