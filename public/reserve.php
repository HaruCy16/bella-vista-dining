<?php
declare(strict_types=1);

/*
 * Booking flow: visit (date, party, time) -> seating -> menu -> pre-order review -> checkout.
 * One page; public/assets/js/reserve.js switches steps (each step has its own #hash, so
 * the browser's back button works) and posts to /public/api/reservations.php.
 * The server re-checks and re-prices everything; numbers here are for display.
 *
 * Optional prefill from the landing page: ?date=Y-m-d&time=HH:MM:SS&guests=N
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$typeRepository = new TableTypeRepository($pdo);
$tableTypes = $typeRepository->allWithTableCounts();
$tables = $typeRepository->tables();
$menuCategories = (new MenuRepository($pdo))->categoriesWithItems();

$now = Config::now();
$slots = Config::reservationSlots();
$minGuests = (int) min(array_column($tableTypes, 'capacity_min') ?: [1]);
$maxGuests = (int) max(array_column($tableTypes, 'capacity_max') ?: [20]);
$standardMinimum = max(array_map(static fn (array $t): int => Money::fromPesos((string) $t['minimum_order']), $tableTypes) ?: [0]);

// Prefill, whitelisted. Anything invalid is ignored and the guest picks again.
$prefill = [];
$date = (string) ($_GET['date'] ?? '');
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && Config::isOperatingDate($date)) {
    $prefill['date'] = $date;
}
$time = (string) ($_GET['time'] ?? '');
if (Config::isReservationSlot($time)) {
    $prefill['time'] = $time;
}
$guests = filter_var($_GET['guests'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minGuests, 'max_range' => $maxGuests]]);
if ($guests !== false && $guests !== null) {
    $prefill['guests'] = $guests;
}

$typeCopy = [
    'Standard' => 'No reservation fee. %minimum% minimum food order upon seating.',
    'Family' => '%fee% reservation payment, fully consumable toward your final food bill.',
    'VIP' => 'Up to %base% guests · Maximum %max%. %fee% fully consumable reservation fee.',
];

$config = [
    'today' => $now->format('Y-m-d'),
    'nowTime' => $now->format('H:i:s'),
    'windowDays' => BookingRules::BOOKING_WINDOW_DAYS,
    'closedWeekday' => Config::CLOSED_WEEKDAY,
    'slots' => array_map(static fn (string $s): array => ['value' => $s, 'label' => Config::slotLabel($s)], $slots),
    'minGuests' => $minGuests,
    'maxGuests' => $maxGuests,
    'depositPercent' => PaymentService::DEPOSIT_PERCENT,
    'graceMinutes' => Config::GRACE_MINUTES,
    'types' => array_map(static fn (array $t): array => [
        'name' => $t['type_name'],
        'min' => (int) $t['capacity_min'],
        'max' => (int) $t['capacity_max'],
        'base' => (int) $t['base_capacity'],
        'fee' => Money::fromPesos((string) $t['reservation_fee']),
        'extra' => Money::fromPesos((string) $t['extra_charge']),
        'minimum' => Money::fromPesos((string) $t['minimum_order']),
    ], $tableTypes),
    'prefill' => $prefill,
];

$pageTitle = 'Reserve a table · Bella Vista Dining';
$activePage = 'reserve';
$flowPage = true;
require __DIR__ . '/../includes/header.php';
?>
<main id="main" class="flow" data-flow>
    <div class="appbar">
        <button class="appbar-back" type="button" data-back aria-label="Back"><?= icon('arrow-left') ?></button>
        <p class="appbar-title" data-app-title aria-hidden="true">Plan your visit</p>
        <a class="appbar-home" href="/index.php" aria-label="Bella Vista Dining, home"><?= icon('sprout') ?></a>
    </div>

    <div class="wrap flow-wrap">
        <div class="flow-main">
            <ol class="flow-steps" aria-label="Booking steps">
                <li data-crumb="visit">Your visit</li>
                <li data-crumb="seating">Seating</li>
                <li data-crumb="menu review">Pre-order</li>
                <li data-crumb="checkout">Checkout</li>
            </ol>

            <!-- Step 1: date, party, time -->
            <section class="flow-step" data-step="visit" aria-labelledby="h-visit">
                <h1 class="flow-heading flow-heading-mobile" id="h-visit" tabindex="-1">There’s a place for you here.</h1>
                <p class="flow-lede">A garden-side meal, a family gathering, or simply a little time together.</p>

                <div class="card cal-card">
                    <div class="cal-head">
                        <p class="label m-0" data-cal-month></p>
                        <div class="cal-nav">
                            <button type="button" data-cal-prev aria-label="Previous week"><?= icon('chevron-left') ?></button>
                            <button type="button" data-cal-next aria-label="Next week"><?= icon('chevron-right') ?></button>
                        </div>
                    </div>
                    <div class="cal-week" role="radiogroup" aria-label="Date" data-cal-days></div>
                    <p class="cal-note">Mondays unavailable · Restaurant closed</p>
                </div>

                <p class="label" id="party-label">Your party</p>
                <div class="card party" role="group" aria-labelledby="party-label">
                    <button type="button" data-guests="-1" aria-label="One fewer guest"><?= icon('minus') ?></button>
                    <output data-guests-value aria-live="polite">2 guests</output>
                    <button type="button" data-guests="1" aria-label="One more guest"><?= icon('plus') ?></button>
                </div>

                <div class="label-row">
                    <p class="label" id="time-label">Choose a time</p>
                    <span class="badge badge-mint"><?= e(Config::SLOT_HOURS) ?>-hour blocks</span>
                </div>
                <div class="slot-grid" role="radiogroup" aria-labelledby="time-label">
                    <?php foreach ($slots as $slot): ?>
                        <button class="slot" type="button" role="radio" aria-checked="false" data-slot="<?= e($slot) ?>"><?= e(Config::slotLabel($slot)) ?></button>
                    <?php endforeach; ?>
                </div>
                <p class="flow-note"><?= icon('clock') ?><span>Tuesday–Sunday · <?= e(Config::displayTime(Config::OPENS_AT)) ?>–<?= e(Config::displayTime(Config::CLOSES_AT)) ?><br><span data-visit-selected></span></span></p>
            </section>

            <!-- Step 2: seating -->
            <section class="flow-step" data-step="seating" aria-labelledby="h-seating" hidden>
                <h1 class="flow-heading" id="h-seating" tabindex="-1">A table for your occasion.</h1>
                <p class="context-line"><?= icon('calendar') ?><span data-context></span></p>
                <p class="flow-lede" data-seating-hint>Choose your setting.</p>
                <p class="field-status" data-seating-status role="status"></p>
                <div class="seat-cards">
                    <?php foreach ($tableTypes as $type):
                        $name = $type['type_name'];
                        $isVip = Money::fromPesos((string) $type['extra_charge']) > 0;
                        $copy = strtr($typeCopy[$name] ?? '%fee% reservation fee.', [
                            '%minimum%' => Money::format(Money::fromPesos((string) $type['minimum_order'])),
                            '%fee%' => Money::format(Money::fromPesos((string) $type['reservation_fee'])),
                            '%base%' => (string) $type['base_capacity'],
                            '%max%' => (string) $type['capacity_max'],
                        ]);
                    ?>
                        <div class="card seat-card" data-type="<?= e($name) ?>">
                            <div class="seat-card-head">
                                <h2><?= e($isVip ? 'VIP rooms' : $name) ?></h2>
                                <?php if ($isVip): ?>
                                    <span class="badge badge-gold">Private</span>
                                <?php else: ?>
                                    <span class="badge badge-mint"><?= e($type['capacity_min']) ?>–<?= e($type['capacity_max']) ?> guests</span>
                                <?php endif; ?>
                            </div>
                            <p class="seat-copy"><?= e($copy) ?></p>
                            <?php if ($isVip): ?>
                                <p class="seat-fine">Add <?= e(Money::format(Money::fromPesos((string) $type['extra_charge']))) ?> per guest beyond <?= e($type['base_capacity']) ?>. Your reservation payment is credited to the food bill.</p>
                            <?php endif; ?>
                            <div class="seat-chips<?= $isVip ? ' seat-chips-wide' : '' ?>" role="radiogroup" aria-label="<?= e($name) ?> tables">
                                <?php foreach ($tables as $table): if ($table['type_name'] !== $name) continue; ?>
                                    <button class="seat-chip" type="button" role="radio" aria-checked="false" data-table="<?= e($table['table_id']) ?>">
                                        <?= icon('check', 'icon seat-check') ?><span><?= e($isVip ? 'VIP Room ' . substr($table['table_id'], 3) : $table['table_id']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <p class="seat-note" data-seat-note></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Step 3a: menu -->
            <section class="flow-step" data-step="menu" aria-labelledby="h-menu" hidden>
                <h1 class="flow-heading" id="h-menu" tabindex="-1">Something for everyone at your table.</h1>
                <p class="flow-lede flow-lede-desktop">Pre-order now and the kitchen times it to your arrival. It’s optional: you can also order at the table.</p>
                <p class="context-pill"><?= icon('calendar') ?><span data-context></span></p>
                <?php $menuOrderable = true; require __DIR__ . '/../includes/partials/menu-list.php'; ?>
            </section>

            <!-- Step 3b: review the pre-order -->
            <section class="flow-step" data-step="review" aria-labelledby="h-review" hidden>
                <h1 class="flow-heading" id="h-review" tabindex="-1">Your table’s favorites.</h1>
                <p class="context-pill"><?= icon('calendar') ?><span data-context></span></p>
                <div class="label-row">
                    <p class="label" data-review-label>Pre-order</p>
                    <button class="text-button" type="button" data-goto="menu">Add more <?= icon('plus') ?></button>
                </div>
                <div class="card review-card">
                    <ul class="review-list" data-review-list></ul>
                    <div class="review-empty" data-review-empty hidden>
                        <p>No dishes yet. Pre-ordering is optional: you can order when you sit down.</p>
                        <button class="button button-outline button-small" type="button" data-goto="menu">Browse the menu</button>
                    </div>
                </div>
                <div class="card totals-card">
                    <dl class="totals" data-review-totals></dl>
                    <p class="totals-note" data-fee-note></p>
                </div>
                <p class="field-status" data-minimum-status role="status"></p>
                <p class="flow-note"><?= icon('cloche') ?><span data-schedule-note></span></p>
            </section>

            <!-- Step 4: details, payment, policies -->
            <section class="flow-step" data-step="checkout" aria-labelledby="h-checkout" hidden>
                <h1 class="flow-heading" id="h-checkout" tabindex="-1">Just a few details. Then, we’ll see you.</h1>
                <p class="context-pill"><?= icon('calendar') ?><span data-context></span></p>
                <form class="checkout-form" id="checkout-form" novalidate>
                    <div class="form-row">
                        <label class="field"><span>First name</span><input name="first_name" required maxlength="50" autocomplete="given-name"></label>
                        <label class="field"><span>Last name</span><input name="last_name" required maxlength="50" autocomplete="family-name"></label>
                    </div>
                    <div class="form-row">
                        <label class="field"><span>Mobile number</span><input name="phone_number" type="tel" required inputmode="tel" autocomplete="tel" placeholder="0917 555 0126"></label>
                        <label class="field"><span>Email address</span><input name="email" type="email" required maxlength="100" autocomplete="email" autocapitalize="none" spellcheck="false"></label>
                    </div>

                    <p class="label">How would you like to pay?</p>
                    <div class="pay-list">
                        <label class="pay-row" data-online><input type="radio" name="payment_option" value="deposit"><span>Downpayment online (<?= e(PaymentService::DEPOSIT_PERCENT) ?>%)<small>GCash, Maya or card · the rest at the restaurant</small></span><b data-pay="deposit"></b></label>
                        <label class="pay-row" data-online><input type="radio" name="payment_option" value="full"><span>Full payment online<small>GCash, Maya or card</small></span><b data-pay="full"></b></label>
                        <label class="pay-row"><input type="radio" name="payment_option" value="onsite" checked><span>Pay at the restaurant<small>Cash or card on the day</small></span><b data-pay="onsite"></b></label>
                    </div>
                    <div class="card totals-card">
                        <dl class="totals" data-checkout-totals></dl>
                        <p class="totals-note" data-pay-note></p>
                    </div>

                    <p class="label">A few things before you visit</p>
                    <ul class="policy-bullets">
                        <li>Arrive within <?= e(Config::GRACE_MINUTES) ?> minutes of your start time or your table is released.</li>
                        <li>Cancel at least <?= e(Config::CANCEL_NOTICE_HOURS) ?> hours ahead. Family and VIP fees aren’t refunded for later or same-day cancellations.</li>
                        <li>Family and VIP fees are credited to your food bill. Any unused amount isn’t refunded.</li>
                        <li>Standard tables have a <?= e(Money::format($standardMinimum)) ?> minimum food order upon seating.</li>
                        <li>No outside food or drinks.</li>
                        <li>Online bookings are held <?= e(PaymentService::HOLD_MINUTES) ?> minutes while you pay.</li>
                    </ul>
                    <label class="check-label"><input type="checkbox" name="terms_accepted" value="1" required><span>I agree to the reservation and cancellation policies.</span></label>
                </form>
            </section>
        </div>

        <aside class="flow-aside" aria-label="Your table">
            <div class="card summary-card">
                <h2>Your table</h2>
                <dl class="totals summary-rows" data-summary-rows></dl>
                <ul class="summary-items" data-summary-items></ul>
                <dl class="totals" data-summary-totals></dl>
            </div>
            <div class="flow-action">
                <div class="form-error" data-flow-error role="alert" hidden><?= icon('alert') ?><span></span></div>
                <p class="flow-meta" data-flow-meta></p>
                <button class="flow-cta" type="button" data-next><span data-next-label>Choose your seating</span><?= icon('arrow-right') ?></button>
            </div>
        </aside>
    </div>
</main>
<script>window.BV_RESERVE = <?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="/public/assets/js/reserve.js?v=20261113b" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
