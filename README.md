# Bella Vista Dining

Bella Vista Dining is a premium farm-to-table restaurant website foundation for Filipino comfort food and continental specialties. This repository contains **Stage 1: branding and UI/UX** and is intentionally structured for continued manual development in VS Code using PHP templates, HTML, TailwindCSS, vanilla JavaScript, and local image references.

## Stage 1 included

The project includes a responsive landing page, menu with category filters and cart drawer, reservation form with fee-preview logic, checkout reference, confirmation reference, About Us, Gallery, hours and policies, branded admin dashboard preview, and kitchen order queue preview. The six approved brand colors, Playfair Display headings, Poppins interface text, original Bella Vista mark, and generated restaurant imagery are used consistently across customer and operations views.

The reservation and checkout states are clearly marked as Stage 1 preview content. No payment is processed, availability is not authoritative, and no customer data is persisted yet.

## Run locally

Install PHP 8.1+ and open a terminal at the project root:

```bash
php -S 127.0.0.1:3000 router.php
```

Then open `http://127.0.0.1:3000`. TailwindCSS is loaded through the official CDN for Stage 1, so there is no build step or Node dependency installation required to preview the pages. JavaScript syntax checks can be run with:

```bash
npm run check:js
```

## Routes

- `/` — Landing page
- `/pages/menu.php` — Digital menu and cart drawer
- `/pages/reservations.php` — Reservation experience
- `/pages/checkout.php` — Cart and checkout reference
- `/pages/confirmation.php` — Confirmation reference
- `/pages/about.php` — About Us
- `/pages/gallery.php` — Gallery
- `/pages/policies.php` — Hours and policies
- `/pages/admin.php` — Admin dashboard visual surface
- `/pages/kitchen.php` — Kitchen order queue visual surface

## Structure

Shared restaurant data and sample pricing live in `includes/data.php`. Shared navigation and footer fragments live in `includes/header.php` and `includes/footer.php`. Page-specific PHP templates are in `pages/`. JavaScript behavior is split into `app.js`, `menu.js`, `cart.js`, and `reservations.js`. Global styling lives in `assets/css/app.css`.

## Stage 2 handoff

Replace the sample arrays in `includes/data.php` with database-backed repositories and keep the existing template fields stable where possible. Add secure authentication and roles, migrations and seed data, server-side operating-day and time-slot validation, overlap-safe table availability, transactional reservation/order creation, authoritative fee calculations, minimum-order enforcement, payment gateway webhooks, and permission checks. The full business rules remain documented in `TODO.md` and the original project brief.

## Generated image note

The generated logo and restaurant images are referenced through Manus-managed storage URLs for the current preview. If you need the ZIP to work fully offline, replace those URLs with local copies under `assets/images/` and `assets/logo/` before continuing production work.
