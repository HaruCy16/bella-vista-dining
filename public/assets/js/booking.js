(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const csrf = $('meta[name="csrf-token"]')?.content ?? '';
    const peso = (c) => '₱' + (c / 100).toLocaleString('en-PH', { minimumFractionDigits: c % 100 ? 2 : 0, maximumFractionDigits: 2 });
    const card = $('[data-booking]');
    const lookupForm = $('[data-lookup]');
    const list = $('[data-my-bookings]');
    let email = '';

    const el = (tag, attrs = {}, ...children) => {
        const node = document.createElement(tag);
        for (const [key, value] of Object.entries(attrs)) {
            if (key === 'class') node.className = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
            else node.setAttribute(key, value);
        }
        children.flat().forEach((child) => child != null && node.append(child));
        return node;
    };

    const api = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.error || 'Something went wrong. Please try again.');
        return result.data;
    };

    const STATUS = {
        Pending: ['Awaiting payment', 'chip-warn'], Confirmed: ['Confirmed', 'chip-ok'], 'Checked-in': ['Seated', 'chip-ok'],
        Completed: ['Completed', 'chip'], Cancelled: ['Cancelled', 'chip-muted'], 'No-show': ['Released (no-show)', 'chip-muted'],
    };
    const PAYMENT = { Unpaid: 'Not paid yet', Downpayment: 'Deposit paid', Paid: 'Paid in full', 'On-site': 'Pay at the restaurant' };

    function render(b) {
        const [label, tone] = STATUS[b.status] ?? [b.status, 'chip'];
        const error = el('p', { class: 'form-error', role: 'alert', hidden: '' });
        const showError = (message) => { error.replaceChildren(message); error.hidden = false; };
        const busy = (button, text) => { button.disabled = true; button.dataset.label = button.textContent; button.textContent = text; };
        const idle = (button) => { button.disabled = false; button.textContent = button.dataset.label; };

        const pay = (option) => async (event) => {
            busy(event.currentTarget, 'Opening checkout…');
            try {
                const data = await api('/public/api/payments.php', { action: 'start', reference_code: b.reference_code, email, option });
                window.location.assign(data.checkout_url);
            } catch (e) { showError(e.message); idle(event.currentTarget); }
        };

        const actions = el('div', { class: 'booking-actions' });
        const live = b.status === 'Pending' || b.status === 'Confirmed';
        if (live && b.balance > 0) {
            if (b.amount_paid === 0 && b.amount_due > 0) {
                const deposit = Math.min(b.amount_due, Math.ceil((b.amount_due * window.BV_DEPOSIT_PERCENT) / 10000) * 100);
                actions.append(el('button', { class: 'button button-secondary', type: 'button', onclick: pay('deposit') }, `Pay ${window.BV_DEPOSIT_PERCENT}% deposit (${peso(deposit)})`));
            }
            actions.append(el('button', { class: 'button button-primary', type: 'button', onclick: pay('full') }, `Pay ${b.amount_paid ? 'the balance' : 'in full'} (${peso(b.balance)})`));
            if (b.payment_status === 'Unpaid') {
                actions.append(el('button', {
                    class: 'button button-secondary', type: 'button',
                    onclick: async (event) => {
                        busy(event.currentTarget, 'Saving…');
                        try { render(await api('/public/api/payments.php', { action: 'onsite', reference_code: b.reference_code, email })); }
                        catch (e) { showError(e.message); idle(event.currentTarget); }
                    },
                }, 'Pay at the restaurant instead'));
            }
        }

        let cancelBox = null;
        if (b.cancellable) {
            const warning = b.total_fee > 0
                ? (b.refundable_if_cancelled_now
                    ? `Cancelling now is free: your ${peso(b.total_fee)} reservation fee is refundable.`
                    : `It's less than 24 hours before your booking (or the same day), so the ${peso(b.total_fee)} reservation fee is non-refundable.`)
                : 'Cancelling is free for this table.';
            const confirm = el('button', {
                class: 'button button-danger', type: 'button', hidden: '',
                onclick: async (event) => {
                    busy(event.currentTarget, 'Cancelling…');
                    try { render(await api('/public/api/reservations.php', { action: 'cancel', reference_code: b.reference_code, email })); }
                    catch (e) { showError(e.message); idle(event.currentTarget); }
                },
            }, 'Yes, cancel my booking');
            const note = el('p', { class: 'hint', hidden: '' }, warning);
            const ask = el('button', {
                class: 'text-link', type: 'button',
                onclick: () => { ask.hidden = true; note.hidden = false; confirm.hidden = false; confirm.focus(); },
            }, 'Cancel this booking');
            cancelBox = el('div', { class: 'cancel-box' }, ask, note, confirm);
        }

        const money = el('dl', { class: 'money' },
            row('Reservation fee', b.total_fee ? peso(b.total_fee) + ' (credited to your bill)' : 'None'),
            row('Pre-order', b.order_total ? peso(b.order_total) : 'None'),
            row('Paid online', peso(b.amount_paid)),
            b.status === 'Cancelled' && b.fee_refundable !== null && b.total_fee > 0
                ? row('Fee', b.fee_refundable ? 'Refundable' : 'Non-refundable (late cancellation)') : null,
            row('Payment', PAYMENT[b.payment_status] ?? b.payment_status),
        );
        const minimum = b.minimum_order > 0 && b.order_total === 0 && live
            ? el('p', { class: 'hint' }, `Standard tables have a ${peso(b.minimum_order)} minimum food order, settled when you're seated.`) : null;

        card.replaceChildren(...[
            el('div', { class: 'booking-card-head' },
                el('div', {}, el('p', { class: 'small m-0 text-muted' }, 'Reference'), el('p', { class: 'reference m-0' }, b.reference_code)),
                el('span', { class: `chip ${tone}` }, label)),
            el('p', { class: 'booking-when' }, b.display_when),
            el('p', { class: 'm-0 text-muted' }, `${b.name} · ${b.number_of_guests} ${b.number_of_guests === 1 ? 'guest' : 'guests'} · ${b.table_type} table ${b.table_id}`),
            b.items.length ? el('ul', { class: 'booking-items' }, b.items.map((i) => el('li', {}, el('span', {}, `${i.quantity}× ${i.name}`), el('span', {}, peso(i.subtotal))))) : null,
            money, minimum, error, actions.childElementCount ? actions : null, cancelBox,
        ].filter(Boolean));
        card.hidden = false;
        lookupForm.hidden = true;
        if (list) list.hidden = true;
        const back = $('[data-back]');
        if (back) back.hidden = false;
    }

    function row(term, value) {
        return el('div', {}, el('dt', {}, term), el('dd', {}, value));
    }

    lookupForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const error = $('.form-error', form);
        const button = $('button[type=submit]', form);
        error.hidden = true;
        email = form.elements.email.value.trim();
        button.disabled = true;
        try {
            const booking = await api('/public/api/reservations.php', { action: 'lookup', reference_code: form.elements.reference_code.value.trim(), email });
            history.replaceState(null, '', `?ref=${encodeURIComponent(booking.reference_code)}`);
            render(booking);
        } catch (e) {
            $('span', error).textContent = e.message;
            error.hidden = false;
        } finally {
            button.disabled = false;
        }
    });

    if (window.BV_BOOKING) render(window.BV_BOOKING);
})();
