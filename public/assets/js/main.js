/* Site-wide behaviour: reveal on scroll and tab lists ([data-tabs]). The booking flow lives in reserve.js. */
(() => {
    'use strict';

    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

    /* ---------- Reveal on scroll ---------- */
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        $$('.reveal').forEach((element) => observer.observe(element));
    } else {
        $$('.reveal').forEach((element) => element.classList.add('visible'));
    }

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
        };
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => select(tab));
            tab.addEventListener('keydown', (event) => {
                const target = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 }[event.key];
                if (target === undefined) return;
                event.preventDefault();
                select(tabs[(target + tabs.length) % tabs.length], true);
            });
        });
    });
})();
