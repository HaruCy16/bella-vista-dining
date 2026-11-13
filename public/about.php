<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Our Story | Bella Vista Dining';
$activePage = 'about';
require __DIR__ . '/../includes/header.php';
?>
<main class="inner-page" id="main">
    <section class="inner-hero"><p class="eyebrow">Our story</p><h1>Rooted in the estate.<br><em>Made for everyone.</em></h1><p>Bella Vista Dining brings local Filipino comfort food and continental specialties to a cozy, ambient garden setting.</p></section>
    <section class="content-panel"><div class="content-image"><img src="<?= e(venue_image('dining-room', '/public/assets/images/menu/items/pancit-canton-special.jpg')) ?>" alt="Bella Vista dining room opening onto a garden" loading="lazy" width="720" height="900"></div><div class="content-copy"><p class="eyebrow">A place to take your time</p><h2>Good food brings people closer together.</h2><p>Established alongside the Bella Vista estate in 2021, our dining hall first welcomed in-house guests. It later opened its doors as an independent destination after guests asked to return for the signature dishes and scenic garden views.</p><p>Our table is guided by a simple idea: thoughtful food, generous hospitality, and room for everyone.</p></div></section>

    <section class="wrap section about-map" aria-labelledby="find-us-title">
        <div class="section-head">
            <p class="eyebrow">Find us</p>
            <h2 class="title" id="find-us-title">Come see the garden for yourself.</h2>
            <p class="lede">Open Tuesday to Sunday, <?= e(Config::displayTime(Config::OPENS_AT)) ?> to <?= e(Config::displayTime(Config::CLOSES_AT)) ?>. Closed Mondays.</p>
        </div>
        <?php require __DIR__ . '/../includes/partials/location.php'; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
