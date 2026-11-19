<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = Auth::guardPage(Auth::MANAGER, Auth::KITCHEN);
$isManager = Auth::isManager($admin);
$now = Config::now();
$hour = (int) $now->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim($admin['full_name']))[0];
$slots = Config::reservationSlots();
$asset = '?v=20261119g';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title>Operations · Bella Vista Dining</title>
    <?= brand_head_tags() ?>
    <link rel="stylesheet" href="/public/assets/css/app.css<?= $asset ?>">
</head>
<body class="admin">
<div class="admin-shell">
<aside class="admin-sidebar">
    <div class="admin-sidebar-brand"><?= brand_logo(52) ?><span>DINING · EST. 2021</span></div>
    <p class="admin-nav-label">Restaurant operations</p>
    <nav class="admin-tabs" role="tablist" aria-label="Dashboard sections">
        <button role="tab" type="button" aria-selected="true" aria-controls="panel-overview" id="tab-overview" data-tab="overview"><?= icon('home') ?> Overview</button>
        <button role="tab" type="button" aria-selected="false" aria-controls="panel-reservations" id="tab-reservations" data-tab="reservations" tabindex="-1"><?= icon('calendar') ?> Reservations</button>
        <button role="tab" type="button" aria-selected="false" aria-controls="panel-tables" id="tab-tables" data-tab="tables" tabindex="-1"><?= icon('users') ?> Tables</button>
        <button role="tab" type="button" aria-selected="false" aria-controls="panel-kitchen" id="tab-kitchen" data-tab="kitchen" tabindex="-1"><?= icon('cloche') ?> Kitchen orders <span class="count" data-queue-count></span></button>
        <?php if ($isManager): ?>
            <button role="tab" type="button" aria-selected="false" aria-controls="panel-customers" id="tab-customers" data-tab="customers" tabindex="-1"><?= icon('users') ?> Customers</button>
            <button role="tab" type="button" aria-selected="false" aria-controls="panel-report" id="tab-report" data-tab="report" tabindex="-1"><?= icon('chart') ?> Daily reports</button>
            <button role="tab" type="button" aria-selected="false" aria-controls="panel-menu" id="tab-menu" data-tab="menu" tabindex="-1"><?= icon('utensils') ?> Menu availability</button>
        <?php endif; ?>
    </nav>
    <div class="admin-sidebar-note"><strong>A little care, every service</strong><span>Tue–Sun · 10 AM–10 PM</span><span>Monday closed</span></div>
    <div class="admin-sidebar-user"><span class="admin-avatar"><?= e(strtoupper(substr($admin['full_name'], 0, 1))) ?></span><span><strong><?= e($admin['full_name']) ?></strong><small><?= e(strtolower($admin['role'])) ?></small></span><form method="post" action="/admin/logout.php"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="text-link" type="submit">Sign out</button></form></div>
</aside>
<div class="admin-content">
<header class="admin-bar"><div class="admin-bar-inner"><p class="admin-breadcrumb"><?= icon('sprout') ?> Bella Vista Dining / <strong data-crumb>Operations</strong></p><span class="admin-live" data-live-date></span><span class="admin-role"><?= e($admin['role']) ?></span></div></header>

<main class="admin-main" id="main" data-manager="<?= $isManager ? '1' : '0' ?>" data-grace="<?= e(Config::GRACE_MINUTES) ?>">
    <p class="admin-status" data-sync aria-live="polite">Loading…</p>
    <p class="form-error" data-admin-error role="alert" hidden><?= icon('alert') ?><span></span></p>

    <!-- Overview -->
    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" data-panel="overview">
        <div class="admin-heading-row">
            <div><h1 class="admin-h" tabindex="-1"><?= e($greeting) ?>, <?= e($firstName) ?>.</h1><p class="text-muted">Your service at a glance. Keep every table, guest and order in rhythm.</p></div>
            <a class="button button-primary" href="#reservations/new"><?= icon('plus') ?> New reservation</a>
        </div>
        <div class="overview-stats" data-overview-stats></div>
        <p class="admin-banner admin-banner-warn" data-overview-alert hidden><?= icon('alert') ?><span></span></p>
        <div class="overview-grid<?= $isManager ? '' : ' overview-grid-single' ?>">
            <?php if ($isManager): ?>
                <section class="admin-panel"><div class="panel-heading"><div><h2>Food sales by dining block</h2><p data-sales-note>Today, every order that is not cancelled</p></div></div><div class="sales-bars" data-sales-bars></div></section>
            <?php endif; ?>
            <section class="admin-panel"><div class="panel-heading"><div><h2>The dining room</h2><p data-room-note>Nine tables &amp; rooms, one service</p></div><a class="button button-small button-secondary" href="#tables">View tables</a></div><div class="room-bar" data-room-bar role="img" aria-label="Table status"></div><div class="room-summary" data-room-summary></div></section>
        </div>
        <section class="admin-panel mt-4"><div class="panel-heading"><div><h2>Arrivals to look after</h2><p>Pending and confirmed bookings · today</p></div><a class="button button-small button-secondary" href="#reservations">All reservations</a></div><div class="table-scroll"><table class="data-table" data-overview-bookings><tbody></tbody></table></div></section>
    </section>

    <!-- Reservations: list -->
    <section id="panel-reservations" role="tabpanel" aria-labelledby="tab-reservations" data-panel="reservations" hidden>
        <div class="admin-heading-row">
            <div><h1 class="admin-h" tabindex="-1">Reservations</h1><p class="text-muted">Every booking for a service date, with its payment and status.</p></div>
            <a class="button button-primary" href="#reservations/new"><?= icon('plus') ?> New reservation</a>
        </div>
        <div class="status-strip" data-res-strip role="group" aria-label="Filter by status"></div>
        <p class="admin-banner"><?= icon('info') ?><span>A table is held for <?= e(Config::GRACE_MINUTES) ?> minutes past the start time, then released. Check guests in as they arrive.</span></p>
        <section class="admin-panel list-panel">
            <form class="filter-grid" data-res-filters role="search" aria-label="Filter reservations" novalidate>
                <label class="field"><span>Search</span><input type="search" name="q" placeholder="Guest, reference or phone" autocomplete="off" maxlength="60"></label>
                <label class="field"><span>Service date</span><input type="date" name="date" value="<?= e($now->format('Y-m-d')) ?>"></label>
                <label class="field"><span>Dining block</span><select name="block"><option value="">All blocks</option><?php foreach ($slots as $slot): ?><option value="<?= e($slot) ?>"><?= e(Config::slotLabel($slot)) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Table category</span><select name="type"><option value="">All categories</option><option value="Standard">Standard</option><option value="Family">Family</option><option value="VIP">VIP rooms</option></select></label>
                <label class="field"><span>Payment</span><select name="payment"><option value="">Any payment</option><option value="Unpaid">Not paid yet</option><option value="Downpayment">Deposit paid</option><option value="Paid">Paid in full</option><option value="On-site">Pay at the restaurant</option></select></label>
            </form>
            <p class="list-count" data-res-count aria-live="polite"></p>
            <div class="table-scroll"><table class="data-table" data-bookings><thead><tr><th scope="col">Reference</th><th scope="col">Guest</th><th scope="col">Table</th><th scope="col">Guests</th><th scope="col">Dining block</th><th scope="col">Payment</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody></tbody></table></div>
        </section>
    </section>

    <!-- Reservations: one booking -->
    <section id="panel-reservation" role="tabpanel" aria-labelledby="tab-reservations" data-panel="reservation" hidden>
        <div data-reservation-detail></div>
    </section>

    <!-- Reservations: new -->
    <section id="panel-reservation-new" role="tabpanel" aria-labelledby="tab-reservations" data-panel="reservation-new" hidden>
        <div class="admin-heading-row">
            <div><h1 class="admin-h" tabindex="-1">A place at the table</h1><p class="text-muted">Book for a guest who phoned or asked in person. They pay at the restaurant, in cash or by card.</p></div>
            <a class="button button-secondary" href="#reservations">Back to reservations</a>
        </div>
        <div class="detail-grid">
            <form class="stack" data-new-res novalidate>
                <section class="admin-panel">
                    <div class="panel-heading"><div><h2>Guest details</h2><p>We use these to find the booking and reach the guest.</p></div></div>
                    <div class="form-grid">
                        <label class="field"><span>First name</span><input name="first_name" required maxlength="50" autocomplete="off"></label>
                        <label class="field"><span>Last name</span><input name="last_name" required maxlength="50" autocomplete="off"></label>
                        <label class="field"><span>Mobile number</span><input name="phone_number" type="tel" required inputmode="tel" autocomplete="off" placeholder="0917 123 4567"></label>
                        <label class="field"><span>Email address</span><input name="email" type="email" required maxlength="100" autocomplete="off" autocapitalize="none" spellcheck="false"></label>
                    </div>
                </section>
                <section class="admin-panel">
                    <div class="panel-heading"><div><h2>Dining details</h2><p>Pick the day and block, then a table that fits the party.</p></div></div>
                    <div class="form-grid">
                        <label class="field"><span>Reservation date</span><input name="reservation_date" type="date" required min="<?= e($now->format('Y-m-d')) ?>"></label>
                        <label class="field"><span>Party size</span><input name="number_of_guests" type="number" required min="2" max="20" value="2" inputmode="numeric"></label>
                    </div>
                    <p class="field-label mt-4" id="new-block-label">Dining block</p>
                    <div class="slot-grid" role="radiogroup" aria-labelledby="new-block-label" data-new-blocks>
                        <?php foreach ($slots as $slot): ?><button class="slot" type="button" role="radio" aria-checked="false" data-block="<?= e($slot) ?>"><?= e(Config::slotLabel($slot)) ?></button><?php endforeach; ?>
                    </div>
                    <p class="field-label mt-4" id="new-table-label">Table</p>
                    <p class="field-status" data-new-status role="status"></p>
                    <div class="seat-chips seat-chips-wide" role="radiogroup" aria-labelledby="new-table-label" data-new-tables></div>
                </section>
                <section class="admin-panel">
                    <label class="check-label"><input type="checkbox" name="terms_accepted" value="1" required><span>I have told the guest the booking policies: <?= e(Config::GRACE_MINUTES) ?>-minute grace period, <?= e(Config::CANCEL_NOTICE_HOURS) ?>-hour cancellation notice, and no outside food.</span></label>
                    <p class="form-error mt-4" data-new-error role="alert" hidden><?= icon('alert') ?><span></span></p>
                    <div class="form-actions"><button class="button button-primary" type="submit" data-new-submit>Create reservation</button><a class="button button-secondary" href="#reservations">Cancel</a></div>
                </section>
            </form>
            <aside class="stack">
                <section class="admin-panel"><div class="panel-heading"><div><h2>Booking summary</h2></div></div><dl class="kv" data-new-summary></dl></section>
                <section class="admin-panel"><div class="panel-heading"><div><h2>Payment method</h2></div></div><p class="pay-fixed"><strong>Pay at the restaurant</strong><span>Cash or card when the guest arrives. Any reservation fee is credited to the food bill.</span></p></section>
            </aside>
        </div>
        <div data-new-done hidden></div>
    </section>

    <!-- Tables -->
    <section id="panel-tables" role="tabpanel" aria-labelledby="tab-tables" data-panel="tables" hidden>
        <div class="admin-heading-row">
            <div><h1 class="admin-h" tabindex="-1">The dining room</h1><p class="text-muted">Live floor status. Seat guests, clear tables and see who is expected next.</p></div>
            <label class="field field-inline"><span>Dining block</span><select data-table-block><option value="now">Current block</option><option value="all">All day</option><?php foreach ($slots as $slot): ?><option value="<?= e($slot) ?>"><?= e(Config::slotLabel($slot)) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="status-legend" data-board-legend></div>
        <p class="admin-banner"><?= icon('info') ?><span>Check a guest in when they arrive: the table turns Occupied and their pre-order goes to the kitchen. Choose Guests left to send the table to clean-up.</span></p>
        <div class="board" data-board></div>
    </section>

    <!-- Kitchen -->
    <section id="panel-kitchen" role="tabpanel" aria-labelledby="tab-kitchen" data-panel="kitchen" hidden>
        <div class="admin-heading-row"><div><h1 class="admin-h" tabindex="-1">Kitchen orders</h1><p class="text-muted">Orders mapped to their tables, from the pass to the table.</p></div></div>
        <p class="admin-banner"><?= icon('info') ?><span>Pre-orders arrive here an hour before the booking, or as soon as the guests are checked in.</span></p>
        <div class="kitchen-board" data-queue></div>
    </section>

    <?php if ($isManager): ?>
        <!-- Customers -->
        <section id="panel-customers" role="tabpanel" aria-labelledby="tab-customers" data-panel="customers" hidden>
            <div class="admin-heading-row"><div><h1 class="admin-h" tabindex="-1">Know your guests</h1><p class="text-muted">Everyone who has booked, how often they return and what they have spent.</p></div></div>
            <div class="overview-stats" data-customer-stats></div>
            <section class="admin-panel list-panel">
                <form class="filter-grid filter-grid-3" data-cust-filters role="search" aria-label="Filter customers" novalidate>
                    <label class="field"><span>Search</span><input type="search" name="q" placeholder="Name, email or phone" autocomplete="off" maxlength="60"></label>
                    <label class="field"><span>Visit history</span><select name="history"><option value="">All guests</option><option value="returning">Returning (2+ bookings)</option><option value="new">New (1 booking)</option><option value="upcoming">With an upcoming booking</option></select></label>
                    <label class="field"><span>Sort by</span><select name="sort"><option value="recent">Most recent visit</option><option value="visits">Most bookings</option><option value="spend">Highest food spend</option><option value="name">Name</option></select></label>
                </form>
                <p class="list-count" data-cust-count aria-live="polite"></p>
                <div class="table-scroll"><table class="data-table" data-customers></table></div>
            </section>
        </section>
        <section id="panel-customer" role="tabpanel" aria-labelledby="tab-customers" data-panel="customer" hidden>
            <div data-customer-detail></div>
        </section>

        <!-- Daily report -->
        <section id="panel-report" role="tabpanel" aria-labelledby="tab-report" data-panel="report" hidden>
            <div class="admin-heading-row">
                <div><h1 class="admin-h" tabindex="-1">Daily service report</h1><p class="text-muted" data-report-sub>Reservations, food sales and fees for one service day.</p></div>
                <label class="field field-inline"><span>Report date</span><input type="date" data-report-date value="<?= e($now->format('Y-m-d')) ?>"></label>
            </div>
            <div data-report></div>
        </section>

        <!-- Menu availability -->
        <section id="panel-menu" role="tabpanel" aria-labelledby="tab-menu" data-panel="menu" hidden>
            <div class="admin-heading-row">
                <div><h1 class="admin-h" tabindex="-1">What's on the menu</h1><p class="text-muted">Keep today's availability clear for reservations, pre-orders and the kitchen.</p></div>
            </div>
            <div class="menu-admin-toolbar">
                <label class="field"><span>Find a menu item</span><input type="search" data-menu-search placeholder="Search dishes or drinks"></label>
                <label class="field"><span>Availability</span><select data-menu-filter><option value="all">All items</option><option value="available">Available</option><option value="sold-out">Sold out</option></select></label>
                <div class="menu-admin-summary" data-menu-summary aria-live="polite"></div>
            </div>
            <div class="menu-admin-notice">
                <?= icon('info') ?>
                <span>Turn off an item to mark it as sold out for new orders and pre-orders. Previously recorded orders keep their original items and amounts.</span>
            </div>
            <div class="menu-admin-grid" data-menu-admin></div>
        </section>
    <?php endif; ?>
</main>
</div></div>
<script src="/public/assets/js/admin.js<?= $asset ?>" defer></script>
<script src="/public/assets/js/admin-reservations.js<?= $asset ?>" defer></script>
<?php if ($isManager): ?>
<script src="/public/assets/js/admin-customers.js<?= $asset ?>" defer></script>
<script src="/public/assets/js/admin-report.js<?= $asset ?>" defer></script>
<?php endif; ?>
</body>
</html>
