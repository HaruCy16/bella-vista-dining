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
    <link rel="stylesheet" href="/public/assets/css/app.css?v=20261114b">
</head>
<body class="admin">
<header class="admin-bar">
    <div class="wrap admin-bar-inner">
        <p class="brand m-0"><?= brand_logo(44) ?><span>Operations</span></p>
        <nav class="admin-tabs" role="tablist" aria-label="Dashboard sections">
            <button role="tab" type="button" aria-selected="true" aria-controls="panel-tables" id="tab-tables" data-tab="tables">Tables</button>
            <button role="tab" type="button" aria-selected="false" aria-controls="panel-kitchen" id="tab-kitchen" data-tab="kitchen" tabindex="-1">Kitchen <span class="count" data-queue-count></span></button>
            <?php if ($isManager): ?>
                <button role="tab" type="button" aria-selected="false" aria-controls="panel-report" id="tab-report" data-tab="report" tabindex="-1">Daily report</button>
            <?php endif; ?>
        </nav>
        <div class="admin-user">
            <span><?= e($admin['full_name']) ?> · <?= e($admin['role']) ?></span>
            <form method="post" action="/admin/logout.php"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="text-link" type="submit">Sign out</button></form>
        </div>
    </div>
</header>

<main class="wrap admin-main" id="main">
    <p class="admin-status" data-sync aria-live="polite">Loading…</p>
    <p class="form-error" data-admin-error role="alert" hidden><?= icon('alert') ?><span></span></p>

    <section id="panel-tables" role="tabpanel" aria-labelledby="tab-tables" data-panel="tables">
        <h1 class="admin-h">Tables today</h1>
        <div class="board" data-board></div>
        <h2 class="admin-h mt-10">Today's bookings</h2>
        <div class="table-scroll"><table class="data-table" data-bookings>
            <thead><tr><th scope="col">Time</th><th scope="col">Table</th><th scope="col">Guest</th><th scope="col">Guests</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody></tbody>
        </table></div>
    </section>

    <section id="panel-kitchen" role="tabpanel" aria-labelledby="tab-kitchen" data-panel="kitchen" hidden>
        <h1 class="admin-h">Kitchen queue</h1>
        <p class="text-muted">Pre-orders appear here an hour before the booking, or as soon as the guests are checked in.</p>
        <div class="queue" data-queue></div>
    </section>

    <?php if ($isManager): ?>
        <section id="panel-report" role="tabpanel" aria-labelledby="tab-report" data-panel="report" hidden>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h1 class="admin-h m-0">Daily report</h1>
                <label class="field w-48"><span>Date</span><input type="date" data-report-date value="<?= e(Config::now()->format('Y-m-d')) ?>"></label>
            </div>
            <div data-report class="mt-6"></div>
        </section>
    <?php endif; ?>
</main>
<script src="/public/assets/js/admin.js?v=20261114b" defer></script>
</body>
</html>
