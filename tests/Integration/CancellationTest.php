<?php
declare(strict_types=1);

final class CancellationTest extends DatabaseTestCase
{
    private function service(): ReservationService
    {
        return ReservationService::make($this->pdo);
    }

    public function testCancelMoreThan24HoursAheadKeepsFeeRefundable(): void
    {
        $booking = $this->book(['reservation_date' => '2026-11-20', 'table_id' => 'VIP1', 'number_of_guests' => 12]);
        $result = $this->service()->cancel($booking['reference_code'], 'ana@example.test', self::at('2026-11-18 12:00'));
        self::assertSame('Cancelled', $result['status']);
        self::assertTrue($result['fee_refundable']);
        self::assertSame(1, (int) $this->value('SELECT fee_refundable FROM reservations'));
        self::assertSame('2026-11-18 12:00:00', $this->value('SELECT cancelled_at FROM reservations'));
    }

    public function testCancelLessThan24HoursAheadForfeitsFamilyFee(): void
    {
        $booking = $this->book(['reservation_date' => '2026-11-21', 'start_time' => '10:00:00']);
        $this->service()->cancel($booking['reference_code'], 'ana@example.test', self::at('2026-11-20 11:00'));
        self::assertSame(0, (int) $this->value('SELECT fee_refundable FROM reservations'));
    }

    public function testSameDayCancellationForfeitsVipFee(): void
    {
        $booking = $this->book(['reservation_date' => '2026-11-20', 'start_time' => '20:00:00', 'table_id' => 'VIP2', 'number_of_guests' => 10]);
        $this->service()->cancel($booking['reference_code'], 'ana@example.test', self::at('2026-11-20 08:00'));
        self::assertSame(0, (int) $this->value('SELECT fee_refundable FROM reservations'));
    }

    public function testCancellingCancelsUnstartedPreOrders(): void
    {
        $booking = $this->book(['cart' => [21 => 1]]);
        $this->service()->cancel($booking['reference_code'], 'ana@example.test', self::at('2026-11-17 10:00'));
        self::assertSame('Cancelled', $this->value('SELECT order_status FROM orders'));
    }

    public function testWrongEmailOrReferenceGivesTheSameNotFound(): void
    {
        $booking = $this->book();
        foreach ([[$booking['reference_code'], 'other@example.test'], ['ZZZZZZZZ', 'ana@example.test'], ["' OR 1=1 --", 'ana@example.test']] as [$ref, $email]) {
            try {
                $this->service()->cancel($ref, $email, self::at('2026-11-17 10:00'));
                self::fail('Expected NotFoundException.');
            } catch (NotFoundException $exception) {
                self::assertSame('We could not find a booking with that reference and email.', $exception->getMessage());
            }
        }
        self::assertSame('Confirmed', $this->value('SELECT status FROM reservations'));
    }

    public function testLookupIsCaseInsensitiveForEmailAndReference(): void
    {
        $booking = $this->book();
        $found = $this->service()->lookup(strtolower($booking['reference_code']), '  ANA@Example.TEST ');
        self::assertSame($booking['reference_code'], $found['reference_code']);
        self::assertSame(50000, $found['amount_due']);
    }

    public function testCannotCancelTwiceOrAfterStart(): void
    {
        $booking = $this->book();
        $this->service()->cancel($booking['reference_code'], 'ana@example.test', self::at('2026-11-17 10:00'));
        $this->expectException(ConflictException::class);
        $this->service()->cancel($booking['reference_code'], 'ana@example.test', self::at('2026-11-17 10:01'));
    }

    public function testCannotCancelOnceTheBookingHasStarted(): void
    {
        $booking = $this->book();
        $this->expectException(ConflictException::class);
        $this->service()->cancel($booking['reference_code'], 'ana@example.test', self::at('2026-11-18 18:05'));
    }
}
