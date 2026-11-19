---
name: Bella Vista Dining
description: "A calm, garden-side restaurant site: warm paper, deep estate green, serif headlines, unhurried motion."
colors:
  canvas: "#F6F3EB"
  canvas-mobile: "#F7F1E5"
  paper: "#FFFEFA"
  paper-mobile: "#FFFDF8"
  ink: "#263D32"
  ink-mobile: "#29352F"
  ink-staff: "#2B2B2B"
  forest: "#203F32"
  forest-mobile: "#1A3721"
  line: "#DADDD1"
  line-mobile: "#DCDDCF"
  line-staff: "#DDDCCF"
  white: "#FFFFFF"
  muted: "#657068"
  pine: "#2F5D50"
  clay: "#99583C"
  rust: "#B35F37"
  rust-hover: "#9E5230"
  mint: "#E6ECDC"
  mist: "#EEF0E9"
  sage: "#E1E8DA"
  qty-btn: "#F0F0EA"
  pay-on: "#EDF2EA"
  sand: "#F1E6D5"
  cream: "#D3DCCD"
  gold: "#C9A24B"
  butter: "#FBF1D8"
  grove: "#1A3721"
  olive: "#286343"
  overlay: "#16382B"
  danger: "#B3261E"
  danger-ink: "#A13831"
  danger-bg: "#FBEDEA"
  danger-dot: "#C2453D"
  ok-ink: "#286343"
  ok-bg: "#EDF1E8"
  ok-dot: "#4C9A6A"
  warn-ink: "#805D0A"
  warn-bg: "#F8EFD7"
  warn-dot: "#E0A100"
  seated-ink: "#356483"
  seated-bg: "#EAF1F7"
  seated-dot: "#5B8DB8"
  idle-ink: "#5D625B"
  idle-bg: "#EEEFEA"
  idle-dot: "#8A8A8A"
typography:
  display:
    fontFamily: "Newsreader, Georgia, serif"
    fontSize: "clamp(2.5rem, 4.6vw, 4.25rem)"
    fontWeight: 500
    lineHeight: 1.02
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Newsreader, Georgia, serif"
    fontSize: "clamp(2rem, 3.4vw, 3rem)"
    fontWeight: 500
    lineHeight: 1.06
    letterSpacing: "-0.02em"
  heading-lg:
    fontFamily: "Newsreader, Georgia, serif"
    fontSize: "2rem"
    fontWeight: 500
    lineHeight: 1.12
    letterSpacing: "-0.015em"
  heading:
    fontFamily: "Newsreader, Georgia, serif"
    fontSize: "1.5rem"
    fontWeight: 500
    lineHeight: 1.2
    letterSpacing: "-0.01em"
  subheading:
    fontFamily: "Newsreader, Georgia, serif"
    fontSize: "1.25rem"
    fontWeight: 500
    lineHeight: 1.3
    letterSpacing: "-0.005em"
  lead:
    fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: 1.6
  body:
    fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.6
  small:
    fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "0.005em"
  caption:
    fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: "0.01em"
  label:
    fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 700
    lineHeight: 1.4
    letterSpacing: "0.12em"
  button:
    fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1
rounded:
  badge: "6px"
  qty: "8px"
  thumb: "8px"
  ctl: "10px"
  card: "16px"
  panel: "20px"
  full: "9999px"
spacing:
  gutter-sm: "16px"
  gutter-md: "24px"
  gutter-lg: "32px"
  section-gap: "64px"
  section-gap-lg: "80px"
  card-pad: "20px"
  row-gap: "10px"
components:
  button-forest:
    backgroundColor: "{colors.forest}"
    textColor: "{colors.paper}"
    typography: "{typography.button}"
    rounded: "{rounded.ctl}"
    padding: "0 20px"
    height: "48px"
  button-forest-hover:
    backgroundColor: "{colors.grove}"
  button-rust:
    backgroundColor: "{colors.rust}"
    textColor: "{colors.white}"
    typography: "{typography.button}"
    rounded: "{rounded.ctl}"
    padding: "0 20px"
    height: "48px"
  button-rust-hover:
    backgroundColor: "{colors.rust-hover}"
  button-paper:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.forest}"
    typography: "{typography.button}"
    rounded: "{rounded.ctl}"
    padding: "0 20px"
    height: "48px"
  button-outline:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.ctl}"
    padding: "0 20px"
    height: "48px"
  flow-cta:
    backgroundColor: "{colors.forest}"
    textColor: "{colors.white}"
    typography: "{typography.button}"
    rounded: "{rounded.ctl}"
    padding: "0 20px"
    height: "52px"
  field-input:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.ctl}"
    padding: "0 12px"
    height: "48px"
  badge-mint:
    backgroundColor: "{colors.mint}"
    textColor: "{colors.pine}"
    typography: "{typography.caption}"
    rounded: "{rounded.badge}"
    padding: "4px 8px"
  badge-gold:
    backgroundColor: "{colors.gold}"
    textColor: "{colors.ink}"
    typography: "{typography.caption}"
    rounded: "{rounded.badge}"
    padding: "4px 8px"
  badge-butter:
    backgroundColor: "{colors.butter}"
    textColor: "{colors.warn-ink}"
    typography: "{typography.caption}"
    rounded: "{rounded.badge}"
    padding: "4px 8px"
  card:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "20px"
  seat-chip:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.small}"
    rounded: "{rounded.ctl}"
    padding: "0 12px"
    height: "44px"
  seat-chip-selected:
    backgroundColor: "{colors.pine}"
    textColor: "{colors.white}"
  slot:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.small}"
    rounded: "{rounded.ctl}"
    height: "48px"
  slot-selected:
    backgroundColor: "{colors.pine}"
    textColor: "{colors.white}"
  menu-tab:
    backgroundColor: "{colors.mist}"
    textColor: "{colors.ink}"
    typography: "{typography.small}"
    rounded: "{rounded.ctl}"
    padding: "0 16px"
    height: "40px"
  menu-tab-selected:
    backgroundColor: "{colors.pine}"
    textColor: "{colors.white}"
  menu-row:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    height: "104px"
  qty-button:
    backgroundColor: "{colors.qty-btn}"
    textColor: "{colors.ink}"
    rounded: "{rounded.qty}"
    size: "36px"
  qty-value:
    backgroundColor: "{colors.sage}"
    textColor: "{colors.ink}"
    typography: "{typography.small}"
    rounded: "{rounded.qty}"
    size: "36px"
  tabbar:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.muted}"
    typography: "{typography.caption}"
    height: "60px"
---

# Design System: Bella Vista Dining

## Overview

**Creative North Star: "The Garden Table"**

Bella Vista looks like a long lunch in the garden of a country estate: warm cream paper, deep green foliage, one terracotta note, and serif headlines that read like a printed menu. Nothing shouts. Surfaces are flat and quiet, photography does the persuading, and every movement is slow enough to feel unhurried and fast enough never to be waited on.

The system serves two moods from one codebase. On a desktop it is an editorial website: wide photographs, generous section gaps, a sticky summary beside the booking flow. On a phone it becomes an app: a centered logo, a pinned bottom tab bar, one full-bleed hero card, and a booking flow with its own app bar and a pinned action button. The two share every component and differ only in a palette shift and chrome, both switched at 1024px.

The type pairing does most of the branding. Newsreader gives headings their warmth; the platform's own sans does all the working text, so forms, prices, dish names and buttons stay fast and legible.

**Key Characteristics:**
- Warm paper backgrounds and deep estate green; terracotta only as a rare warm note.
- Serif for voice, system sans for work.
- Flat surfaces with hairline borders; depth from translucent sticky chrome, not shadows.
- Soft, generous corners (10 to 20px) and large tap targets (40 to 52px).
- Selected always means pine green fill with white text.
- Motion is rise-and-fade, ease-out, staggered, capped, and off for reduced-motion users.

**Surfaces and their status.**
- **Shipped and governed by this file:** landing page, booking flow (visit, seating, menu, pre-order review, checkout), confirmation, menu page, mobile tab bar and app bar, header, footer.
- **Shipped as a second surface (Operate mode):** the admin: login, Overview, Reservations (list, booking detail, new reservation), Tables, Kitchen orders, Customers (directory, guest record), Daily report and Menu availability. See Components, Admin.
- **Pending redesign, do not copy:** the About, Visit and Contact pages, the booking-lookup page ("Your booking") and the sandbox checkout still use older styles (`.inner-hero`, `.eyebrow`, `.content-panel`, `.visit-page-content`); treat them as legacy, not as patterns to extend.

## Colors

A cream and green palette with one clay-to-terracotta accent. Five core colours are CSS variables that change by context; everything else is a fixed token.

### Primary
- **Estate Forest** (#203F32 on desktop, #1A3721 on phones and staff screens): headings, the primary button, the closing and visit bands, the mobile tab bar's active item. Dark enough that white text on it reads at about 11.4:1.
- **Pine Leaf** (#2F5D50): every "selected" and "interactive" state: chosen dates, time slots, seat chips, menu tabs, action links (`.text-link`, `.text-button`), icons, focus rings, quantity labels, the confirmation reference. The system's second voice, lighter than forest.

### Secondary
- **Clay Label** (#99583C): small uppercase section labels, the year in company history, the current step in the desktop step trail, the current-page underline in the header, and inline links inside running text (the base `a` style, underlined where they sit in a paragraph). Darkened from the Figma value (#A96143) to pass 4.5:1 on the cream backgrounds.
- **Terracotta** (#B35F37, hover #9E5230): the phone hero's "Reserve now" button. Darkened from the Figma value (#C4693D) so white text passes. Reserved as a warm accent; see the Terracotta Rule.

### Tertiary
- **Brass** (#C9A24B): the "Private" seating badge only.
- **Butter** (#FBF1D8, text #805D0A): the pre-order status badge on the confirmation screen.

### Neutral
- **Garden Paper** (#F6F3EB web, #F7F1E5 phone and staff): page background (`canvas`).
- **Linen Card** (#FFFEFA web, #FFFDF8 phone and staff): cards, forms, sticky bars (`paper`).
- **Moss Ink** (#263D32 web, #29352F phone, #2B2B2B staff): body text (`ink`).
- **Reed Line** (#DADDD1 web, #DCDDCF phone, #DDDCCF staff): dividers and card outlines (`line`).
- **Lichen Gray** (#657068): all secondary text. Darkened from the Figma value (#73796E, 4.0:1) to 4.65:1.
- **Dew Mint** (#E6ECDC): the menu highlights band, context pills, active badges, the confirmation seal.
- **Mist** (#EEF0E9): inactive menu tabs. **Fern Sage** (#E1E8DA): image placeholders and the quantity value. **Pay Green** (#EDF2EA): the chosen payment row. **Sand Panel** (#F1E6D5): policy and arrival-reminder panels. **Quantity Button** (#F0F0EA).
- **Pale Cream** (#D3DCCD): text on the dark green bands (8.2:1 on forest).

### Status (chips, notices, staff board)
Each status is a trio of text, tint and dot. Available is green (#286343 on #EDF1E8, dot #4C9A6A); Reserved/pending is amber (#805D0A on #F8EFD7, dot #E0A100); Seated is blue (#356483 on #EAF1F7, dot #5B8DB8); Cancelled and errors are red (#A13831 on #FBEDEA, dot #C2453D, solid #B3261E); No-show and completed are grey (#5D625B on #EEEFEA, dot #8A8A8A).

### Named Rules
**The One Switch Rule.** Canvas, paper, ink, forest and line are CSS variables (`--canvas`, `--paper`, `--ink`, `--forest`, `--line`). Below 1024px they take the phone values; inside `body.admin` and `body.admin-auth` they take the staff values. Use the token names (`bg-canvas`, `text-ink`, `bg-forest`, `border-line`); never hard-code their hex.

**The Pine Selected Rule.** Anything the guest has chosen is pine green with white text. Dates, time slots, seat chips, menu tabs and the quantity-chosen row border all use it, so selection reads the same everywhere.

**The Terracotta Rule.** Terracotta appears once per screen at most, on the single warm call to action. Everywhere else the primary action is forest. (The "Your booking" page still uses terracotta for several buttons; that is legacy, see Known drift.)

**The Readable Muted Rule.** Secondary text is Lichen Gray (4.65:1). On the mint band it steps to #5D625B so it stays above 4.5:1. If a Figma value fails 4.5:1 for small text, darken it and record the change here.

## Typography

**Display Font:** Newsreader (Google Fonts, weights 400 to 600, optical sizing on), with Georgia, serif.
**Body Font:** the platform UI font (`system-ui`, `-apple-system`, Segoe UI, Roboto, Helvetica Neue, Arial).
**Label/Mono Font:** the same system sans for labels; the platform monospace for booking references.

**Character:** A book serif for the voice and the system's own sans for the work. Headings are medium weight with tight leading and negative tracking that grows with size; body text is neutral; small text opens up slightly.

### Hierarchy
- **Display** (500, `clamp(2.5rem, 4.6vw, 4.25rem)`, 1.02, -0.025em): the desktop hero headline only.
- **Title** (500, `clamp(2rem, 3.4vw, 3rem)`, 1.06, -0.02em): section titles ("Your people. Your kind of table."), flow step headings on desktop.
- **Heading-lg** (500, 2rem, 1.12): secondary section headings, the mobile flow headline.
- **Heading** (500, 1.5rem, 1.2): card titles, menu panel titles, the booking summary title.
- **Subheading** (500, 1.25rem, 1.3): dish names on the landing cards, app bar titles, calendar day numbers.
- **Lead** (sans 400, 1.125rem, 1.6): the paragraph under a section title, capped at 34rem.
- **Body** (sans 400, 1rem, 1.6): running text, buttons (600), inputs (16px minimum so iOS never zooms).
- **Small** (sans, 0.875rem, 1.5): helper text, totals, chips, tabs.
- **Caption** (sans, 0.8125rem, 1.4): tab bar labels, badges, notes.
- **Label** (sans 700, 0.8125rem, 0.12em, uppercase): the small section label above headings ("FROM OUR KITCHEN", "YOUR PARTY"). Pine by default, clay or muted by modifier.

### Named Rules
**The Serif Voice Rule.** Newsreader is for headings, the band taglines, calendar day numbers and the confirmation date. Dish names in lists, prices, buttons and form text are always the system sans.

**The One Label Rule.** Section labels use `.label` (bold, uppercase, 0.12em). The older `.eyebrow` (semibold, 0.04em, not uppercase) is legacy; do not use it on new screens.

**The Measure Rule.** Paragraphs sit in `max-w-measure` (34rem). Headings use balanced wrapping and paragraphs use pretty wrapping.

## Layout

A single centered column, `max-w-page` (75rem, 1200px), with side gutters of 16px, 24px and 32px at the base, 640px and 1024px breakpoints. Vertical rhythm comes from one value: sections carry only bottom padding (64px, 80px from 1024px), and a section that follows a coloured band adds the same amount on top. The gap between two sections is therefore always one number.

**1024px is the big switch.** Below it: palette variables change, the header shows only the centered logo, a fixed bottom tab bar (60px plus the safe area) appears, the hero becomes a photo card with the call to action on it, and the booking flow gets its own app bar. At 1024px and above: the header becomes a text nav on the right of the logo, the tab bar and app bar disappear, the hero is a two-column editorial layout, and the booking flow becomes two columns.

**Booking flow.** Mobile: one column, 40rem maximum, an app bar (56px) with back and home, the step content, and a fixed action bar at the bottom holding a small step line and a single full-width button (52px). Desktop: a main column and a sticky 22 to 24rem "Your table" summary that holds the same action button; a text step trail (Your visit / Seating / Pre-order / Checkout) replaces the app bar.

**Grids.** Table-type cards: three columns from 768px. Menu rows: one column, two on the menu page from 768px. Dish highlights and the gallery: a swipeable snap row on phones (cards at 78% width), three columns from 640px. Calendar week: seven equal columns. Time slots: three columns.

**Density.** Comfortable. Tap targets are 48px for buttons, fields and slots; 44px for chips and the party stepper; 40px for tabs and small buttons; 36px for quantity buttons.

## Elevation & Depth

Flat by default. Cards sit on the page with a Reed Line hairline border and one barely-there ambient shadow (`0 1px 2px rgba(32,63,50,0.04)`). Depth comes from three sources instead of shadows: tonal layering (paper cards on canvas; mint and paper-tinted bands), a dark green full-width band for the visit section and closing call to action, and translucent sticky chrome.

### Shadow Vocabulary
- **Ambient card** (`box-shadow: 0 1px 2px rgba(32,63,50,0.04)`): the standard card. Almost invisible; the border does the work.
- **Frosted chrome** (`backdrop-filter: blur(12px)`, background at 85 to 95% opacity): the sticky header (canvas 85%), the app bar (canvas 90%), the pinned action bar and tab bar (canvas or paper 95%). Content scrolls under them. A reduced-transparency preference makes them opaque.
- **Photo scrim** (`linear-gradient` from #16382B at 92% at the bottom to transparent at 78%): the phone hero card, so the white headline stays legible over any photo.

### Named Rules
**The Flat-By-Default Rule.** Surfaces have no shadow at rest beyond the ambient card line. Do not add drop shadows to buttons, chips or rows. State is shown by colour (pine fill, border tint), not elevation.

## Shapes

Soft and consistent, in a few steps: 6px badges, 8px quantity controls and thumbnails, 10px for every button, input, chip and slot, 16px for cards and rows, 20px for the largest surfaces (the hero photo, the phone hero card). Circles (9999px) are only for round icon buttons, the confirmation seal and status dots. Photos are always cropped to a rectangle with the card radius; they never get frames or borders. Hairlines (1px, Reed Line) separate content; there are no thick borders, except the 2px pine outline on the selected seating card.

## Components

Every control is soft and unhurried: rounded, flat, quiet at rest, answering a press in about 150ms, and never dramatic. Hover styles apply only where a device can hover (`hoverOnlyWhenSupported`), and every control has a 2px pine focus ring with a 2px offset.

### Buttons
- **Shape:** 10px corners, 48px high (40px small, 52px for the flow's main action), 20px side padding, semibold 16px sans.
- **Forest (primary):** Estate Forest fill, Linen Card text; hover darkens to Grove (#1A3721). The default for every primary action.
- **Terracotta:** Terracotta fill, white text; reserved for the phone hero.
- **Paper:** Linen Card fill with a Reed Line border and forest text; used on the green bands and for secondary actions beside a forest button.
- **Outline / secondary:** transparent with a Reed Line border; the border darkens on hover.
- **Press:** scales to 0.97 on press (0.98 on the large flow button), 150ms ease-out. Disabled during work: reduced opacity and a wait cursor.

### Badges and chips
- **Badge:** 6px corners, caption text. Mint with pine text for active and neutral info (seat capacity, "2-hour blocks"), Brass with ink uppercase text for "Private", Butter with amber text for "Scheduled".
- **Status chips:** the status trios under Colors, with no border.
- **Seat chip:** 44px, Garden Paper fill with ink text, becomes pine with a leading check mark when chosen; booked tables show a strikethrough and are disabled.

### Cards / Containers
- **Corner style:** 16px. **Background:** Linen Card. **Border:** 1px Reed Line. **Shadow:** ambient only.
- **Padding:** 16px on phones, 20px from 640px.
- **Seat card:** 2px transparent border that becomes pine when one of its tables is chosen.
- **Context pill:** a mint, 10px-rounded strip with a calendar icon showing the chosen date, time, party and table at the top of every flow step after the first.

### Inputs / Fields
- **Style:** white fill, 1px Reed Line stroke, 10px corners, 48px high, 16px text. A small medium-weight label sits above.
- **Focus:** the border turns pine and a 2px pine ring at 25% opacity appears.
- **Error:** the border turns red and an inline message in a red-tinted strip with an alert icon explains what to fix, in plain language.
- **Payment rows:** radio rows on Linen Card; the chosen row turns Pay Green with a pine-tinted border.

### Navigation
- **Desktop header:** sticky, 72px, frosted canvas with a bottom hairline. Logo left; three text links right (Our story, Menu & preorder, Reserve a table). The current page is underlined in clay, 2px, offset 6px. No open-now status and no button.
- **Mobile tab bar:** four equal items (Discover, Menu, Reserve, Booking), icon above a caption label, 60px plus safe area. Active is forest and semibold; inactive is Lichen Gray. Hidden inside the booking flow.
- **Mobile app bar (booking flow):** 56px, back arrow left, serif step title, sprout icon (links home) right.
- **Footer:** one slim row (logo, tagline and three links, hours) on a single hairline.

### Menu browser (signature)
The menu is a set of category tabs above rows of dishes, shared by the menu page (read-only) and the booking flow (with quantity steppers).
- **Tabs:** sticky below the header, 40px, Mist fill with ink text. A single pine pill slides behind the selected tab (320ms, ease-out `cubic-bezier(0.23, 1, 0.32, 1)`) instead of each tab changing colour.
- **Rows:** 16px-rounded cards, 104px minimum, with the photo filling the full height of the left edge (96px, 112px from 640px), then name, a two-line description, price, and the stepper. On a mouse device the photo zooms slowly to 1.06 on hover (500ms); touch devices get no hover effect.
- **Stepper:** minus and plus buttons (36px, 8px corners, Quantity Button fill) around a Fern Sage count; minus is disabled at zero.
- **Entrance:** rows rise 16px while fading in over 520ms, in small staggered batches as they scroll into view (55ms apart, the first eight only; later rows arrive together). Switching a tab, entering the menu step and returning with the browser's Back button all replay it.

### Calendar week and time slots (signature)
A seven-column week with a month label and previous/next arrows; each day is a 64px tile with a small weekday and a serif day number. Chosen is pine with white text; closed days are tinted with a strikethrough; unavailable days dim to 40%. Time slots are a three-column grid of 48px chips showing the two-hour range ("6–8 PM"); chosen is pine. On the landing page the same pieces form a form: choosing a time submits to the booking flow with the date and time filled in.

### Booking action bar (signature)
The single forward button of the flow. On phones it is fixed to the bottom over a frosted bar, with a centered caption above it ("Step 2 of 4 · Family F2 selected"); on desktop the same button sits in the sticky summary. The label always says what happens next ("Explore the menu", "Continue to checkout", "Confirm & pay ₱1,460"). While submitting it is disabled and says what is happening.

### Confirmation (signature)
A mint seal with a check, a small label, a serif headline with the guest's first name, then a two-column layout of cards: the reservation (pine reference code in monospace, serif date, status badge) and the payment breakdown, with a Sand Panel arrival reminder. It is the only place the Butter badge appears.

### Admin (staff screens)
An Operate surface: the visitor is doing a task, so it is dense, flat and plain. The same palette and type, with the staff values of the five core colours (`body.admin` and `body.admin-auth`), and no entrance animation. Only press and colour feedback (150ms) moves.
- **Shell:** a 250px Estate Forest sidebar (white logo, section label, one tab per screen; the current tab is a Linen Card pill) beside a content column of at most 1320px with a thin top bar (breadcrumb, live time, role badge). Below 900px the sidebar becomes a top strip. Kitchen Staff see Overview, Reservations, Tables and Kitchen only; Customers, Daily reports and Menu availability are Manager screens.
- **Screens are hash routes** (`#reservations`, `#reservation/12`, `#customer/3`), so Back works. A screen's filters, typed text and forms are never re-rendered by the 15-second refresh, which only replaces live data.
- **Panels:** Linen Card, hairline border, about 13px corners, serif panel title with a small muted subtitle. Tables sit inside a panel without a second border (no nested cards).
- **Heading row:** a serif page title, one line of muted explanation, and the screen's main action on the right (a forest button; a terracotta button is never used here).
- **Summary strip and filters (lists):** a row of counts that double as status filters (the chosen one is a Linen Card pill), then a filter row of labelled controls in small uppercase labels, a count line ("5 of 13 bookings for Thu, Nov 19"), and the table with one "View booking" or "Guest record" button per row.
- **Stat tiles:** four tiles in one bordered strip (icon, label, serif number, one line of context). Used on Overview, Customers, a guest record and the report.
- **Status vocabulary:** the status trios under Colors (green available/confirmed, amber reserved/pending, blue seated/occupied, red cancelled, grey no-show/completed/clean-up). A table card carries its status in a 3px top border and a chip.
- **Tables board:** cards of a table or room with its capacity, who is booked in the chosen block, and the next sensible action (Check in, Guests left, Mark available, View booking). A seated party stays on its table whatever the block.
- **Kitchen board:** three columns (Pending, Preparing, Served) with a count each; a ticket shows the table, order number, arrival, items and the next action. Empty columns say what will appear there.
- **Booking detail and guest record:** a heading row with actions, a chip row (status, payment, table, order), then two columns from 1280px (main facts and food on the left, bill summary and payment record on the right) and one column below. Money is right-aligned; the balance is the one large serif figure.
- **Charts:** plain CSS bars in Pine Leaf, labelled with their value, with a visible zero. Never decorative and never estimated.

**The Real Numbers Rule.** Every figure on an admin screen is read from the database for the stated date. If there is no data, show an empty state that says what will appear and when; never scale, split or invent a number to fill a chart.

### Motion
One easing for entrances and slides: `cubic-bezier(0.23, 1, 0.32, 1)`. Feedback (press, colour, border) takes 150 to 200ms; sliding indicators 320ms; entrances 460 to 520ms. Only `transform` and `opacity` move. Staggers are capped at eight steps so a long list never makes anyone wait. All entrance animations replay on every page view, including pages restored from the browser's back/forward cache. With `prefers-reduced-motion: reduce` everything appears at once and transitions drop to near zero.

## Do's and Don'ts

### Do:
- **Do** use the token names (`canvas`, `paper`, `ink`, `forest`, `line`, `pine`, `clay`, `mint`, `sand`) and never a raw hex; the palette changes by context through the five variables.
- **Do** mark every chosen thing with the Pine Selected Rule: pine fill, white text.
- **Do** keep the primary action forest, and use terracotta once per screen at most.
- **Do** write section labels with `.label` and headings in Newsreader at the type scale; keep dish names, prices and form text in the system sans.
- **Do** keep tap targets at 40px or more (48px for primary actions) and inputs at 16px or more.
- **Do** put fixed heights on the images themselves, not on the grid that holds them. A fixed-height grid with `overflow: visible` let photos spill over the next section.
- **Do** dim disabled text by colour, not opacity, so it stays readable; reserve opacity dimming for non-text (unavailable calendar days).
- **Do** animate only `transform` and `opacity`, with the shared ease-out, and stagger no more than eight items.
- **Do** darken any Figma colour that fails 4.5:1 for small text, and record the change under Colors.
- **Do** check a layout change by looking at it at phone, tablet and desktop widths; an accessibility scan does not catch overlap. For the admin, check 1024px as well: the sidebar leaves about 730px.

### Don't:
- **Don't** add drop shadows to buttons, chips or rows; state is colour.
- **Don't** use `.eyebrow` or the old `.inner-hero` / `.content-panel` styles on new screens.
- **Don't** use terracotta for ordinary buttons or for links. Action links ("See the full menu", "Add more") are pine; inline links in running text are clay.
- **Don't** use bold uppercase labels for anything but section labels.
- **Don't** hotlink photos or load anything from another site's image host; use local files and the `venue_image()` helper.
- **Don't** animate width, height, margin or top/left (the one exception is the tab indicator's width, which is a single element).
- **Don't** apply hover styles that need a mouse without the hover media guard; the Tailwind setting already does this for `hover:` utilities.
- **Don't** let an animation hide content from users with reduced-motion turned on.
- **Don't** animate anything in the admin beyond press and colour feedback, or fill a chart with estimated numbers.

### Known drift (recorded, not part of the system)
- Legacy token `deep` (#7A3E2A) and the `.eyebrow` label style are still referenced by older pages.
- One-off values outside the config: the terracotta hover (#9E5230), the quantity button border (#DCDDCF), `accent-[#2F5D50]` on checkboxes and radios, the `2.5rem` phone hero headline, and the legacy `rgba(35,37,28,…)` step-trail separator and `rgba(74,94,40,…)` open-status halo.
- Unused rules left in the stylesheet: `.footer-grid`, `.footer-bottom`, `.map-placeholder`, `.open-status`.
- The booking-lookup page and the sandbox checkout use terracotta and the older card styling.
- The admin's first-pass styles (sidebar shell, menu availability, login) are plain CSS with literal hex values that equal the staff tokens, and 0.8rem panel corners instead of the 16px card radius. Newer admin rules use tokens; migrate the older ones when touched.
