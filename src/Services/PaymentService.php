<?php
declare(strict_types=1);

/**
 * Online payments for a booking (client spec VI.4: downpayment or full payment
 * online, or cash/card on site).
 *
 *   amount due = reservation fee + live pre-order total
 *   deposit    = 50% of the amount due (rounded up to the peso), minus what is already paid
 *   full       = amount due minus what is already paid
 *
 * State changes happen only in complete(), which is idempotent: a provider
 * webhook or the mock page can call it any number of times for the same payment.
 */
final class PaymentService
{
    public const DEPOSIT_PERCENT = 50;
    /** Unpaid online bookings hold their table this long before housekeeping releases them. */
    public const HOLD_MINUTES = 30;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ReservationRepository $reservations,
        private readonly OrderRepository $orders,
        private readonly PaymentRepository $payments,
        private readonly PaymentGateway $gateway,
    ) {
    }

    public static function make(PDO $pdo, ?PaymentGateway $gateway = null): self
    {
        return new self($pdo, new ReservationRepository($pdo), new OrderRepository($pdo), new PaymentRepository($pdo), $gateway ?? self::defaultGateway());
    }

    public static function defaultGateway(): PaymentGateway
    {
        $provider = strtolower(Env::get('PAYMENT_PROVIDER', 'mock') ?? 'mock');
        if ($provider === 'mock' && MockGateway::enabled()) {
            return new MockGateway();
        }
        throw new RuntimeException("Payment provider '{$provider}' is not configured.");
    }

    /**
     * Creates a pending payment and a hosted checkout for it.
     *
     * @return array{checkout_url: string, amount: int, payment_type: string}
     */
    public function start(int $reservationId, string $option, string $returnBaseUrl): array
    {
        if (!in_array($option, ['deposit', 'full'], true)) {
            throw ValidationException::field('payment_option', 'Choose to pay a deposit or the full amount.');
        }

        $this->pdo->beginTransaction();
        try {
            $reservation = $this->reservations->findById($reservationId, forUpdate: true);
            if ($reservation === null) {
                throw new NotFoundException('Booking not found.');
            }
            if (!in_array($reservation['status'], ['Pending', 'Confirmed'], true)) {
                throw new ConflictException('This booking can no longer be paid online.');
            }
            $amount = $this->amountFor($reservation, $option);
            if ($amount <= 0) {
                throw new ConflictException($option === 'deposit' ? 'Your deposit is already paid.' : 'Nothing left to pay online.');
            }
            $type = $option === 'full' ? 'Full' : 'Downpayment';
            $paymentId = $this->payments->insertPending([
                'reservation_id' => $reservationId,
                'payment_type' => $type,
                'amount' => Money::pesos($amount),
                'method' => $this->gateway->name(),
                'provider_reference' => 'pending_' . bin2hex(random_bytes(12)),
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $reference = $reservation['reference_code'];
        $checkout = $this->gateway->createCheckout([
            'payment_id' => $paymentId,
            'amount_centavos' => $amount,
            'description' => "Bella Vista Dining booking {$reference} ({$type})",
            'reference_code' => $reference,
            'customer_name' => $reservation['first_name'] . ' ' . $reservation['last_name'],
            'customer_email' => $reservation['email'],
            'success_url' => "{$returnBaseUrl}/public/confirmation.php?ref={$reference}&payment=success",
            'cancel_url' => "{$returnBaseUrl}/public/booking.php?ref={$reference}&payment=cancelled",
        ]);
        $this->payments->attachProviderReference($paymentId, $checkout['provider_reference']);

        return ['checkout_url' => $checkout['checkout_url'], 'amount' => $amount, 'payment_type' => $type];
    }

    /**
     * Applies a provider result. Idempotent: a payment that is no longer Pending is
     * left as it is. On success the reservation becomes Confirmed and its
     * payment_status becomes Downpayment or Paid, in the same transaction.
     *
     * @return array{changed: bool, state: string, reference_code: string}
     */
    public function complete(string $providerReference, bool $succeeded): array
    {
        $this->pdo->beginTransaction();
        try {
            $payment = $this->payments->findByProviderReference($providerReference, forUpdate: true);
            if ($payment === null) {
                throw new NotFoundException('Payment not found.');
            }
            $reservation = $this->reservations->findById((int) $payment['reservation_id'], forUpdate: true);
            if ($payment['payment_state'] !== 'Pending') {
                $this->pdo->commit();
                return ['changed' => false, 'state' => $payment['payment_state'], 'reference_code' => $reservation['reference_code']];
            }

            if (!$succeeded) {
                $this->payments->setState((int) $payment['payment_id'], 'Failed');
                $this->pdo->commit();
                return ['changed' => true, 'state' => 'Failed', 'reference_code' => $reservation['reference_code']];
            }

            $this->payments->setState((int) $payment['payment_id'], 'Succeeded');
            $paid = $this->payments->paidTotal((int) $reservation['reservation_id']);
            $due = $this->amountDue($reservation);
            $paymentStatus = $paid >= $due ? 'Paid' : 'Downpayment';
            // A late payment for a booking housekeeping already released stays recorded,
            // but does not resurrect the booking: staff refund it manually.
            $status = $reservation['status'] === 'Pending' ? 'Confirmed' : null;
            if (in_array($reservation['status'], ['Pending', 'Confirmed', 'Checked-in', 'Completed'], true)) {
                $this->reservations->updatePaymentStatus((int) $reservation['reservation_id'], $paymentStatus, $status);
            }
            $this->pdo->commit();
            return ['changed' => true, 'state' => 'Succeeded', 'reference_code' => $reservation['reference_code']];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** Guest chooses to settle everything at the restaurant instead. */
    public function chooseOnsite(int $reservationId): void
    {
        $reservation = $this->reservations->findById($reservationId);
        if ($reservation === null || !in_array($reservation['status'], ['Pending', 'Confirmed'], true)) {
            throw new ConflictException('This booking can no longer be changed.');
        }
        if ($reservation['payment_status'] === 'Unpaid') {
            $this->reservations->updatePaymentStatus($reservationId, 'On-site', 'Confirmed');
        }
    }

    public function amountDue(array $reservation): int
    {
        return Money::fromPesos((string) $reservation['total_fee'])
            + $this->orders->activeTotalForReservation((int) $reservation['reservation_id']);
    }

    private function amountFor(array $reservation, string $option): int
    {
        $due = $this->amountDue($reservation);
        $paid = $this->payments->paidTotal((int) $reservation['reservation_id']);
        if ($option === 'full') {
            return $due - $paid;
        }
        // DEPOSIT_PERCENT of the amount due, rounded up to whole pesos, integer math only.
        $deposit = intdiv($due * self::DEPOSIT_PERCENT + 100 * 100 - 1, 100 * 100) * 100;
        return min($deposit, $due) - $paid;
    }
}
