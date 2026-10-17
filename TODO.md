# Bella Vista Dining — Stage 1 TODO

## 1. Establish the Bella Vista brand system — complete

- Use Forest Green `#183B2E`, Sage Green `#6B8F7B`, Cream `#F8F6EE`, Warm Beige `#E6D8BC`, Terracotta `#C9754B`, and Walnut Brown `#4B3629` consistently throughout the customer website and admin preview.
- Use Playfair Display for headings and Poppins for body text and interface elements.
- Create an original Bella Vista Dining logo using botanical elements, elegant typography, or a subtle dining-inspired symbol and keep it consistent across customer and admin layouts.
- Build an elegant farm-to-table aesthetic with generous whitespace, high-quality food and restaurant imagery, subtle botanical motifs, rounded cards, restrained shadows, accessible contrast, and responsive layouts.

## 2. Deliver the responsive customer-facing page references — complete

- Landing page includes navigation with logo, Home, About, Menu, Reservations, Gallery, and Login; atmospheric hero photography; the headline “Farm-to-Table Flavors, Unforgettable Moments.”; supporting text; “Reserve a Table” and “Explore Our Menu” CTAs; About section; signature dishes; dining highlights; gallery; operating hours; reservation/order CTA; and footer contact, navigation, policy, and social content.
- Digital menu includes at least three items in each category: Main Courses / Specialties, Appetizers & Soups, and Desserts & Drinks.
- The required menu items are Crispy Pata, Beef Kare-Kare, Grilled Salmon, Caesar Salad, Sinigang na Hipon, Creamy Mushroom Soup, Halo-Halo Cheesecake, Fresh Fruit Shakes, and Iced Tea, with sample Philippine-peso prices, image, description, category, availability state, and add-to-cart controls.
- Menu UI includes category filtering, responsive cards, unavailable states, and a visible add-to-cart acknowledgement.
- Reservation UI includes customer name, email, contact number, reservation date, time slot, guest count, table category, specific table option, optional food pre-order, special requests, fee preview, additional guest fee preview, and minimum-order messaging.
- Checkout UI displays selected items, quantity controls, item prices, subtotal, reservation fee, additional guest fees, minimum-order validation state, total amount due, payment method choices, terms acknowledgement, and reservation summary.
- Checkout distinguishes online downpayment, online full payment, and onsite cash/card payment, and does not represent online payment as completed.
- Confirmation UI displays a unique demo reference, customer/reservation details, assigned-table placeholder, date/time, guest count, food order summary, reservation fee breakdown, payment status, cancellation policy, and 15-minute arrival grace-period reminder, with a print option.
- About, Gallery, Operating Hours, and Policies page references are present and visually consistent.

## 3. Deliver Stage 1 operational preview surfaces — complete

- Admin dashboard preview includes today’s reservations, total food orders, total food sales, pending orders, table occupancy, table management, order management, reservation management, menu management, and report placeholders with explicit Stage 1 preview labeling.
- Kitchen view preview includes a readable order queue with large order identifiers, table references, item quantities, special requests, high-contrast status indicators, and status-update controls.

## 4. Prepare Stage 2 integration seams — open

- Keep PHP fragments reusable and keep menu/table data in one include so database-backed records can replace sample data without rewriting page structure.
- Add secure authentication, role-based authorization, relational database schema, migrations/seed data, server-side reservation validation, overlapping table checks, cart calculations, payment status handling, and database transactions in Stage 2.
- Keep the following business rules for the Stage 2 implementation: Tuesday–Sunday operation, Monday closed; 10:00 AM–10:00 PM operating hours; two-hour slots; Standard tables S1–S4 for 2–4 guests with ₱0 reservation fee and ₱1,000 minimum food order; Family tables F1–F3 for 5–8 guests with ₱500 consumable reservation fee; VIP Room 1–2 up to 15 standard / 20 maximum guests with ₱2,500 consumable reservation fee and `max(0, guests − 15) × ₱300` extra guest fee; no overlapping bookings; 24-hour cancellation policy; 15-minute grace period; outside food prohibited; and transparent payment status.

## 5. Verify the delivered Stage 1 package — open

- Verify key PHP routes through the configured local server and confirm the route manifest returns HTTP 200 JSON.
- Run JavaScript syntax checks and inspect responsive layouts at desktop and mobile widths.
- Confirm sample content is clearly labeled as demo/stage-preview content and no simulated payment is presented as genuine.
- Commit the project to a separate private GitHub repository so it can be downloaded as a ZIP and continued in VS Code.
