/*
 * Admin core: routing, shared helpers (window.BVAdmin) and the Overview, Tables, Kitchen and Menu views.
 * Reservations, Customers and the Daily report live in their own scripts and register with BVAdmin.register().
 *
 * Screens are hash routes (#reservations, #reservation/12, #customer/3 ...) so Back works and an open
 * screen survives the 15-second refresh: the refresh only re-renders live data, never the forms.
 * Everything is built with textContent, so nothing from the database is ever parsed as HTML.
 */
(() => {
    'use strict';

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    const csrf = $('meta[name="csrf-token"]').content;
    const peso = (c) => '₱' + (c / 100).toLocaleString('en-PH', { minimumFractionDigits: c % 100 ? 2 : 0, maximumFractionDigits: 2 });
    const POLL_MS = 15000;
    const TABLE_STATUSES = ['Available', 'Reserved', 'Occupied', 'Clean-up'];
    const isManager = $('#main').dataset.manager === '1';
    const errorBox = $('[data-admin-error]');
    const sync = $('[data-sync]');
    let timer = null;
    let busy = false;
    let again = false;
    let dash = null;
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
        children.flat().forEach((c) => c != null && c !== false && node.append(c));
        return node;
    };
    const ICONS = {
        calendar: '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        ticket: '<path d="M4 7.5A1.5 1.5 0 015.5 6h13A1.5 1.5 0 0120 7.5V10a2 2 0 000 4v2.5a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 16.5V14a2 2 0 000-4z"/>',
        users: '<circle cx="9" cy="8.5" r="3.5"/><path d="M2.5 19.5c.8-3.4 3.3-5 6.5-5s5.7 1.6 6.5 5"/>',
        cloche: '<path d="M3 18h18M5 18a7 7 0 0114 0M12 8V6.5"/>',
        utensils: '<path d="M7 3v8M4.5 3v5a2.5 2.5 0 005 0V3M7 11v10"/><path d="M17 21V3c-2.2 1.2-3.5 3.6-3.5 7v3H17"/>',
        clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        info: '<circle cx="12" cy="12" r="8.5"/><path d="M12 10.5v5M12 7.5v.3"/>',
        alert: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v5.5M12 16.2v.3"/>',
        arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
        back: '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        check: '<path d="M5 12.5l4.5 4.5L19 7"/>',
        plus: '<path d="M12 5v14M5 12h14"/>',
    };
    const icon = (name) => {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true'); svg.innerHTML = ICONS[name] || '';
        return svg;
    };

    const showError = (message) => { $('span', errorBox).textContent = message; errorBox.hidden = false; };
    const clearError = () => { errorBox.hidden = true; };

    async function request(url, options = {}) {
        const response = await fetch(url, { headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, ...options });
        const result = await response.json().catch(() => ({}));
        if (response.status === 401) { window.location.assign('/admin/login.php'); throw new Error('Signed out'); }
        if (!response.ok) {
            const error = new Error(result.error || 'Request failed.');
            error.status = response.status;
            error.fields = result.errors || {};
            throw error;
        }
        return result.data;
    }
    const post = (body) => request('/admin/api.php', { method: 'POST', body: JSON.stringify(body) });
    const get = (params) => request('/admin/api.php?' + new URLSearchParams(params));

    async function act(body) {
        clearError();
        try { await post(body); } catch (e) { showError(e.message); }
        await refresh();
    }

    /* ---------- Vocabulary shared by every screen ---------- */
    const chip = (text, tone = '') => el('span', { class: `chip ${tone}` }, text);
    const tone = (status) => ({ Available: 'chip-ok', Reserved: 'chip-warn', Occupied: 'chip-busy', 'Clean-up': 'chip-muted',
        Confirmed: 'chip-ok', Pending: 'chip-warn', 'Checked-in': 'chip-busy', Completed: 'chip-muted', Cancelled: 'chip-danger', 'No-show': 'chip-muted',
        Scheduled: 'chip-warn', Preparing: 'chip-busy', Served: 'chip-ok' }[status] ?? '');
    const STATUS_LABEL = { Pending: 'Awaiting payment', Confirmed: 'Confirmed', 'Checked-in': 'Seated', Completed: 'Completed', Cancelled: 'Cancelled', 'No-show': 'No-show' };
    const PAYMENT_LABEL = { Unpaid: 'Not paid yet', Downpayment: 'Deposit paid', Paid: 'Paid in full', 'On-site': 'Pay at the restaurant' };
    const statusChip = (status) => chip(STATUS_LABEL[status] ?? status, tone(status));
    const tableLabel = (id) => (id.startsWith('VIP') ? `VIP Room ${id.slice(3)}` : id);
    const fmtDate = (iso, options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-PH', options);
    const link = (href, text, cls = 'button button-small button-secondary') => el('a', { class: cls, href }, text);
    const emptyRow = (cols, text) => el('tr', {}, el('td', { colspan: String(cols), class: 'empty-cell' }, text));
    const stat = (label, value, hint, iconName) => el('div', { class: 'overview-stat' },
        el('span', { class: 'overview-stat-icon' }, icon(iconName)),
        el('div', {}, el('p', { class: 'stat-label' }, label), el('strong', {}, value), el('p', { class: 'small text-muted m-0' }, hint)));

    /* ---------- Views and routing ---------- */
    const views = {};
    const listeners = [];
    const tabs = $$('[data-tab]');
    let current = null;
    const TITLES = { overview: 'Overview', reservations: 'Reservations', reservation: 'Booking', 'reservation-new': 'New reservation', tables: 'Tables', kitchen: 'Kitchen orders', customers: 'Customers', customer: 'Guest record', report: 'Daily reports', menu: 'Menu availability' };

    function parseHash() {
        const [first = 'overview', arg = ''] = location.hash.replace(/^#/, '').split('/');
        if (first === 'reservations' && arg === 'new') return { name: 'reservation-new', panel: 'reservation-new', tab: 'reservations', arg: '' };
        if (first === 'reservation') return { name: 'reservation', panel: 'reservation', tab: 'reservations', arg };
        if (first === 'customer') return { name: 'customer', panel: 'customer', tab: 'customers', arg };
        return { name: first || 'overview', panel: first || 'overview', tab: first || 'overview', arg: '' };
    }
    function route(focus = true) {
        let r = parseHash();
        if (!$(`[data-panel="${r.panel}"]`)) r = { name: 'overview', panel: 'overview', tab: 'overview', arg: '' };
        current = r;
        tabs.forEach((t) => { const on = t.dataset.tab === r.tab; t.setAttribute('aria-selected', String(on)); t.tabIndex = on ? 0 : -1; });
        $$('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== r.panel; });
        $('[data-crumb]').textContent = TITLES[r.name] ?? 'Operations';
        document.title = `${TITLES[r.name] ?? 'Operations'} · Bella Vista Dining`;
        clearError();
        views[r.name]?.enter?.(r.arg);
        if (dash) views[r.name]?.dashboard?.(dash);
        if (focus) { window.scrollTo({ top: 0 }); $(`[data-panel="${r.panel}"] h1`)?.focus({ preventScroll: true }); }
    }
    tabs.forEach((t, i) => {
        t.addEventListener('click', () => { location.hash = t.dataset.tab; });
        t.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            e.preventDefault();
            const forward = e.key === 'ArrowDown' || e.key === 'ArrowRight';
            const next = tabs[(i + (forward ? 1 : tabs.length - 1)) % tabs.length];
            next.focus(); location.hash = next.dataset.tab;
        });
    });
    window.addEventListener('hashchange', () => route());

    window.BVAdmin = {
        $, $$, el, icon, peso, chip, tone, statusChip, tableLabel, fmtDate, link, emptyRow, stat, isManager,
        STATUS_LABEL, PAYMENT_LABEL, shortBlock: (label) => label.replace(/ (AM|PM)(?=–)/, ''), request, post, get, act, showError, clearError, refresh: () => refresh(),
        dashboard: () => dash,
        register: (name, view) => { views[name] = view; if (current?.name === name) { view.enter?.(current.arg); if (dash) view.dashboard?.(dash); } },
        onDashboard: (fn) => listeners.push(fn),
    };

    /* ---------- Overview ---------- */
    const minutes = (hhmm) => Number(hhmm.slice(0, 2)) * 60 + Number(hhmm.slice(3, 5));

    function renderOverview(data) {
        const reservations = data.board.reservations;
        const tables = data.board.tables;
        const live = reservations.filter((r) => !['Cancelled', 'No-show'].includes(r.status));
        const openOrders = data.queue.filter((o) => o.status !== 'Served');
        const report = data.report;
        $('[data-overview-stats]').replaceChildren(
            stat("Today's reservations", String(live.length), `${live.filter((r) => r.status === 'Completed').length} completed · ${live.filter((r) => r.status === 'Checked-in').length} seated · ${live.filter((r) => ['Pending', 'Confirmed'].includes(r.status)).length} to come`, 'calendar'),
            stat('Food sales today', report ? peso(report.food_sales) : '—', report ? `${report.orders} orders · ${peso(report.served_sales)} served` : 'Managers see sales', 'ticket'),
            stat('Tables occupied', `${tables.filter((t) => t.status === 'Occupied').length} / ${tables.length}`, `${tables.filter((t) => t.status === 'Available').length} available · ${tables.filter((t) => t.status === 'Reserved').length} reserved · ${tables.filter((t) => t.status === 'Clean-up').length} clean-up`, 'users'),
            stat('Kitchen queue', `${openOrders.length} ${openOrders.length === 1 ? 'order' : 'orders'}`, `${data.queue.filter((o) => o.status === 'Preparing').length} preparing · ${data.queue.filter((o) => o.status === 'Pending').length} pending`, 'cloche'));

        // The banner only says what is true right now.
        const nowMin = minutes(data.now.slice(11, 16));
        const soon = reservations.filter((r) => ['Pending', 'Confirmed'].includes(r.status) && minutes(r.start_time) - nowMin <= 60 && minutes(r.start_time) - nowMin > -Number($('#main').dataset.grace));
        const unpaid = reservations.filter((r) => r.status === 'Pending');
        const parts = [];
        if (soon.length) parts.push(`${soon.length} ${soon.length === 1 ? 'booking arrives' : 'bookings arrive'} within the hour.`);
        if (unpaid.length) parts.push(`${unpaid.length} ${unpaid.length === 1 ? 'booking is' : 'bookings are'} still awaiting an online payment.`);
        const banner = $('[data-overview-alert]');
        banner.hidden = parts.length === 0;
        $('span', banner).textContent = parts.join(' ');

        const salesBars = $('[data-sales-bars]');
        if (salesBars) {
            const blocks = report?.sales_by_block ?? [];
            const max = Math.max(1, ...blocks.map((b) => b.sales));
            salesBars.replaceChildren(...blocks.map((b) => el('div', { class: 'sales-bar-wrap' },
                el('span', { class: 'sales-bar-value' }, b.sales ? peso(b.sales) : '—'),
                el('span', { class: 'sales-bar', style: `height:${b.sales ? Math.max(3, Math.round((b.sales / max) * 100)) : 2}%`, title: `${b.label}: ${peso(b.sales)} from ${b.orders} ${b.orders === 1 ? 'order' : 'orders'}` }),
                el('small', {}, window.BVAdmin.shortBlock(b.label)))));
            $('[data-sales-note]').textContent = `Today · ${report ? report.orders : 0} orders that are not cancelled, by the block they were served in`;
        }

        const counts = tables.reduce((all, t) => { all[t.status] = (all[t.status] || 0) + 1; return all; }, {});
        $('[data-room-bar]').replaceChildren(...TABLE_STATUSES.filter((s) => counts[s]).map((s) => el('span', { class: `room-seg room-seg-${s.toLowerCase()}`, style: `flex-grow:${counts[s]}`, title: `${s}: ${counts[s]}` })));
        $('[data-room-summary]').replaceChildren(...TABLE_STATUSES.map((status) => el('div', { class: 'room-row' }, chip(status, tone(status)), el('strong', {}, `${counts[status] || 0} ${counts[status] === 1 ? 'table' : 'tables'}`))));

        const arrivals = reservations.filter((r) => ['Pending', 'Confirmed'].includes(r.status)).slice(0, 6);
        $('[data-overview-bookings]').replaceChildren(
            el('thead', {}, el('tr', {}, ['Booking · guest', 'Table', 'Guests', 'Starts', 'Payment', 'Status', ''].map((h) => el('th', { scope: 'col' }, h)))),
            el('tbody', {}, ...(arrivals.length ? arrivals.map((b) => el('tr', {},
                el('td', {}, el('span', { class: 'ref' }, b.reference_code), el('div', { class: 'small text-muted' }, b.name)),
                el('td', {}, tableLabel(b.table_id)), el('td', {}, String(b.guests)), el('td', {}, b.display_time),
                el('td', {}, PAYMENT_LABEL[b.payment_status] ?? b.payment_status), el('td', {}, statusChip(b.status)),
                el('td', { class: 'text-right' }, link(`#reservation/${b.reservation_id}`, 'View booking'))))
                : [emptyRow(7, 'No arrivals waiting. New bookings appear here as they come in.')])));
    }
    views.overview = { dashboard: renderOverview };

    /* ---------- Tables ---------- */
    let tableBlock = 'now';
    const currentBlock = (data) => {
        const hour = Number(data.now.slice(11, 13));
        return `${String(Math.min(20, Math.max(10, hour - (hour % 2)))).padStart(2, '0')}:00`;
    };

    function renderBoard(data) {
        const board = data.board;
        const counts = board.tables.reduce((all, t) => { all[t.status] = (all[t.status] || 0) + 1; return all; }, {});
        $('[data-board-legend]').replaceChildren(...TABLE_STATUSES.map((s) => el('span', { class: 'legend-item' }, chip(s, tone(s)), el('strong', {}, String(counts[s] || 0)))),
            el('span', { class: 'legend-note' }, `${board.tables.length} tables and rooms`));
        const block = tableBlock === 'now' ? currentBlock(data) : tableBlock;

        $('[data-board]').replaceChildren(...board.tables.map((t) => {
            const live = t.bookings;
            // Guests who are seated stay on their table whatever the block, so an Occupied card never says "no booking".
            const seated = live.find((b) => b.status === 'Checked-in') ?? null;
            const shown = tableBlock === 'all'
                ? (seated ?? live.find((b) => ['Pending', 'Confirmed'].includes(b.status)) ?? null)
                : (live.find((b) => b.start_time.slice(0, 5) === block.slice(0, 5)) ?? seated);
            const actions = [];
            if (shown && ['Pending', 'Confirmed'].includes(shown.status)) actions.push(el('button', { class: 'button button-small button-primary', type: 'button', onclick: () => act({ action: 'check-in', reservation_id: shown.reservation_id }) }, 'Check in'));
            if (shown && shown.status === 'Checked-in') actions.push(el('button', { class: 'button button-small button-primary', type: 'button', onclick: () => act({ action: 'finish', reservation_id: shown.reservation_id }) }, 'Guests left'));
            if (t.status === 'Clean-up') actions.push(el('button', { class: 'button button-small button-primary', type: 'button', onclick: () => act({ action: 'table-status', table_id: t.table_id, status: 'Available' }) }, 'Mark available'));
            if (shown) actions.push(link(`#reservation/${shown.reservation_id}`, 'View booking'));
            const select = el('select', { 'aria-label': `Set the status of ${tableLabel(t.table_id)}`, onchange: (e) => act({ action: 'table-status', table_id: t.table_id, status: e.target.value }) },
                TABLE_STATUSES.map((s) => el('option', { value: s, selected: s === t.status }, s)));
            return el('article', { class: `board-card status-${t.status.toLowerCase()}` },
                el('div', { class: 'board-card-head' }, el('h2', {}, tableLabel(t.table_id)), chip(t.status, tone(t.status))),
                el('p', { class: 'board-meta' }, icon('users'), `${t.type_name} · ${t.capacity_min}–${t.capacity_max} guests`),
                el('div', { class: 'board-booking' },
                    shown ? [el('strong', {}, `${shown.name} · ${shown.guests} ${shown.guests === 1 ? 'guest' : 'guests'}`),
                        el('span', {}, `${shown.display_time} · ${STATUS_LABEL[shown.status] ?? shown.status}${['Pending', 'Confirmed'].includes(shown.status) ? ` · held until ${shown.grace_ends}` : ''}`)]
                        : [el('strong', {}, tableBlock === 'all' ? 'No more bookings today' : 'No booking in this block'), el('span', {}, 'Open for walk-ins')]),
                el('div', { class: 'board-actions' }, ...actions, el('label', { class: 'board-select' }, el('span', { class: 'sr-only' }, 'Status'), select)));
        }));
    }
    $('[data-table-block]')?.addEventListener('change', (e) => { tableBlock = e.target.value; if (dash) renderBoard(dash); });
    views.tables = { dashboard: renderBoard };

    /* ---------- Kitchen ---------- */
    const ORDER_BUTTON = { Preparing: 'Start preparing', Served: 'Mark served', Cancelled: 'Cancel', Pending: 'Send to kitchen' };
    const EMPTY_COLUMN = { Pending: 'Nothing waiting. New orders land here.', Preparing: 'Nothing on the pass.', Served: 'Nothing served yet today.' };

    function ticket(o) {
        const buttons = o.next.map((s) => el('button', {
            class: `button button-small ${s === 'Cancelled' ? 'button-secondary' : 'button-primary'}`, type: 'button',
            onclick: () => act({ action: 'order-status', order_id: o.order_id, status: s }),
        }, ORDER_BUTTON[s] ?? s));
        return el('article', { class: `ticket ticket-${o.status.toLowerCase()}` },
            el('div', { class: 'ticket-head' }, el('h2', { class: 'ticket-table' }, tableLabel(o.table_id)), el('span', { class: 'ticket-no' }, `#${o.order_id}`)),
            el('p', { class: 'small text-muted m-0' }, [o.source, o.time, o.guests ? `${o.guests} guests` : null, o.reference_code].filter(Boolean).join(' · ')),
            el('ul', { class: 'ticket-items' }, o.items.map((i) => el('li', {}, i))),
            buttons.length ? el('div', { class: 'ticket-actions' }, buttons) : null);
    }

    function renderQueue(data) {
        const queue = data.queue;
        const open = queue.filter((o) => o.status !== 'Served').length;
        $('[data-queue-count]').textContent = open ? String(open) : '';
        const COLUMNS = [['Pending', 'Waiting for the kitchen'], ['Preparing', 'On the pass'], ['Served', 'Out to the table']];
        $('[data-queue]').replaceChildren(...COLUMNS.map(([status, hint]) => {
            const orders = queue.filter((o) => o.status === status);
            const body = orders.length ? orders.map(ticket) : [el('p', { class: 'kitchen-empty' }, EMPTY_COLUMN[status])];
            return el('section', { class: `kitchen-col kitchen-col-${status.toLowerCase()}`, 'aria-label': `${status} orders` },
                el('header', {}, el('div', {}, chip(status, tone(status)), el('small', {}, hint)), el('strong', {}, `${orders.length} ${orders.length === 1 ? 'order' : 'orders'}`)),
                ...body);
        }));
    }
    views.kitchen = { dashboard: renderQueue };

    /* ---------- Menu availability (Manager) ---------- */
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
                    el('div', { class: 'menu-admin-table-head' }, el('span', {}, 'Menu item'), el('span', {}, 'Price'), el('span', {}, 'Status'), el('span', {}, 'Availability')),
                    ...rows));
        });
        target.replaceChildren(...(sections.length ? sections : [el('p', { class: 'menu-empty text-muted' }, 'No menu items match these filters.')]));
    }
    $('[data-menu-search]')?.addEventListener('input', (event) => {
        menuQuery = event.target.value.trim().toLowerCase();
        renderMenu();
    });
    $('[data-menu-filter]')?.addEventListener('change', (event) => {
        menuFilter = event.target.value;
        renderMenu();
    });

    /* ---------- Live refresh ---------- */
    async function refresh() {
        if (busy) { again = true; return; }
        busy = true;
        try {
            dash = await get({ action: 'dashboard' });
            renderOverview(dash);
            renderBoard(dash);
            renderQueue(dash);
            renderMenu(dash.menu || []);
            listeners.forEach((fn) => fn(dash));
            $('[data-live-date]').textContent = new Date(dash.now.replace(' ', 'T')).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
            sync.textContent = `Updated ${new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' })} · refreshes every ${POLL_MS / 1000}s`;
        } catch (e) {
            if (e.message !== 'Signed out') sync.textContent = `Couldn't refresh: ${e.message}`;
        } finally {
            busy = false;
            if (again) { again = false; refresh(); }
        }
        views[current?.name]?.poll?.();
    }

    const schedule = () => { clearInterval(timer); timer = setInterval(() => { if (!document.hidden) refresh(); }, POLL_MS); };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    $$('[data-panel] h1').forEach((h) => { h.tabIndex = -1; });
    route(false);
    refresh();
    schedule();
})();
