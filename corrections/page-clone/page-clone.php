<?php
/**
 * Page layout clone — corrections/page-clone
 *
 * THE PROBLEM (navel / belly button, requested 2026-09-01)
 * The German page /navel-belly-button/ (post 667) never received its own
 * content. It was still carrying the *sponsorship programme* boilerplate
 * ("Built by Tattoo Artists, for Tattoo Artists", "Flexible Sponsorship Terms",
 * "Monthly Content Collaboration"), which has nothing to do with piercing, and
 * a single unrelated background photo:
 *
 *     tattoo_styles-chicano-Nelida4.jpg   <- a chicano tattoo, on a navel page
 *
 * The English page /en/navel-belly-button-en/ (post 4797) has the finished
 * article: the correct copy and four navel photographs used as container
 * backgrounds, Piercing-Navel-1.jpg through Piercing-Navel-4.jpg.
 *
 * THE FIX
 * Copy the source page's Elementor data verbatim onto the target page. Because
 * the layout, the container settings and the background images all live inside
 * that one blob, copying it reproduces the design exactly — there is no second
 * place where a setting could be missed, and no hand-rebuilt approximation to
 * drift from the original.
 *
 * The photographs are container backgrounds, not image widgets, which is why
 * nothing shows up in a scan for <img> tags on either page. Elementor renders
 * them from post-<id>.css, so the generated CSS has to be dropped afterwards
 * for the target page to be rebuilt with the new backgrounds.
 *
 * WHAT IS NOT TOUCHED
 * Post title, slug, language assignment, menus and Polylang translation links
 * all stay exactly as they are. Only the page body layout is replaced, and the
 * previous layout is written to uploads/mm-rollback-<id>.json first.
 */

if (!defined('ABSPATH')) exit;

/**
 * Layout clones to enforce: target post id => source post id (+ a label).
 * Listed explicitly so every clone is visible and auditable.
 */
function mm_page_clone_map() {
    return array(
        667 => array(
            'from'  => 4797,
            'label' => 'navel-belly-button (DE 667) <- navel-belly-button-en (EN 4797)',
        ),
        // 2026-09-03: /floral/ was a placeholder - Lorem Ipsum bodies under
        // sponsorship-programme headings. The English page has the finished
        // floral article; its layout is copied and then translated by
        // corrections/translate-de.
        543 => array(
            'from'  => 3448,
            'label' => 'floral (DE 543) <- floral-en (EN 3448)',
        ),
    );
}

/**
 * Copy the Elementor layout of each source page onto its target.
 */
function mm_clone_page_layouts() {
    $report = array();
    $errors = 0;

    foreach (mm_page_clone_map() as $target => $spec) {
        $source = (int) $spec['from'];
        $target = (int) $target;
        $label  = $spec['label'];

        if (!get_post($source)) { $errors++; $report[] = "ERROR: source post $source does not exist."; continue; }
        if (!get_post($target)) { $errors++; $report[] = "ERROR: target post $target does not exist."; continue; }

        $data = get_post_meta($source, '_elementor_data', true);
        if (!is_string($data) || $data === '') {
            $errors++; $report[] = "ERROR: source post $source has no _elementor_data.";
            continue;
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded) || empty($decoded)) {
            $errors++; $report[] = "ERROR: source post $source _elementor_data is not usable JSON.";
            continue;
        }

        // Guard against copying a page that is itself empty or broken.
        if (mm_clone_count_elements($decoded) < 5) {
            $errors++; $report[] = "ERROR: source post $source looks empty — refusing to overwrite $target.";
            continue;
        }

        // mm_write_elementor_data saves a rollback copy, writes, then reads
        // back and compares element counts before reporting success.
        $res = mm_write_elementor_data($target, $data, $label);
        if (empty($res['ok'])) { $errors++; $report[] = $res['msg']; continue; }

        // Carry over the page-level Elementor settings (page padding, layout
        // width, hidden title) so the copy is not framed differently.
        foreach (array('_elementor_page_settings', '_elementor_template_type', '_elementor_edit_mode', '_elementor_version') as $key) {
            $val = get_post_meta($source, $key, true);
            if ($val !== '' && $val !== false) {
                update_post_meta($target, $key, is_string($val) ? wp_slash($val) : $val);
            }
        }

        // The generated stylesheet still describes the OLD layout — including
        // the old background image — so it must be discarded.
        delete_post_meta($target, '_elementor_css');

        $imgs = mm_clone_background_images($decoded);
        $report[] = sprintf(
            '%s: copied %d elements%s',
            $label,
            mm_clone_count_elements($decoded),
            $imgs ? ', backgrounds: ' . implode(', ', $imgs) : ', no background images found'
        );
    }

    mm_force_elementor_css_rebuild();

    return ($errors ? 'ERROR: ' : 'SUCCESS: ') . implode(' | ', $report);
}

/** Count every element in an Elementor tree. */
function mm_clone_count_elements($nodes) {
    $n = 0;
    foreach ((array) $nodes as $node) {
        if (isset($node['elType'])) $n++;
        if (!empty($node['elements'])) $n += mm_clone_count_elements($node['elements']);
    }
    return $n;
}

/** List the background image filenames in an Elementor tree, for the report. */
function mm_clone_background_images($nodes) {
    $found = array();
    mm_clone_collect_bg($nodes, $found);
    return $found;
}

function mm_clone_collect_bg($nodes, array &$found) {
    foreach ((array) $nodes as $node) {
        if (!empty($node['settings']) && is_array($node['settings'])) {
            foreach ($node['settings'] as $key => $val) {
                if (strpos((string) $key, 'background_image') === false) continue;
                if (is_array($val) && !empty($val['url'])) {
                    $name = basename((string) parse_url($val['url'], PHP_URL_PATH));
                    if ($name !== '' && !in_array($name, $found, true)) $found[] = $name;
                }
            }
        }
        if (!empty($node['elements'])) mm_clone_collect_bg($node['elements'], $found);
    }
}

/**
 * Read-only comparison shown on the admin screen, so the current state of each
 * clone is visible without running anything.
 */
function mm_page_clone_state() {
    $rows = array();
    foreach (mm_page_clone_map() as $target => $spec) {
        $source = (int) $spec['from'];
        $src = get_post_meta($source, '_elementor_data', true);
        $tgt = get_post_meta((int) $target, '_elementor_data', true);
        $rows[] = array(
            'label'    => $spec['label'],
            'source'   => $source,
            'target'   => (int) $target,
            'srcBytes' => is_string($src) ? strlen($src) : 0,
            'tgtBytes' => is_string($tgt) ? strlen($tgt) : 0,
            'match'    => (is_string($src) && is_string($tgt) && $src === $tgt),
            'srcImgs'  => is_string($src) ? mm_clone_background_images(json_decode($src, true)) : array(),
            'tgtImgs'  => is_string($tgt) ? mm_clone_background_images(json_decode($tgt, true)) : array(),
        );
    }
    return $rows;
}
