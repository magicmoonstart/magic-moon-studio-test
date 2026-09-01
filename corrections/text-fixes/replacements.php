<?php
/**
 * Editorial text corrections — corrections/text-fixes
 *
 * Plain before => after pairs. Write them exactly as they read on the page,
 * with real umlauts; do NOT add JSON-escaped duplicates.
 *
 * WHY: Elementor stores page data as JSON with non-ASCII escaped, so the
 * database actually holds "Unterstützung", not "Unterstützung". MySQL
 * REPLACE is a byte comparison, so a rule written with a real "ü" matches
 * nothing in _elementor_data — which is why the first attempt changed the
 * English heading but left the German one untouched. mm_apply_text_fixes()
 * now derives the escaped variant of every rule with json_encode() and runs
 * both forms, so this file stays readable and both encodings are covered.
 *
 * Ordered longest-first so a longer phrase is replaced before any shorter
 * phrase that sits inside it.
 */

if (!defined('ABSPATH')) exit;

return array(

    /* ------------------------------------------------------------------
     * Requested 2026-09-01: remove "Franchise" from the partner-studio
     * pages — heading AND body copy, both languages.
     *   EN  /en/offer-for-partner-studio-en/
     *   DE  /angebot-fuer-partnerstudios/
     * ------------------------------------------------------------------ */

    // headings
    'Client Growth and Optional Digital Franchise Support'
        => 'Client Growth and Optional Digital Support',

    'Kundengewinnung und optionale digitale Franchise-Unterstützung'
        => 'Kundengewinnung und optionale digitale Unterstützung',

    // body copy
    'optional digital franchise-style website support service'
        => 'optional digital website support service',

    'digitale Franchise-Lösung zum Aufbau einer Website'
        => 'digitale Lösung zum Aufbau einer Website',

    // catch-alls for any remaining stray wording elsewhere on the site
    'Franchise-Unterstützung' => 'Unterstützung',
    'Franchise-Lösung'        => 'Lösung',
    'franchise-style '        => '',
    'Franchise-style '        => '',

);
