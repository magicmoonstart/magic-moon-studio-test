<?php
/**
 * Container background lazy-load fix — corrections/lazyload-fix
 *
 * THE MEASURED PROBLEM
 * Elementor ships three inline rules that blank container background images
 * until it decides the container is in view:
 *
 *   .e-con.e-parent:nth-of-type(n+4):not(.e-lazyloaded):not(.e-no-lazyload),
 *   .e-con.e-parent:nth-of-type(n+4):not(.e-lazyloaded):not(.e-no-lazyload) *
 *       { background-image: none !important; }
 *
 *   @media (max-height:1024px)   the same, from nth-of-type(n+3)
 *   @media (max-height:640px)    the same, from nth-of-type(n+2)
 *
 * The .e-lazyloaded class is supposed to be added by Elementor's frontend
 * script when a container scrolls into view. On this site it never arrives past
 * the third container. Measured live on /anti-tragus/ at 1280x720:
 *
 *   container 1-3  e-lazyloaded=true   -> image shows
 *   container 4    e-lazyloaded=false  -> child 1d7c0333 computes to none
 *   container 6    e-lazyloaded=false  -> child 309ae064 computes to none
 *
 * So two of that page's four photographs never appear, no matter how far you
 * scroll. The viewport-height variants make it worse than it first looks: most
 * laptop windows are under 1024px tall, which moves the cutoff to the third
 * container, and under 640px it reaches the second.
 *
 * WHY IT CANNOT BE FIXED IN CSS
 * The declaration is "none !important" and Elementor does not expose the image
 * URL anywhere a stylesheet can read it back — the suppressed elements carry no
 * custom property holding it. There is nothing for an override to restore the
 * background to, so the suppression itself has to be prevented.
 *
 * THE FIX
 * Add Elementor's own opt-out class, .e-no-lazyload, to every container. All
 * three selectors are written as :not(.e-no-lazyload), so this is the framework's
 * documented escape hatch rather than a fight with it.
 *
 * A MutationObserver started in <head> tags containers as the HTML parser
 * creates them, so they are opted out before first paint and there is no flash
 * of a missing background. It disconnects a few seconds after load.
 *
 * SCOPE AND RISK
 * No page content, no Elementor data and no image file is modified — this only
 * stops images being hidden. Disable instantly by appending ?mm_no_lazyfix=1 to
 * any URL. The editor and Elementor's preview are excluded so the canvas is
 * never affected.
 */

if (!defined('ABSPATH')) exit;

function mm_lazyload_fix_script() {
    if (is_admin()) return;
    if (function_exists('is_preview') && is_preview()) return;
    if (isset($_GET['elementor-preview'])) return;
    if (isset($_GET['mm_no_lazyfix'])) return;   // instant off switch

    ?>
<script id="mm-lazyload-fix">
(function () {
    'use strict';
    var CLS = 'e-no-lazyload';

    function tag(node) {
        if (!node || node.nodeType !== 1 || !node.classList) return;
        if (node.classList.contains('e-con') && !node.classList.contains(CLS)) {
            node.classList.add(CLS);
        }
    }

    function scan(root) {
        if (!root || root.nodeType !== 1) root = document.documentElement;
        tag(root);
        if (root.querySelectorAll) {
            var list = root.querySelectorAll('.e-con');
            for (var i = 0; i < list.length; i++) tag(list[i]);
        }
    }

    var mo = null;
    if (typeof MutationObserver === 'function') {
        mo = new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) {
                var added = muts[i].addedNodes;
                for (var j = 0; j < added.length; j++) scan(added[j]);
            }
        });
        try {
            mo.observe(document.documentElement, { childList: true, subtree: true });
        } catch (e) { mo = null; }
    }

    document.addEventListener('DOMContentLoaded', function () {
        scan(document.documentElement);
    });

    window.addEventListener('load', function () {
        scan(document.documentElement);
        // Elementor can still inject containers (popups, nested widgets); give
        // it a moment, then stop observing so nothing is left running.
        setTimeout(function () {
            scan(document.documentElement);
            if (mo) { try { mo.disconnect(); } catch (e) {} }
        }, 3000);
    });
})();
</script>
    <?php
}
add_action('wp_head', 'mm_lazyload_fix_script', 1);
