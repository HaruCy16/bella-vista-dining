<?php
$pageTitle = 'The menu';
$activePage = 'menu';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-hero page-hero-menu"><div class="max-w-[1320px] mx-auto px-5 sm:px-8 lg:px-12"><p class="eyebrow eyebrow-light"><span class="eyebrow-rule"></span> From the Bella Vista kitchen</p><h1 class="page-title text-white mt-5">The menu, <em>in season.</em></h1><p class="page-lead text-white/70 mt-5">Familiar comfort, fresh ingredients, and a few surprises worth making room for.</p></div></section>
<section class="section-pad pt-12 lg:pt-16" data-menu-page>
    <div class="max-w-[1320px] mx-auto px-5 sm:px-8 lg:px-12">
        <div class="menu-toolbar"><div><p class="eyebrow"><span class="eyebrow-rule"></span> A table full of good things</p><h2 class="section-title mt-4">Choose your <em>starting point.</em></h2></div><div class="menu-filters" role="tablist" aria-label="Menu categories"><button class="filter-pill is-active" type="button" data-menu-filter="all" role="tab" aria-selected="true">All dishes</button><button class="filter-pill" type="button" data-menu-filter="mains" role="tab" aria-selected="false">Main courses</button><button class="filter-pill" type="button" data-menu-filter="starters" role="tab" aria-selected="false">Appetizers & soups</button><button class="filter-pill" type="button" data-menu-filter="desserts" role="tab" aria-selected="false">Desserts & drinks</button></div></div>
        <div class="menu-grid mt-12" data-menu-grid>
            <?php foreach ($menuItems as $item): ?>
                <article class="menu-card" data-menu-category="<?= $item['category_key'] ?>" data-menu-card>
                    <div class="menu-card-image"><img src="<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy"><span class="menu-badge"><?= htmlspecialchars($item['badge']) ?></span></div>
                    <div class="menu-card-body"><div class="flex justify-between gap-3 items-start"><div><p class="card-kicker"><?= htmlspecialchars($item['category']) ?></p><h3 class="card-title mt-2"><?= htmlspecialchars($item['name']) ?></h3></div><span class="price whitespace-nowrap"><?= peso($item['price']) ?></span></div><p class="card-description mt-3"><?= htmlspecialchars($item['description']) ?></p><div class="flex items-center justify-between gap-4 mt-6"><span class="available-state"><span class="status-dot"></span><?= $item['available'] ? 'Available today' : 'Currently unavailable' ?></span><button class="btn btn-small btn-primary" type="button" data-add-to-cart data-item-id="<?= $item['id'] ?>" <?= !$item['available'] ? 'disabled' : '' ?>>Add to cart <span aria-hidden="true">+</span></button></div></div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="empty-state mt-10" data-menu-empty hidden><span class="empty-state-mark">✦</span><h3 class="card-title mt-3">A quiet corner of the menu</h3><p class="card-description mt-2">Nothing is showing in this category just yet. Try another table setting.</p></div>
        <div class="menu-note mt-14"><span class="menu-note-icon">i</span><p><strong>Sample menu for Stage 1.</strong> Prices and availability shown here are realistic development content. The live menu, dietary details, and daily availability will connect to the restaurant database in Stage 2.</p></div>
    </div>
</section>
<div class="cart-drawer" data-cart-drawer aria-hidden="true"><div class="cart-backdrop" data-cart-close></div><aside class="cart-panel" aria-label="Your order"><div class="flex justify-between items-center"><div><p class="eyebrow">Your table, your way</p><h2 class="font-display text-3xl text-forest mt-2">Your order</h2></div><button class="close-button" type="button" data-cart-close aria-label="Close cart">×</button></div><div class="cart-items mt-8" data-cart-items><div class="cart-empty"><span class="empty-state-mark">✦</span><p class="font-display text-2xl text-forest mt-4">Your cart is ready.</p><p class="text-sm text-walnut/60 mt-2">Add something delicious from the menu.</p></div></div><div class="cart-summary mt-8"><div class="summary-row"><span>Subtotal</span><strong data-cart-subtotal>₱0</strong></div><p class="text-xs leading-5 text-walnut/55 mt-3">Reservation fees and applicable table charges are calculated at checkout.</p><a href="/pages/checkout.php" class="btn btn-primary w-full justify-center mt-5">Continue to checkout <span aria-hidden="true">↗</span></a></div></aside></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
