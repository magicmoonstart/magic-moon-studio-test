<?php
/**
 * Missing navigation entries — corrections/menu-items
 *
 * THE PROBLEM
 * The German "Piercing" submenu carried 17 entries; the English one carried 19.
 * Two German pages existed, were published, were assigned German, and were
 * correctly linked to their English counterparts by Polylang — but had no menu
 * entry at all, so they were reachable only by typing the URL:
 *
 *     667  /navel-belly-button/   (EN twin 4797)
 *     626  /snug/                 (EN twin 4777)
 *
 * THE FIX
 * Add the two entries under the German "Piercing" parent (menu item 442), in
 * the positions their English counterparts occupy, then renumber the menu so
 * the order is deterministic rather than left to menu_order ties.
 *
 * IDEMPOTENT
 * An entry is only created if that parent does not already link to that page,
 * so a re-run adds nothing. Positions are re-asserted every run, which is what
 * repairs the order if someone drags an item in wp-admin.
 */

if (!defined('ABSPATH')) exit;

/**
 * Entries that must exist.
 *   parent  : existing menu item that should be the parent
 *   page    : page id to link to
 *   label   : menu label
 *   after   : place directly after this existing menu item id (optional)
 *   before  : place directly before this existing menu item id (optional)
 */
function mm_menu_required_items() {
    return array(
        array(
            'parent' => 442,          // "Piercing" in the German menu
            'page'   => 626,          // /snug/
            'label'  => 'Snug',
            'before' => 678,          // Nasenflügel — mirrors EN (Snug, Nostril, Navel)
        ),
        array(
            'parent' => 442,
            'page'   => 667,          // /navel-belly-button/
            'label'  => 'Bauchnabel',
            'after'  => 678,          // last in the submenu, as on the English side
        ),
    );
}

/** The nav menu a given menu item belongs to, or 0. */
function mm_menu_id_of_item($item_id) {
    $terms = wp_get_object_terms((int) $item_id, 'nav_menu');
    if (is_wp_error($terms) || empty($terms)) return 0;
    return (int) $terms[0]->term_id;
}

/**
 * Find an existing child of $parent_item that links to $page_id.
 * Returns the menu item id or 0.
 */
function mm_menu_find_child($menu_id, $parent_item, $page_id) {
    $items = wp_get_nav_menu_items($menu_id, array('orderby' => 'menu_order'));
    foreach ((array) $items as $it) {
        if ((int) $it->menu_item_parent !== (int) $parent_item) continue;
        if ((int) $it->object_id === (int) $page_id && $it->type === 'post_type') {
            return (int) $it->ID;
        }
    }
    return 0;
}

function mm_add_missing_menu_items() {
    if (!function_exists('wp_update_nav_menu_item')) {
        require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
    }
    if (!function_exists('wp_update_nav_menu_item')) {
        return 'ERROR: nav-menu admin functions unavailable.';
    }

    $report = array();
    $errors = 0;
    $touched_menus = array();

    foreach (mm_menu_required_items() as $spec) {
        $parent = (int) $spec['parent'];
        $page   = (int) $spec['page'];
        $label  = (string) $spec['label'];

        $menu_id = mm_menu_id_of_item($parent);
        if (!$menu_id) {
            $errors++; $report[] = "ERROR: parent menu item $parent is not in any menu.";
            continue;
        }
        if (!get_post($page) || get_post_status($page) !== 'publish') {
            $errors++; $report[] = "ERROR: page $page missing or not published.";
            continue;
        }

        $existing = mm_menu_find_child($menu_id, $parent, $page);

        if ($existing) {
            $report[] = "menu item $existing already links page $page ('$label') - kept";
            $item_id = $existing;
        } else {
            $item_id = wp_update_nav_menu_item($menu_id, 0, array(
                'menu-item-title'     => $label,
                'menu-item-object'    => 'page',
                'menu-item-object-id' => $page,
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
                'menu-item-parent-id' => $parent,
            ));
            if (is_wp_error($item_id) || !$item_id) {
                $errors++;
                $report[] = "ERROR: could not create entry for page $page: "
                          . (is_wp_error($item_id) ? $item_id->get_error_message() : 'unknown');
                continue;
            }
            $report[] = "created menu item $item_id -> page $page ('$label')";
        }

        // Position it relative to the reference item, then renumber.
        $ref  = isset($spec['after']) ? (int) $spec['after'] : (isset($spec['before']) ? (int) $spec['before'] : 0);
        $mode = isset($spec['after']) ? 'after' : 'before';
        if ($ref) {
            $moved = mm_menu_reorder($menu_id, (int) $item_id, $ref, $mode);
            $report[] = $moved
                ? "positioned $item_id $mode $ref"
                : "WARNING: reference item $ref not found in menu $menu_id, left at end";
        }

        $touched_menus[$menu_id] = true;
    }

    foreach (array_keys($touched_menus) as $mid) {
        wp_cache_delete('last_changed', 'posts');
        delete_transient('mm_nav_' . $mid);
    }
    wp_cache_flush();

    return ($errors ? 'ERROR: ' : 'SUCCESS: ') . implode(' | ', $report);
}

/**
 * Move $item_id directly before/after $ref_id, then renumber every item in the
 * menu sequentially. Renumbering the whole menu is what makes the result
 * deterministic: WordPress orders siblings by menu_order, and ties resolve
 * arbitrarily, so leaving gaps or duplicates is how menu order drifts.
 */
function mm_menu_reorder($menu_id, $item_id, $ref_id, $mode = 'after') {
    $items = wp_get_nav_menu_items($menu_id, array('orderby' => 'menu_order'));
    if (empty($items)) return false;

    $ids = array();
    foreach ($items as $it) $ids[] = (int) $it->ID;

    if (!in_array((int) $ref_id, $ids, true)) return false;

    // pull the item out, then splice it back in next to the reference
    $ids = array_values(array_diff($ids, array((int) $item_id)));
    $at  = array_search((int) $ref_id, $ids, true);
    if ($at === false) return false;

    $insert = ($mode === 'after') ? $at + 1 : $at;
    array_splice($ids, $insert, 0, array((int) $item_id));

    foreach ($ids as $i => $id) {
        wp_update_post(array('ID' => $id, 'menu_order' => $i + 1));
    }
    return true;
}

/**
 * Read-only state for the admin screen.
 */
function mm_menu_items_state() {
    $rows = array();
    foreach (mm_menu_required_items() as $spec) {
        $parent  = (int) $spec['parent'];
        $page    = (int) $spec['page'];
        $menu_id = mm_menu_id_of_item($parent);
        $found   = $menu_id ? mm_menu_find_child($menu_id, $parent, $page) : 0;
        $post    = get_post($page);

        $rows[] = array(
            'label'   => (string) $spec['label'],
            'page'    => $page,
            'slug'    => $post ? $post->post_name : '(missing)',
            'menu'    => $menu_id,
            'item'    => $found,
            'present' => (bool) $found,
        );
    }
    return $rows;
}
