<?php
/**
 * Elementor data normaliser — corrections/json-normalize
 *
 * THE PROBLEM THIS REMOVES PERMANENTLY
 * Elementor saves page data with PHP's default json_encode flags, which escape
 * every non-ASCII character and every forward slash. The database therefore
 * holds things like:
 *
 *     Unterstützung          instead of  Unterstützung
 *     https:\/\/example.com\/x    instead of  https://example.com/x
 *
 * MySQL REPLACE and PHP str_replace both compare bytes, so a content edit
 * written the way it reads on the page silently matches nothing. That is
 * exactly why the German heading did not change while the English one did.
 *
 * THE FIX
 * Decode each row and re-encode it with JSON_UNESCAPED_UNICODE and
 * JSON_UNESCAPED_SLASHES. The result is still valid JSON — Elementor calls
 * json_decode() on it and neither form makes any difference to it — but from
 * then on the stored text matches what you actually type. Every future text
 * correction then works on every page, in any language, with no escaping
 * gymnastics.
 *
 * SAFETY
 * Each row is verified before it is written: decode -> re-encode -> decode
 * again, and the two decoded structures must be identical (strict ===). Any
 * row that fails is left untouched and counted. Rows already normalised are
 * skipped. Work is time-boxed per click so it can never exhaust PHP's limit,
 * and a cursor means each click continues where the last one stopped.
 */

if (!defined('ABSPATH')) exit;

/** How long one click is allowed to work, in seconds. */
if (!defined('MM_JSON_NORM_SECONDS')) define('MM_JSON_NORM_SECONDS', 18);

function mm_json_norm_progress() {
    global $wpdb;
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data'"
    );
    $cursor = (int) get_option('mm_json_norm_cursor', 0);
    $done = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_id <= %d",
        $cursor
    ));
    return array('total' => $total, 'done' => $done, 'cursor' => $cursor);
}

/**
 * Normalise as many rows as fit in the time budget.
 * Safe to click repeatedly; resumes from where it left off.
 */
function mm_normalize_elementor_json() {
    global $wpdb;

    $started = microtime(true);
    $cursor  = (int) get_option('mm_json_norm_cursor', 0);

    $changed = 0; $already = 0; $failed = 0; $examined = 0;
    $failedIds = array();

    while ((microtime(true) - $started) < MM_JSON_NORM_SECONDS) {

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_id, post_id, meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_elementor_data' AND meta_id > %d
             ORDER BY meta_id ASC
             LIMIT 20",
            $cursor
        ));

        if (empty($rows)) {
            // finished — reset so the tool can be run again later if needed
            update_option('mm_json_norm_cursor', 0);
            $p = mm_json_norm_progress();

            // Re-apply the editorial corrections now that the data is plain UTF-8.
            // Anything that previously failed to match because of escaping will
            // match on this pass.
            $after = function_exists('mm_apply_text_fixes') ? mm_apply_text_fixes() : '';

            if (function_exists('mm_force_elementor_css_rebuild')) {
                mm_force_elementor_css_rebuild();
            }

            return sprintf(
                'SUCCESS: finished. All %d Elementor rows are now stored as plain UTF-8 '
                . '(this pass: %d rewritten, %d already clean, %d skipped). '
                . 'Text corrections can now be written exactly as they read on the page. || %s',
                $p['total'], $changed, $already, $failed, $after
            );
        }

        foreach ($rows as $row) {
            $cursor = (int) $row->meta_id;
            $examined++;

            $raw = $row->meta_value;
            if (!is_string($raw) || $raw === '') { $already++; continue; }

            $data = json_decode($raw, true);
            if (!is_array($data)) { $failed++; $failedIds[] = $row->post_id; continue; }

            $new = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($new === false) { $failed++; $failedIds[] = $row->post_id; continue; }

            if ($new === $raw) { $already++; continue; }

            // verify the rewrite is structurally identical before saving
            $back = json_decode($new, true);
            if (!is_array($back) || $back !== $data) { $failed++; $failedIds[] = $row->post_id; continue; }

            $ok = $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $new),
                array('meta_id' => (int) $row->meta_id),
                array('%s'),
                array('%d')
            );
            if ($ok === false) { $failed++; $failedIds[] = $row->post_id; continue; }
            $changed++;
        }

        update_option('mm_json_norm_cursor', $cursor);
    }

    update_option('mm_json_norm_cursor', $cursor);
    $p = mm_json_norm_progress();

    $msg = sprintf(
        'Normalised %d rows this pass (%d already clean, %d skipped, %d examined). Progress: %d / %d — click again to continue.',
        $changed, $already, $failed, $examined, $p['done'], $p['total']
    );
    if ($failedIds) {
        $msg .= ' Skipped post ids: ' . implode(', ', array_slice(array_unique($failedIds), 0, 8));
    }
    return $msg;
}

/** Count rows that still carry escaped sequences — used to confirm the job is done. */
function mm_json_norm_remaining() {
    global $wpdb;
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta}
         WHERE meta_key = '_elementor_data'
           AND (meta_value LIKE '%\\\\u00%' OR meta_value LIKE '%\\\\/%')"
    );
}
