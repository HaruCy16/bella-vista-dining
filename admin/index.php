<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = Auth::guardPage(Auth::MANAGER, Auth::KITCHEN);
$isManager = Auth::isManager($admin);
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
    <link rel="stylesheet" href="/public/assets/css/app.css?v=20261118b">
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
        <button role="tab" type="button" aria-selected="false" aria-controls="panel-customers" id="tab-customers" data-tab="customers" tabindex="-1"><?= icon('users') ?> Customers</button>
        <?php if ($isManager): ?>
            <button role="tab" type="button" aria-selected="false" aria-controls="panel-report" id="tab-report" data-tab="report" tabindex="-1"><?= icon('chart') ?> Daily reports</button>
            <button role="tab" type="button" aria-selected="false" aria-controls="panel-menu" id="tab-menu" data-tab="menu" tabindex="-1"><?= icon('utensils') ?> Menu availability</button>
        <?php endif; ?>
    </nav>
    <div class="admin-sidebar-note"><strong>A little care, every service</strong><span>Tue–Sun · 10 AM–10 PM</span><span>Monday closed</span></div>
    <div class="admin-sidebar-user"><span class="admin-avatar"><?= e(strtoupper(substr($admin['full_name'], 0, 1))) ?></span><span><strong><?= e($admin['full_name']) ?></strong><small><?= e(strtolower($admin['role'])) ?></small></span><form method="post" action="/admin/logout.php"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="text-link" type="submit">Sign out</button></form></div>
</aside>
<div class="admin-content">
<header class="admin-bar"><div class="admin-bar-inner"><p class="admin-breadcrumb"><?= icon('sprout') ?> Bella Vista Dining / <strong>Operations</strong></p><span class="admin-live" data-live-date></span><span class="admin-role"><?= e($admin['role']) ?></span></div></header>

<main class="admin-main" id="main">
    <p class="admin-status" data-sync aria-live="polite">Loading…</p>
    <p class="form-error" data-admin-error role="alert" hidden><?= icon('alert') ?><span></span></p>

    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" data-panel="overview">
        <div class="admin-heading-row"><div><h1 class="admin-h">Good afternoon, <?= e(explode(' ', trim($admin['full_name']))[0]) ?>.</h1><p class="text-muted">Your service at a glance. Keep every table, guest and order in rhythm.</p></div><button class="button button-primary" type="button" data-tab-jump="reservations"><?= icon('plus') ?> New reservation</button></div>
        <div class="overview-stats" data-overview-stats></div>
        <div class="overview-grid"><section class="admin-panel"><div class="panel-heading"><div><h2>Food sales by dining block</h2><p>Settled bills only · today</p></div></div><div class="sales-bars" data-sales-bars></div><p class="small text-muted">Consumable deposits are credited once. Extra-person fees are not food sales.</p></section><section class="admin-panel"><div class="panel-heading"><div><h2>The dining room</h2><p>Nine tables &amp; rooms, one service</p></div><button class="button button-small button-secondary" type="button" data-tab-jump="tables">View tables</button></div><div class="room-summary" data-room-summary></div></section></div>
        <section class="admin-panel mt-6"><div class="panel-heading"><div><h2>Arrivals to look after</h2><p>Confirmed reservations · today</p></div><button class="button button-small button-secondary" type="button" data-tab-jump="reservations">All reservations</button></div><div class="table-scroll"><table class="data-table" data-overview-bookings><tbody></tbody></table></div></section>
    </section>

    <section id="panel-reservations" role="tabpanel" aria-labelledby="tab-reservations" data-panel="reservations" hidden>
        <div class="admin-heading-row"><div><h1 class="admin-h">Reservations</h1><p class="text-muted">Today’s arrivals and service status.</p></div><button class="button button-primary" type="button">+ New reservation</button></div>
        <div class="table-scroll"><table class="data-table" data-bookings><thead><tr><th>Time</th><th>Table</th><th>Guest</th><th>Guests</th><th>Status</th><th>Payment</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody></tbody></table></div>
    </section>

    <section id="panel-tables" role="tabpanel" aria-labelledby="tab-tables" data-panel="tables" hidden>
        <div class="admin-heading-row"><div><h1 class="admin-h">Tables today</h1><p class="text-muted">Live floor status for the dining room.</p></div></div>
        <div class="board" data-board></div>
    </section>

    <section id="panel-kitchen" role="tabpanel" aria-labelledby="tab-kitchen" data-panel="kitchen" hidden>
        <div class="admin-heading-row"><div><h1 class="admin-h">Kitchen orders</h1><p class="text-muted">Pre-orders appear an hour before the booking, or when guests are checked in.</p></div></div>
        <div class="queue" data-queue></div>
    </section>

    <section id="panel-customers" role="tabpanel" aria-labelledby="tab-customers" data-panel="customers" hidden><div class="admin-heading-row"><div><h1 class="admin-h">Customers</h1><p class="text-muted">Guests who have joined Bella Vista at the table.</p></div></div><div class="table-scroll"><table class="data-table" data-customers><tbody></tbody></table></div></section>

    <?php if ($isManager): ?>
        <section id="panel-report" role="tabpanel" aria-labelledby="tab-report" data-panel="report" hidden>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h1 class="admin-h m-0">Daily report</h1>
                <label class="field w-48"><span>Date</span><input type="date" data-report-date value="<?= e(Config::now()->format('Y-m-d')) ?>"></label>
            </div>
            <div data-report class="mt-6"></div>
        </section>
        <section id="panel-menu" role="tabpanel" aria-labelledby="tab-menu" data-panel="menu" hidden>
            <div class="admin-heading-row">
                <div><h1 class="admin-h">What's on the menu</h1><p class="text-muted">Keep today's availability clear for reservations, pre-orders and the kitchen.</p></div>
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
<script src="/public/assets/js/admin.js?v=20261118b" defer></script>
</body>
</html>
