<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$menuCategories = (new MenuRepository(db()))->categoriesWithItems();
$pageTitle = 'Menu · Bella Vista Dining';
$activePage = 'menu';
require __DIR__ . '/../includes/header.php';
?>
<main id="main" class="wrap menu-page">
    <div class="menu-page-head">
        <div>
            <p class="label label-clay">Fresh from our kitchen</p>
            <h1 class="title">Made to be shared.</h1>
            <p class="lede">Filipino classics, family-style favourites and local drinks. Pre-order when you book and the kitchen times it to your table.</p>
        </div>
        <a class="button button-forest" href="/public/reserve.php">Reserve &amp; pre-order</a>
    </div>
    <?php $menuOrderable = false; require __DIR__ . '/../includes/partials/menu-list.php'; ?>
    <p class="hint menu-sample-note">Sample menu and prices.</p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
