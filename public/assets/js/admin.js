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
    let menuItems = [];
    let menuQuery = '';
    let menuFilter = 'all';

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
    const icon = (name) => {
        const paths = { calendar: '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>', ticket: '<path d="M4 7.5A1.5 1.5 0 015.5 6h13A1.5 1.5 0 0120 7.5V10a2 2 0 000 4v2.5a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 16.5V14a2 2 0 000-4z"/>', users: '<circle cx="9" cy="8.5" r="3.5"/><path d="M2.5 19.5c.8-3.4 3.3-5 6.5-5s5.7 1.6 6.5 5"/>', cloche: '<path d="M3 18h18M5 18a7 7 0 0114 0M12 8V6.5"/>', utensils: '<path d="M7 3v8M4.5 3v5a2.5 2.5 0 005 0V3M7 11v10"/><path d="M17 21V3c-2.2 1.2-3.5 3.6-3.5 7v3H17"/>' };
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true'); svg.innerHTML = paths[name] || '';
        return svg;
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
    $$('[data-tab-jump]').forEach((button) => button.addEventListener('click', () => selectTab(button.dataset.tabJump)));

    /* ---------- Rendering ---------- */
    const chip = (text, tone = '') => el('span', { class: `chip ${tone}` }, text);
    const tone = (status) => ({ Available: 'chip-ok', Reserved: 'chip-warn', Occupied: 'chip-busy', 'Clean-up': 'chip-muted',
        Confirmed: 'chip-ok', Pending: 'chip-warn', 'Checked-in': 'chip-busy', Completed: 'chip-muted', Cancelled: 'chip-danger', 'No-show': 'chip-muted',
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

    function renderOverview(data) {
        const reservations = data.board.reservations;
        const tables = data.board.tables;
        const openOrders = data.queue.filter((o) => !['Served', 'Cancelled'].includes(o.status));
        const money = data.report ? peso(data.report.food_sales) : '—';
        const stat = (label, value, hint, iconName) => el('div', { class: 'overview-stat' },
            el('span', { class: 'overview-stat-icon' }, icon(iconName)),
            el('div', {}, el('p', { class: 'stat-label' }, label), el('strong', {}, value), el('p', { class: 'small text-muted m-0' }, hint)));
        $('[data-overview-stats]').replaceChildren(
            stat("Today's reservations", String(reservations.length), `${reservations.filter((r) => r.status === 'Completed').length} completed · ${reservations.filter((r) => r.status === 'Confirmed').length} confirmed`, 'calendar'),
            stat('Food sales today', money, data.report ? `${data.report.orders} settled orders` : 'Manager view only', 'ticket'),
            stat('Tables occupied', `${tables.filter((t) => t.status === 'Occupied').length} / ${tables.length}`, `${tables.filter((t) => t.status === 'Available').length} available · ${tables.filter((t) => t.status === 'Reserved').length} reserved`, 'users'),
            stat('Kitchen queue', `${openOrders.length} orders`, `${data.queue.filter((o) => o.status === 'Preparing').length} preparing · ${data.queue.filter((o) => o.status === 'Pending').length} pending`, 'cloche'));
        const counts = tables.reduce((all, t) => { all[t.status] = (all[t.status] || 0) + 1; return all; }, {});
        $('[data-room-summary]').replaceChildren(...TABLE_STATUSES.map((status) => el('div', { class: 'room-row' }, chip(status, tone(status)), el('strong', {}, `${counts[status] || 0} ${counts[status] === 1 ? 'table' : 'tables'}`))));
        const bars = [10, 12, 14, 16, 18, 20].map((hour, index) => {
            const value = data.report ? Math.max(0, Math.round(Number(data.report.food_sales) * [0.08, 0.2, 0.48, 0.12, 0.08, 0.04][index])) : 0;
            return el('div', { class: 'sales-bar-wrap' }, el('span', { class: 'sales-bar-value' }, value ? peso(value) : '—'), el('span', { class: 'sales-bar', style: `height:${Math.max(4, Math.min(100, value / Math.max(1, Number(data.report?.food_sales || 1)) * 180))}%` }), el('small', {}, `${hour}–${hour + 2}`));
        });
        $('[data-sales-bars]').replaceChildren(...bars);
        const body = $('[data-overview-bookings] tbody');
        body.replaceChildren(...reservations.slice(0, 5).map((b) => el('tr', {},
            el('td', {}, b.reference_code, el('div', { class: 'small text-muted' }, b.name)),
            el('td', {}, b.table_id), el('td', {}, b.guests), el('td', {}, b.display_time),
            el('td', {}, b.payment_status), el('td', {}, chip(b.status, tone(b.status))))));
    }

    function renderCustomers(customers) {
        const table = $('[data-customers]');
        if (!table) return;
        table.replaceChildren(
            el('thead', {}, el('tr', {}, ['Customer', 'Contact', 'Visits', 'Last visit'].map((h) => el('th', { scope: 'col' }, h)))),
            el('tbody', {}, ...(customers.length ? customers.map((c) => el('tr', {}, el('td', {}, `${c.first_name} ${c.last_name}`), el('td', {}, c.email, el('div', { class: 'small text-muted' }, c.phone_number)), el('td', {}, String(c.visits)), el('td', {}, c.last_visit || '—'))) : [el('tr', {}, el('td', { colspan: '4', class: 'text-muted' }, 'No customers yet.'))])));
    }

    function renderMenu(items) {
        const target = $('[data-menu-admin]');
        if (!target) return;
        if (items) menuItems = items;
        const available = menuItems.filter((item) => Number(item.is_available) === 1).length;
        const summary = $('[data-menu-summary]');
        if (summary) {
            summary.replaceChildren(
                el('strong', {}, `${menuItems.length} items`),
                el('span', { class: 'menu-summary-available' }, `${available} available`),
                el('span', { class: 'menu-summary-sold' }, `${menuItems.length - available} sold out`),
            );
        }
        const visibleItems = menuItems.filter((item) => {
            const matchesQuery = menuQuery === '' || `${item.item_name} ${item.description}`.toLowerCase().includes(menuQuery);
            const matchesFilter = menuFilter === 'all'
                || (menuFilter === 'available' && Number(item.is_available) === 1)
                || (menuFilter === 'sold-out' && Number(item.is_available) !== 1);
            return matchesQuery && matchesFilter;
        });
        const groups = visibleItems.reduce((all, item) => { (all[item.category_name] ||= []).push(item); return all; }, {});
        const categoryTitle = (category) => ({
            'Main Courses': 'Main Courses and Specialties',
            'Appetizers & Soups': 'Appetizers and Soups',
            'Desserts & Drinks': 'Desserts and Drinks',
        }[category] ?? category);
        const sections = Object.entries(groups).map(([category, categoryItems]) => {
            const rows = categoryItems.map((item) => {
                const isAvailable = Number(item.is_available) === 1;
                const toggle = el('input', {
                    type: 'checkbox',
                    checked: isAvailable,
                    'aria-label': `${item.item_name}: ${isAvailable ? 'available' : 'sold out'}`,
                    onchange: (event) => act({ action: 'menu-availability', item_id: item.item_id, available: event.target.checked }),
                });
                return el('div', { class: `menu-admin-row ${isAvailable ? '' : 'is-off'}` },
                    el('div', { class: 'menu-item-name' }, el('strong', {}, item.item_name), el('small', {}, item.description)),
                    el('strong', { class: 'menu-item-price' }, peso(Math.round(Number(item.price) * 100))),
                    chip(isAvailable ? 'Available' : 'Sold out', isAvailable ? 'chip-ok' : 'chip-danger'),
                    el('label', { class: 'menu-switch' }, toggle, el('span', { 'aria-hidden': 'true' }), el('small', {}, isAvailable ? 'On' : 'Off')));
            });
            return el('section', { class: 'menu-admin-section' },
                el('div', { class: 'menu-admin-section-heading' },
                    el('div', {}, el('h2', {}, categoryTitle(category)), el('p', {}, `${categoryItems.length} items · ${categoryItems.filter((item) => Number(item.is_available) === 1).length} available`))
                ),
                el('div', { class: 'menu-admin-table-wrap' },
                    el('div', { class: 'menu-admin-table-head', role: 'row' }, el('span', {}, 'Menu item'), el('span', {}, 'Price'), el('span', {}, 'Status'), el('span', {}, 'Availability')),
                    ...rows));
        });
        target.replaceChildren(...(sections.length ? sections : [el('p', { class: 'menu-empty text-muted' }, 'No menu items match these filters.')]));
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
    $('[data-menu-search]')?.addEventListener('input', (event) => {
        menuQuery = event.target.value.trim().toLowerCase();
        renderMenu();
    });
    $('[data-menu-filter]')?.addEventListener('change', (event) => {
        menuFilter = event.target.value;
        renderMenu();
    });

    async function refresh() {
        if (busy) return;
        busy = true;
        try {
            const data = await request('/admin/api.php?action=dashboard');
            renderBoard(data.board);
            renderQueue(data.queue);
            renderOverview(data);
            renderCustomers(data.customers || []);
            renderMenu(data.menu || []);
            $('[data-live-date]').textContent = new Date(data.now.replace(' ', 'T')).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
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
