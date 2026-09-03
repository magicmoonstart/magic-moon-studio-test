/*
 * Magic Moon renderer — behaviour
 *
 * Small, dependency-free replacements for the JavaScript Elementor and
 * ElementsKit provided: the mobile menu, submenu toggles on touch devices,
 * the language switcher, and click-to-play for videos that show a poster.
 * The hero and card sliders are handled by corrections/slider/mm-slider.js.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    ready(function () {
        /* mobile menu */
        var navs = document.querySelectorAll('.mm-nav');
        for (var i = 0; i < navs.length; i++) initNav(navs[i]);

        /* language switcher */
        var langs = document.querySelectorAll('.mm-lang');
        for (var j = 0; j < langs.length; j++) initLang(langs[j]);

        /* poster -> video */
        var vids = document.querySelectorAll('.mm-video-lazy');
        for (var k = 0; k < vids.length; k++) initVideo(vids[k]);

        /* close open menus on outside click / Escape */
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.mm-nav')) closeAll('.mm-nav.is-open');
            if (!e.target.closest('.mm-lang')) closeAll('.mm-lang.is-open');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeAll('.mm-nav.is-open'); closeAll('.mm-lang.is-open'); closeAll('.mm-nav__list li.is-open'); }
        });
    });

    function closeAll(sel) {
        var els = document.querySelectorAll(sel);
        for (var i = 0; i < els.length; i++) {
            els[i].classList.remove('is-open');
            var b = els[i].querySelector('[aria-expanded]');
            if (b) b.setAttribute('aria-expanded', 'false');
        }
    }

    function initNav(nav) {
        var toggle = nav.querySelector('.mm-nav__toggle');
        if (toggle) {
            toggle.addEventListener('click', function () {
                var open = nav.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
        // on narrow screens (or touch) a parent link first opens its submenu
        var parents = nav.querySelectorAll('li.menu-item-has-children > a');
        for (var i = 0; i < parents.length; i++) {
            parents[i].addEventListener('click', function (e) {
                var li = this.parentNode;
                var narrow = window.matchMedia('(max-width: 1024px)').matches;
                var isHash = (this.getAttribute('href') || '#') === '#';
                if (narrow || isHash) {
                    e.preventDefault();
                    var wasOpen = li.classList.contains('is-open');
                    // close siblings
                    var sib = li.parentNode.children;
                    for (var s = 0; s < sib.length; s++) sib[s].classList.remove('is-open');
                    if (!wasOpen) li.classList.add('is-open');
                }
            });
        }
    }

    function initLang(box) {
        var t = box.querySelector('.mm-lang__toggle');
        if (!t) return;
        t.addEventListener('click', function (e) {
            e.preventDefault();
            var open = box.classList.toggle('is-open');
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function initVideo(wrap) {
        var overlay = wrap.querySelector('.mm-video-overlay');
        var tpl = wrap.querySelector('template.mm-video-src');
        if (!overlay || !tpl) return;
        function play() {
            var frag = tpl.content ? tpl.content.cloneNode(true) : null;
            if (!frag) { wrap.innerHTML = tpl.innerHTML; return; }
            wrap.appendChild(frag);
            overlay.parentNode.removeChild(overlay);
            var v = wrap.querySelector('video');
            if (v) { v.setAttribute('autoplay', ''); v.play && v.play().catch(function () {}); }
            var f = wrap.querySelector('iframe');
            if (f && f.src.indexOf('autoplay=0') !== -1) f.src = f.src.replace('autoplay=0', 'autoplay=1');
        }
        overlay.addEventListener('click', play);
        overlay.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); play(); } });
    }
})();
