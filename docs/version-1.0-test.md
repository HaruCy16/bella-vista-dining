# Bella Vista Dining · Version 1.0 test report

| | |
|---|---|
| **Date** | 19 November 2026 (demo: 28 November 2026) |
| **Build under test** | Branch `feat/admin-figma` at `14ef271`, plus the uncommitted working-tree changes listed in section 6 |
| **Environment** | Docker (PHP 8.3 / Apache, MySQL 8). Browser checks ran against a throw-away server on the `bella_vista_test` database. The real `bella_vista` database was only read. |
| **Scope** | Guest booking flow, booking management, payments (mock gateway), admin and kitchen screens, security, accessibility, responsive layout |
| **Not tested** | Real phones, PayMongo (not built yet), email delivery (not built), load testing, Safari and Firefox |

## 1. Verdict

**Not ready to tag 1.0 until bug B1 is resolved.** Everything else is minor or a known gap. The core product works: bookings, fees, pre-orders, payments (sandbox), cancellation rules, the kitchen queue, the staff screens and the reports all behave as specified, and nothing leaked in the security probes.

| Area | Result |
|---|---|
| Automated tests (PHPUnit) | **Pass**: 138 tests, 380 assertions |
| Browser end-to-end flows | **Fail**: 2 of 4 flows stop at the booking page (B1). They passed 45/45 earlier today before that page changed. |
| Accessibility (axe, WCAG 2 A/AA) | 9 public pages × 2 widths: 1 failing page, 4 failing nodes (B2). Admin screens: 0 violations (earlier run). |
| Responsive (390 px and 1440 px) | No horizontal overflow on any page |
| Server and data exposure | `.env`, `.git`, `vendor`, `src`, `database`, `docker-compose.yml` and similar all return 403 |
| Access control and CSRF | All checked endpoints refuse anonymous and cross-role calls |

## 2. What was tested and how

1. **PHPUnit**: unit, integration and HTTP tests (including a real concurrent double-booking test) against `bella_vista_test`.
2. **Browser flows (Playwright 1.49)**: (1) Family table with pre-order, deposit, balance; (2) Standard table on a phone, minimum-order block, on-site payment, cancel; (3) two guests racing for one VIP room; (4) staff and kitchen roles, search, check-in, kitchen columns, phone booking, customers, report.
3. **Page sweep**: every public page, the sign-in page and an unknown URL, at 1440 px and 390 px. For each: HTTP status, console errors, failed requests, broken images, missing alt text, horizontal overflow, headings, stray `null`/`undefined` text, and an axe WCAG 2 A/AA scan.
4. **Rule probes over HTTP**: closed day, past date, past time today, off-slot time, 60-day window, guest counts at and outside each table's limits, malformed input, SQL-style input, a script tag in a URL.
5. **Security probes**: private paths, anonymous API calls, POST without a CSRF token, wrong HTTP method, cookie flags, response headers.
6. **Code review**: escaping of output, role checks, CSRF coverage, money handling, session handling, payment gateway switching.

## 3. Spec compliance (client requirements)

| Requirement | Evidence | Result |
|---|---|---|
| Standard S1–S4, 2–4 guests, ₱1,000 minimum order upon seating | `BookingTest`: one guest rejected, under-minimum pre-order rejected, at-minimum accepted | Pass |
| Family F1–F3, 5–8 guests, ₱500 consumable fee | `testValidFamilyBookingIsStoredWithServerComputedFee`, 16 guests rejected | Pass |
| VIP1–VIP2, 15 included, max 20, ₱2,500 + ₱300 per extra guest | 18 guests = ₱3,400 tested; 21 guests rejected | Pass |
| Tue–Sun, 10 AM–10 PM, six 2-hour blocks | Monday, 11:00 start and out-of-hours all rejected with clear messages (checked over HTTP too) | Pass |
| Bookings only in the future | Past date and past time today return "That time has already passed" | Pass |
| Booking window | 60 days ahead; later dates return "Bookings open 60 days ahead" | Pass |
| 15-minute grace period | `testNoShowAfterGracePeriod…` frees the slot and cancels the order | Pass |
| Cancel 24 h ahead; VIP/Family fee non-refundable on the day | Four cancellation tests, including same-day VIP | Pass |
| Two guests cannot take one table | Database unique key plus `FOR UPDATE`; 409 message shown on the seating step | Pass |
| Terms must be accepted | `testTermsMustBeAccepted` | Pass |
| Admin: table statuses, order queue, daily reports | Operations tests and browser flow 4 | Pass |
| Kitchen Staff cannot see reports | 403 on reports, customers and customer record; tab hidden | Pass |

## 4. Bugs to fix

Severity: **High** blocks the demo or release, **Medium** should be fixed before the demo, **Low** polish.

### B1 · High · Booking page no longer opens a booking from its link

**Where:** `public/booking.php`, `public/assets/js/booking.js` (uncommitted edits in the working tree).
**What happens:** The page no longer loads a booking from `?ref=`. It only shows the lookup form. Every place that sends a guest to `booking.php?ref=…` now lands on a blank form and asks for the email again:
- the confirmation page's **Manage booking** link
- **View booking** on the sandbox checkout
- the return from checkout when payment **fails or is cancelled** (the banner says "your booking is still saved below" but nothing is shown)

The **My bookings** list (current and previous bookings) added in commit `e66c6a5` is also gone from the working tree.
**Evidence:** Browser flow 1 stops at "Manage booking" (the booking card never appears). Flow 2 stops the same way. Both passed earlier today.
**Likely cause:** A deliberate change (for example to keep references out of the URL), made without updating the links and tests that depend on the old behaviour. I did not change it.
**Fix:** Decide the intent. (a) Restore the `?ref=` load from the session and the list, or (b) keep the form-only page and change the links, the payment-result banners and the tests to match.

### B2 · Medium · Dates beyond the booking window fail colour contrast

**Where:** `/public/reserve.php`, calendar days past the 60-day window (axe: 6 nodes, contrast 2.02:1, `#b2b7b1` on `#fffefa`; seen at 1440 px and 390 px).
**Note:** These dates are unavailable, and WCAG exempts inactive controls, but the buttons are not truly `disabled`, so scanners flag them.
**Fix:** Mark them `disabled`, or use a text colour that reaches 4.5:1 while keeping the "unavailable" look.

### B3 · Medium · No browser security headers

**Where:** Every response, `.htaccess` / Apache config.
**What happens:** None of `X-Content-Type-Options`, `X-Frame-Options` (or CSP `frame-ancestors`), `Referrer-Policy`, `Content-Security-Policy` or `Strict-Transport-Security` (on HTTPS) is sent. The admin pages can be framed by another site.
**Fix:** Add them with `Header always set …` in `.htaccess` (needs `mod_headers`). Start with `nosniff`, `frame-ancestors 'self'` and `strict-origin-when-cross-origin`.

### B4 · Medium · Public endpoints have no rate limit

**Where:** `public/api/reservations.php` (lookup, create), `public/api/cart.php`.
**What happens:** Admin sign-in is throttled, but lookup and booking creation are not. Anyone can script unlimited bookings and hold tables as Pending until the hold expires, or try many reference and email pairs. Guessing needs both values, so the risk is low; table-holding spam is the practical one.
**Fix:** Reuse the login-throttle idea per IP for booking creation and lookup, and cap open Pending holds per email.

### B5 · Low · Availability endpoint does not validate the guest count

`GET /public/api/availability.php?guests=1` (or 21, 25) returns `200` with an empty list instead of a `422` with a message. The booking endpoint itself rejects these correctly (tested), and the website never sends them.

### B6 · Low · Branded error pages and 404 markup

Unknown and forbidden URLs show Apache's plain default page (no `lang`, unbranded). Add `ErrorDocument 403/404` pointing at a simple page that uses the site header.

### B7 · Low · Stale cache-busting on the sandbox checkout

`public/pay/mock.php` still loads `app.css?v=20261114d`, so it can show an old stylesheet after deploys. Bump it with the others (or generate the version from the file's modified time so it never goes stale).

### B8 · Low · Page structure on the reservation flow

`/public/reserve.php` contains five `<h1>` elements, one per step, with only one visible at a time. Screen readers read it correctly, but a single `<h1>` per page is cleaner. The landing page has two.

### B9 · Low · End-to-end suite is not re-runnable

Flow 1 books a real table, so a second run against the same database fails ("Seating offers only tables that seat 6") until PHPUnit resets the test database. Reset the test database at the start of `flows.mjs`.

### B10 · Info · Housekeeping

- `scripts/cleanup-test-booking.php` (permanently deletes one booking and its records) is untracked. It is CLI-only and validates its input; commit it or leave it out of the repository.
- 8 files have uncommitted changes (section 6). Commit or discard them before tagging.
- Stopped Playwright containers can be removed with `docker container prune`.

## 5. Gaps and open questions (not bugs)

**Features not built yet (found during the QA scan earlier this week):**
- Staff cannot cancel a booking, take a walk-in order, or record a cash or card payment.
- No list of refunds due for cancelled bookings, and no email or SMS confirmations.
- PayMongo is not connected; the mock gateway is the only payment path. Real payments need the client's test keys.
- Admin accounts have no password-change or reset screen.

**Questions for the client (the code makes a choice for each, so please confirm):**
1. **Table fee at checkout:** the Figma and the guest pages say the fee is credited toward the food bill, but the code adds the fee on top of food in the amount due. Which does the client want?
2. **24-hour rule:** "cancel at least 24 hours ahead" is implemented as: later than that, the fee is not refunded. Can they cancel at all after 24 hours?
3. **Deposit amount:** currently 50% or full payment.
4. **VIP minimum party size:** the code uses 9 (the first number above the Family maximum of 8). A group of 9 can only book a VIP room at ₱2,500.

## 6. Build state at the time of testing

Uncommitted changes in the working tree (not part of `14ef271`): `README.md`, `admin/login.php`, `includes/header.php`, `public/assets/css/app.css`, `public/assets/js/booking.js`, `public/booking.php`, `src/input.css`, and the untracked `scripts/cleanup-test-booking.php`.

## 7. What passed cleanly

- All checked pages return the right status, with no console errors, failed requests, broken images or missing alt text.
- No page scrolls sideways at 390 px or 1440 px.
- No stray `null`, `undefined` or `NaN` text on any page.
- Admin and cross-role access: anonymous calls get 401, wrong-role calls get 403.
- POST without a CSRF token returns 419 on every checked public endpoint.
- Private paths (`.env`, `.git`, `vendor`, `src`, `database`, `scripts`, project files) return 403. A script tag in a URL parameter is not reflected.
- Session cookie is `HttpOnly` and `SameSite=Lax`; `Secure` is set automatically on HTTPS.
- The mock payment gateway is switched off when `APP_ENV=production`.
- Money is handled as integer centavos; no float arithmetic in `src/`.
- Output is escaped; the few `innerHTML` uses in `reserve.js` escape text or insert numbers only.
- Admin sign-in is throttled and the session id is regenerated on sign-in and sign-out.
