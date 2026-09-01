/*
 * Consultation popup behaviour — free replacement for Elementor Pro's Popup Builder.
 *
 * Mirrors the reference popup's own settings:
 *   page_load trigger with a 3 second delay, fadeIn over 1.2s.
 *
 * Additions the Pro version does not give you for free: once a visitor closes
 * it, it stays closed for the rest of the browsing session, so it does not
 * reappear on every page view. Escape closes it too.
 */
(function () {
    'use strict';

    var KEY = 'mmConsultDismissed';

    function dismissed() {
        try { return sessionStorage.getItem(KEY) === '1'; } catch (e) { return false; }
    }

    function remember() {
        try { sessionStorage.setItem(KEY, '1'); } catch (e) { /* private mode — fine */ }
    }

    function init() {
        var box = document.querySelector('.mm-consult');
        if (!box || box.dataset.mmReady === '1') return;
        box.dataset.mmReady = '1';

        if (dismissed()) { box.remove(); return; }

        var delay = parseInt(box.getAttribute('data-mm-delay'), 10);
        if (isNaN(delay)) delay = 3000;

        var closeBtn = box.querySelector('.mm-consult__close');

        function close() {
            box.classList.remove('is-open');
            remember();
            setTimeout(function () { if (box.parentNode) box.remove(); }, 1300);
            document.removeEventListener('keydown', onKey);
        }

        function onKey(e) {
            if (e.key === 'Escape' || e.key === 'Esc') close();
        }

        if (closeBtn) closeBtn.addEventListener('click', close);

        setTimeout(function () {
            if (dismissed()) { box.remove(); return; }
            box.classList.add('is-open');
            document.addEventListener('keydown', onKey);
        }, delay);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
