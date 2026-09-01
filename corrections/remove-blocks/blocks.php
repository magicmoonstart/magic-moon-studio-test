<?php
/**
 * Content blocks to delete — corrections/remove-blocks
 *
 * SCOPED, NOT SITEWIDE. Each entry lists the exact post ids it may touch.
 * Nothing outside that list is examined, so the same sentences appearing
 * legitimately elsewhere — for example the sponsorship sections on
 * /en/offer-for-individual-artist-en/ (3194) and its German twin
 * /angebot-fuer-einzelne-kuenstler/ (2404) — are never at risk.
 *
 * HOW A BLOCK IS IDENTIFIED
 * By distinctive sentences ("signatures"), not by widget id: the same block was
 * copied onto different pages and carries different element ids on each.
 * Any Elementor *widget* whose settings contain a signature is marked; the
 * remover then deletes the smallest container holding only marked widgets and
 * buttons, so the heading, the body copy and that section's own
 * call-to-action all go together and no orphan button is left behind.
 */

if (!defined('ABSPATH')) exit;

return array(

    /* ==================================================================
     * Requested 2026-09-01 for /polynesian-maori/.
     *
     * An English sponsorship section had been copied into the German
     * Polynesian / Māori page, where it sat between "Beste Bereiche für
     * strukturierte Bänder" and "Herausforderungen bei polynesischer /
     * Māori-Ästhetik" — four widgets: heading, two text blocks, and the
     * section's CTA.
     *
     * SCOPE: this page and its English counterpart only.
     *   450  /polynesian-maori/          (DE) — carries the block
     *   2759 /en/polynesian-maori-en/    (EN) — verified clean already,
     *        listed so the run confirms that rather than assuming it
     * ================================================================== */
    'polynesian-monthly-content-collaboration' => array(

        'label' => 'Monthly Content Collaboration (EN sponsorship section) on the Polynesian / Maori pages',

        'posts' => array(450, 2759),

        'signatures' => array(
            'Monthly Content Collaboration with Real Working Value',
            'The core of the sponsorship is simple and practical',
            'If the artist does not enjoy speaking to the camera',
            'An important part of this sponsorship is that the artist receives free Magic Moon products',
        ),
    ),

);
