/*
 * Booking flow (public/reserve.php): visit -> seating -> menu -> review -> checkout.
 * Each step has its own #hash so the browser back button and the back gesture work.
 * Progress is kept in sessionStorage (no personal details) so leaving and coming back in the
 * same tab keeps it; an explicit reload clears it and starts a fresh booking (no accounts here).
 * Every number shown here is a display mirror; the server re-validates and re-prices.
 */
(() => {
    'use strict';

    const C = window.BV_RESERVE;
    const root = document.querySelector('[data-flow]');
    if (!C || !root) return;

    const $ = (selector, scope = root) => scope.querySelector(selector);
    const $$ = (selector, scope = root) => [...scope.querySelectorAll(selector)];
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const peso = (centavos) => '₱' + (centavos / 100).toLocaleString('en-PH', { minimumFractionDigits: centavos % 100 ? 2 : 0, maximumFractionDigits: 2 });
    const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;
    const offlineMessage = 'You seem to be offline. Check your connection and try again.';

    const STEPS = ['visit', 'seating', 'menu', 'review', 'checkout'];
    const TITLES = { visit: 'Plan your visit', seating: 'A table for your occasion', menu: 'Fresh from our kitchen', review: 'Your table’s favorites', checkout: 'Make it a date' };
    const STORE = 'bv-reserve';
    const MAX_QTY = 50;
    const MAX_LINES = 30;

    // A reload starts over: drop saved progress, then reopen the bare page so the URL's
    // step and prefill (and any form values the browser would restore) are gone too.
    if (performance.getEntriesByType('navigation')[0]?.type === 'reload') {
        try { sessionStorage.removeItem(STORE); } catch { /* storage blocked */ }
        location.replace(location.pathname);
        return;
    }

    /* ---------- Dates (all YYYY-MM-DD strings in Manila time, handled as UTC dates) ---------- */
    const toDate = (iso) => new Date(iso + 'T00:00:00Z');
    const toIso = (date) => date.toISOString().slice(0, 10);
    const addDays = (iso, days) => { const d = toDate(iso); d.setUTCDate(d.getUTCDate() + days); return toIso(d); };
    const addMonths = (iso, months) => { const d = toDate(iso); d.setUTCDate(1); d.setUTCMonth(d.getUTCMonth() + months); return toIso(d); };
    const firstOfMonth = (iso) => `${iso.slice(0, 7)}-01`;
    const isoWeekday = (iso) => toDate(iso).getUTCDay() || 7;
    const fmt = (iso, options) => toDate(iso).toLocaleDateString('en-PH', { timeZone: 'UTC', ...options });
    const lastDate = addDays(C.today, C.windowDays);
    const slotOpen = (date, time) => date !== C.today || time > C.nowTime;
    const startLabel = (time) => { const h = Number(time.slice(0, 2)); return `${h % 12 || 12} ${h < 12 ? 'AM' : 'PM'}`; };
    const slotLabel = (time) => C.slots.find((s) => s.value === time)?.label ?? '';
    const dateOpen = (iso) => iso >= C.today && iso <= lastDate && isoWeekday(iso) !== C.closedWeekday
        && C.slots.some((s) => slotOpen(iso, s.value));
    const firstOpenDate = () => { for (let i = 0; i <= C.windowDays; i++) { const d = addDays(C.today, i); if (dateOpen(d)) return d; } return C.today; };
    const mondayOf = (iso) => addDays(iso, 1 - isoWeekday(iso));

    /* ---------- State ---------- */
    const clampGuests = (n) => Math.min(C.maxGuests, Math.max(C.minGuests, Number(n) || C.minGuests));
    let state = { date: null, time: null, guests: Math.max(2, C.minGuests), table: null, cart: {} };
    try { state = { ...state, ...JSON.parse(sessionStorage.getItem(STORE) || '{}') }; } catch { /* storage blocked: start fresh */ }
    if (Object.keys(C.prefill).length) {
        state = { ...state, table: null, ...C.prefill };
    }
    state.guests = clampGuests(state.guests);
    if (!state.date || !dateOpen(state.date)) state.date = firstOpenDate();
    if (!state.time || !slotOpen(state.date, state.time)) state.time = defaultTime(state.date);
    const save = () => { try { sessionStorage.setItem(STORE, JSON.stringify(state)); } catch { /* not critical */ } };

    function defaultTime(date) {
        const open = C.slots.filter((s) => slotOpen(date, s.value)).map((s) => s.value);
        return open.includes('18:00:00') ? '18:00:00' : (open[0] ?? null);
    }

    /* ---------- Menu items and money ---------- */
    const items = new Map($$('.menu-row[data-item-id]').map((row) => [row.dataset.itemId, { name: row.dataset.name, price: Number(row.dataset.price) }]));
    for (const id of Object.keys(state.cart)) if (!items.has(id)) delete state.cart[id];

    const typeOfTable = (tableId) => {
        const name = $(`[data-table="${CSS.escape(tableId ?? '')}"]`)?.closest('[data-type]')?.dataset.type;
        return C.types.find((t) => t.name === name) ?? null;
    };
    const tableLabel = (tableId) => (tableId?.startsWith('VIP') ? `VIP Room ${tableId.slice(3)}` : tableId);
    const cartLines = () => Object.entries(state.cart).filter(([, qty]) => qty > 0);
    const food = () => cartLines().reduce((sum, [id, qty]) => sum + items.get(id).price * qty, 0);
    const itemCount = () => cartLines().reduce((sum, [, qty]) => sum + qty, 0);
    /* Mirror of FeeCalculator + PaymentService: amount due = table fee + pre-order. */
    const fee = () => {
        const t = typeOfTable(state.table);
        return t ? t.fee + Math.max(0, state.guests - t.base) * t.extra : 0;
    };
    const due = () => fee() + food();
    const deposit = () => Math.min(due(), Math.ceil((due() * C.depositPercent) / 10000) * 100);
    const minimumShort = () => {
        const t = typeOfTable(state.table);
        return t && t.minimum > 0 && food() > 0 && food() < t.minimum ? t : null;
    };

    /* ---------- Elements ---------- */
    const errorBox = $('[data-flow-error]');
    const nextButton = $('[data-next]');
    const form = $('#checkout-form');
    let current = null;
    let depth = 0; // steps pushed onto history by this page, so Back can use history.back()

    const showError = (message) => { $('span', errorBox).textContent = message; errorBox.hidden = false; };
    const clearError = () => { errorBox.hidden = true; };

    /* ---------- Step 1: calendar, party, time ---------- */
    let calendarMonth = firstOfMonth(state.date);

    function renderVisit() {
        const days = $('[data-cal-days]');
        const gridStart = mondayOf(calendarMonth);
        days.replaceChildren(...Array.from({ length: 42 }, (_, i) => {
            const iso = addDays(gridStart, i);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'cal-day';
            button.setAttribute('role', 'radio');
            button.setAttribute('aria-checked', String(iso === state.date));
            button.setAttribute('aria-label', fmt(iso, { weekday: 'long', month: 'long', day: 'numeric' }) + (isoWeekday(iso) === C.closedWeekday ? ', closed' : ''));
            button.dataset.date = iso;
            button.disabled = !dateOpen(iso);
            button.classList.toggle('is-closed', isoWeekday(iso) === C.closedWeekday);
            button.classList.toggle('is-outside', iso.slice(0, 7) !== calendarMonth.slice(0, 7));
            button.innerHTML = `<small>${fmt(iso, { weekday: 'short' })}</small><b>${toDate(iso).getUTCDate()}</b>`;
            return button;
        }));
        $('[data-cal-month]').textContent = fmt(calendarMonth, { month: 'long', year: 'numeric' });
        $('[data-cal-prev]').disabled = calendarMonth <= firstOfMonth(C.today);
        $('[data-cal-next]').disabled = calendarMonth >= firstOfMonth(lastDate);

        $('[data-guests-value]').textContent = plural(state.guests, 'guest', 'guests');
        $('[data-guests="-1"]').disabled = state.guests <= C.minGuests;
        $('[data-guests="1"]').disabled = state.guests >= C.maxGuests;

        $$('[data-slot]').forEach((slot) => {
            slot.disabled = !slotOpen(state.date, slot.dataset.slot);
            slot.setAttribute('aria-checked', String(slot.dataset.slot === state.time));
        });
        $('[data-visit-selected]').textContent = `${fmt(state.date, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })} selected`;
    }

    root.addEventListener('click', (event) => {
        const day = event.target.closest('[data-date]');
        if (day && !day.disabled) {
            state.date = day.dataset.date;
            if (!state.time || !slotOpen(state.date, state.time)) state.time = defaultTime(state.date);
            state.table = null;
            changed();
            return;
        }
        if (event.target.closest('[data-cal-prev]')) { calendarMonth = addMonths(calendarMonth, -1); renderVisit(); return; }
        if (event.target.closest('[data-cal-next]')) { calendarMonth = addMonths(calendarMonth, 1); renderVisit(); return; }
        const guests = event.target.closest('[data-guests]');
        if (guests) {
            state.guests = clampGuests(state.guests + Number(guests.dataset.guests));
            const t = typeOfTable(state.table);
            if (t && (state.guests < t.min || state.guests > t.max)) state.table = null;
            changed();
            return;
        }
        const slot = event.target.closest('[data-slot]');
        if (slot && !slot.disabled) { state.time = slot.dataset.slot; state.table = null; changed(); return; }

        const chip = event.target.closest('[data-table]');
        if (chip && !chip.disabled) { state.table = chip.dataset.table; changed(); return; }

        const qty = event.target.closest('[data-qty]');
        if (qty) { changeQty(qty.closest('[data-item-id]').dataset.itemId, Number(qty.dataset.qty)); return; }

        const goto = event.target.closest('[data-goto]');
        if (goto) { go(goto.dataset.goto); return; }
        if (event.target.closest('[data-back]')) back();
    });

    /* ---------- Step 2: seating ---------- */
    let free = null; // Set of table ids free in the chosen slot; null while unknown
    let freeKey = '';
    let availabilityRequest = 0;

    async function loadAvailability() {
        const key = `${state.date} ${state.time}`;
        if (key === freeKey && free) return;
        const request = ++availabilityRequest;
        const status = $('[data-seating-status]');
        free = null;
        freeKey = key;
        status.textContent = 'Checking open tables…';
        status.classList.remove('is-error');
        renderSeating();
        try {
            const response = await fetch(`/public/api/availability.php?date=${encodeURIComponent(state.date)}&start_time=${encodeURIComponent(state.time)}`, { headers: { Accept: 'application/json' } });
            const payload = await response.json().catch(() => ({}));
            if (request !== availabilityRequest) return;
            if (!response.ok) throw new Error(payload.error || 'We couldn’t load open tables. Please try again.');
            free = new Set((payload.data || []).map((t) => t.table_id));
            status.textContent = free.size ? '' : 'Fully booked at this time. Go back to pick another time or day.';
            status.classList.toggle('is-error', !free.size);
            if (state.table && !free.has(state.table)) { state.table = null; save(); }
        } catch (error) {
            if (request !== availabilityRequest) return;
            freeKey = '';
            status.textContent = error instanceof TypeError ? offlineMessage : error.message;
            status.classList.add('is-error');
        }
        renderAll();
    }

    function renderSeating() {
        const fitting = C.types.find((t) => state.guests >= t.min && state.guests <= t.max);
        $('[data-seating-hint]').textContent = fitting
            ? `Choose your setting. ${fitting.extra > 0 ? 'A private VIP room' : `${fitting.name} seating`} is just right for ${plural(state.guests, 'guest', 'guests')}.`
            : 'Choose your setting.';
        $$('.seat-card').forEach((card) => {
            const t = C.types.find((type) => type.name === card.dataset.type);
            const fits = t && state.guests >= t.min && state.guests <= t.max;
            const chips = $$('[data-table]', card);
            card.classList.toggle('is-disabled', !fits);
            card.classList.toggle('is-selected', chips.some((c) => c.dataset.table === state.table));
            $('.seat-chips', card).setAttribute('aria-busy', String(free === null));
            chips.forEach((chip) => {
                const open = free?.has(chip.dataset.table) ?? false;
                chip.disabled = !fits || !open;
                chip.classList.toggle('is-booked', fits && free !== null && !open);
                chip.setAttribute('aria-checked', String(chip.dataset.table === state.table));
                chip.title = fits && free !== null && !open ? 'Booked at this time' : '';
            });
            const note = $('[data-seat-note]', card);
            const selected = chips.find((c) => c.dataset.table === state.table);
            note.classList.toggle('is-error', !fits);
            if (!fits) note.textContent = state.guests > t.max ? `Too small for ${state.guests}` : `For ${t.min}–${t.max} guests`;
            else if (selected) note.textContent = `${tableLabel(state.table)} selected · ${t.extra > 0 ? 'A private room for your celebration' : 'A comfortable table for your party'}`;
            else if (free !== null && !chips.some((c) => free.has(c.dataset.table))) note.textContent = 'Fully booked at this time';
            else note.textContent = '';
        });
    }

    /* ---------- Step 3: menu and review ---------- */
    function changeQty(id, step) {
        if (!items.has(id)) return;
        const next = Math.min(MAX_QTY, Math.max(0, (state.cart[id] || 0) + step));
        if (next > 0 && !state.cart[id] && cartLines().length >= MAX_LINES) { showError(`You can pre-order up to ${MAX_LINES} different dishes.`); return; }
        if (next === 0) delete state.cart[id]; else state.cart[id] = next;
        changed();
    }

    function renderMenu() {
        $$('.menu-row[data-item-id]').forEach((row) => {
            const qty = state.cart[row.dataset.itemId] || 0;
            $('[data-qty-value]', row).textContent = qty;
            $('[data-qty="-1"]', row).disabled = qty === 0;
            row.classList.toggle('is-selected', qty > 0);
        });
    }

    const totalsRows = (rows) => rows.filter(Boolean).map(([label, value, cls = '']) => `<div class="${cls}"><dt>${label}</dt><dd>${value}</dd></div>`).join('');
    const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const feeLabel = () => {
        const t = typeOfTable(state.table);
        const extraGuests = t ? Math.max(0, state.guests - t.base) : 0;
        return extraGuests ? `Reservation fee (incl. ${plural(extraGuests, 'extra guest', 'extra guests')})` : 'Reservation fee';
    };
    const moneyRows = () => totalsRows([
        ['Pre-order', peso(food())],
        fee() ? [feeLabel(), peso(fee())] : null,
        ['Total', peso(due()), 'totals-total'],
    ]);

    function renderReview() {
        const lines = cartLines();
        $('[data-review-label]').textContent = `Pre-order for ${state.guests}`;
        $('[data-review-list]').innerHTML = lines.map(([id, qty]) => {
            const item = items.get(id);
            return `<li data-item-id="${escapeHtml(id)}"><div><strong>${escapeHtml(item.name)}</strong><small>${peso(item.price)} each · ${peso(item.price * qty)}</small></div>
                <div class="qty" role="group" aria-label="${escapeHtml(item.name)} quantity"><button type="button" data-qty="-1" aria-label="Remove one ${escapeHtml(item.name)}">−</button><output>${qty}</output><button type="button" data-qty="1" aria-label="Add one ${escapeHtml(item.name)}"${qty >= MAX_QTY ? ' disabled' : ''}>+</button></div></li>`;
        }).join('');
        $('[data-review-list]').hidden = !lines.length;
        $('[data-review-empty]').hidden = !!lines.length;
        $('[data-review-totals]').innerHTML = moneyRows();

        const t = typeOfTable(state.table);
        $('[data-fee-note]').textContent = fee()
            ? `Your ${t.name} table fee of ${peso(fee())} is credited to your food bill at the restaurant.`
            : t && t.minimum ? `${t.name} tables have a ${peso(t.minimum)} minimum food order upon seating.` : '';
        const short = minimumShort();
        const status = $('[data-minimum-status]');
        status.textContent = short ? `Pre-orders for a ${short.name} table must be at least ${peso(short.minimum)}. Add ${peso(short.minimum - food())} more, or remove the pre-order and order on arrival.` : '';
        status.classList.toggle('is-error', !!short);
        $('[data-schedule-note]').textContent = lines.length
            ? `Prepared for your visit, not right now. Your pre-order is scheduled for ${fmt(state.date, { month: 'short', day: 'numeric' })} at ${startLabel(state.time)}.`
            : 'No pre-order? No problem. You can order when you sit down.';
    }

    /* ---------- Step 4: checkout ---------- */
    const payOption = () => form.elements.payment_option.value || 'onsite';

    function renderCheckout() {
        const amount = due();
        $$('[data-online]').forEach((row) => { row.hidden = amount === 0; });
        if (amount === 0) form.elements.payment_option.value = 'onsite';
        $('[data-pay="deposit"]').textContent = peso(deposit());
        $('[data-pay="full"]').textContent = peso(amount);
        $('[data-pay="onsite"]').textContent = amount ? peso(amount) : 'No charge';
        $('[data-checkout-totals]').innerHTML = moneyRows();
        const option = payOption();
        $('[data-pay-note]').textContent = amount === 0
            ? 'Nothing to pay now. You’ll pay for what you order at the restaurant.'
            : option === 'deposit' ? `Pay ${peso(deposit())} now. The remaining ${peso(amount - deposit())} is paid at the restaurant.`
            : option === 'full' ? `Pay ${peso(amount)} now. Anything you add on the day is paid at the restaurant.`
            : `Nothing to pay now. Pay ${peso(amount)} at the restaurant in cash or by card.`;
    }
    form.addEventListener('change', (event) => {
        event.target.removeAttribute('aria-invalid');
        if (event.target.name === 'payment_option') { renderCheckout(); renderAction(); }
    });
    form.addEventListener('input', (event) => event.target.removeAttribute('aria-invalid'));
    form.addEventListener('submit', (event) => { event.preventDefault(); submit(); });

    /* ---------- Shared: context line, summary, action bar ---------- */
    function renderShared() {
        const when = `${fmt(state.date, { weekday: 'short', month: 'short', day: 'numeric' })} · ${slotLabel(state.time)} · ${plural(state.guests, 'guest', 'guests')}`;
        $$('[data-context]').forEach((el) => { el.textContent = state.table ? `${when} · ${tableLabel(state.table)}` : when; });

        const t = typeOfTable(state.table);
        $('[data-summary-rows]').innerHTML = totalsRows([
            ['Date', fmt(state.date, { weekday: 'long', month: 'long', day: 'numeric' })],
            ['Time', `${slotLabel(state.time)} · 2 hours`],
            ['Party', plural(state.guests, 'guest', 'guests')],
            ['Table', t ? `${t.name} ${tableLabel(state.table)}` : 'Not chosen yet'],
        ]);
        $('[data-summary-items]').innerHTML = cartLines().map(([id, qty]) => `<li><span>${qty} × ${escapeHtml(items.get(id).name)}</span><span>${peso(items.get(id).price * qty)}</span></li>`).join('');
        $('[data-summary-totals]').innerHTML = moneyRows();
    }

    function renderAction() {
        const lines = cartLines();
        const meta = {
            visit: 'Step 1 of 4 · Your visit',
            seating: `Step 2 of 4 · ${state.table ? `${typeOfTable(state.table).name} ${tableLabel(state.table)} selected` : 'Choose a table'}`,
            menu: `Sample menu & prices · ${plural(lines.length, 'selection', 'selections')} · ${plural(itemCount(), 'item', 'items')}`,
            review: 'Step 3 of 4 · Review your pre-order',
            checkout: 'Step 4 of 4 · Reservation details',
        };
        const label = {
            visit: 'Choose your seating',
            seating: 'Explore the menu',
            menu: lines.length ? `View pre-order · ${peso(food())}` : 'Continue without pre-order',
            review: 'Continue to checkout',
            checkout: payOption() === 'onsite' || due() === 0 ? 'Confirm booking' : `Confirm & pay ${peso(payOption() === 'deposit' ? deposit() : due())}`,
        };
        $('[data-flow-meta]').textContent = meta[current];
        $('[data-next-label]').textContent = label[current];
    }

    function renderAll() {
        renderVisit();
        renderSeating();
        renderMenu();
        renderReview();
        renderCheckout();
        renderShared();
        renderAction();
    }

    function changed() {
        save();
        clearError();
        renderAll();
        if (current === 'seating') loadAvailability();
    }

    /* ---------- Navigation ---------- */
    const firstIncomplete = () => (!state.date || !state.time || !slotOpen(state.date, state.time) ? 'visit' : !state.table ? 'seating' : null);

    function show(step, { focus = true } = {}) {
        const blocker = firstIncomplete();
        if (blocker && STEPS.indexOf(step) > STEPS.indexOf(blocker)) step = blocker;
        current = step;
        $$('[data-step]').forEach((section) => { section.hidden = section.dataset.step !== step; });
        $('[data-app-title]').textContent = TITLES[step];
        $$('[data-crumb]').forEach((crumb) => {
            const index = STEPS.indexOf(crumb.dataset.crumb.split(' ')[0]);
            crumb.classList.toggle('is-done', index < STEPS.indexOf(step) && !crumb.dataset.crumb.includes(step));
            if (crumb.dataset.crumb.split(' ').includes(step)) crumb.setAttribute('aria-current', 'step'); else crumb.removeAttribute('aria-current');
        });
        document.body.dataset.step = step;
        if (step === 'visit') calendarMonth = firstOfMonth(state.date);
        clearError();
        renderAll();
        if (step === 'seating') loadAvailability();
        if (step === 'menu') document.dispatchEvent(new CustomEvent('bv:menu-shown'));
        if (focus) {
            window.scrollTo({ top: 0 });
            $(`[data-step="${step}"] .flow-heading`)?.focus({ preventScroll: true });
        }
        return step;
    }

    function go(step) {
        const shown = show(step);
        if (location.hash !== '#' + shown) { history.pushState({ step: shown }, '', '#' + shown); depth++; }
    }

    function back() {
        if (depth > 0) { history.back(); return; }
        const previous = { seating: 'visit', menu: 'seating', review: 'menu', checkout: cartLines().length ? 'review' : 'menu' }[current];
        if (!previous) { location.assign('/index.php'); return; }
        const shown = show(previous);
        history.replaceState({ step: shown }, '', '#' + shown);
    }

    window.addEventListener('popstate', () => {
        depth = Math.max(0, depth - 1);
        const step = location.hash.slice(1);
        const shown = show(STEPS.includes(step) ? step : 'visit');
        if (location.hash !== '#' + shown) history.replaceState({ step: shown }, '', '#' + shown);
    });

    nextButton.addEventListener('click', () => {
        clearError();
        if (current === 'visit') {
            if (!state.time || !slotOpen(state.date, state.time)) { showError('Please choose a time.'); return; }
            go('seating');
        } else if (current === 'seating') {
            if (!state.table) { showError('Please choose a table.'); return; }
            go('menu');
        } else if (current === 'menu' || current === 'review') {
            const short = minimumShort();
            if (short) {
                showError(`Pre-orders for a ${short.name} table must be at least ${peso(short.minimum)}. Add ${peso(short.minimum - food())} more, or remove the pre-order.`);
                if (current === 'menu') go('review');
                return;
            }
            go(current === 'menu' && cartLines().length ? 'review' : 'checkout');
        } else {
            submit();
        }
    });

    /* ---------- Submit ---------- */
    const PHONE = /^(\+639|09)\d{9}$/;
    const FIELD_STEP = { reservation_date: 'visit', start_time: 'visit', number_of_guests: 'visit', table_id: 'seating', cart: 'review' };
    let submitting = false;

    async function submit() {
        if (submitting) return;
        const fields = form.elements;
        const phoneOk = PHONE.test(fields.phone_number.value.replace(/[\s\-()]/g, ''));
        const invalid = ['first_name', 'last_name', 'phone_number', 'email'].find((name) => !fields[name].checkValidity() || (name === 'phone_number' && !phoneOk));
        if (invalid) {
            const label = fields[invalid].closest('label').querySelector('span').textContent.toLowerCase();
            showError(invalid === 'phone_number' && fields[invalid].value ? 'Enter a mobile number like 0917 123 4567.'
                : invalid === 'email' && fields.email.value ? 'Enter a valid email address.'
                : `Please fill in your ${label}.`);
            fields[invalid].setAttribute('aria-invalid', 'true');
            fields[invalid].focus();
            return;
        }
        if (!fields.terms_accepted.checked) {
            showError('Please accept the booking policies to continue.');
            fields.terms_accepted.focus();
            return;
        }

        const payload = {
            action: 'create',
            first_name: fields.first_name.value.trim(), last_name: fields.last_name.value.trim(),
            email: fields.email.value.trim(), phone_number: fields.phone_number.value.trim(),
            reservation_date: state.date, start_time: state.time, number_of_guests: state.guests,
            table_id: state.table, cart: Object.fromEntries(cartLines()),
            payment_option: due() === 0 ? 'onsite' : payOption(), terms_accepted: true,
        };

        submitting = true;
        nextButton.disabled = true;
        $('[data-next-label]').textContent = payload.payment_option === 'onsite' ? 'Booking your table…' : 'Opening secure checkout…';
        try {
            const response = await fetch('/public/api/reservations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(result.error || 'We couldn’t complete your booking. Please try again.');
                error.status = response.status;
                error.fields = result.errors || {};
                throw error;
            }
            const booking = result.data;
            try { sessionStorage.removeItem(STORE); } catch { /* ignore */ }
            if (booking.checkout_url) { window.location.assign(booking.checkout_url); return; }
            const params = new URLSearchParams({ ref: booking.reference_code });
            if (booking.payment_error) params.set('payment', 'unavailable');
            window.location.assign(`/public/confirmation.php?${params}`);
        } catch (error) {
            submitting = false;
            nextButton.disabled = false;
            const message = error instanceof TypeError ? offlineMessage : error.message;
            if (error.status === 409) {
                state.table = null; freeKey = ''; save();
                go('seating');
                showError(message);
                return;
            }
            const field = Object.keys(error.fields || {}).find((name) => FIELD_STEP[name]);
            if (field) go(FIELD_STEP[field]);
            for (const name of Object.keys(error.fields || {})) fields[name]?.setAttribute?.('aria-invalid', 'true');
            showError(message);
            renderAction();
        }
    }

    /* ---------- Start ---------- */
    window.addEventListener('pageshow', (event) => { if (event.persisted) { submitting = false; nextButton.disabled = false; renderAction(); } });
    const initial = location.hash.slice(1);
    const start = show(STEPS.includes(initial) ? initial : 'visit', { focus: false });
    history.replaceState({ step: start }, '', '#' + start);
})();
