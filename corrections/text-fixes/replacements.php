<?php
/**
 * Editorial text corrections — corrections/text-fixes
 *
 * Plain before => after pairs, written exactly as they read on the page, with
 * real umlauts and real HTML. An empty "after" deletes the passage.
 *
 * ENCODING — you do not have to think about it here.
 * Elementor stores page data as a JSON string, so the database may hold a rule
 * in any of three forms: as written; fully escaped ("ü", "<\/p>", "\"");
 * or half escaped, after corrections/json-normalize has run, where unicode and
 * slashes are plain again but a quote inside an HTML attribute is still \".
 * mm_apply_text_fixes() derives all three from each rule and runs every one,
 * so this file stays readable.
 *
 * ORDER MATTERS. Entries run top to bottom, so a passage wrapped in markup is
 * listed before the bare sentence it contains. Removing "<p>…</p>" as one unit
 * is what keeps an empty paragraph from being left behind; the bare-text rules
 * below are only a fallback for wrappers we have not seen.
 */

if (!defined('ABSPATH')) exit;

/* The sponsorship paragraph, as it appears on the live site. Defined once so
   the wrapped and bare rules below cannot drift apart. */
$mm_sponsor_en = 'This program is designed for artists who are serious about their craft and want to build stronger visibility, stronger credibility, and stronger professional development. Whether you are already building a name for yourself or pushing to the next level, Magic Moon supports your growth through authentic collaboration. We know what artists need because tattooing is part of our own foundation. That makes this sponsorship more personal, more useful, and more aligned with real artistic work.';

$mm_sponsor_de = 'Dieses Programm richtet sich an Artists, die ihre Arbeit ernst nehmen und ihre Sichtbarkeit, Professionalität und persönliche Marke gezielt ausbauen möchten. Egal ob du dir gerade einen Namen aufbaust oder den nächsten Schritt gehen willst – Magic Moon unterstützt dich mit einer Zusammenarbeit, die sich an echter Tattoo-Praxis orientiert. Wir wissen, was Artists brauchen, weil tätowieren Teil unserer eigenen Geschichte ist. Dadurch ist diese Partnerschaft nicht oberflächlich, sondern praxisnah, glaubwürdig und auf langfristiges Wachstum ausgelegt.';

$mm_span = '<span style="font-weight: 400;">';

return array(

    /* ==================================================================
     * Requested 2026-09-01: remove the sponsorship paragraph entirely,
     * German and English.
     *
     * Seen in two wrappers on the live site:
     *   /flat/ and the other service pages ... <p>TEXT</p>
     *   /en/offer-for-individual-artist-en/ .. <p><span …>TEXT</span></p>
     *   /angebot-fuer-einzelne-kuenstler/ .... <p><span …>TEXT</span></p>
     *
     * It also lives inside the elementor_library templates
     * (the-final-single-service-page, individual-en, individual-studio-de),
     * which is what pushes it onto the service pages. A sitewide replace
     * covers templates and pages alike.
     * ================================================================== */

    // whole block, span-wrapped — the form on the two offer pages
    '<p>' . $mm_span . $mm_sponsor_en . '</span></p>' => '',
    '<p>' . $mm_span . $mm_sponsor_de . '</span></p>' => '',

    // whole block, bare paragraph — the form on /flat/ and siblings
    '<p>' . $mm_sponsor_en . '</p>' => '',
    '<p>' . $mm_sponsor_de . '</p>' => '',

    // fallback: any wrapper we have not catalogued. Leaves the wrapper behind,
    // which the empty-shell rule below then clears.
    $mm_sponsor_en => '',
    $mm_sponsor_de => '',

    // empty shell left by the fallback above
    '<p>' . $mm_span . '</span></p>' => '',

    /* ==================================================================
     * Navel / belly button page, requested 2026-09-01.
     * Two defects in the supplied copy, fixed sitewide so the German and
     * English pages stay identical.
     * ================================================================== */

    // Step 1 heading was left in German inside otherwise English copy
    '1) Intensivberatung buchen (Health and Hygiene)'
        => '1) Book an Intensive Consultation (Health and Hygiene)',

    // stray character at the end of the "Style versatility" bullet
    'evolve with personal style over time.q'
        => 'evolve with personal style over time.',

    /* ==================================================================
     * Requested 2026-09-01 for /grafisches-tattoo/:
     * remove "American Academy of Dermatology)."
     *
     * That phrase could not be found anywhere on the live site — 21 style
     * pages in both languages were checked in their served HTML, and a
     * site-wide search for the exact phrase returned nothing ("Academy"
     * matches only the two artists pages). It appears to have been removed
     * already.
     *
     * What it left behind is visible though: on exactly the two Graphic
     * Tattoo pages, the sentence that carried the citation now ends with no
     * full stop at all —
     *     DE 523 "...damit Tattoos länger gut aussehen</p>"
     *     EN 3077 "...keeping tattoos looking their best</p>"
     * — which is the fingerprint of "(... American Academy of Dermatology)."
     * having been cut out, taking the closing period with it. No other page
     * carrying a dermatology reference has this problem.
     *
     * So two things are done here:
     *  1. The citation is removed defensively, in every wording it plausibly
     *     had. If it genuinely is not in the database these rules simply
     *     report NO MATCH, which costs nothing and proves it is gone.
     *  2. The two missing full stops are restored.
     *
     * The punctuation rules are anchored on the closing tag so they are
     * idempotent — after they run the pattern no longer matches, so a second
     * run cannot add a second period.
     * ================================================================== */

    // 1) the citation, longest wording first
    ' (Quelle: American Academy of Dermatology)' => '',
    ' (source: American Academy of Dermatology)' => '',
    ' (American Academy of Dermatology)'         => '',
    'American Academy of Dermatology'            => '',

    // 2) the full stops it took with it
    'damit Tattoos länger gut aussehen</p>'
        => 'damit Tattoos länger gut aussehen.</p>',

    'as a key habit for keeping tattoos looking their best</p>'
        => 'as a key habit for keeping tattoos looking their best.</p>',

    /* ------------------------------------------------------------------
     * Requested earlier: remove "Franchise" from the partner-studio pages
     * — heading AND body copy, both languages.
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
