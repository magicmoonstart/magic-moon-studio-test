/*
 * Magic Moon sliders — free replacements for two Elementor Pro widgets.
 *
 *   initHero()  drives .mm-hero (was the Pro "slides" widget): autoplay,
 *               prev/next arrows, dots, keyboard, pause on hover.
 *   initCards() adds sliding + arrows to the card grids that replaced the
 *               8 Pro "nested-carousel" widgets. The reference carousels used
 *               slides_to_show = 2, autoplay 3000ms and loop, so the cards are
 *               cloned once and the track scrolls one card at a time.
 *
 * No dependencies. Safe to run twice (each root is marked once initialised).
 */
(function () {
    'use strict';

    var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ------------------------------------------------------------------ */
    /* Hero slider                                                         */
    /* ------------------------------------------------------------------ */
    function initHero(root) {
        if (root.dataset.mmReady === '1') return;
        root.dataset.mmReady = '1';

        var track = root.querySelector('.mm-hero__track');
        var slides = root.querySelectorAll('.mm-hero__slide');
        var dots = root.querySelectorAll('.mm-hero__dot');
        var prev = root.querySelector('.mm-hero__nav--prev');
        var next = root.querySelector('.mm-hero__nav--next');
        if (!track || slides.length === 0) return;

        var i = 0;
        var count = slides.length;
        var delay = parseInt(root.getAttribute('data-mm-autoplay'), 10) || 6000;
        var timer = null;

        function paint() {
            track.style.transform = 'translateX(' + (-100 * i) + '%)';
            for (var d = 0; d < dots.length; d++) {
                dots[d].classList.toggle('is-active', d === i);
            }
        }

        function go(n) {
            i = (n + count) % count;
            paint();
        }

        function start() {
            if (REDUCED || count < 2) return;
            stop();
            timer = setInterval(function () { go(i + 1); }, delay);
        }

        function stop() {
            if (timer) { clearInterval(timer); timer = null; }
        }

        if (prev) prev.addEventListener('click', function () { go(i - 1); start(); });
        if (next) next.addEventListener('click', function () { go(i + 1); start(); });

        for (var d = 0; d < dots.length; d++) {
            (function (idx) {
                dots[idx].addEventListener('click', function () { go(idx); start(); });
            })(d);
        }

        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);

        // Only hide arrows/dots when there is genuinely nothing to move to
        if (count < 2) {
            if (prev) prev.style.display = 'none';
            if (next) next.style.display = 'none';
            var dotWrap = root.querySelector('.mm-hero__dots');
            if (dotWrap) dotWrap.style.display = 'none';
        }

        paint();
        start();
    }

    /* ------------------------------------------------------------------ */
    /* Card sliders                                                        */
    /* ------------------------------------------------------------------ */
    function initCards(root) {
        if (root.dataset.mmReady === '1') return;

        // Direct child containers are the cards Elementor rendered
        var cards = [];
        for (var c = 0; c < root.children.length; c++) {
            if (root.children[c].classList.contains('e-con')) cards.push(root.children[c]);
        }
        if (cards.length === 0) return;
        root.dataset.mmReady = '1';

        // Move the cards into a track we can translate
        var track = document.createElement('div');
        track.className = 'mm-cards__track';
        cards.forEach(function (card) { track.appendChild(card); });

        // Clone once so the loop has somewhere to travel (reference used loop:true)
        var originals = cards.length;
        if (originals > 1) {
            cards.forEach(function (card) {
                var clone = card.cloneNode(true);
                clone.setAttribute('aria-hidden', 'true');
                clone.dataset.mmClone = '1';
                track.appendChild(clone);
            });
        }
        root.appendChild(track);

        function perView() { return window.innerWidth >= 768 ? 2 : 1; }

        var i = 0;
        var timer = null;
        var DELAY = 3000;   // matches the reference carousels' autoplay_speed

        function paint(animate) {
            track.style.transition = animate === false || REDUCED ? 'none' : '';
            var step = 100 / perView();
            track.style.transform = 'translateX(' + (-step * i) + '%)';
        }

        function go(n) {
            i = n;
            paint(true);
            // After a full pass through the originals, snap silently back to 0
            if (i >= originals) {
                setTimeout(function () {
                    i = 0;
                    paint(false);
                }, 620);
            } else if (i < 0) {
                i = originals - 1;
                paint(true);
            }
        }

        function start() {
            if (REDUCED || originals < 2) return;
            stop();
            timer = setInterval(function () { go(i + 1); }, DELAY);
        }

        function stop() {
            if (timer) { clearInterval(timer); timer = null; }
        }

        if (originals > 1) {
            var prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'mm-cards__nav mm-cards__nav--prev';
            prev.setAttribute('aria-label', 'Previous');
            prev.innerHTML = '&#10094;';

            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'mm-cards__nav mm-cards__nav--next';
            next.setAttribute('aria-label', 'Next');
            next.innerHTML = '&#10095;';

            prev.addEventListener('click', function () { go(i - 1); start(); });
            next.addEventListener('click', function () { go(i + 1); start(); });

            root.appendChild(prev);
            root.appendChild(next);

            root.addEventListener('mouseenter', stop);
            root.addEventListener('mouseleave', start);
        }

        window.addEventListener('resize', function () { paint(false); });

        paint(false);
        start();
    }

    /* ------------------------------------------------------------------ */
    function boot() {
        var heroes = document.querySelectorAll('.mm-hero');
        for (var h = 0; h < heroes.length; h++) initHero(heroes[h]);

        var grids = document.querySelectorAll('.e-con[class*="elementor-element-mmgrd"]');
        for (var g = 0; g < grids.length; g++) initCards(grids[g]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // Elementor can rebuild containers on lazy-load; re-run defensively
    window.addEventListener('load', boot);
})();
