/*
 * Admin (Manager): the guest directory and one guest's record. Guests are grouped by email on the server,
 * so a returning guest is one row however many times they booked.
 */
(() => {
    'use strict';

    const BV = window.BVAdmin;
    if (!BV) return;
    const { $, el, icon, peso, chip, statusChip, tableLabel, fmtDate, link, emptyRow, stat, PAYMENT_LABEL } = BV;
    const debounce = (fn, ms) => { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); }; };
    const initials = (name) => name.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();

    /* ================= Directory ================= */
    const form = $('[data-cust-filters]');
    const state = { q: '', history: '', sort: 'recent' };
    let directory = null;

    async function loadDirectory() {
        try { directory = await BV.get({ action: 'customers' }); renderDirectory(); }
        catch (e) { if (e.message !== 'Signed out') BV.showError(e.message); }
    }

    const SORTS = {
        recent: (a, b) => (b.last_visit ?? '').localeCompare(a.last_visit ?? '') || a.name.localeCompare(b.name),
        visits: (a, b) => b.reservations - a.reservations || a.name.localeCompare(b.name),
        spend: (a, b) => b.food_spend - a.food_spend || a.name.localeCompare(b.name),
        name: (a, b) => a.name.localeCompare(b.name),
    };
    const HISTORY = {
        returning: (c) => c.reservations >= 2,
        new: (c) => c.reservations === 1,
        upcoming: (c) => c.upcoming > 0,
    };

    function renderDirectory() {
        const { summary, rows } = directory;
        $('[data-customer-stats]').replaceChildren(
            stat('Guests', String(summary.total), 'Everyone who has booked', 'users'),
            stat('Returning guests', String(summary.returning), `${summary.total ? Math.round((summary.returning / summary.total) * 100) : 0}% have booked more than once`, 'calendar'),
            stat('Upcoming bookings', String(summary.upcoming), 'Pending or confirmed, from today', 'clock'),
            stat('Food spend', peso(summary.food_spend), 'Settled (served) orders only', 'ticket'));
        const needle = state.q.toLowerCase();
        const shown = rows
            .filter((c) => !needle || `${c.name} ${c.email} ${c.phone}`.toLowerCase().includes(needle))
            .filter((c) => !state.history || HISTORY[state.history](c))
            .sort(SORTS[state.sort]);
        $('[data-cust-count]').textContent = `${shown.length} of ${rows.length} ${rows.length === 1 ? 'guest' : 'guests'}.`;
        $('[data-customers]').replaceChildren(
            el('thead', {}, el('tr', {}, ['Guest', 'Phone', 'Bookings', 'Completed', 'Last visit', 'Food spend', ''].map((h, i) => el('th', { scope: 'col', class: i === 5 ? 'text-right' : '' }, h)))),
            el('tbody', {}, ...(shown.length ? shown.map((c) => el('tr', {},
                el('td', {}, el('strong', {}, c.name), el('div', { class: 'small text-muted' }, c.email)),
                el('td', {}, c.phone), el('td', {}, String(c.reservations)), el('td', {}, String(c.completed)),
                el('td', {}, c.last_visit ? fmtDate(c.last_visit, { month: 'short', day: 'numeric', year: 'numeric' }) : '—'),
                el('td', { class: 'text-right' }, c.food_spend ? peso(c.food_spend) : '—'),
                el('td', { class: 'text-right' }, link(`#customer/${c.customer_id}`, 'Guest record'))))
                : [emptyRow(7, rows.length ? 'No guests match these filters.' : 'No guests yet. They appear here after their first booking.')])));
    }
    form.addEventListener('submit', (e) => e.preventDefault());
    form.addEventListener('input', debounce((e) => { if (e.target.name === 'q') { state.q = e.target.value.trim(); if (directory) renderDirectory(); } }, 150));
    form.addEventListener('change', (e) => { if (e.target.name && e.target.name !== 'q') { state[e.target.name] = e.target.value; if (directory) renderDirectory(); } });
    BV.register('customers', { enter: loadDirectory, poll: loadDirectory });

    /* ================= One guest ================= */
    const holder = $('[data-customer-detail]');
    let customerId = 0;
    let requestNo = 0;

    async function loadRecord(quiet = false) {
        const request = ++requestNo;
        if (!quiet) holder.replaceChildren(el('p', { class: 'text-muted' }, 'Loading guest record…'));
        try {
            const r = await BV.get({ action: 'customer', id: customerId });
            if (request === requestNo) renderRecord(r);
        } catch (e) {
            if (request !== requestNo) return;
            holder.replaceChildren(el('div', { class: 'admin-panel' }, el('h1', { class: 'admin-h', tabindex: '-1' }, 'Guest not found'), el('p', { class: 'text-muted' }, e.message), link('#customers', 'Back to guests')));
        }
    }

    const OUTCOME_ORDER = ['Completed', 'Confirmed', 'Pending', 'Checked-in', 'Cancelled', 'No-show'];
    const panel = (title, sub, ...body) => el('section', { class: 'admin-panel' }, el('div', { class: 'panel-heading' }, el('div', {}, el('h2', {}, title), sub ? el('p', {}, sub) : null)), ...body);

    function renderRecord(r) {
        const [first, ...rest] = r.name.split(' ');
        const prefill = () => {
            try { sessionStorage.setItem('bv-admin-prefill', JSON.stringify({ first_name: first, last_name: rest.join(' '), email: r.email, phone_number: r.phone })); } catch { /* storage blocked */ }
            location.hash = 'reservations/new';
        };
        const max = Math.max(1, ...r.spend_by_visit.map((v) => v.amount));
        const totalOutcomes = Object.values(r.outcomes).reduce((a, b) => a + b, 0) || 1;
        const outcomes = OUTCOME_ORDER.filter((s) => r.outcomes[s]);
        holder.replaceChildren(
            el('div', { class: 'admin-heading-row' },
                el('div', {}, el('h1', { class: 'admin-h', tabindex: '-1' }, r.name), el('p', { class: 'text-muted' }, `Guest since ${fmtDate(r.member_since.slice(0, 10), { month: 'long', day: 'numeric', year: 'numeric' })}${r.preferred_seating ? ` · usually books ${r.preferred_seating === 'VIP' ? 'VIP rooms' : `${r.preferred_seating} tables`}` : ''}.`)),
                el('div', { class: 'heading-actions' }, el('button', { class: 'button button-primary', type: 'button', onclick: prefill }, icon('plus'), ' Create reservation'), link('#customers', [icon('back'), ' Back to guests'], 'button button-secondary'))),
            el('section', { class: 'admin-panel guest-card' }, el('span', { class: 'avatar avatar-lg' }, initials(r.name)),
                el('div', {}, el('strong', {}, r.email), el('p', { class: 'small text-muted m-0' }, r.phone))),
            el('div', { class: 'overview-stats overview-stats-spaced' },
                stat('Reservations', String(r.stats.reservations), 'All bookings, any status', 'calendar'),
                stat('Completed visits', String(r.stats.completed), 'Seated and finished', 'check'),
                stat('Food spend', peso(r.stats.food_spend), 'Settled orders on completed visits', 'ticket'),
                stat('Average per visit', peso(r.stats.average_spend), r.stats.completed ? `Across ${r.stats.completed} completed ${r.stats.completed === 1 ? 'visit' : 'visits'}` : 'No completed visits yet', 'utensils')),
            el('div', { class: 'overview-grid' },
                panel('Food spend by visit', 'Most recent completed visits, oldest first',
                    r.spend_by_visit.length ? el('div', { class: 'sales-bars' }, r.spend_by_visit.map((v) => el('div', { class: 'sales-bar-wrap' },
                        el('span', { class: 'sales-bar-value' }, peso(v.amount)), el('span', { class: 'sales-bar', style: `height:${Math.max(3, Math.round((v.amount / max) * 100))}%` }), el('small', {}, fmtDate(v.date, { month: 'short', day: 'numeric' }))))) : el('p', { class: 'text-muted m-0' }, 'No completed visits yet.')),
                panel('Reservation outcomes', `${r.stats.reservations} ${r.stats.reservations === 1 ? 'booking' : 'bookings'} in total`,
                    el('div', { class: 'room-bar', role: 'img', 'aria-label': 'Outcomes' }, outcomes.map((s) => el('span', { class: `room-seg outcome-${s.toLowerCase()}`, style: `flex-grow:${r.outcomes[s] / totalOutcomes}`, title: `${s}: ${r.outcomes[s]}` }))),
                    el('div', { class: 'room-summary' }, outcomes.map((s) => el('div', { class: 'room-row' }, statusChip(s), el('strong', {}, String(r.outcomes[s]))))))),
            el('section', { class: 'admin-panel mt-4' }, el('div', { class: 'panel-heading' }, el('div', {}, el('h2', {}, 'Reservation history'), el('p', {}, 'Newest first, with what they pre-ordered'))),
                el('div', { class: 'table-scroll table-flush' }, el('table', { class: 'data-table' },
                    el('thead', {}, el('tr', {}, ['Booking', 'Date', 'Table', 'Guests', 'Status', 'Pre-order', ''].map((h) => el('th', { scope: 'col' }, h)))),
                    el('tbody', {}, ...r.history.map((h) => el('tr', {},
                        el('td', {}, el('span', { class: 'ref' }, h.reference_code)),
                        el('td', {}, fmtDate(h.date, { month: 'short', day: 'numeric', year: 'numeric' }), el('div', { class: 'small text-muted' }, h.time)),
                        el('td', {}, tableLabel(h.table_id), el('div', { class: 'small text-muted' }, h.table_type)),
                        el('td', {}, String(h.guests)), el('td', {}, statusChip(h.status)),
                        el('td', { class: 'cell-clamp' }, h.items || el('span', { class: 'text-muted' }, 'None'), h.food_total ? el('div', { class: 'small text-muted' }, `${peso(h.food_total)} · ${PAYMENT_LABEL[h.payment_status] ?? h.payment_status}`) : null),
                        el('td', { class: 'text-right' }, link(`#reservation/${h.reservation_id}`, 'View booking')))))))));
    }

    BV.register('customer', { enter: (arg) => { customerId = Number(arg) || 0; if (customerId) loadRecord(); }, poll: () => { if (customerId) loadRecord(true); } });
})();
