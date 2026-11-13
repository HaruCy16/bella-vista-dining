<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$standardMinimum = 0;
foreach ((new TableTypeRepository(db()))->allWithTableCounts() as $type) {
    $standardMinimum = max($standardMinimum, Money::fromPesos((string) $type['minimum_order']));
}
$pageTitle = 'Visit & policies · Bella Vista Dining';
$activePage = 'visit';
require __DIR__ . '/../includes/header.php';
?>
<main class="inner-page" id="main">
    <section class="inner-hero"><p class="eyebrow">Plan your visit</p><h1>Make a day<br><em>of it.</em></h1><p>Come hungry, stay awhile, and let the view do some of the talking.</p></section>
    <section class="visit-page-content"><div><p class="eyebrow">Opening hours</p><h2>Take your time.</h2><div class="hours"><div><span>Open</span><strong>Tuesday – Sunday</strong></div><div><span>Hours</span><strong><?= e(Config::displayTime(Config::OPENS_AT)) ?> – <?= e(Config::displayTime(Config::CLOSES_AT)) ?></strong></div><div><span>Closed</span><strong>Monday</strong></div></div></div><?php $locationStacked = true; require __DIR__ . '/../includes/partials/location.php'; ?></section>

    <section class="wrap section" id="policies" aria-labelledby="policies-title">
        <div class="policies">
            <div>
                <h2 class="heading-lg" id="policies-title">Good to know before you book.</h2>
                <p class="lede">Our booking policies. You’ll confirm them at checkout.</p>
            </div>
            <dl class="policy-list">
                <div><dt>Seating</dt><dd>Every reservation is a <?= e(Config::SLOT_HOURS) ?>-hour block, Tuesday to Sunday.</dd></div>
                <div><dt>Running late</dt><dd>We hold your table for <?= e(Config::GRACE_MINUTES) ?> minutes past your start time, then release it.</dd></div>
                <div><dt>Cancelling</dt><dd>Cancel at least <?= e(Config::CANCEL_NOTICE_HOURS) ?> hours ahead on the <a href="/public/booking.php">Your booking</a> page. Family and VIP fees aren’t refunded for later or same-day cancellations.</dd></div>
                <div><dt>Minimum order</dt><dd>Standard tables have a <?= e(Money::format($standardMinimum)) ?> minimum food order upon seating.</dd></div>
                <div><dt>Fees</dt><dd>Family and VIP fees are credited to your food bill. If your food comes to less than the fee, the difference isn’t refunded.</dd></div>
                <div><dt>Paying</dt><dd>Pay a downpayment or the full amount online, or pay at the restaurant in cash or by card.</dd></div>
                <div><dt>Outside food</dt><dd>Please don’t bring outside food or drinks.</dd></div>
            </dl>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
