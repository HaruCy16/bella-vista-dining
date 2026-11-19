<?php
declare(strict_types=1);

/*
 * Manage a booking: find it by reference + email (or from this browser's session),
 * see status and payments, pay a deposit / the balance, or cancel.
 * All actions go through /public/api/*.php; this page only renders.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$reference = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$paymentResult = in_array($_GET['payment'] ?? '', ['success', 'failed', 'cancelled'], true) ? $_GET['payment'] : null;
$booking = null;
if ($reference !== '' && ($email = GuestSession::emailFor($reference)) !== null) {
    try {
        $booking = ReservationService::make(db())->lookup($reference, $email);
    } catch (NotFoundException) {
        $booking = null;
    }
}

$mine = ['upcoming' => [], 'past' => []];
if ($booking === null) {
    $service = ReservationService::make(db());
    $now = Config::now();
    foreach (array_reverse(GuestSession::all(), true) as $ref => $mail) {
        try {
            $b = $service->lookup((string) $ref, $mail);
        } catch (NotFoundException) {
            continue;
        }
        $live = in_array($b['status'], ['Pending', 'Confirmed', 'Checked-in'], true)
            && BookingRules::startOf($b['reservation_date'], $b['end_time']) > $now;
        $mine[$live ? 'upcoming' : 'past'][] = $b;
    }
    usort($mine['upcoming'], fn ($a, $b) => [$a['reservation_date'], $a['start_time']] <=> [$b['reservation_date'], $b['start_time']]);
}
$statusLabel = ['Pending' => 'Awaiting payment', 'Confirmed' => 'Confirmed', 'Checked-in' => 'Seated', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled', 'No-show' => 'Released (no-show)'];

$pageTitle = 'Your booking · Bella Vista Dining';
$activePage = 'booking';
require __DIR__ . '/../includes/header.php';

?>
<main id="main" class="wrap booking-page">
    <div class="booking-head">
        <h1 class="title m-0">Your booking</h1>
        <p class="lede mt-3">Check your table, pay online, or cancel. You'll need the reference from your confirmation and the email you booked with.</p>
    </div>

    <?php if ($paymentResult === 'success'): ?>
        <p class="notice notice-success" role="status"><?= icon('check') ?><span>Payment received. Thank you! Your table is confirmed.</span></p>
    <?php elseif ($paymentResult === 'failed'): ?>
        <p class="notice notice-error" role="alert"><?= icon('alert') ?><span>The payment didn't go through and you weren't charged. You can try again below or pay at the restaurant.</span></p>
    <?php elseif ($paymentResult === 'cancelled'): ?>
        <p class="notice" role="status"><?= icon('alert') ?><span>Checkout was cancelled. Your booking is still saved below.</span></p>
    <?php endif; ?>

    <?php if ($mine['upcoming'] || $mine['past']): ?>
        <div class="my-bookings" data-my-bookings>
            <?php foreach (['upcoming' => 'Current bookings', 'past' => 'Previous bookings'] as $key => $heading): if (!$mine[$key]) continue; ?>
                <section aria-labelledby="mb-<?= $key ?>">
                    <h2 id="mb-<?= $key ?>" class="mb-heading"><?= e($heading) ?></h2>
                    <ul class="mb-list">
                        <?php foreach ($mine[$key] as $b): ?>
                            <li><a class="mb-item" href="?ref=<?= e(urlencode($b['reference_code'])) ?>">
                                <span class="mb-main"><strong><?= e($b['display_when']) ?></strong>
                                    <small><?= e($b['table_type']) ?> table <?= e($b['table_id']) ?> · <?= (int) $b['number_of_guests'] ?> <?= $b['number_of_guests'] === 1 ? 'guest' : 'guests' ?> · <?= e($b['reference_code']) ?></small></span>
                                <span class="chip <?= in_array($b['status'], ['Confirmed', 'Checked-in'], true) ? 'chip-ok' : ($b['status'] === 'Pending' ? 'chip-warn' : 'chip-muted') ?>"><?= e($statusLabel[$b['status']] ?? $b['status']) ?></span>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
            <p class="hint">Showing bookings made or looked up on this device. Have a reference from elsewhere? Find it below.</p>
        </div>
    <?php endif; ?>

    <form class="lookup-form" data-lookup <?= $booking ? 'hidden' : '' ?>>
        <label class="field"><span>Booking reference</span><input name="reference_code" required maxlength="8" autocomplete="off" autocapitalize="characters" value="<?= e($reference) ?>" placeholder="e.g. K7M2QXPA"></label>
        <label class="field"><span>Email</span><input name="email" type="email" required autocomplete="email" autocapitalize="none"></label>
        <button class="button button-primary" type="submit">Find my booking</button>
        <p class="form-error col-span-full" role="alert" hidden><?= icon('alert') ?><span></span></p>
    </form>

    <p class="m-0 mt-6" data-back <?= $booking ? '' : 'hidden' ?>><a class="text-link" href="/public/booking.php">← All my bookings</a></p>
    <section class="booking-card" data-booking hidden aria-live="polite">
    </section>
</main>
<script>window.BV_DEPOSIT_PERCENT = <?= PaymentService::DEPOSIT_PERCENT ?>; window.BV_BOOKING = <?= json_encode($booking, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="/public/assets/js/booking.js?v=20261119a" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
