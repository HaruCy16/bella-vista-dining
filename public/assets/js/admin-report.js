/*
 * Admin (Manager): the daily service report. Everything here is read from the database for the chosen
 * date: nothing is estimated.
 */
(() => {
    'use strict';

    const BV = window.BVAdmin;
    if (!BV) return;
    const { $, el, peso, statusChip, fmtDate, stat, shortBlock } = BV;
    const input = $('[data-report-date]');
    const holder = $('[data-report]');
    let requestNo = 0;

    async function load() {
        if (!input.value) return;
        const request = ++requestNo;
        try {
            const report = await BV.get({ action: 'report', date: input.value });
            if (request === requestNo) render(report);
        } catch (e) { if (e.message !== 'Signed out') BV.showError(e.message); }
    }

    const panel = (title, sub, ...body) => el('section', { class: 'admin-panel' }, el('div', { class: 'panel-heading' }, el('div', {}, el('h2', {}, title), sub ? el('p', {}, sub) : null)), ...body);
    const kv = (rows) => el('dl', { class: 'kv kv-1' }, rows.map(([k, v, cls = '']) => el('div', { class: cls }, el('dt', {}, k), el('dd', {}, v))));
    const STATUS_ORDER = ['Completed', 'Checked-in', 'Confirmed', 'Pending', 'Cancelled', 'No-show'];

    function render(r) {
        $('[data-report-sub]').textContent = `Reservations, food sales and fees for ${fmtDate(r.date, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}.`;
        const maxCategory = Math.max(1, ...r.sales_by_category.map((c) => c.sales));
        const maxBlock = Math.max(1, ...r.sales_by_block.map((b) => b.sales));
        const statuses = STATUS_ORDER.filter((s) => r.status_counts[s]);

        holder.replaceChildren(
            el('div', { class: 'overview-stats overview-stats-spaced' },
                stat('Reservations', String(r.reservations.booked), `${r.reservations.guests} guests · ${r.reservations.cancelled} cancelled · ${r.reservations.no_shows} no-show`, 'calendar'),
                stat('Food sales', peso(r.food_sales), `${r.orders} orders · ${r.pre_orders} pre-orders · ${r.walk_in_orders} walk-in`, 'ticket'),
                stat('Served', peso(r.served_sales), 'Orders already out to the table', 'utensils'),
                stat('Collected online', peso(r.online_collected), 'Deposits and full payments', 'check')),
            el('div', { class: 'overview-grid' },
                panel('Food sales by menu category', `${peso(r.food_sales)} across ${r.orders} orders that are not cancelled`,
                    el('div', { class: 'hbars' }, r.sales_by_category.map((c) => el('div', { class: 'hbar' },
                        el('span', { class: 'hbar-label' }, c.name), el('span', { class: 'hbar-track' }, el('span', { class: 'hbar-fill', style: `width:${c.sales ? Math.max(2, Math.round((c.sales / maxCategory) * 100)) : 0}%` })),
                        el('strong', {}, peso(c.sales)), el('small', {}, `${c.quantity} sold`))))),
                panel('Reservation status', `${r.reservations.booked + r.reservations.cancelled} reservations for the day`,
                    statuses.length ? el('div', { class: 'room-summary' }, statuses.map((s) => el('div', { class: 'room-row' }, statusChip(s), el('strong', {}, String(r.status_counts[s]))))) : el('p', { class: 'text-muted m-0' }, 'No reservations on this date.'))),
            el('div', { class: 'overview-grid mt-4' },
                panel('Table category usage', 'Cancelled bookings are not counted',
                    el('div', { class: 'table-scroll table-flush' }, el('table', { class: 'data-table' },
                        el('thead', {}, el('tr', {}, ['Category', 'Bookings', 'Guests', 'Reservation fees', 'Food sales'].map((h, i) => el('th', { scope: 'col', class: i > 2 ? 'text-right' : '' }, h)))),
                        el('tbody', {}, ...r.table_types.map((t) => el('tr', {}, el('td', {}, t.type === 'VIP' ? 'VIP rooms' : t.type), el('td', {}, String(t.bookings)), el('td', {}, String(t.guests)),
                            el('td', { class: 'text-right' }, t.fees ? peso(t.fees) : '—'), el('td', { class: 'text-right' }, t.food_sales ? peso(t.food_sales) : '—'))))))),
                panel('Fees and credits', 'Kept apart from food sales',
                    kv([['Reservation fees booked', peso(r.reservation_fees)], ['of which extra-guest charges', peso(r.extra_guest_fees)], ['Forfeited by late cancellation', peso(r.forfeited_fees)], ['Collected online', peso(r.online_collected), 'kv-total']]),
                    el('p', { class: 'small text-muted' }, 'Reservation fees are credited to the guest’s food bill at the restaurant, so they are not added to food sales.'))),
            el('div', { class: 'overview-grid mt-4' },
                panel('Food sales by dining block', 'Pre-orders count in their booking’s block; walk-in orders in the block they were taken',
                    el('div', { class: 'sales-bars' }, r.sales_by_block.map((b) => el('div', { class: 'sales-bar-wrap' },
                        el('span', { class: 'sales-bar-value' }, b.sales ? peso(b.sales) : '—'), el('span', { class: 'sales-bar', style: `height:${b.sales ? Math.max(3, Math.round((b.sales / maxBlock) * 100)) : 2}%` }), el('small', {}, shortBlock(b.label)))))),
                panel('Top dishes', 'By quantity ordered',
                    r.top_items.length ? el('div', { class: 'table-scroll table-flush' }, el('table', { class: 'data-table' },
                        el('thead', {}, el('tr', {}, ['Dish', 'Qty', 'Sales'].map((h, i) => el('th', { scope: 'col', class: i ? 'text-right' : '' }, h)))),
                        el('tbody', {}, ...r.top_items.map((i) => el('tr', {}, el('td', {}, i.name), el('td', { class: 'text-right' }, String(i.quantity)), el('td', { class: 'text-right' }, peso(i.sales)))))))
                        : el('p', { class: 'text-muted m-0' }, 'No food orders on this day.'))));
    }

    input.addEventListener('change', load);
    BV.register('report', { enter: load, poll: load });
})();
