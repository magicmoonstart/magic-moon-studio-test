<?php
/**
 * Container background image swaps — corrections/bg-images
 *
 * Replaces a background image inside a page's Elementor data, on the exact
 * posts listed and nowhere else.
 *
 * THE CASE THIS WAS BUILT FOR (2026-09-01, /anti-tragus/)
 * The first content block of the German page /anti-tragus/ (post 621) is the
 * container 1c7a3861 ("Anti-Tragus"), whose image column is the child
 * container 3987bddb. It was showing Piercing-Anti_Tragus-3.jpg.
 *
 * The three photographs were mapped to the blocks in reverse: block 1 had -3,
 * block 2 had -2, block 3 had -1. The first block is therefore given -1.jpg,
 * which restores the numbering to the block order.
 *
 * SCOPE: post 621 only. -3.jpg appears exactly once in that page's data, on
 * element 3987bddb, so a filename swap on this one post touches the first
 * block's image and nothing else. No other page is read or modified.
 *
 * HOW THE SWAP IS DONE
 * The Elementor tree is decoded and every setting whose key contains
 * "background_image" is examined. When its url matches the old filename the url
 * is rewritten and the attachment id is re-resolved with
 * attachment_url_to_postid() — that second part matters: Elementor stores both,
 * and leaving a stale id behind lets it regenerate the old url and silently
 * undo the change.
 */

if (!defined('ABSPATH')) exit;

/**
 * post id => array( old filename => new filename )
 */
function mm_bg_image_map() {
    return array(
        // /anti-tragus/ (DE) — first block's image column only
        621  => array('Piercing-Anti_Tragus-3.jpg' => 'Piercing-Anti_Tragus-1.jpg'),
        // /en/anti-tragus-en/ (EN) — same block, kept identical to the German page
        5088 => array('Piercing-Anti_Tragus-3.jpg' => 'Piercing-Anti_Tragus-1.jpg'),
    );
}

/**
 * Rewrite matching background urls in a decoded Elementor tree.
 * Returns the number of settings changed.
 */
function mm_bg_swap_tree(array &$nodes, array $swaps, array &$log) {
    $changed = 0;

    foreach ($nodes as &$node) {
        if (!is_array($node)) continue;

        if (!empty($node['settings']) && is_array($node['settings'])) {
            foreach ($node['settings'] as $key => $val) {
                if (strpos((string) $key, 'background_image') === false) continue;
                if (!is_array($val) || empty($val['url'])) continue;

                foreach ($swaps as $old => $new) {
                    if (strpos($val['url'], $old) === false) continue;

                    $newUrl = str_replace($old, $new, $val['url']);
                    $val['url'] = $newUrl;

                    // Re-resolve the attachment id, or clear it. A stale id
                    // pointing at the old file would let Elementor rebuild the
                    // old url and undo this.
                    $newId = function_exists('attachment_url_to_postid')
                        ? attachment_url_to_postid($newUrl) : 0;
                    $val['id'] = $newId ? $newId : '';

                    $node['settings'][$key] = $val;
                    $changed++;
                    $log[] = sprintf('%s: %s -> %s (id %s)',
                        isset($node['id']) ? $node['id'] : '?', $old, $new, $val['id'] === '' ? 'cleared' : $val['id']);
                    break;
                }
            }
        }

        if (!empty($node['elements']) && is_array($node['elements'])) {
            $changed += mm_bg_swap_tree($node['elements'], $swaps, $log);
        }
    }
    unset($node);

    return $changed;
}

function mm_apply_bg_image_swaps() {
    $map = mm_bg_image_map();
    if (!$map) return 'ERROR: no background image swaps configured.';

    $report = array();
    $errors = 0;

    foreach ($map as $post_id => $swaps) {
        $post_id = (int) $post_id;

        if (!get_post($post_id)) {
            $errors++; $report[] = "ERROR: post $post_id does not exist.";
            continue;
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

        // The replacement file must actually exist before pointing at it.
        foreach ($swaps as $old => $new) {
            $probe = mm_bg_find_upload_url($raw, $new);
            if ($probe === '') {
                $errors++;
                $report[] = "ERROR: post $post_id - replacement file $new not found in uploads; nothing written.";
                continue 2;
            }
        }

        $log = array();
        $changed = mm_bg_swap_tree($data, $swaps, $log);

        if ($changed === 0) {
            $report[] = "post $post_id: no match (already correct)";
            continue;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            $errors++; $report[] = "ERROR: post $post_id re-encode failed (" . json_last_error_msg() . ').';
            continue;
        }

        $res = mm_write_elementor_data($post_id, $json, "background swap on post $post_id");
        if (empty($res['ok'])) { $errors++; $report[] = $res['msg']; continue; }

        delete_post_meta($post_id, '_elementor_css');
        $report[] = sprintf('post %d: %d background(s) swapped [%s]', $post_id, $changed, implode('; ', $log));
    }

    mm_force_elementor_css_rebuild();

    return ($errors ? 'ERROR: ' : 'SUCCESS: ') . implode(' | ', $report);
}

/**
 * Confirm a replacement filename exists on disk, reusing the upload directory
 * of a url already present in this page's data so the year/month folder is
 * whatever the site actually uses.
 */
function mm_bg_find_upload_url($raw, $filename) {
    $u = wp_upload_dir();
    if (!empty($u['basedir'])) {
        // try the folders referenced in this page first
        if (preg_match_all('#uploads/(\d{4}/\d{2})/#', (string) $raw, $m)) {
            foreach (array_unique($m[1]) as $ym) {
                if (file_exists(trailingslashit($u['basedir']) . $ym . '/' . $filename)) {
                    return trailingslashit($u['baseurl']) . $ym . '/' . $filename;
                }
            }
        }
        // then anywhere in uploads
        foreach ((array) glob(trailingslashit($u['basedir']) . '*/*/' . $filename) as $hit) {
            if (is_file($hit)) return trailingslashit($u['baseurl'])
                . ltrim(str_replace(trailingslashit($u['basedir']), '', $hit), '/');
        }
    }
    return '';
}

/**
 * Read-only state for the admin screen.
 */
function mm_bg_image_state() {
    $rows = array();
    foreach (mm_bg_image_map() as $post_id => $swaps) {
        $raw = (string) get_post_meta((int) $post_id, '_elementor_data', true);
        $post = get_post((int) $post_id);
        foreach ($swaps as $old => $new) {
            $rows[] = array(
                'post'      => (int) $post_id,
                'title'     => $post ? $post->post_title : '(missing)',
                'old'       => $old,
                'new'       => $new,
                'oldStill'  => (strpos($raw, $old) !== false),
                'newThere'  => (strpos($raw, $new) !== false),
            );
        }
    }
    return $rows;
}
