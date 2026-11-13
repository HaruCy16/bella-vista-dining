<?php
declare(strict_types=1);

final class PaymentTest extends DatabaseTestCase
{
    private function payments(): PaymentService
    {
        return PaymentService::make($this->pdo, new MockGateway());
    }

    private function reservationId(): int
    {
        return (int) $this->value('SELECT reservation_id FROM reservations');
    }

    public function testDepositThenBalanceFlow(): void
    {
        // Family fee ₱500 + Crispy Pata ₱650 = ₱1,150 due; deposit = 50% = ₱575.
        $this->book(['cart' => [23 => 1], 'payment_option' => 'deposit']);
        $deposit = $this->payments()->start($this->reservationId(), 'deposit', 'http://localhost');
        self::assertSame(['amount' => 57500, 'payment_type' => 'Downpayment'], array_intersect_key($deposit, ['amount' => 0, 'payment_type' => 0]));
        self::assertStringStartsWith('/public/pay/mock.php?session=mock_', $deposit['checkout_url']);

        $session = $this->value("SELECT provider_reference FROM payments WHERE payment_state = 'Pending'");
        $result = $this->payments()->complete($session, true);
        self::assertTrue($result['changed']);
        self::assertSame(['Confirmed', 'Downpayment'], array_values($this->pdo->query('SELECT status, payment_status FROM reservations')->fetch()));

        $balance = $this->payments()->start($this->reservationId(), 'full', 'http://localhost');
        self::assertSame(57500, $balance['amount']);
        $this->payments()->complete($this->value("SELECT provider_reference FROM payments WHERE payment_state = 'Pending'"), true);
        self::assertSame('Paid', $this->value('SELECT payment_status FROM reservations'));
        self::assertSame('1150.00', $this->value("SELECT SUM(amount) FROM payments WHERE payment_state = 'Succeeded'"));
    }

    public function testCompletingTwiceIsIdempotent(): void
    {
        $this->book(['payment_option' => 'full']);
        $this->payments()->start($this->reservationId(), 'full', 'http://localhost');
        $session = $this->value('SELECT provider_reference FROM payments');
        self::assertTrue($this->payments()->complete($session, true)['changed']);
        $again = $this->payments()->complete($session, true);
        self::assertFalse($again['changed']);
        self::assertSame('Succeeded', $again['state']);
        // A late "failed" callback must not undo a success.
        self::assertFalse($this->payments()->complete($session, false)['changed']);
        self::assertSame(['Succeeded', 'Paid'], [$this->value('SELECT payment_state FROM payments'), $this->value('SELECT payment_status FROM reservations')]);
    }

    public function testFailedPaymentKeepsBookingPendingAndRetryable(): void
    {
        $this->book(['payment_option' => 'full']);
        $this->payments()->start($this->reservationId(), 'full', 'http://localhost');
        $this->payments()->complete($this->value('SELECT provider_reference FROM payments'), false);
        self::assertSame(['Pending', 'Unpaid'], array_values($this->pdo->query('SELECT status, payment_status FROM reservations')->fetch()));
        $retry = $this->payments()->start($this->reservationId(), 'full', 'http://localhost');
        self::assertSame(50000, $retry['amount']);
    }

    public function testChoosingOnSiteConfirmsTheBooking(): void
    {
        $this->book(['payment_option' => 'deposit']);
        $this->payments()->chooseOnsite($this->reservationId());
        self::assertSame(['Confirmed', 'On-site'], array_values($this->pdo->query('SELECT status, payment_status FROM reservations')->fetch()));
    }

    public function testNothingLeftToPayIsAConflict(): void
    {
        $this->book(['payment_option' => 'full']);
        $this->payments()->start($this->reservationId(), 'full', 'http://localhost');
        $this->payments()->complete($this->value('SELECT provider_reference FROM payments'), true);
        $this->expectException(ConflictException::class);
        $this->payments()->start($this->reservationId(), 'full', 'http://localhost');
    }

    public function testCancelledBookingCannotBePaid(): void
    {
        $booking = $this->book(['payment_option' => 'full']);
        ReservationService::make($this->pdo)->cancel($booking['reference_code'], 'ana@example.test', self::at('2026-11-17 09:30'));
        $this->expectException(ConflictException::class);
        $this->payments()->start($this->reservationId(), 'full', 'http://localhost');
    }

    public function testUnknownPaymentReferenceIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->payments()->complete('mock_doesnotexist', true);
    }
}
