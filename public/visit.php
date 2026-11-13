<?php
$pageTitle = 'Visit | Bella Vista Dining';
$activePage = 'visit';
require __DIR__ . '/../templates/header.php';
?>
<main class="inner-page" id="main">
    <section class="inner-hero"><p class="eyebrow">Plan your visit</p><h1>Make a day<br><em>of it.</em></h1><p>Come hungry, stay awhile, and let the view do some of the talking.</p></section>
    <section class="visit-page-content"><div><p class="eyebrow">Opening hours</p><h2>Take your time.</h2><div class="hours"><div><span>Open</span><strong>Tuesday – Sunday</strong></div><div><span>Hours</span><strong>10:00 AM – 10:00 PM</strong></div><div><span>Closed</span><strong>Monday</strong></div></div></div><?php $locationStacked = true; require __DIR__ . '/../templates/partials/location.php'; ?></section>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
