<?php
/**
 * Editorial text corrections — corrections/text-fixes
 *
 * Straight find-and-replace across Elementor page data and post content, for
 * copy changes requested after the backup was taken. Keys are the exact strings
 * currently live; values are the replacements.
 *
 * Ordered longest-first so a longer phrase is replaced before any shorter
 * phrase that sits inside it. MySQL's REPLACE is case-sensitive, so list each
 * capitalisation that actually occurs.
 */

if (!defined('ABSPATH')) exit;

return array(

    /* ------------------------------------------------------------------
     * Requested 2026-09-01: drop "Franchise" from the partner-studio heading
     * on both language versions.
     *   EN  /en/offer-for-partner-studio-en/
     *   DE  /angebot-fuer-partnerstudios/
     * Only the HEADINGS are changed here. Each page also mentions a
     * "franchise-style website support service" / "Franchise-Lösung" in its
     * body copy — left untouched deliberately, since only the heading was
     * requested.
     * ------------------------------------------------------------------ */
    'Client Growth and Optional Digital Franchise Support'
        => 'Client Growth and Optional Digital Support',

    'Kundengewinnung und optionale digitale Franchise-Unterstützung'
        => 'Kundengewinnung und optionale digitale Unterstützung',

);
