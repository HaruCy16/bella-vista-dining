/*
 * Admin: Reservations list, one booking in full, and the staff "New reservation" form.
 * Filters, typed text and the form live outside the 15-second refresh, which only re-fetches the rows.
 */
(() => {
    'use strict';

    const BV = window.BVAdmin;
    if (!BV) return;
    const { $, $$, el, icon, peso, chip, statusChip, tableLabel, fmtDate, link, emptyRow, stat, PAYMENT_LABEL, STATUS_LABEL, isManager } = BV;

    const hour12 = (hhmm) => { const h = Number(hhmm.slice(0, 2)); return `${h % 12 || 12} ${h < 12 ? 'AM' : 'PM'}`; };
    const blockText = (start, end) => { const [a, b] = [hour12(start), hour12(end)]; return a.slice(-2) === b.slice(-2) ? `${a.slice(0, -3)}–${b}` : `${a}–${b}`; };
    const when = (iso) => new Date(iso.replace(' ', 'T')).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
    const debounce = (fn, ms) => { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); }; };

    /* ================= List ================= */
    const form = $('[data-res-filters]');
    const filters = { q: '', date: form.elements.date.value, block: '', type: '', payment: '', status: '' };
    let listRequest = 0;
    let listData = null;

    const STRIP = [['', 'Bookings'], ['Pending', 'Awaiting payment'], ['Confirmed', 'Confirmed'], ['Checked-in', 'Seated'], ['Completed', 'Completed'], ['Cancelled', 'Cancelled'], ['No-show', 'No-show']];

    async function loadList() {
        if (!filters.date) return;
        const request = ++listRequest;
        const params = { action: 'reservations' };
        for (const [k, v] of Object.entries(filters)) if (v) params[k] = v;
        try {
            const data = await BV.get(params);
            if (request !== listRequest) return;
            listData = data;
            renderList();
        } catch (e) {
            if (request === listRequest && e.message !== 'Signed out') BV.showError(e.message);
        }
    }

    function renderList() {
        const { counts, total, rows } = listData;
        $('[data-res-strip]').replaceChildren(...STRIP.map(([status, label]) => {
            const count = status ? (counts[status] ?? 0) : total;
            if (status && !count && filters.status !== status) return null;
            const on = filters.status === status;
            return el('button', { type: 'button', class: `strip-item${on ? ' is-on' : ''}`, 'aria-pressed': String(on), onclick: () => { filters.status = status; loadList(); } },
                el('strong', {}, String(count)), el('span', {}, label));
        }).filter(Boolean));
        const narrowed = Object.entries(filters).some(([k, v]) => k !== 'date' && v);
        $('[data-res-count]').textContent = `${rows.length}${narrowed ? ` of ${total}` : ''} ${rows.length === 1 ? 'booking' : 'bookings'} for ${fmtDate(listData.date)}${narrowed ? ' match your filters' : ''}.`;
        const clear = el('button', { type: 'button', class: 'text-link', onclick: resetFilters }, 'Clear filters');
        $('[data-bookings] tbody').replaceChildren(...(rows.length ? rows.map((b) => el('tr', {},
            el('td', {}, el('span', { class: 'ref' }, b.reference_code)),
            el('td', {}, b.name, el('div', { class: 'small text-muted' }, b.phone)),
            el('td', {}, tableLabel(b.table_id), el('div', { class: 'small text-muted' }, b.table_type)),
            el('td', {}, String(b.guests)),
            el('td', {}, b.display_time),
            el('td', {}, PAYMENT_LABEL[b.payment_status] ?? b.payment_status, el('div', { class: 'small text-muted' }, peso(b.fee + b.food_total) + ' booked')),
            el('td', {}, statusChip(b.status)),
            el('td', { class: 'text-right' }, link(`#reservation/${b.reservation_id}`, 'View booking'))))
            : [el('tr', {}, el('td', { colspan: '8', class: 'empty-cell' }, narrowed ? ['Nothing matches these filters. ', clear] : 'No reservations for this date yet.'))]));
    }

    function resetFilters() {
        form.reset();
        form.elements.date.value = filters.date;
        Object.assign(filters, { q: '', block: '', type: '', payment: '', status: '' });
        loadList();
    }
    form.addEventListener('submit', (e) => e.preventDefault());
    form.addEventListener('input', debounce((e) => { if (e.target.name === 'q') { filters.q = e.target.value.trim(); loadList(); } }, 250));
    form.addEventListener('change', (e) => { if (e.target.name && e.target.name !== 'q') { filters[e.target.name] = e.target.value; loadList(); } });

    BV.register('reservations', { enter: () => loadList(), poll: () => loadList() });

    /* ================= One booking ================= */
    let detailId = null;
    let detailRequest = 0;
    const holder = $('[data-reservation-detail]');

    async function loadDetail(id, quiet = false) {
        const request = ++detailRequest;
        if (!quiet) holder.replaceChildren(el('p', { class: 'text-muted' }, 'Loading booking…'));
        try {
            const d = await BV.get({ action: 'reservation', id });
            if (request !== detailRequest || detailId !== id) return;
            renderDetail(d);
        } catch (e) {
            if (request !== detailRequest) return;
            holder.replaceChildren(el('div', { class: 'admin-panel' }, el('h1', { class: 'admin-h', tabindex: '-1' }, 'Booking not found'),
                el('p', { class: 'text-muted' }, e.message), link('#reservations', 'Back to reservations')));
        }
    }

    const kv1 = (rows) => el('dl', { class: 'kv kv-1' }, rows.filter(Boolean).map(([k, v, cls = '']) => el('div', { class: cls }, el('dt', {}, k), el('dd', {}, v))));
    const kv = (rows) => el('dl', { class: 'kv' }, rows.filter(Boolean).map(([k, v, cls = '']) => el('div', { class: cls }, el('dt', {}, k), el('dd', {}, v))));
    const panel = (title, sub, ...body) => el('section', { class: 'admin-panel' }, el('div', { class: 'panel-heading' }, el('div', {}, el('h2', {}, title), sub ? el('p', {}, sub) : null)), ...body);

    function renderDetail(d) {
        const initials = d.name.split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase();
        const actions = [];
        if (d.can_check_in) actions.push(el('button', { class: 'button button-primary', type: 'button', onclick: async () => { await BV.act({ action: 'check-in', reservation_id: d.reservation_id }); loadDetail(detailId, true); } }, 'Check in'));
        if (d.can_finish) actions.push(el('button', { class: 'button button-primary', type: 'button', onclick: async () => { await BV.act({ action: 'finish', reservation_id: d.reservation_id }); loadDetail(detailId, true); } }, 'Guests left'));
        actions.push(link('#reservations', [icon('back'), ' Back to reservations'], 'button button-secondary'));

        const itemsTotal = d.items.reduce((sum, i) => sum + i.subtotal, 0);
        const food = d.items.length
            ? el('div', { class: 'table-scroll table-flush' }, el('table', { class: 'data-table' },
                el('thead', {}, el('tr', {}, ['Item', 'Qty', 'Price', 'Amount'].map((h, i) => el('th', { scope: 'col', class: i ? 'text-right' : '' }, h)))),
                el('tbody', {}, ...d.items.map((i) => el('tr', {}, el('td', {}, i.name), el('td', { class: 'text-right' }, String(i.quantity)), el('td', { class: 'text-right' }, peso(i.unit_price)), el('td', { class: 'text-right' }, peso(i.subtotal)))),
                    el('tr', { class: 'total-row' }, el('td', { colspan: '3' }, 'Pre-order total'), el('td', { class: 'text-right' }, peso(itemsTotal))))))
            : el('p', { class: 'text-muted m-0' }, 'No pre-order. The guest orders at the table.');

        holder.replaceChildren(
            el('div', { class: 'admin-heading-row' },
                el('div', {}, el('h1', { class: 'admin-h', tabindex: '-1' }, `Booking ${d.reference_code}`), el('p', { class: 'text-muted' }, `${d.name} · ${d.display_when}`)),
                el('div', { class: 'heading-actions' }, ...actions)),
            el('div', { class: 'chip-row' }, statusChip(d.status), chip(PAYMENT_LABEL[d.payment_status] ?? d.payment_status, d.payment_status === 'Unpaid' ? 'chip-warn' : 'chip-ok'),
                chip(`${tableLabel(d.table_id)} · ${d.table_type}`), d.order_statuses.length ? chip(`Order: ${d.order_statuses.join(', ')}`, 'chip-muted') : null),
            el('div', { class: 'detail-grid' },
                el('div', { class: 'stack' },
                    panel('Guest and reservation', null,
                        el('div', { class: 'guest-head' }, el('span', { class: 'avatar' }, initials), el('div', {}, el('strong', {}, d.name), el('p', { class: 'small text-muted m-0' }, `${d.email} · ${d.phone}`)),
                            isManager ? link(`#customer/${d.customer_id}`, 'Guest record') : null),
                        kv([['Table', `${tableLabel(d.table_id)} · ${d.table_type}`], ['Date', fmtDate(d.reservation_date)], ['Dining block', blockText(d.start_time, d.end_time)], ['Party', `${d.number_of_guests} ${d.number_of_guests === 1 ? 'guest' : 'guests'}`],
                            ['Reference', el('span', { class: 'ref' }, d.reference_code)], ['Booked', when(d.created_at)]]),
                        el('p', { class: 'admin-banner admin-banner-flat' }, icon('info'), el('span', {},
                            ['Pending', 'Confirmed'].includes(d.status) ? `The table is held until ${d.grace_ends}, then released.` : `This booking is ${STATUS_LABEL[d.status] ?? d.status}.`,
                            d.minimum_order > 0 ? ` ${d.table_type} tables have a ${peso(d.minimum_order)} minimum food order upon seating.` : ''))),
                    panel('Pre-ordered food', d.items.length ? `${d.items.length} ${d.items.length === 1 ? 'dish' : 'dishes'} · sent to the kitchen when the guests are checked in` : null, food)),
                el('div', { class: 'stack' },
                    panel('Bill summary', null,
                        kv1([['Pre-order', peso(d.order_total)], d.base_fee ? ['Reservation fee', peso(d.base_fee)] : null, d.extra_fee ? ['Extra-guest charge', peso(d.extra_fee)] : null,
                            ['Total', peso(d.amount_due), 'kv-total'], ['Paid online', d.amount_paid ? `−${peso(d.amount_paid)}` : peso(0)]]),
                        el('p', { class: 'balance' }, el('span', {}, d.status === 'Cancelled' ? 'Balance' : 'Balance at the restaurant'), el('strong', {}, peso(d.status === 'Cancelled' ? 0 : d.balance))),
                        d.total_fee ? el('p', { class: 'small text-muted m-0' }, d.status === 'Cancelled'
                            ? `Fee ${d.fee_refundable ? 'refundable: cancelled at least 24 hours ahead' : 'not refundable: cancelled late'}.`
                            : 'The reservation fee is credited to the food bill.') : null),
                    panel('Payment record', null, d.payments.length
                        ? el('ul', { class: 'pay-record' }, d.payments.map((p) => el('li', {}, el('div', {}, el('strong', {}, `${p.type === 'Full' ? 'Full payment' : 'Downpayment'} · ${p.method}`), el('span', { class: 'small text-muted' }, when(p.at))),
                            el('div', { class: 'text-right' }, el('strong', {}, peso(p.amount)), chip(p.state, p.state === 'Succeeded' ? 'chip-ok' : p.state === 'Pending' ? 'chip-warn' : 'chip-muted')))))
                        : el('p', { class: 'text-muted m-0' }, d.payment_status === 'On-site' ? 'No online payment. The guest pays at the restaurant in cash or by card.' : 'No payment recorded yet.')))));
    }

    BV.register('reservation', {
        enter: (arg) => { detailId = Number(arg) || 0; if (detailId) loadDetail(detailId); },
        poll: () => { if (detailId) loadDetail(detailId, true); },
    });

    /* ================= New reservation ================= */
    const nf = $('[data-new-res]');
    const newState = { block: null, table: null, tables: [], key: '' };
    let availabilityRequest = 0;
    const errorBox = $('[data-new-error]');
    const submit = $('[data-new-submit]');

    const today = () => (BV.dashboard()?.now ?? new Date().toISOString()).slice(0, 10);
    const nowTime = () => (BV.dashboard()?.now ?? '').slice(11, 19);
    function firstOpenDate() {
        let day = new Date(`${today()}T00:00:00`);
        if (nowTime() >= '20:00:00') day.setDate(day.getDate() + 1);
        while (day.getDay() === 1) day.setDate(day.getDate() + 1);   // closed Mondays
        return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
    }
    const slotOpen = (slot) => nf.elements.reservation_date.value !== today() || !nowTime() || slot > nowTime();
    const guests = () => Math.max(0, Math.floor(Number(nf.elements.number_of_guests.value) || 0));
    const chosenTable = () => newState.tables.find((t) => t.table_id === newState.table) ?? null;
    const fee = () => { const t = chosenTable(); return t ? t.reservation_fee + Math.max(0, guests() - t.base_capacity) * t.extra_charge : 0; };

    function showNewError(message) { $('span', errorBox).textContent = message; errorBox.hidden = false; }

    function renderNew() {
        $$('[data-block]', nf).forEach((b) => { b.disabled = !slotOpen(b.dataset.block); b.setAttribute('aria-checked', String(b.dataset.block === newState.block)); });
        const t = chosenTable();
        const box = $('[data-new-tables]');
        box.setAttribute('aria-busy', String(newState.key === '' && !!newState.block));
        box.replaceChildren(...newState.tables.map((tbl) => el('button', { type: 'button', role: 'radio', 'aria-checked': String(tbl.table_id === newState.table), class: 'seat-chip', 'data-table': tbl.table_id,
            onclick: () => { newState.table = tbl.table_id; renderNew(); } }, icon('check'), el('span', {}, `${tableLabel(tbl.table_id)} · ${tbl.type_name}`))));
        $('[data-new-summary]').replaceChildren(...[
            ['Date', nf.elements.reservation_date.value ? fmtDate(nf.elements.reservation_date.value) : 'Not chosen'],
            ['Dining block', newState.block ? $(`[data-block="${newState.block}"]`, nf).textContent : 'Not chosen'],
            ['Party', guests() ? `${guests()} guests` : 'Not chosen'],
            ['Table', t ? `${tableLabel(t.table_id)} · ${t.type_name}` : 'Not chosen'],
            ['Reservation fee', t ? (fee() ? peso(fee()) : 'None') : '—'],
            ['Due at the restaurant', t ? (fee() ? `${peso(fee())} + food` : 'Food only') : '—', 'kv-total'],
        ].map(([k, v, cls = '']) => el('div', { class: cls }, el('dt', {}, k), el('dd', {}, v))));
    }

    async function loadTables() {
        const date = nf.elements.reservation_date.value;
        const status = $('[data-new-status]');
        newState.tables = []; newState.table = null; newState.key = '';
        if (!date || !newState.block || guests() < 1) { status.textContent = newState.block ? '' : 'Choose a date, party size and dining block to see open tables.'; status.classList.remove('is-error'); renderNew(); return; }
        const request = ++availabilityRequest;
        status.textContent = 'Checking open tables…'; status.classList.remove('is-error');
        renderNew();
        try {
            const tables = await BV.request(`/public/api/availability.php?${new URLSearchParams({ date, start_time: newState.block, guests: String(guests()) })}`);
            if (request !== availabilityRequest) return;
            newState.tables = tables; newState.key = `${date} ${newState.block} ${guests()}`;
            status.textContent = tables.length ? `${tables.length} ${tables.length === 1 ? 'table fits' : 'tables fit'} ${guests()} guests in this block.` : 'No table is free for this party in that block. Try another block or day.';
            status.classList.toggle('is-error', tables.length === 0);
        } catch (e) {
            if (request !== availabilityRequest) return;
            status.textContent = e.message; status.classList.add('is-error');
        }
        renderNew();
    }

    nf.addEventListener('click', (e) => { const b = e.target.closest('[data-block]'); if (b && !b.disabled) { newState.block = b.dataset.block; loadTables(); } });
    nf.elements.reservation_date.addEventListener('change', () => { if (newState.block && !slotOpen(newState.block)) newState.block = null; loadTables(); });
    nf.elements.number_of_guests.addEventListener('change', loadTables);
    nf.addEventListener('input', (e) => { e.target.removeAttribute?.('aria-invalid'); if (e.target.name === 'number_of_guests') renderNew(); });

    const PHONE = /^(\+639|09)\d{9}$/;
    nf.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorBox.hidden = true;
        const f = nf.elements;
        const labels = { first_name: 'a first name', last_name: 'a last name', phone_number: 'a mobile number', email: 'an email address', reservation_date: 'a date' };
        const bad = Object.keys(labels).find((n) => !f[n].value.trim() || !f[n].checkValidity());
        if (bad) { f[bad].setAttribute('aria-invalid', 'true'); f[bad].focus(); showNewError(`Please enter ${labels[bad]}.`); return; }
        if (!PHONE.test(f.phone_number.value.replace(/[\s\-()]/g, ''))) { f.phone_number.setAttribute('aria-invalid', 'true'); f.phone_number.focus(); showNewError('Enter a mobile number like 0917 123 4567.'); return; }
        if (!newState.block) { showNewError('Please choose a dining block.'); return; }
        if (!newState.table) { showNewError('Please choose a table.'); return; }
        if (!f.terms_accepted.checked) { f.terms_accepted.focus(); showNewError('Please confirm you have told the guest the booking policies.'); return; }
        submit.disabled = true; submit.textContent = 'Creating reservation…';
        try {
            const result = await BV.post({
                action: 'staff-book', first_name: f.first_name.value.trim(), last_name: f.last_name.value.trim(), email: f.email.value.trim(), phone_number: f.phone_number.value.trim(),
                reservation_date: f.reservation_date.value, start_time: newState.block, number_of_guests: guests(), table_id: newState.table, terms_accepted: true,
            });
            BV.refresh();
            showDone(result);
        } catch (err) {
            for (const name of Object.keys(err.fields || {})) f[name]?.setAttribute?.('aria-invalid', 'true');
            showNewError(err.message);
            if (err.status === 409) loadTables();
        } finally {
            submit.disabled = false; submit.textContent = 'Create reservation';
        }
    });

    function showDone(result) {
        $('.detail-grid', $('[data-panel="reservation-new"]')).hidden = true;
        const done = $('[data-new-done]');
        done.hidden = false;
        done.replaceChildren(el('section', { class: 'admin-panel done-panel' },
            el('span', { class: 'done-seal' }, icon('check')),
            el('h2', {}, 'Reservation created'),
            el('p', { class: 'text-muted' }, 'Give the guest this reference. They can use it with their email to look up or cancel the booking online.'),
            el('p', { class: 'reference-code' }, result.reference_code),
            el('div', { class: 'form-actions form-actions-center' },
                link(`#reservation/${result.reservation_id}`, 'View booking', 'button button-primary'),
                el('button', { class: 'button button-secondary', type: 'button', onclick: resetNew }, 'Add another'),
                link('#reservations', 'Back to reservations', 'button button-secondary'))));
        done.querySelector('h2').tabIndex = -1; done.querySelector('h2').focus();
    }

    function resetNew() {
        nf.reset();
        $('.detail-grid', $('[data-panel="reservation-new"]')).hidden = false;
        $('[data-new-done]').hidden = true;
        errorBox.hidden = true;
        $$('[aria-invalid]', nf).forEach((n) => n.removeAttribute('aria-invalid'));
        Object.assign(newState, { block: null, table: null, tables: [], key: '' });
        nf.elements.reservation_date.value = firstOpenDate();
        nf.elements.number_of_guests.value = '2';
        let prefill = null;
        try { prefill = JSON.parse(sessionStorage.getItem('bv-admin-prefill') || 'null'); sessionStorage.removeItem('bv-admin-prefill'); } catch { /* storage blocked */ }
        if (prefill) for (const [k, v] of Object.entries(prefill)) if (nf.elements[k]) nf.elements[k].value = v;
        loadTables();
    }

    BV.register('reservation-new', { enter: resetNew });
})();
