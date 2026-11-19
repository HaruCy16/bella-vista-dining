<?php
declare(strict_types=1);

/*
 * Permanently remove one test booking and its related records.
 *
 * docker compose exec app php scripts/cleanup-test-booking.php 4AAZLJWV
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/Support/Env.php';
Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../includes/db.php';

$reference = strtoupper(trim((string) ($argv[1] ?? '')));
if (!preg_match('/^[A-Z0-9]{8}$/', $reference)) {
    fwrite(STDERR, "Usage: php scripts/cleanup-test-booking.php <8-character reference>\n");
    exit(1);
}

$pdo = db();
$statement = $pdo->prepare(
    'SELECT reservation_id, customer_id FROM reservations WHERE reference_code = :reference'
);
$statement->execute(['reference' => $reference]);
$booking = $statement->fetch();

if ($booking === false) {
    fwrite(STDOUT, "Booking {$reference} was not found.\n");
    exit(0);
}

$reservationId = (int) $booking['reservation_id'];
$customerId = (int) $booking['customer_id'];

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'DELETE FROM payments
         WHERE reservation_id = :reservation_id
            OR order_id IN (SELECT order_id FROM orders WHERE reservation_id = :reservation_id)'
    )->execute(['reservation_id' => $reservationId]);

    $pdo->prepare('DELETE FROM orders WHERE reservation_id = :reservation_id')
        ->execute(['reservation_id' => $reservationId]);

    $pdo->prepare('DELETE FROM reservations WHERE reservation_id = :reservation_id')
        ->execute(['reservation_id' => $reservationId]);

    $otherBookings = $pdo->prepare(
        'SELECT COUNT(*) FROM reservations WHERE customer_id = :customer_id'
    );
    $otherBookings->execute(['customer_id' => $customerId]);
    if ((int) $otherBookings->fetchColumn() === 0) {
        $pdo->prepare('DELETE FROM customers WHERE customer_id = :customer_id')
            ->execute(['customer_id' => $customerId]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

fwrite(STDOUT, "Removed test booking {$reference} and its related payment/order records.\n");
