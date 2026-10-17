# Bella Vista Dining — Stage 1 Plan

## Scope

Stage 1 delivers the branded, responsive customer-facing experience and visual references for Bella Vista Dining. The work is intentionally frontend-first: all displayed menu prices, fee summaries, availability notes, and confirmation states are sample/demo UI until Stage 2 adds server-side validation, persistence, authentication, and payment confirmation.

The project uses plain PHP templates for reusable page structure, TailwindCSS via the official CDN for utility styling, and vanilla JavaScript modules for navigation, menu filtering, cart state, reservation fee previews, and accessible UI feedback. It stays framework-light so the code can be opened and continued manually in VS Code.

## Design direction

### Design movement

Farm-to-table editorial hospitality: an intimate blend of Philippine garden dining, premium magazine layout, and warm natural materials.

### Core principles

- **Atmospheric restraint:** cream surfaces, walnut typography, generous margins, and strong food photography carry the premium feeling.
- **Warm navigation:** every interaction should feel like a host guiding a guest, with concise microcopy and clear next steps.
- **Natural rhythm:** botanical line motifs, terracotta signals, and organic image crops break up the page without adding visual noise.
- **Conversion with calm:** reservation and menu CTAs stay visible and decisive, while forms remain approachable and low-friction.

### Color philosophy

Cream is the breathing room and dining-room light. Forest green anchors trust and belonging, sage green softens interactive states, warm beige creates tactile card surfaces, terracotta marks moments worth noticing, and walnut brown keeps editorial text grounded and readable. Terracotta is the signature brand accent used sparingly for appetite and action.

| Token | Value | Use |
|---|---|---|
| Forest Green | `#183B2E` | navigation, primary buttons, footer, admin rail |
| Sage Green | `#6B8F7B` | supporting actions, icons, active states |
| Cream | `#F8F6EE` | page background, forms, primary surfaces |
| Warm Beige | `#E6D8BC` | cards, rules, muted sections |
| Terracotta | `#C9754B` | promotion, selected accents, small status signals |
| Walnut Brown | `#4B3629` | headings, body copy, secondary text |

### Layout paradigm

A magazine-style vertical narrative rather than a centered SaaS grid: an offset hero with a floating reservation card, full-bleed image bands, asymmetric text-and-image splits, and editorial menu cards that shift from one column to three columns as space allows.

### Signature elements

1. A botanical monogram mark built from a small leaf sprig and a dining bowl silhouette.
2. Terracotta eyebrow labels and hand-drawn-style divider lines that frame key sections.
3. Dark green image captions and reservation panels that feel like printed menu inserts.

### Interaction philosophy

Interactions should feel like a maître d' confirming a detail: filter changes are immediate, cart updates are visibly acknowledged, form feedback is specific, and disabled or unavailable states explain why rather than simply disappearing.

### Animation

Use short 180–260ms ease-out transitions for buttons, filter pills, cards, drawer opening, and image hover lifts. Respect `prefers-reduced-motion`. No looping decorative animation competes with food imagery or reservation actions.

### Typography system

- **Display:** Playfair Display, 600–700 weight; large editorial headlines with tight line-height and restrained tracking.
- **Interface:** Poppins, 400–600 weight; navigation, labels, prices, form controls, and body copy.
- **Hierarchy:** hero title 56–88px desktop / 42–52px mobile, section titles 40–56px desktop / 34–40px mobile, body 15–17px, labels 11–12px uppercase with tracking.

### Brand essence

**Bella Vista is a garden-side dining destination where Filipino comfort and continental craft meet in an unhurried, memorable setting.** Personality: warm, cultivated, quietly celebratory.

### Brand voice

Headlines are sensory and confident. CTAs are hospitable invitations, not sales commands.

- “Bring your favorite people. We’ll take care of the rest.”
- “A table set for slow afternoons and stories worth keeping.”

### Wordmark & logo

The logo uses a compact leaf-and-bowl emblem beside a refined Bella Vista wordmark. The leaf suggests the estate garden and farm-to-table sourcing; the bowl gives the mark a dining cue without relying on a generic fork-and-knife icon. The full lockup is used in the header/footer; the emblem alone is used for mobile and dashboard rail contexts.

### Signature brand color

Terracotta `#C9754B` is the ownable accent: the color of clay, ember, and the first bite of something warm.

## Application structure

```text
bellavista/
├── index.php                 # Landing page entry point
├── pages/                    # Stage 1 page templates
│   ├── menu.php
│   ├── reservations.php
│   ├── checkout.php
│   ├── confirmation.php
│   ├── about.php
│   ├── gallery.php
│   ├── policies.php
│   ├── admin.php
│   └── kitchen.php
├── includes/                 # Reusable PHP fragments
│   ├── data.php
│   ├── header.php
│   ├── footer.php
│   └── page-shell.php
├── assets/
│   ├── css/app.css
│   ├── js/app.js
│   ├── js/menu.js
│   ├── js/reservations.js
│   ├── js/cart.js
│   ├── images/
│   └── logo/
├── public/manus-routes.json  # Preview route manifest
├── router.php                # PHP dev-server fallback
├── index.html                # Static fallback for simple hosting/inspection
├── README.md
├── TODO.md
└── plan.md
```

## Page and interaction coverage

- Landing page: hero, about preview, signature dishes, experience highlights, gallery preview, hours, CTA, footer.
- Menu: category filters, responsive dish cards, unavailable state, add-to-cart confirmation, cart drawer.
- Reservations: guest details, date/time/party/table category fields, table options, fee breakdown, policy acknowledgement, demo validation feedback.
- Checkout: order summary, quantity controls, reservation fee and extra guest fee rows, payment choice cards, minimum-order warning, terms acknowledgement.
- Confirmation: reference number, reservation summary, assigned-table placeholder, payment status distinction, cancellation and grace-period reminders, print action.
- About/Gallery/Policies: the brand story, image-led dining moments, hours and policies.
- Admin/Kitchen: visual-only dashboard layouts with realistic labeled sample states, explicitly marked as Stage 1 preview surfaces.

## Material constraints and boundaries

Stage 1 uses generated local image assets for the hero, menu, dining room, garden, and logo mark. No online payment is represented as complete. Frontend fee previews mirror the brief's rules for Standard, Family, and VIP table categories but are not authoritative until Stage 2 server-side logic exists. PHP templates are structured for later database integration and do not yet persist customer data.

## Build and verification workflow

Run the local PHP server with `php -S 0.0.0.0:3000 router.php`, verify `/`, `/pages/menu.php`, `/pages/reservations.php`, `/pages/checkout.php`, `/pages/confirmation.php`, `/pages/admin.php`, and `/pages/kitchen.php`, then inspect the route manifest and run the JavaScript syntax checks. The project will be committed to a new private GitHub repository named `bella-vista-dining` so it can be downloaded as a ZIP and continued in VS Code.
