<?php
/**
 * Alphabetical submenus — corrections/menu-sort
 *
 * Sorts the children of each listed menu parent alphabetically, in both the
 * German and the English menu. Requested 2026-09-01.
 *
 * WHAT IS SORTED, AND WHAT IS NOT
 * Only the parents named in mm_menu_sort_targets(). The top-level bar is
 * deliberately absent: "Startseite" / "HOME" must stay first, so alphabetising
 * it would be wrong.
 *
 * SUBTREES MOVE INTACT
 * "Dienstleistungen" / "SERVICES" hold Piercing, TATTOO DESIGN and PMU, which
 * each hold 14-50 children of their own. Sorting those three therefore has to
 * carry whole subtrees, not just single rows. The menu is rebuilt as a tree,
 * the chosen sibling lists are sorted, and the tree is then flattened
 * depth-first and renumbered 1..N. WordPress orders siblings by menu_order and
 * resolves ties arbitrarily, so a full sequential renumber is what makes the
 * result stable rather than accidental.
 *
 * GERMAN COLLATION
 * Sorting is on a normalised key, not the raw label: umlauts fold to their
 * base letter and ß to ss (ä=a, ö=o, ü=u — DIN 5007-1, German dictionary
 * order), case is ignored, and invisible characters are stripped. Several
 * labels on this site carry a zero-width space — "Realism​", "Beauty Mark /
 * Mole Enhancement​" — which would otherwise sort them to unexpected places.
 *
 * NON-LINK HEADERS ARE PINNED FIRST
 * A child whose URL is "#" is a section label, not a destination. Those are
 * kept ahead of the real entries instead of being mixed in among them. This
 * matters for the German TATTOO DESIGN submenu, which contains a stray
 * "CLASSIC & TRADITIONAL STYLES" header sitting in the middle of the list (the
 * English menu has no equivalent). Sorting it in among the styles would leave
 * an unclickable row in an arbitrary position; pinning it puts it at the top
 * where a section label belongs.
 *
 * REVERSIBLE
 * The previous menu_order of every item touched is stored in the option
 * mm_menu_sort_backup before anything changes, and mm_menu_sort_restore()
 * puts it all back.
 */

if (!defined('ABSPATH')) exit;

/**
 * Menu parents whose children should be alphabetised.
 * Keys are existing menu item ids; values are labels for the report.
 */
function mm_menu_sort_targets() {
    return array(
        // German menu
        2417 => 'Über uns (DE)',
        2611 => 'Dienstleistungen (DE)',
        442  => 'Piercing (DE)',
        440  => 'TATTOO DESIGN (DE)',
        443  => 'PMU / Permanent Make-up (DE)',
        // English menu
        4592 => 'ABOUT US (EN)',
        4591 => 'SERVICES (EN)',
        4612 => 'Piercing (EN)',
        4613 => 'TATTOO DESIGN (EN)',
        4614 => 'PMU (EN)',
    );
}

/**
 * Sort key for a menu label: German dictionary order, case- and
 * invisible-character-insensitive.
 */
function mm_menu_sort_key($label) {
    $s = (string) $label;

    // zero-width space / joiners / BOM — present in several labels on this site
    $s = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}\x{00AD}]/u', '', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = trim($s);
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);

    // DIN 5007-1: umlauts sort as their base vowel, ß as ss
    $s = strtr($s, array(
        'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'å' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'š' => 's', 'ž' => 'z',
    ));

    // ignore leading punctuation so "(Inner)" style labels sort on their word
    $s = preg_replace('/^[^\p{L}\p{N}]+/u', '', $s);

    return $s;
}

/** True if this child acts as a section label rather than a link. */
function mm_menu_is_header($item) {
    $url = isset($item->url) ? trim((string) $item->url) : '';
    return ($url === '' || $url === '#');
}

/** The nav menu term id a menu item belongs to, or 0. */
function mm_menu_sort_menu_of($item_id) {
    $terms = wp_get_object_terms((int) $item_id, 'nav_menu');
    if (is_wp_error($terms) || empty($terms)) return 0;
    return (int) $terms[0]->term_id;
}

/**
 * Alphabetise every listed submenu.
 */
function mm_menu_sort_apply() {
    $targets = mm_menu_sort_targets();
    if (!$targets) return 'ERROR: no sort targets configured.';

    // group targets by the menu they live in, so each menu is rebuilt once
    $byMenu = array();
    $missing = array();
    foreach ($targets as $parent => $label) {
        $menu_id = mm_menu_sort_menu_of((int) $parent);
        if (!$menu_id) { $missing[] = "$label (item $parent not in any menu)"; continue; }
        $byMenu[$menu_id][(int) $parent] = $label;
    }

    $report  = array();
    $errors  = count($missing);
    $backup  = (array) get_option('mm_menu_sort_backup', array());

    foreach ($byMenu as $menu_id => $parents) {

        $items = wp_get_nav_menu_items($menu_id, array('orderby' => 'menu_order'));
        if (empty($items)) { $errors++; $report[] = "ERROR: menu $menu_id has no items."; continue; }

        // save the original order once, so a restore is always possible
        if (!isset($backup[$menu_id])) {
            $snap = array();
            foreach ($items as $it) $snap[(int) $it->ID] = (int) $it->menu_order;
            $backup[$menu_id] = $snap;
        }

        // build id => node, and children lists
        $children = array();          // parent id (0 = root) => array of items
        foreach ($items as $it) {
            $p = (int) $it->menu_item_parent;
            $children[$p][] = $it;
        }

        // sort the requested sibling lists
        foreach ($parents as $parent => $label) {
            if (empty($children[$parent])) {
                $errors++; $report[] = "ERROR: $label has no children.";
                continue;
            }

            $before = array();
            foreach ($children[$parent] as $c) $before[] = $c->title;

            $headers = array();
            $links   = array();
            foreach ($children[$parent] as $c) {
                if (mm_menu_is_header($c)) $headers[] = $c; else $links[] = $c;
            }

            $cmp = function ($a, $b) {
                $ka = mm_menu_sort_key($a->title);
                $kb = mm_menu_sort_key($b->title);
                $r  = strcmp($ka, $kb);
                if ($r !== 0) return $r;
                // stable tie-break so equal labels keep a deterministic order
                return ((int) $a->ID) - ((int) $b->ID);
            };
            usort($headers, $cmp);
            usort($links, $cmp);

            $children[$parent] = array_merge($headers, $links);

            $after = array();
            foreach ($children[$parent] as $c) $after[] = $c->title;

            $report[] = sprintf(
                '%s: %d items sorted%s (was "%s..." -> now "%s...")',
                $label, count($after),
                $headers ? ', ' . count($headers) . ' header(s) pinned first' : '',
                implode(', ', array_slice($before, 0, 2)),
                implode(', ', array_slice($after, 0, 2))
            );
        }

        // flatten depth-first and renumber 1..N
        $order = array();
        $walk = function ($parent_id) use (&$walk, &$children, &$order) {
            if (empty($children[$parent_id])) return;
            foreach ($children[$parent_id] as $it) {
                $order[] = (int) $it->ID;
                $walk((int) $it->ID);
            }
        };
        $walk(0);

        // safety: every item must appear exactly once
        if (count($order) !== count($items)) {
            $errors++;
            $report[] = sprintf(
                'ERROR: menu %d rebuild covered %d of %d items - not written.',
                $menu_id, count($order), count($items)
            );
            continue;
        }

        foreach ($order as $i => $id) {
            wp_update_post(array('ID' => $id, 'menu_order' => $i + 1));
        }
    }

    update_option('mm_menu_sort_backup', $backup);
    wp_cache_delete('last_changed', 'posts');
    wp_cache_flush();

    if ($missing) $report = array_merge($report, array('NOT FOUND: ' . implode('; ', $missing)));

    return ($errors ? 'ERROR: ' : 'SUCCESS: ') . implode(' | ', $report);
}

/**
 * Put every touched menu back to the order it had before the first sort.
 */
function mm_menu_sort_restore() {
    $backup = (array) get_option('mm_menu_sort_backup', array());
    if (!$backup) return 'ERROR: no saved order to restore.';

    $n = 0;
    foreach ($backup as $menu_id => $snap) {
        foreach ((array) $snap as $id => $ord) {
            wp_update_post(array('ID' => (int) $id, 'menu_order' => (int) $ord));
            $n++;
        }
    }
    wp_cache_flush();
    return "SUCCESS: restored the previous order of $n menu items. The saved snapshot is kept, so you can sort again.";
}

/**
 * Read-only state: is each listed submenu currently in alphabetical order?
 */
function mm_menu_sort_state() {
    $rows = array();
    foreach (mm_menu_sort_targets() as $parent => $label) {
        $menu_id = mm_menu_sort_menu_of((int) $parent);
        $kids = array();
        if ($menu_id) {
            $items = wp_get_nav_menu_items($menu_id, array('orderby' => 'menu_order'));
            foreach ((array) $items as $it) {
                if ((int) $it->menu_item_parent === (int) $parent) $kids[] = $it;
            }
        }

        $sorted = true;
        $linkKeys = array();
        foreach ($kids as $k) {
            if (!mm_menu_is_header($k)) $linkKeys[] = mm_menu_sort_key($k->title);
        }
        for ($i = 1; $i < count($linkKeys); $i++) {
            if (strcmp($linkKeys[$i - 1], $linkKeys[$i]) > 0) { $sorted = false; break; }
        }

        $rows[] = array(
            'label'   => $label,
            'parent'  => (int) $parent,
            'count'   => count($kids),
            'headers' => count(array_filter($kids, 'mm_menu_is_header')),
            'sorted'  => (count($linkKeys) < 2) ? true : $sorted,
            'first'   => $kids ? $kids[0]->title : '',
        );
    }
    return $rows;
}
