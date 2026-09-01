<?php
/**
 * Section remover — corrections/remove-blocks
 *
 * Deletes whole Elementor sections listed in blocks.php, on the exact posts
 * named there and nowhere else.
 *
 * WHY WIDGETS ARE DELETED RATHER THAN TEXT REPLACED
 * A sitewide find-and-replace can only blank the text. That leaves the heading
 * widget rendering an empty <h2> with its margins, and — worse — leaves the
 * section's own call-to-action button behind, so two CTAs end up side by side
 * where the removed section used to separate them. Deleting the elements
 * removes the whole section cleanly.
 *
 * HOW THE SECTION BOUNDARY IS FOUND
 * Signatures mark individual widgets. The tree is then walked from the top and
 * a container is deleted when its subtree contains at least one marked widget
 * and no unmarked content widget. Buttons count as collateral, not as content,
 * which is what lets the section's CTA go with it. Any container holding other
 * real content therefore survives, and the walk stops descending once it has
 * removed something, so the largest safe wrapper goes rather than three
 * separate fragments leaving empty shells behind.
 *
 * SAFETY
 *  - Only posts listed in the block definition are read at all.
 *  - A post is skipped, with a report line, if removal would leave it with no
 *    content widgets — a page is never blanked.
 *  - Containers emptied by the removal are pruned.
 *  - The write goes through mm_write_elementor_data(), which saves
 *    uploads/mm-rollback-<id>.json, writes, reads back and compares element
 *    counts before reporting success.
 *  - A post where nothing matches is reported as "no match", not as an error,
 *    so a page that was already clean is confirmed rather than assumed.
 */

if (!defined('ABSPATH')) exit;

function mm_remove_blocks_map() {
    $file = __DIR__ . '/blocks.php';
    if (!file_exists($file)) return array();
    $map = include $file;
    return is_array($map) ? $map : array();
}

/** True if this node is an Elementor widget (as opposed to a container/section). */
function mm_rb_is_widget($node) {
    return is_array($node) && isset($node['elType']) && $node['elType'] === 'widget';
}

/** True if this widget is a button — treated as collateral, not as content. */
function mm_rb_is_button($node) {
    return mm_rb_is_widget($node)
        && isset($node['widgetType'])
        && strpos((string) $node['widgetType'], 'button') !== false;
}

/** Does this widget's own settings contain any signature? */
function mm_rb_widget_marked($node, array $signatures) {
    if (!mm_rb_is_widget($node) || empty($node['settings'])) return false;
    $hay = wp_json_encode($node['settings']);
    if (!is_string($hay)) return false;
    foreach ($signatures as $sig) {
        if ($sig !== '' && strpos($hay, $sig) !== false) return true;
    }
    return false;
}

/**
 * Tally one subtree: how many marked widgets, unmarked content widgets and
 * buttons it holds.
 */
function mm_rb_tally($node, array $signatures) {
    $t = array('marked' => 0, 'content' => 0, 'buttons' => 0);

    if (mm_rb_is_widget($node)) {
        if (mm_rb_widget_marked($node, $signatures))  $t['marked']++;
        elseif (mm_rb_is_button($node))               $t['buttons']++;
        else                                          $t['content']++;
        return $t;
    }

    if (!empty($node['elements']) && is_array($node['elements'])) {
        foreach ($node['elements'] as $child) {
            $c = mm_rb_tally($child, $signatures);
            $t['marked']  += $c['marked'];
            $t['content'] += $c['content'];
            $t['buttons'] += $c['buttons'];
        }
    }
    return $t;
}

/**
 * Remove marked sections in place. Returns the number of elements deleted.
 * Walks top-down: the first node whose subtree is all-marked-plus-buttons is
 * dropped whole, so the widest safe wrapper goes and no empty shell remains.
 */
function mm_rb_strip(array &$nodes, array $signatures, &$removed) {
    $keep = array();

    foreach ($nodes as $node) {
        if (!is_array($node)) { $keep[] = $node; continue; }

        $t = mm_rb_tally($node, $signatures);

        if ($t['marked'] > 0 && $t['content'] === 0) {
            // Whole subtree is the block (plus its own CTA) — drop it.
            $removed += mm_rb_count($node);
            continue;
        }

        if ($t['marked'] > 0 && !empty($node['elements']) && is_array($node['elements'])) {
            // Mixed: descend and strip the marked parts only.
            mm_rb_strip($node['elements'], $signatures, $removed);

            // Prune a container left with nothing in it.
            if (!mm_rb_is_widget($node) && empty($node['elements'])) {
                $removed++;
                continue;
            }
        }

        $keep[] = $node;
    }

    $nodes = $keep;
}

/** Count every element in a subtree, including the node itself. */
function mm_rb_count($node) {
    $n = isset($node['elType']) ? 1 : 0;
    if (!empty($node['elements']) && is_array($node['elements'])) {
        foreach ($node['elements'] as $c) $n += mm_rb_count($c);
    }
    return $n;
}

/** Count content widgets (excluding buttons) across a whole tree. */
function mm_rb_content_widgets(array $nodes) {
    $n = 0;
    foreach ($nodes as $node) {
        if (!is_array($node)) continue;
        if (mm_rb_is_widget($node)) {
            if (!mm_rb_is_button($node)) $n++;
        } elseif (!empty($node['elements']) && is_array($node['elements'])) {
            $n += mm_rb_content_widgets($node['elements']);
        }
    }
    return $n;
}

/**
 * Apply every block definition.
 */
function mm_remove_blocks() {
    $map = mm_remove_blocks_map();
    if (!$map) {
        return 'ERROR: corrections/remove-blocks/blocks.php not found or empty - deploy latest version first.';
    }

    $report = array();
    $errors = 0;

    foreach ($map as $key => $block) {
        $sigs  = isset($block['signatures']) ? (array) $block['signatures'] : array();
        $posts = isset($block['posts']) ? array_map('intval', (array) $block['posts']) : array();

        if (!$sigs || !$posts) {
            $errors++; $report[] = "ERROR: block '$key' has no signatures or no posts.";
            continue;
        }

        foreach ($posts as $post_id) {
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

            $before = mm_rb_count(array('elements' => $data));
            $removed = 0;
            mm_rb_strip($data, $sigs, $removed);

            if ($removed === 0) {
                $report[] = "post $post_id: no match (already clean)";
                continue;
            }

            // Never blank a page.
            if (mm_rb_content_widgets($data) === 0) {
                $errors++;
                $report[] = "ERROR: post $post_id would be left with no content - skipped, nothing written.";
                continue;
            }

            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || $json === '') {
                $errors++; $report[] = "ERROR: post $post_id re-encode failed (" . json_last_error_msg() . ').';
                continue;
            }

            $res = mm_write_elementor_data($post_id, $json, "block removal on post $post_id");
            if (empty($res['ok'])) { $errors++; $report[] = $res['msg']; continue; }

            delete_post_meta($post_id, '_elementor_css');

            // Elementor also keeps a flattened copy in post_content, which is
            // what WordPress search and excerpts read. Strip the signatures
            // from it too, on this post only.
            $pc_hits = mm_rb_clean_post_content($post_id, $sigs);

            $report[] = sprintf(
                'post %d: removed %d of %d elements, %d remain%s',
                $post_id, $removed, $before, mm_rb_count(array('elements' => $data)),
                $pc_hits ? ", post_content cleaned ($pc_hits)" : ''
            );
        }
    }

    mm_force_elementor_css_rebuild();

    return ($errors ? 'ERROR: ' : 'SUCCESS: ') . implode(' | ', $report);
}

/**
 * Remove the signature paragraphs from a single post's post_content, so the
 * deleted text stops showing up in WordPress search results and excerpts.
 * Scoped to one post id — never a sitewide replace.
 */
function mm_rb_clean_post_content($post_id, array $signatures) {
    global $wpdb;

    $content = $wpdb->get_var($wpdb->prepare(
        "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", (int) $post_id
    ));
    if (!is_string($content) || $content === '') return 0;

    $original = $content;
    $hits = 0;

    foreach ($signatures as $sig) {
        if ($sig === '') continue;
        // Drop the whole element the signature sits in, when we can see one.
        $pattern = '#<(p|h[1-6]|div|span|li)\b[^>]*>(?:(?!</?\1\b).)*?'
                 . preg_quote($sig, '#') . '.*?</\1>#is';
        $content = preg_replace($pattern, '', $content, -1, $n);
        $hits += (int) $n;
        // Fallback: at least remove the sentence itself.
        if (strpos($content, $sig) !== false) {
            $content = str_replace($sig, '', $content);
            $hits++;
        }
    }

    if ($content === $original) return 0;

    $wpdb->update($wpdb->posts, array('post_content' => $content), array('ID' => (int) $post_id), array('%s'), array('%d'));
    clean_post_cache((int) $post_id);
    return $hits;
}

/**
 * Read-only state for the admin screen: how many marked widgets each listed
 * post still holds.
 */
function mm_remove_blocks_state() {
    $rows = array();
    foreach (mm_remove_blocks_map() as $key => $block) {
        $sigs  = isset($block['signatures']) ? (array) $block['signatures'] : array();
        $posts = isset($block['posts']) ? array_map('intval', (array) $block['posts']) : array();

        foreach ($posts as $post_id) {
            $raw  = get_post_meta($post_id, '_elementor_data', true);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $t = is_array($data)
                ? mm_rb_tally(array('elements' => $data), $sigs)
                : array('marked' => -1, 'content' => 0, 'buttons' => 0);

            $post = get_post($post_id);
            $rows[] = array(
                'label'  => $block['label'],
                'post'   => $post_id,
                'title'  => $post ? $post->post_title : '(missing)',
                'marked' => $t['marked'],
            );
        }
    }
    return $rows;
}
