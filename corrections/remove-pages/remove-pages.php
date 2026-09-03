<?php
/**
 * Retire pages — corrections/remove-pages
 *
 * Requested 2026-09-03: "remove soft colour tattoo both from english and german
 * page menu and subsection, i dont want this page".
 *
 * WHAT WAS FOUND
 * Only one page exists: /soft-color-tattoo/ (post 546, German). There is no
 * English translation — the English menu's "Soft Color Tattoo" entry (item
 * 4652) links to the same German page, as does the German entry (item 558).
 * Its content was not Soft Color at all but a mix of upper-lobe piercing and
 * floral text.
 *
 * WHAT THIS DOES, PER LISTED PAGE
 *   1. deletes every nav-menu item in every menu that links to the page
 *      (covers both language menus, and the mobile copy, in one pass)
 *   2. sets the page to DRAFT — it disappears from the site and search, but
 *      nothing is destroyed; it can be republished from wp-admin in one click
 *
 * It deliberately does NOT trash or delete the post: permanent removal is a
 * decision to make in wp-admin with the content in front of you.
 */

if (!defined('ABSPATH')) exit;

function mm_retire_pages_map() {
    return array(
        546  => 'Soft Color Tattoo (/soft-color-tattoo/) - no EN twin exists',
        // 2026-09-03: PMU section, both languages
        979  => 'Dehnungsstreifen-Camouflage (DE /dehnungsstreifen-camouflage-nach-einzelfallpruefung/, menu item 1024)',
        4241 => 'Stretch Mark Camouflage (EN /en/stretch-mark-camouflage-case-by-case-en/, menu item 4668)',
    );
}

function mm_retire_pages() {
    global $wpdb;
    $report = array(); $errors = 0;

    foreach (mm_retire_pages_map() as $post_id => $label) {
        $post_id = (int) $post_id;
        $post = get_post($post_id);
        if (!$post) { $report[] = "post $post_id: already gone"; continue; }

        // 1. menu items pointing at this page, in any menu
        $items = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
               JOIN {$wpdb->postmeta} o ON o.post_id = p.ID AND o.meta_key = '_menu_item_object_id'
               JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_menu_item_type'
              WHERE p.post_type = 'nav_menu_item' AND o.meta_value = %d AND t.meta_value = 'post_type'",
            $post_id
        ));
        $removed = array();
        foreach ((array) $items as $mid) {
            // never orphan children: re-parent any sub-items to this item's parent
            $parent = (int) get_post_meta((int) $mid, '_menu_item_menu_item_parent', true);
            $kids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_menu_item_parent' AND meta_value = %s", (string) $mid));
            foreach ((array) $kids as $kid) update_post_meta((int) $kid, '_menu_item_menu_item_parent', (string) $parent);
            if (wp_delete_post((int) $mid, true)) $removed[] = (int) $mid;
        }

        // 2. unpublish (reversible)
        $status = $post->post_status;
        if ($status === 'publish') {
            $r = wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'), true);
            if (is_wp_error($r)) { $errors++; $report[] = "ERROR: post $post_id could not be set to draft: " . $r->get_error_message(); continue; }
            $status = 'draft';
        }
        clean_post_cache($post_id);

        $report[] = sprintf('%s: %d menu item(s) removed [%s], page now %s',
            $label, count($removed), implode(', ', $removed), $status);
    }

    wp_cache_flush();
    if (function_exists('mm_force_elementor_css_rebuild')) mm_force_elementor_css_rebuild();
    return ($errors ? 'ERROR: ' : 'SUCCESS: ') . implode(' | ', $report);
}
