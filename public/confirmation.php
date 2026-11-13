<?php
declare(strict_types=1);

/*
 * "Your table is waiting." Shown after booking (on-site payment) and after a
 * successful online payment. Only for bookings this browser made or looked up
 * (GuestSession); anyone else is sent to the lookup form.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$reference = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$payment = in_array($_GET['payment'] ?? '', ['success', 'unavailable'], true) ? $_GET['payment'] : null;
$email = preg_match('/^[A-Z0-9]{8}$/', $reference) ? GuestSession::emailFor($reference) : null;
$booking = null;
if ($email !== null) {
    try {
        $booking = ReservationService::make(db())->lookup($reference, $email);
    } catch (NotFoundException) {
        $booking = null;
    }
}
if ($booking === null) {
    Response::redirect('/public/booking.php' . ($reference !== '' && preg_match('/^[A-Z0-9]{8}$/', $reference) ? '?ref=' . rawurlencode($reference) : ''));
}

$start = BookingRules::startOf($booking['reservation_date'], $booking['start_time']);
$arriveBy = BookingRules::graceEndsAt($start);
$firstName = strtok($booking['name'], ' ') ?: $booking['name'];
$isVip = str_starts_with($booking['table_id'], 'VIP');
$tableName = $isVip ? 'VIP Room ' . substr($booking['table_id'], 3) : $booking['table_type'] . ' ' . $booking['table_id'];
$statusClass = ['Confirmed' => 'badge-mint', 'Pending' => 'badge-gold'][$booking['status']] ?? 'badge-muted';
$paymentLabel = [
    'On-site' => 'Pay at the restaurant',
    'Unpaid' => 'Awaiting online payment',
    'Downpayment' => 'Downpayment paid online',
    'Paid' => 'Paid in full online',
][$booking['payment_status']] ?? $booking['payment_status'];

$pageTitle = 'Booking confirmed · Bella Vista Dining';
$activePage = 'booking';
require __DIR__ . '/../templates/header.php';
?>
<main id="main" class="wrap confirm">
    <div class="confirm-head">
        <span class="confirm-badge"><?= icon('check') ?></span>
        <p class="label">See you at Bella Vista</p>
        <h1 class="title"><?= $booking['status'] === 'Pending' ? 'Your table is on hold.' : e($firstName) . ', your table is waiting.' ?></h1>
        <p class="lede">
            <?= $booking['status'] === 'Pending'
                ? 'We’re holding it for ' . e(PaymentService::HOLD_MINUTES) . ' minutes while you finish paying.'
                : 'A lovely ' . ((int) $start->format('G') >= 17 ? 'evening' : 'meal') . ' is on the calendar. Keep your reference for changes or cancellations.' ?>
        </p>
    </div>

    <?php if ($payment === 'unavailable'): ?>
        <p class="notice notice-error" role="alert"><?= icon('alert') ?><span>Online payment is unavailable right now. Your table is held for <?= e(PaymentService::HOLD_MINUTES) ?> minutes: pay from <a href="/public/booking.php?ref=<?= e(rawurlencode($reference)) ?>">your booking page</a>, or choose to pay at the restaurant there.</span></p>
    <?php elseif ($payment === 'success'): ?>
        <p class="notice notice-success" role="status"><?= icon('check') ?><span>Payment received. Thank you!</span></p>
    <?php endif; ?>

    <div class="confirm-grid">
        <div class="confirm-main">
            <section class="card confirm-card" aria-labelledby="confirm-when">
                <div class="confirm-card-head">
                    <p class="reference-code" title="Booking reference"><?= e($booking['reference_code']) ?></p>
                    <span class="badge <?= e($statusClass) ?>"><?= e($booking['status']) ?></span>
                </div>
                <h2 id="confirm-when"><?= e($start->format('l, F j')) ?></h2>
                <dl class="totals">
                    <div><dt>Dining time</dt><dd><?= e(Config::slotLabel($booking['start_time'])) ?> · <?= e(Config::SLOT_HOURS) ?> hours</dd></div>
                    <div><dt>Your table</dt><dd><?= e($tableName) ?> · <?= e($booking['number_of_guests']) ?> guests</dd></div>
                    <div><dt>Reserved for</dt><dd><?= e($booking['name']) ?></dd></div>
                </dl>
            </section>

            <section class="confirm-section" aria-labelledby="confirm-preorder">
                <div class="label-row">
                    <h2 class="label" id="confirm-preorder">Your pre-order</h2>
                    <?php if ($booking['items']): ?><span class="badge badge-gold">Scheduled · <?= e(Config::displayTime($booking['start_time'])) ?></span><?php endif; ?>
                </div>
                <?php if ($booking['items']): ?>
                    <p class="confirm-items"><?= e(implode(' · ', array_map(static fn (array $i): string => $i['quantity'] . ' × ' . $i['name'], $booking['items']))) ?></p>
                <?php else: ?>
                    <p class="confirm-items">No pre-order. You’ll order when you sit down.</p>
                <?php endif; ?>
            </section>

            <div class="arrive-note">
                <strong>Arrive by <?= e($arriveBy->format('g:i A')) ?></strong>
                <p>We hold your table for <?= e(Config::GRACE_MINUTES) ?> minutes after <?= e(Config::displayTime($booking['start_time'])) ?>, then release it. We look forward to welcoming you.</p>
            </div>
        </div>

        <section class="card confirm-card" aria-labelledby="confirm-payment">
            <h2 class="label" id="confirm-payment">Your payment</h2>
            <dl class="totals">
                <div><dt>Pre-order</dt><dd><?= e(Money::format($booking['order_total'])) ?></dd></div>
                <?php if ($booking['total_fee'] > 0): ?>
                    <div><dt>Reservation fee</dt><dd><?= e(Money::format($booking['total_fee'])) ?></dd></div>
                <?php endif; ?>
                <?php if ($booking['amount_paid'] > 0): ?>
                    <div><dt>Paid online</dt><dd>−<?= e(Money::format($booking['amount_paid'])) ?></dd></div>
                <?php endif; ?>
                <div class="totals-total"><dt>Balance at your visit</dt><dd><?= e(Money::format($booking['balance'])) ?></dd></div>
            </dl>
            <p class="totals-note"><?= e($paymentLabel) ?>.<?= $booking['total_fee'] > 0 ? ' Your reservation fee is credited to your food bill.' : '' ?></p>
            <div class="confirm-actions">
                <a class="button button-forest button-block" href="/public/booking.php?ref=<?= e(rawurlencode($booking['reference_code'])) ?>">Manage booking</a>
                <a class="button button-outline button-block" href="/index.php">Back to discover</a>
            </div>
        </section>
    </div>
</main>
<?php require __DIR__ . '/../templates/footer.php'; ?>
