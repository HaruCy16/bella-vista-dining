<?php
declare(strict_types=1);

/*
 * Sandbox checkout for the Mock gateway. Stands in for a provider's hosted page:
 * the tester chooses the outcome, and the result goes through the same
 * PaymentService::complete() a real webhook will use. Disabled in production.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!MockGateway::enabled()) {
    http_response_code(404);
    exit('Not found');
}

$session = (string) ($_GET['session'] ?? '');
$payment = str_starts_with($session, 'mock_') ? (new PaymentRepository(db()))->findByProviderReference($session) : null;
$reservation = $payment ? (new ReservationRepository(db()))->findById((int) $payment['reservation_id']) : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="robots" content="noindex">
    <title>Test checkout · Bella Vista Dining</title>
    <link rel="stylesheet" href="/public/assets/css/app.css?v=20261114c">
</head>
<body class="mock-pay">
<main class="mock-card">
    <p class="chip chip-warn">Sandbox · no real money</p>
    <?php if ($payment === null || $reservation === null): ?>
        <h1>Checkout not found</h1>
        <p class="text-muted">This test checkout link is invalid or has expired.</p>
        <a class="button button-secondary" href="/public/booking.php">Go to your booking</a>
    <?php elseif ($payment['payment_state'] !== 'Pending'): ?>
        <h1>Already <?= e(strtolower($payment['payment_state'])) ?></h1>
        <p class="text-muted">This payment was already processed.</p>
        <a class="button button-primary" href="/public/booking.php?ref=<?= e(rawurlencode($reservation['reference_code'])) ?>">View booking</a>
    <?php else: ?>
        <h1>Bella Vista Dining</h1>
        <p class="text-muted m-0">Booking <?= e($reservation['reference_code']) ?> · <?= e($payment['payment_type'] === 'Full' ? 'Full payment' : 'Deposit') ?></p>
        <p class="mock-amount"><?= e(Money::format(Money::fromPesos((string) $payment['amount']))) ?></p>
        <p class="text-muted small">Choose the result you want to test. A real provider (PayMongo test mode) will show its own GCash, Maya and card screens here.</p>
        <div class="grid gap-3">
            <button class="button button-primary" type="button" data-outcome="success">Simulate successful payment</button>
            <button class="button button-secondary" type="button" data-outcome="failure">Simulate failed payment</button>
        </div>
        <p class="form-error mt-4" role="alert" hidden></p>
        <script>
            document.querySelectorAll('[data-outcome]').forEach((button) => button.addEventListener('click', async () => {
                document.querySelectorAll('[data-outcome]').forEach((b) => { b.disabled = true; });
                try {
                    const response = await fetch('/public/api/payments.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ action: 'mock-complete', session: <?= json_encode($session, JSON_HEX_TAG | JSON_HEX_AMP) ?>, outcome: button.dataset.outcome }),
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.error || 'Payment could not be processed.');
                    window.location.assign(result.data.redirect);
                } catch (error) {
                    const box = document.querySelector('.form-error');
                    box.textContent = error.message; box.hidden = false;
                    document.querySelectorAll('[data-outcome]').forEach((b) => { b.disabled = false; });
                }
            }));
        </script>
    <?php endif; ?>
</main>
</body>
</html>
