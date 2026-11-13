/* Site-wide behaviour: reveal on scroll, staggered entrances and tab lists ([data-tabs]).
   The booking flow lives in reserve.js. Animations replay on every page view, including
   pages restored from the browser's back/forward cache. */
(() => {
    'use strict';

    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const MAX_STAGGER = 8; // items after the 8th arrive together, so long lists never make you wait

    /* Number the children of staggered groups so CSS can delay each one. */
    const indexChildren = (items) => items.forEach((item, index) => item.style.setProperty('--i', Math.min(index, MAX_STAGGER)));
    $$('.stagger').forEach((group) => indexChildren([...group.children]));

    /* ---------- Reveal on scroll ---------- */
    let observer = null;
    function startReveal() {
        observer?.disconnect();
        $$('.reveal').forEach((element) => element.classList.remove('visible'));
        if (!('IntersectionObserver' in window)) {
            $$('.reveal').forEach((element) => element.classList.add('visible'));
            return;
        }
        observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        $$('.reveal').forEach((element) => observer.observe(element));
    }

    /* ---------- Menu rows: replay the entrance whenever a category is shown ---------- */
    function enterPanel(panel) {
        if (!panel || panel.hidden) return;
        indexChildren($$('.menu-row', panel));
        panel.classList.remove('is-entering');
        void panel.offsetWidth; // restart the CSS animation
        panel.classList.add('is-entering');
    }
    const enterVisiblePanels = () => $$('.menu-panel:not([hidden])').forEach(enterPanel);
    document.addEventListener('bv:menu-shown', enterVisiblePanels); // sent by reserve.js on the menu step

    /* ---------- Tabs (WAI-ARIA tabs pattern: arrows, Home/End move between tabs) ---------- */
    $$('[data-tabs]').forEach((group) => {
        const tabs = $$('[role="tab"]', group);
        const select = (tab, focus = false) => {
            tabs.forEach((other) => {
                const selected = other === tab;
                other.setAttribute('aria-selected', String(selected));
                other.tabIndex = selected ? 0 : -1;
                document.getElementById(other.getAttribute('aria-controls')).hidden = !selected;
            });
            if (focus) tab.focus();
            const list = tab.parentElement;
            const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            list.scrollTo({ left: list.scrollLeft + tab.getBoundingClientRect().left - list.getBoundingClientRect().left - 16, behavior: smooth ? 'smooth' : 'auto' });
            // Scrolled deep into a long category? Start the new one from its top, under the sticky tabs.
            const stuckAt = parseFloat(getComputedStyle(list).top) || 0;
            if (group.getBoundingClientRect().top < stuckAt) {
                window.scrollBy({ top: group.getBoundingClientRect().top - stuckAt });
            }
            enterPanel(document.getElementById(tab.getAttribute('aria-controls')));
        };
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => { if (tab.getAttribute('aria-selected') !== 'true') select(tab); });
            tab.addEventListener('keydown', (event) => {
                const target = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 }[event.key];
                if (target === undefined) return;
                event.preventDefault();
                select(tabs[(target + tabs.length) % tabs.length], true);
            });
        });
    });

    startReveal();
    enterVisiblePanels();
    /* Back/forward cache restores the page as it was left (animations finished): play them again. */
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        startReveal();
        enterVisiblePanels();
    });
})();
