<?php
/**
 * German copy applier — corrections/translate-de
 *
 * Applies corrections/translate-de/translations.php to the pages named in it.
 *
 * WHY BY WIDGET ID
 * The alternative — find-and-replace on the English sentences — has to match
 * exact whitespace inside a JSON-escaped blob, and a single non-breaking space
 * or double space makes a rule silently match nothing. Addressing the element
 * id and overwriting the named setting removes that whole class of failure:
 * either the id is there and is written, or it is missing and reported.
 *
 * WHY THE TREE IS EDITED, NOT THE JSON TEXT
 * The data is decoded to a PHP array, edited, and re-encoded. Escaping is then
 * entirely json_encode's problem, so umlauts and quotes in the German copy can
 * never corrupt the stored value.
 *
 * SAFETY
 * The write goes through mm_write_elementor_data(), which stores a rollback
 * copy in uploads/mm-rollback-<id>.json, writes, reads back, and compares
 * element counts before reporting success. A page is skipped entirely if any
 * of its widget ids are missing, so a half-translated page is never produced.
 */

if (!defined('ABSPATH')) exit;

function mm_de_translation_map() {
    $file = __DIR__ . '/translations.php';
    if (!file_exists($file)) return array();
    $map = include $file;
    return is_array($map) ? $map : array();
}

/**
 * Overwrite the named settings on the named elements, in place.
 * $applied collects the ids actually found and written.
 */
function mm_de_walk(array &$nodes, array $map, array &$applied) {
    foreach ($nodes as &$node) {
        if (!is_array($node)) continue;

        if (isset($node['id']) && isset($map[$node['id']])) {
            if (!isset($node['settings']) || !is_array($node['settings'])) {
                $node['settings'] = array();
            }
            foreach ($map[$node['id']] as $key => $val) {
                // "link" is an array of url/is_external/nofollow — merge so the
                // existing flags survive and only the url changes.
                if (is_array($val) && isset($node['settings'][$key]) && is_array($node['settings'][$key])) {
                    $node['settings'][$key] = array_merge($node['settings'][$key], $val);
                } else {
                    $node['settings'][$key] = $val;
                }
            }
            $applied[] = $node['id'];
        }

        if (!empty($node['elements']) && is_array($node['elements'])) {
            mm_de_walk($node['elements'], $map, $applied);
        }
    }
    unset($node);
}

function mm_apply_de_translations() {
    $map = mm_de_translation_map();
    if (!$map) {
        return 'ERROR: corrections/translate-de/translations.php not found or empty - deploy latest version first.';
    }

    $report = array();
    $errors = 0;

    foreach ($map as $post_id => $widgets) {
        $post_id = (int) $post_id;

        if (!get_post($post_id)) {
            $errors++; $report[] = "ERROR: post $post_id does not exist.";
            continue;
        }

        // Page-level directives (keys starting with "_") are not widget ids and
        // must be taken out before the walk, or they would be counted missing.
        $directives = array();
        foreach ($widgets as $k => $v) {
            if (is_string($k) && $k !== '' && $k[0] === '_') {
                $directives[$k] = $v;
                unset($widgets[$k]);
            }
        }

        if (isset($directives['_post_title'])) {
            $want = (string) $directives['_post_title'];
            $post = get_post($post_id);
            if ($post && $post->post_title !== $want) {
                // Title only — post_name is left alone so existing links keep working.
                wp_update_post(array('ID' => $post_id, 'post_title' => $want));
                $now = get_post($post_id);
                $report[] = ($now && $now->post_title === $want)
                    ? "post $post_id title -> \"$want\""
                    : "WARNING: post $post_id title did not change";
            }
        }

        $raw = get_post_meta($post_id, '_elementor_data', true);
        if (!is_string($raw) || $raw === '') {
            $errors++; $report[] = "ERROR: post $post_id has no _elementor_data.";
            continue;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data)) {
            $errors++; $report[] = "ERROR: post $post_id _elementor_data is not usable JSON.";
            continue;
        }

        $applied = array();
        mm_de_walk($data, $widgets, $applied);

        // Every id must be present. If any are missing the layout is not the
        // one this translation was written against — most likely page-clone has
        // not run yet — so nothing is written at all.
        $missing = array_values(array_diff(array_keys($widgets), $applied));
        if ($missing) {
            $errors++;
            $report[] = sprintf(
                'ERROR: post %d is missing %d of %d widget ids (%s) - run the page layout clone first; nothing was written.',
                $post_id, count($missing), count($widgets), implode(', ', array_slice($missing, 0, 6))
            );
            continue;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            $errors++; $report[] = "ERROR: post $post_id re-encode failed (" . json_last_error_msg() . ').';
            continue;
        }

        $res = mm_write_elementor_data($post_id, $json, "German copy for post $post_id");
        if (empty($res['ok'])) { $errors++; $report[] = $res['msg']; continue; }

        delete_post_meta($post_id, '_elementor_css');
        $report[] = sprintf('post %d: %d widgets translated, %s', $post_id, count($applied), $res['msg']);
    }

    mm_force_elementor_css_rebuild();

    return ($errors ? 'ERROR: ' : 'SUCCESS: ') . implode(' | ', $report);
}

/**
 * Read-only check for the admin screen: how much English is still sitting on
 * each target page. Counts the widget ids whose stored value still differs
 * from the German copy we intend it to have.
 */
function mm_de_translation_state() {
    $rows = array();
    foreach (mm_de_translation_map() as $post_id => $widgets) {
        // Page-level directives are not widgets — exclude them from the counts.
        foreach (array_keys($widgets) as $k) {
            if (is_string($k) && $k !== '' && $k[0] === '_') unset($widgets[$k]);
        }

        $raw = get_post_meta((int) $post_id, '_elementor_data', true);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        $found = 0; $done = 0;
        if (is_array($data)) {
            mm_de_state_walk($data, $widgets, $found, $done);
        }
        $rows[] = array(
            'post'  => (int) $post_id,
            'total' => count($widgets),
            'found' => $found,
            'done'  => $done,
        );
    }
    return $rows;
}

function mm_de_state_walk(array $nodes, array $map, &$found, &$done) {
    foreach ($nodes as $node) {
        if (!is_array($node)) continue;
        if (isset($node['id']) && isset($map[$node['id']])) {
            $found++;
            $ok = true;
            foreach ($map[$node['id']] as $key => $val) {
                if (is_array($val)) continue; // link settings are not a language signal
                $cur = isset($node['settings'][$key]) ? $node['settings'][$key] : null;
                if ($cur !== $val) { $ok = false; break; }
            }
            if ($ok) $done++;
        }
        if (!empty($node['elements']) && is_array($node['elements'])) {
            mm_de_state_walk($node['elements'], $map, $found, $done);
        }
    }
}
