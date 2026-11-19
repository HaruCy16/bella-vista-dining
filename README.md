# Bella Vista Dining

Restaurant table reservation and food ordering system: a customer portal
(menu, reservations, pre-orders, payment) and a secure admin/kitchen dashboard.

**Stack:** PHP 8.3 (no framework) · vanilla JavaScript · Tailwind CSS 3 · MySQL 8.0 · Docker

> School/portfolio project. Menu names, descriptions, images, prices, address
> and contact details are sample content until verified by the client.

---

## Quick start (Docker)

Requirements: Docker Desktop with Compose v2. Nothing else (no local PHP, MySQL or Node needed).

```bash
git clone <repo-url> bella_vista_dining_web_app
cd bella_vista_dining_web_app
docker compose up -d --build --wait
```

| Service | URL | Notes |
|---|---|---|
| Website | http://localhost:8080 | Admin login at `/admin/login.php` |
| phpMyAdmin | http://localhost:8081 | Server `db`, user `bella`, password `bella_dev_password` |
| MySQL | `localhost:3306` | Same credentials; root password `root_dev_password` |

On the **first** start, MySQL builds the database automatically from
`database/schema.sql` and `database/seed.sql`, and creates an empty
`bella_vista_test` database for PHPUnit. You do **not** need to import anything
in phpMyAdmin when using Docker.

Create the admin accounts (the script asks for each password, min 10 characters):

```bash
docker compose exec app php scripts/seed-admin.php manager Manager "Maria Santos"
docker compose exec app php scripts/seed-admin.php kitchen "Kitchen Staff" "Juan Dela Cruz"
```

Everyday commands:

```bash
docker compose up -d            # start
docker compose down             # stop (keeps the database)
docker compose down -v          # stop AND delete the database (next start re-seeds it)
docker compose logs -f app      # PHP / Apache errors
```

Remove one test reservation and its related payments, orders, and customer record:

```bash
docker compose exec app php scripts/cleanup-test-booking.php 4AAZLJWV
```

### Configuration (.env)

Every setting has a working default in `docker-compose.yml`. To change one,
copy `.env.example` to `.env` (git-ignored) and edit it.

| Variable | Default | Purpose |
|---|---|---|
| `APP_PORT` / `PMA_PORT` / `DB_HOST_PORT` | 8080 / 8081 / 3306 | Host ports |
| `DB_NAME` | `bella_vista` | Database name |
| `DB_USER` / `DB_PASSWORD` | `bella` / `bella_dev_password` | App database account (created on first start) |
| `MYSQL_ROOT_PASSWORD` | `root_dev_password` | MySQL root (first start only) |
| `STRIPE_SECRET_KEY` / `STRIPE_WEBHOOK_SECRET` | empty | Stripe **test** keys (Phase 5); empty = Mock payments |

`DB_*` values only apply when the database volume is first created. After
changing them, run `docker compose down -v` to rebuild.

These are development credentials. Never reuse them on a real server.

---

## Database

```
database/
├── schema.sql        tables, keys, CHECK constraints (structure only)
├── seed.sql          table types, 9 tables, 3 menu categories, 50 sample items
└── migrations/       numbered changes for databases that already exist
```

- **The schema is frozen (Phase 0).** Any later change goes in **both**
  `schema.sql` and a new numbered file in `database/migrations/`. See
  [database/migrations/README.md](database/migrations/README.md).
- `schema.sql` has no `CREATE DATABASE`/`USE`, so it also imports into a
  host-named database on shared hosting.
- Admin users are never seeded in SQL. Use `scripts/seed-admin.php`, which
  stores `password_hash()` output.

**Manual import (shared hosting / phpMyAdmin only):** create or select an
empty database, then use **Import** to run `schema.sql`, then `seed.sql`.

### What the database enforces

| Rule | How |
|---|---|
| No double-booking of a table slot | Generated `slot_lock` + unique key `(table_id, reservation_date, start_time, slot_lock)`; cancelled/no-show rows free the slot |
| Valid start times (10, 12, 14, 16, 18, 20) | `CHECK` on `start_time` |
| `end_time = start_time + 2h` | `CHECK` |
| Prices: menu `price > 0`, order `unit_price >= 0`, fees `>= 0` | `CHECK` |
| Quantity `> 0`, payment amount `> 0`, payment has an owner | `CHECK` |
| `order_items.subtotal` | Generated column (`unit_price * quantity`) |
| One record per Stripe payment | Unique `provider_reference` |

### What the service layer must enforce (with tests)

- Guest count within the table type's `capacity_min..capacity_max`.
- Closed Mondays; no bookings in the past (date **and** slot time).
- `orders.table_id` is copied from the reservation, never taken from the client.
- `orders.total_amount` is computed by the server in the same transaction as
  the item inserts, and must equal `SUM(order_items.subtotal)` (tested).
- `dining_tables.status` is **today's** live state only. Future availability
  is always derived from `reservations`.
- `reservations.payment_status` and `payments` are updated in one transaction.
- Pre-orders are `Scheduled` until the reservation day, then released to
  `Pending`; the kitchen queue never shows future pre-orders.
- Cancelling a reservation cancels its pre-orders; reports exclude `Cancelled`.
- Money is handled as integer centavos (or bcmath) in PHP, never floats.

---

## Business rules

- Open **Tuesday to Sunday, 10:00 to 22:00**, closed Mondays.
- Reservations are **2-hour blocks** starting at 10:00, 12:00, 14:00, 16:00, 18:00 or 20:00.
- A table is held **15 minutes** past the start time, then released.

| Table type | Tables | Guests | Reservation fee | Minimum food order |
|---|---|---|---|---|
| Standard | S1–S4 | 1–4 | none | ₱1,000 |
| Family | F1–F3 | 5–8 | ₱500 (consumable) | none |
| VIP room | VIP1–VIP2 | 9–20 (15 included) | ₱2,500 (consumable) + ₱300 per guest above 15 | none |

Example: VIP for 18 guests = ₱2,500 + 3 × ₱300 = **₱3,400**.

- **Cancellation:** allowed up to 24 hours before the start time. Family/VIP
  fees are **non-refundable** when cancelled less than 24 hours before or on
  the day itself. `fee_refundable` is recorded at cancellation time.
- **Terms:** no outside food. Terms must be accepted before checkout and
  `terms_accepted_at` is stored.
- **Payment:** online (downpayment or full; Stripe test mode with a Mock
  fallback) or on-site (cash/card).

### Decisions log

| Decision | Choice |
|---|---|
| Standard guests | **2 to 4** (client spec; migration 001) |
| Standard ₱1,000 minimum | Applies **upon seating**. Pre-ordering is optional; a pre-order for a Standard table must reach ₱1,000 |
| Menu | **3 categories** from the spec: Main Courses (23), Appetizers & Soups (17), Desserts & Drinks (10). Item IDs 1–50 kept stable |
| Fee credit | Final bill = food total − consumable fee, never below ₱0. If food < fee, the unused fee is **forfeited, not refunded**. *Pending client confirmation.* |
| Cancellation | ≥ 24 h before and not on the day itself: fee refundable. Later or same day: still cancellable online, but Family/VIP fees are forfeited (`fee_refundable = 0`) |
| Online payment | Amount due = reservation fee + pre-order. Deposit = 50% (rounded up to the peso); full = remaining balance. Unpaid online bookings are held 30 min |
| No-show | Bookings not checked in 15 min after the start become `No-show` and free the slot (housekeeping runs on availability checks and dashboard refreshes; no cron needed) |
| Kitchen queue | Pre-orders stay `Scheduled` until 1 h before the booking or check-in, then `Pending` → `Preparing` → `Served` (or `Cancelled`) |
| Payment provider | Mock gateway now (`/public/pay/mock.php`, disabled when `APP_ENV=production`); PayMongo test mode next (GCash, Maya, cards). Stripe dropped: no GCash/PayPal, PH merchant accounts unclear |
| `orders.total_amount` | Computed in the service layer, same transaction as items |
| Timezone | `Asia/Manila` everywhere: PHP (`date.timezone`, `date_default_timezone_set`), containers (`TZ`), MySQL session (`SET time_zone = '+08:00'` on connect; Manila has no DST) |
| Web root | The project root is served (shared hosting cannot change it). `.htaccess` returns 403 for dotfiles, `database/`, `includes/`, `src/`, `tests/`, `scripts/`, `docker/`, `sketch/`, `vendor/`, `node_modules/` and tooling files |
| Location & map | **Sample data** (spec gives no address): Santo Tomas, Batangas, set in `Config::ADDRESS_LINES` / `LATITUDE` / `LONGITUDE`. Google Maps Embed API when `GOOGLE_MAPS_EMBED_API_KEY` is set, keyless Google embed otherwise; "Get directions" uses Google Maps URLs (no key) |
| Admin screens | Figma staff frames, built on current functions only. Kitchen Staff: Overview, Reservations, Tables, Kitchen. Manager: also Customers, Daily reports, Menu availability. Customers are Manager only (guest personal data) |
| Staff "New reservation" | Uses the guest booking rules unchanged (slot not started, table fits, Standard minimum), so it covers phone and advance bookings, paid at the restaurant. A guest at the door for a block already under way is rejected; walk-ins, staff order entry, recording cash/card, staff cancellation and a refunds-due list are not built yet |
| Guests in admin | Each booking stores its own `customers` row, so the directory groups guests by email. Spend counts settled (served) orders only |
| Compiled CSS | `public/assets/css/app.css` is committed so hosts without Node work |
| Booking flow | Full page, not a modal: `/public/reserve.php` (visit → seating → menu → pre-order → checkout), then `/public/confirmation.php`. Each step has its own `#hash` so Back works. Landing CTAs link to it, with `?date=&time=&guests=` prefilled by the landing picker. Follows the Figma mobile + desktop frames |
| Checkout total | The flow shows *pre-order + reservation fee* as the amount due, matching "Online payment" above. The Figma checkout instead shows the fee credited against the pre-order (₱3,540 − ₱500). **Open question for the client**; changing it means changing `PaymentService::amountDue()` and `ReservationService::present()` |
| Venue photos | None supplied yet. Dish photos stand in; drop `hero.jpg`, `dining-room.jpg`, `gallery-1..3.jpg` into `public/assets/images/venue/` and `venue_image()` uses them automatically |

---

## Development

### Tests

```bash
docker compose exec app composer install      # first time only
docker compose exec app vendor/bin/phpunit --testdox
```

Tests run against `bella_vista_test`, which is rebuilt from `schema.sql` +
`seed.sql` on every run. They never touch `bella_vista`.

- `tests/Unit`: fee calculator, booking/cancellation rules, validator, Config, Money
- `tests/Integration`: booking, cancellation, payments, kitchen/admin, login throttling, and the admin read models (per-block sales, categories, reservation search, booking detail, guest directory and record) (MySQL)
- `tests/Http`: real HTTP requests (CSRF, 409 race, roles, injection/XSS, malformed input) against a built-in server on the test DB
- `tests/e2e`: browser flows, see [tests/e2e/README.md](tests/e2e/README.md)
- `tests/fixtures/demo-day.php`: fills the **test** database with a realistic service day (every status, all six blocks, orders at each kitchen stage, a returning guest) so the admin screens can be seen with data: `docker compose exec -T -e DB_NAME=bella_vista_test app php tests/fixtures/demo-day.php`. It refuses to run on any database not ending in `_test`, and PHPUnit wipes it.

### Tailwind CSS

Source: `src/input.css`; theme (colors, fonts) in `tailwind.config.js`.

```bash
docker compose run --rm assets                      # build once
docker compose run --rm assets npm run watch:css    # rebuild on change
# or, with Node installed locally: npm install && npm run build:css
```

Commit the rebuilt `public/assets/css/app.css`.

### Project structure

```
index.php                 landing page
public/                   pages (menu, about, visit, contact), api/ JSON endpoints, assets/
admin/                    login, dashboard shell, api.php (admin JSON actions)
public/assets/js/admin*.js  admin front end: core + routing, reservations, customers, report
includes/                 bootstrap.php, db.php (PDO), session.php
src/Support/              Config, Money, Csrf, Auth
src/Repositories/         PDO queries
src/Services/             business rules
includes/header.php, footer.php, partials/   shared layout (with bootstrap, db, session, view helpers)
database/                 schema.sql, seed.sql, migrations/
scripts/                  CLI tools (seed-admin.php)
tests/                    PHPUnit
docker/                   PHP, Apache and MySQL init config
sketch/, context.drawio   CFD / context diagrams, Gantt chart
```

Request flow: `public/` endpoint (thin) → `src/Services` (rules) →
`src/Repositories` (PDO, prepared statements, emulation off) → MySQL.

### Security baseline

PDO prepared statements only · output escaped with `htmlspecialchars` · CSRF
token on every POST and JSON endpoint · `password_hash`/`password_verify` with
`session_regenerate_id` on login · role check on every admin endpoint · errors
logged server-side, never shown to users (`display_errors=Off`) · secrets only
in environment variables.

---

## Status

| Phase | Target | Status |
|---|---|---|
| 0. Freeze: schema, seeds, Docker, env, Tailwind build | Nov 6 | **Done** |
| 1. Foundation: bootstrap, PDO, Config, Money, Response, Validator | Nov 7–8 | **Done** |
| 2. Public pages: landing (about, history, gallery), menu, visit + map | Nov 9–10 | **Done** (Figma redesign Nov 13); venue photos pending |
| 3. Booking engine + PHPUnit rules | Nov 11–13 | **Done** |
| 4. Booking flow, pre-order cart, checkout, confirmation | Nov 13–15 | **Done** |
| 5. Payments (Mock done; PayMongo test mode, webhook) | Nov 15–16 | Mock done; PayMongo needs test keys |
| 6. Admin: roles, table board, kitchen queue, daily report | Nov 16–19 | **Done**, redesigned from Figma Nov 19: Overview, Reservations (filters, booking detail, staff phone booking), Tables, Kitchen columns, Customers + guest record, Daily report, Menu availability |
| 7. QA (PHPUnit + Playwright flows, real phone) | Nov 20–23 | Automated suites pass; real-device test pending |
| 8. Ship | Nov 24–27 | Demo Nov 28 |

**Cut line if behind:** keep booking, cart, mock payment, kitchen queue and
daily report. Drop Stripe webhook polish and report extras first.

### Known issues (found in the Phase 0 review)

All nine were fixed in the backend work (Nov 13) and are covered by tests:
the availability query, hard-coded pre-order IDs, the reused PDO placeholder,
the slot race (now 409), float money, past slots, admin roles/throttling/errors,
the kitchen queue release, and the guest minimum.

### Deployment note (InfinityFree)

InfinityFree needs `pdo_mysql` enabled, and its database names are prefixed,
so select the database before importing `schema.sql` and `seed.sql`. Set
`DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASSWORD` to the values the host gives
you. InfinityFree has no environment-variable panel, so `includes/` will need a
small `.env` loader before deployment (Phase 1 task). InfinityFree shows a browser security check to non-browser clients, so
**Stripe webhooks cannot reach it**. Demo Stripe locally (Stripe CLI) or use
the Mock provider on that host.
