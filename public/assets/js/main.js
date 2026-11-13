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

    /* ---------- Menu rows: rise in as they scroll into view, in staggered batches ---------- */
    const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rowObserver = 'IntersectionObserver' in window && !reducedMotion()
        ? new IntersectionObserver((entries) => {
            // Rows that arrive together form one batch; each waits a little longer than the last.
            entries.filter((entry) => entry.isIntersecting).forEach((entry, index) => {
                entry.target.style.setProperty('--i', Math.min(index, MAX_STAGGER));
                entry.target.classList.add('in');
                rowObserver.unobserve(entry.target);
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -4% 0px' })
        : null;
    if (rowObserver) $$('.menu-browser').forEach((browser) => browser.classList.add('is-animated'));

    /* Hide a panel's rows again and let them rise in as they come into view. */
    function enterPanel(panel) {
        if (!rowObserver || !panel || panel.hidden) return;
        $$('.menu-row', panel).forEach((row) => {
            row.classList.remove('in');
            rowObserver.unobserve(row);
            rowObserver.observe(row);
        });
    }
    const enterVisiblePanels = () => $$('.menu-panel:not([hidden])').forEach(enterPanel);
    document.addEventListener('bv:menu-shown', () => { enterVisiblePanels(); placeIndicators(false); }); // sent by reserve.js on the menu step

    /* ---------- Sliding highlight behind the selected menu tab ---------- */
    function placeIndicator(list, animate = true) {
        const indicator = list.querySelector('.menu-tab-indicator');
        const tab = list.querySelector('[role="tab"][aria-selected="true"]');
        if (!indicator || !tab || !tab.offsetWidth) return;
        if (!animate) list.classList.remove('is-ready');
        indicator.style.width = `${tab.offsetWidth}px`;
        indicator.style.height = `${tab.offsetHeight}px`;
        indicator.style.transform = `translate(${tab.offsetLeft}px, ${tab.offsetTop}px)`;
        list.classList.add('has-indicator');
        if (!animate) requestAnimationFrame(() => requestAnimationFrame(() => list.classList.add('is-ready')));
    }
    const placeIndicators = (animate) => $$('.menu-tabs').forEach((list) => placeIndicator(list, animate));
    placeIndicators(false);
    document.fonts?.ready.then(() => placeIndicators(false)); // tab widths change once the web font loads
    window.addEventListener('resize', () => placeIndicators(false));

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
            const smooth = !reducedMotion();
            list.scrollTo({ left: list.scrollLeft + tab.getBoundingClientRect().left - list.getBoundingClientRect().left - 16, behavior: smooth ? 'smooth' : 'auto' });
            // Scrolled deep into a long category? Start the new one from its top, under the sticky tabs.
            const stuckAt = parseFloat(getComputedStyle(list).top) || 0;
            if (group.getBoundingClientRect().top < stuckAt) {
                window.scrollBy({ top: group.getBoundingClientRect().top - stuckAt });
            }
            placeIndicator(list);
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
