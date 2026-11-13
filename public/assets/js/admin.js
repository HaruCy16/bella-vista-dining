(() => {
    'use strict';

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    const csrf = $('meta[name="csrf-token"]').content;
    const peso = (c) => '₱' + (c / 100).toLocaleString('en-PH', { minimumFractionDigits: c % 100 ? 2 : 0, maximumFractionDigits: 2 });
    const POLL_MS = 15000;
    const TABLE_STATUSES = ['Available', 'Reserved', 'Occupied', 'Clean-up'];
    const errorBox = $('[data-admin-error]');
    const sync = $('[data-sync]');
    let timer = null;
    let busy = false;

    const el = (tag, attrs = {}, ...children) => {
        const node = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (v === false || v == null) continue;
            if (k === 'class') node.className = v;
            else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
            else node.setAttribute(k, v === true ? '' : v);
        }
        children.flat().forEach((c) => c != null && node.append(c));
        return node;
    };

    const showError = (message) => { $('span', errorBox).textContent = message; errorBox.hidden = false; };

    async function request(url, options = {}) {
        const response = await fetch(url, { headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, ...options });
        const result = await response.json().catch(() => ({}));
        if (response.status === 401) { window.location.assign('/admin/login.php'); throw new Error('Signed out'); }
        if (!response.ok) throw new Error(result.error || 'Request failed.');
        return result.data;
    }
    const post = (body) => request('/admin/api.php', { method: 'POST', body: JSON.stringify(body) });

    async function act(body) {
        errorBox.hidden = true;
        try { await post(body); } catch (e) { showError(e.message); }
        await refresh();
    }

    /* ---------- Tabs ---------- */
    const tabs = $$('[data-tab]');
    function selectTab(name) {
        tabs.forEach((t) => { const on = t.dataset.tab === name; t.setAttribute('aria-selected', String(on)); t.tabIndex = on ? 0 : -1; });
        $$('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== name; });
        if (name === 'report') loadReport();
    }
    tabs.forEach((t, i) => {
        t.addEventListener('click', () => selectTab(t.dataset.tab));
        t.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            const next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
            next.focus(); selectTab(next.dataset.tab);
        });
    });

    /* ---------- Rendering ---------- */
    const chip = (text, tone = '') => el('span', { class: `chip ${tone}` }, text);
    const tone = (status) => ({ Available: 'chip-ok', Reserved: 'chip-warn', Occupied: 'chip-busy', 'Clean-up': 'chip-muted',
        Confirmed: 'chip-ok', Pending: 'chip-warn', 'Checked-in': 'chip-busy', Completed: 'chip-muted', Cancelled: 'chip-muted', 'No-show': 'chip-muted',
        Preparing: 'chip-busy', Served: 'chip-ok' }[status] ?? '');

    function renderBoard(board) {
        $('[data-board]').replaceChildren(...board.tables.map((t) => {
            const next = t.bookings.find((b) => ['Pending', 'Confirmed', 'Checked-in'].includes(b.status));
            const select = el('select', { 'aria-label': `Status of table ${t.table_id}`, onchange: (e) => act({ action: 'table-status', table_id: t.table_id, status: e.target.value }) },
                TABLE_STATUSES.map((s) => el('option', { value: s, selected: s === t.status }, s)));
            return el('article', { class: `board-card status-${t.status.toLowerCase()}` },
                el('div', { class: 'board-card-head' }, el('strong', {}, t.table_id), chip(t.status, tone(t.status))),
                el('p', { class: 'small text-muted m-0' }, `${t.type_name} · ${t.capacity_min}–${t.capacity_max}`),
                el('p', { class: 'small m-0 mt-2' }, next ? `${next.display_time} · ${next.name} (${next.guests})` : 'No more bookings today'),
                el('label', { class: 'field mt-3' }, el('span', { class: 'sr-only' }, 'Status'), select));
        }));

        const tbody = $('[data-bookings] tbody');
        const rows = board.reservations.map((b) => {
            const actions = [];
            if (b.status === 'Pending' || b.status === 'Confirmed') actions.push(el('button', { class: 'button button-small button-primary', type: 'button', onclick: () => act({ action: 'check-in', reservation_id: b.reservation_id }) }, 'Check in'));
            if (b.status === 'Checked-in') actions.push(el('button', { class: 'button button-small button-secondary', type: 'button', onclick: () => act({ action: 'finish', reservation_id: b.reservation_id }) }, 'Guests left'));
            return el('tr', {},
                el('td', {}, b.display_time, el('div', { class: 'small text-muted' }, `held until ${b.grace_ends}`)),
                el('td', {}, b.table_id),
                el('td', {}, b.name, el('div', { class: 'small text-muted' }, `${b.reference_code} · ${b.phone}`)),
                el('td', {}, String(b.guests)),
                el('td', {}, chip(b.status, tone(b.status))),
                el('td', {}, b.payment_status),
                el('td', { class: 'text-right' }, actions));
        });
        tbody.replaceChildren(...(rows.length ? rows : [el('tr', {}, el('td', { colspan: '7', class: 'text-muted' }, 'No bookings today.'))]));
    }

    function renderQueue(queue) {
        const open = queue.filter((o) => o.status !== 'Served').length;
        $('[data-queue-count]').textContent = open ? String(open) : '';
        const cards = queue.map((o) => el('article', { class: `ticket ticket-${o.status.toLowerCase()}` },
            el('div', { class: 'ticket-head' }, el('strong', { class: 'ticket-table' }, `Table ${o.table_id}`), chip(o.status, tone(o.status))),
            el('p', { class: 'small text-muted m-0' }, [`#${o.order_id}`, o.source, o.time, o.guests ? `${o.guests} guests` : null, o.reference_code].filter(Boolean).join(' · ')),
            el('ul', { class: 'ticket-items' }, o.items.map((i) => el('li', {}, i))),
            el('div', { class: 'ticket-actions' }, o.next.map((s) => el('button', {
                class: `button button-small ${s === 'Cancelled' ? 'button-secondary' : 'button-primary'}`, type: 'button',
                onclick: () => act({ action: 'order-status', order_id: o.order_id, status: s }),
            }, { Preparing: 'Start preparing', Served: 'Mark served', Cancelled: 'Cancel', Pending: 'Send to kitchen' }[s] ?? s)))));
        $('[data-queue]').replaceChildren(...(cards.length ? cards : [el('p', { class: 'text-muted' }, 'No orders in the queue.')]));
    }

    function renderReport(r) {
        const stat = (label, value, hint) => el('div', { class: 'stat' }, el('p', { class: 'stat-label' }, label), el('p', { class: 'stat-value' }, value), hint ? el('p', { class: 'small text-muted m-0' }, hint) : null);
        $('[data-report]').replaceChildren(
            el('div', { class: 'stats' },
                stat('Reservations', String(r.reservations.booked), `${r.reservations.guests} guests · ${r.reservations.cancelled} cancelled · ${r.reservations.no_shows} no-show`),
                stat('Food sales', peso(r.food_sales), `${r.orders} orders · ${peso(r.served_sales)} served`),
                stat('Reservation fees', peso(r.reservation_fees), 'Credited to food bills'),
                stat('Collected online', peso(r.online_collected), 'Deposits and full payments')),
            el('h2', { class: 'admin-h mt-8' }, 'Top dishes'),
            r.top_items.length
                ? el('table', { class: 'data-table' }, el('thead', {}, el('tr', {}, el('th', { scope: 'col' }, 'Dish'), el('th', { scope: 'col' }, 'Qty'), el('th', { scope: 'col', class: 'text-right' }, 'Sales'))),
                    el('tbody', {}, r.top_items.map((i) => el('tr', {}, el('td', {}, i.name), el('td', {}, String(i.quantity)), el('td', { class: 'text-right' }, peso(i.sales))))))
                : el('p', { class: 'text-muted' }, 'No food orders on this day.'));
    }

    async function loadReport() {
        const input = $('[data-report-date]');
        if (!input) return;
        try { renderReport(await request(`/admin/api.php?action=report&date=${encodeURIComponent(input.value)}`)); }
        catch (e) { showError(e.message); }
    }
    $('[data-report-date]')?.addEventListener('change', loadReport);

    async function refresh() {
        if (busy) return;
        busy = true;
        try {
            const data = await request('/admin/api.php?action=dashboard');
            renderBoard(data.board);
            renderQueue(data.queue);
            sync.textContent = `Updated ${new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' })} · refreshes every ${POLL_MS / 1000}s`;
        } catch (e) {
            if (e.message !== 'Signed out') sync.textContent = `Couldn't refresh: ${e.message}`;
        } finally {
            busy = false;
        }
    }

    const schedule = () => { clearInterval(timer); timer = setInterval(() => { if (!document.hidden) refresh(); }, POLL_MS); };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    refresh();
    schedule();
})();
