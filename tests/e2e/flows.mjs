// Browser end-to-end flows (Playwright) against the TEST database.
// How to run: see tests/e2e/README.md. Exits non-zero if any check fails.
import { chromium } from 'playwright';

const BASE = process.env.BASE_URL ?? 'http://app:8097';
const OUT = process.env.OUT_DIR ?? '/e2e/out';
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok: !!ok, detail }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' + detail : ''}`); };

// Dates in Manila time.
const manila = (offsetDays = 0) => {
    const d = new Date(Date.now() + 8 * 3600e3 + offsetDays * 86400e3);
    return d.toISOString().slice(0, 10);
};
const nextOpen = (fromOffset) => { for (let i = fromOffset; ; i++) { const s = manila(i); if (new Date(s + 'T00:00:00Z').getUTCDay() !== 1) return s; } };
const FUTURE = nextOpen(3);
const TODAY_OPEN = new Date(manila(0) + 'T00:00:00Z').getUTCDay() !== 1;

const browser = await chromium.launch();
const newPage = async (opts = {}) => {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 }, ...opts });
    const page = await ctx.newPage();
    page.errors = [];
    page.on('pageerror', (e) => page.errors.push(e.message));
    return page;
};

async function fillDetails(page, email) {
    await page.fill('#checkout-form [name=first_name]', 'Ana');
    await page.fill('#checkout-form [name=last_name]', 'Cruz');
    await page.fill('#checkout-form [name=email]', email);
    await page.fill('#checkout-form [name=phone_number]', '0917 123 4567');
    await page.check('#checkout-form [name=terms_accepted]');
}
async function addQty(page, itemId, times) {
    for (let i = 0; i < times; i++) await page.click(`.menu-row[data-item-id="${itemId}"] [data-qty="1"]`);
}
const next = (page) => page.click('[data-next]');
const enabledTables = (page) => page.$$eval('[data-table]:not([disabled])', (cs) => cs.map((c) => c.dataset.table));
/* From the visit step: go to seating and wait for availability. */
async function toSeating(page) {
    await next(page);
    await page.waitForSelector('.seat-chips[aria-busy="false"]');
}
async function openFlow(page, date, time, guests) {
    await page.goto(`${BASE}/public/reserve.php?date=${date}&time=${time}&guests=${guests}`);
    await toSeating(page);
}

/* 1. Landing picker -> Family table, pre-order, 50% deposit through sandbox checkout, then the balance */
{
    const page = await newPage();
    await page.goto(`${BASE}/index.php`);
    await page.click(`.gathering label:has([name=date][value="${FUTURE}"])`);
    await page.click('.gathering label:has([name=time][value="18:00:00"])');
    await Promise.all([page.waitForURL(/reserve\.php/), page.click('.gathering button[type=submit]')]);
    for (let i = 0; i < 4; i++) await page.click('[data-guests="1"]');
    check('Landing picker carries the date and time into the flow', (await page.textContent('[data-visit-selected]')).length > 0
        && (await page.getAttribute('[data-slot="18:00:00"]', 'aria-checked')) === 'true');
    await toSeating(page);
    const tables = await enabledTables(page);
    check('Seating offers only tables that seat 6', JSON.stringify(tables) === '["F1","F2","F3"]', tables.join(','));
    await page.click('[data-table="F1"]');
    await next(page);
    await addQty(page, 21, 2);
    check('Menu bar shows the pre-order total', (await page.textContent('[data-next-label]')).includes('₱1,160'));
    await next(page);
    const totals = await page.$$eval('[data-review-totals] dd', (d) => d.map((x) => x.textContent));
    check('Review: pre-order ₱1,160 + fee ₱500 = ₱1,660', totals.join('|') === '₱1,160|₱500|₱1,660', totals.join('|'));
    await next(page);
    await page.check('[name=payment_option][value=deposit]');
    await fillDetails(page, 'family@example.test');
    await page.screenshot({ path: `${OUT}/1-checkout-family.png`, fullPage: true });
    await Promise.all([page.waitForURL(/pay\/mock\.php/), next(page)]);
    check('Deposit redirects to sandbox checkout for ₱830', (await page.textContent('.mock-amount')).trim() === '₱830');
    await page.screenshot({ path: `${OUT}/2-mock-checkout.png` });
    await Promise.all([page.waitForURL(/confirmation\.php.*payment=success/), page.click('[data-outcome=success]')]);
    const confirmation = await page.textContent('main');
    check('Confirmation: table waiting, Confirmed, ₱830 paid online', confirmation.includes('your table is waiting') && confirmation.includes('Confirmed') && confirmation.includes('₱830'));
    await page.screenshot({ path: `${OUT}/3-confirmation.png`, fullPage: true });
    await Promise.all([page.waitForURL(/booking\.php/), page.click('text=Manage booking')]);
    await page.waitForSelector('[data-booking]:not([hidden])');
    await Promise.all([page.waitForURL(/pay\/mock\.php/), page.click('text=Pay the balance')]);
    check('Balance checkout is the remaining ₱830', (await page.textContent('.mock-amount')).trim() === '₱830');
    await Promise.all([page.waitForURL(/payment=success/), page.click('[data-outcome=success]')]);
    check('After balance: paid in full, nothing left', (await page.textContent('main')).includes('Paid in full online') && (await page.textContent('.totals-total dd')).trim() === '₱0');
    check('No JavaScript errors (flow 1)', page.errors.length === 0, page.errors.join('; '));
    await page.context().close();
}

/* 2. Standard table on a phone: minimum-order guard, book on-site, manage, cancel */
{
    const page = await newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    await page.goto(`${BASE}/public/menu.php`);
    await Promise.all([page.waitForURL(/reserve\.php/), page.click('.tabbar >> text=Reserve')]);
    await openFlow(page, FUTURE, '12:00:00', 2);
    await page.click('[data-table="S1"]');
    await next(page);
    await page.click('.menu-tab >> nth=1');
    await addQty(page, 11, 1);
    await next(page);
    const hint = await page.textContent('[data-minimum-status]');
    check('Standard pre-order under ₱1,000 shows how much more is needed', hint.includes('Add ₱780 more'), hint.trim());
    await next(page);
    check('Continuing under the minimum is blocked', (await page.textContent('[data-flow-error]')).includes('at least ₱1,000'));
    await page.click('[data-review-list] [data-qty="-1"]');
    await next(page);
    check('Online pay options hide when nothing is due', await page.isHidden('[data-online] >> nth=0'));
    await fillDetails(page, 'standard@example.test');
    await Promise.all([page.waitForURL(/confirmation\.php/), next(page)]);
    const reference = (await page.textContent('.reference-code')).trim();
    check('On-site booking succeeds with an 8-character reference', /^[A-Z2-9]{8}$/.test(reference), reference);
    await page.screenshot({ path: `${OUT}/4-mobile-confirmation.png`, fullPage: true });
    await Promise.all([page.waitForURL(/booking\.php/), page.click('text=Manage booking')]);
    await page.waitForSelector('[data-booking]:not([hidden])');
    check('Manage page shows the minimum-order note', (await page.textContent('[data-booking]')).includes('₱1,000 minimum food order'));
    await page.click('text=Cancel this booking');
    check('Cancel warns that Standard is free to cancel', (await page.textContent('.cancel-box')).includes('free'));
    await page.click('text=Yes, cancel my booking');
    await page.waitForFunction(() => document.querySelector('[data-booking]').textContent.includes('Cancelled'));
    check('Booking shows Cancelled', true);
    await page.screenshot({ path: `${OUT}/5-mobile-cancelled.png`, fullPage: true });
    check('No JavaScript errors (flow 2)', page.errors.length === 0, page.errors.join('; '));
    await page.context().close();
}

/* 3. Two guests race for the same room: the second gets a clear message and fresh tables */
{
    const [a, b] = [await newPage(), await newPage()];
    for (const page of [a, b]) {
        await openFlow(page, FUTURE, '14:00:00', 10);
        await page.click('[data-table="VIP1"]');
        await next(page);
        await next(page); // no pre-order
        await fillDetails(page, page === a ? 'race-a@example.test' : 'race-b@example.test');
    }
    await Promise.all([a.waitForURL(/confirmation\.php/), next(a)]);
    await next(b);
    await b.waitForSelector('[data-flow-error]:not([hidden])');
    const message = await b.textContent('[data-flow-error]');
    check('Second guest sees "just booked" (409)', message.includes('just booked'), message.trim());
    await b.waitForSelector('[data-table="VIP1"][disabled]');
    check('Back on seating, the taken room is unavailable', b.url().endsWith('#seating'));
    await b.screenshot({ path: `${OUT}/6-slot-taken.png` });
    await a.context().close(); await b.context().close();
}

/* 4. Staff: wrong password, kitchen role limits, check-in -> kitchen -> served -> report */
{
    let todayRef = null;
    if (TODAY_OPEN && new Date(Date.now() + 8 * 3600e3).getUTCHours() < 20) {
        const guest = await newPage();
        await openFlow(guest, manila(0), '20:00:00', 5);
        await guest.click('[data-table="F2"]');
        await next(guest);
        await addQty(guest, 23, 1);
        await next(guest);
        await next(guest);
        await fillDetails(guest, 'tonight@example.test');
        await Promise.all([guest.waitForURL(/confirmation\.php/), next(guest)]);
        todayRef = (await guest.textContent('.reference-code')).trim();
        await guest.context().close();
    }

    const kitchen = await newPage();
    await kitchen.goto(`${BASE}/admin/login.php`);
    await kitchen.fill('[name=username]', 'kitchen');
    await kitchen.fill('[name=password]', 'wrong-password');
    await kitchen.click('button[type=submit]');
    check('Wrong password shows a generic error', (await kitchen.textContent('.form-error')).includes('Incorrect username or password'));
    await kitchen.fill('[name=password]', 'e2e-kitchen-pass');
    await Promise.all([kitchen.waitForURL(/admin\/index\.php/), kitchen.click('button[type=submit]')]);
    await kitchen.waitForSelector('[data-board] .board-card');
    check('Kitchen staff: no Daily report tab', !(await kitchen.$('[data-tab=report]')));
    check('Board shows all 9 tables', (await kitchen.$$('[data-board] .board-card')).length === 9);
    if (todayRef) {
        const row = kitchen.locator('[data-bookings] tr', { hasText: todayRef });
        await row.locator('text=Check in').click();
        await kitchen.waitForFunction(() => document.querySelector('[data-board]').textContent.includes('Occupied'));
        await kitchen.click('[data-tab=kitchen]');
        await kitchen.waitForSelector('.ticket');
        check('Checked-in pre-order appears in the kitchen queue for table F2', (await kitchen.textContent('.ticket')).includes('Table F2'));
        await kitchen.click('.ticket >> text=Start preparing');
        await kitchen.waitForSelector('.ticket >> text=Mark served');
        await kitchen.click('.ticket >> text=Mark served');
        await kitchen.waitForFunction(() => document.querySelector('.ticket')?.textContent.includes('Served'));
        check('Kitchen moves the order Pending → Preparing → Served', true);
        await kitchen.screenshot({ path: `${OUT}/7-kitchen-queue.png`, fullPage: true });
    } else {
        check('No bookable slot left today: skipped check-in flow', true);
    }
    check('No JavaScript errors (kitchen)', kitchen.errors.length === 0, kitchen.errors.join('; '));
    await kitchen.context().close();

    const manager = await newPage();
    await manager.goto(`${BASE}/admin/login.php`);
    await manager.fill('[name=username]', 'manager');
    await manager.fill('[name=password]', 'e2e-manager-pass');
    await Promise.all([manager.waitForURL(/admin\/index\.php/), manager.click('button[type=submit]')]);
    await manager.click('[data-tab=report]');
    await manager.waitForSelector('.stat');
    const report = await manager.textContent('[data-report]');
    check('Manager sees the daily report', report.includes('Reservations') && report.includes('Food sales'));
    if (todayRef) check("Today's report counts the served Crispy Pata", report.includes('₱650') && report.includes('Crispy Pata'), report.replace(/\s+/g, ' ').slice(0, 160));
    await manager.screenshot({ path: `${OUT}/8-manager-report.png`, fullPage: true });
    await manager.click('text=Sign out');
    await manager.waitForURL(/admin\/login\.php/);
    check('Sign out returns to the login page', true);
    await manager.context().close();
}

await browser.close();
const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
process.exit(failed.length ? 1 : 0);
