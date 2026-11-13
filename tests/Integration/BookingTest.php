<?php
declare(strict_types=1);

/** Phase 3 required tests: ReservationService::book() against MySQL. */
final class BookingTest extends DatabaseTestCase
{
    private function assertRejected(array $overrides, string $field, ?DateTimeImmutable $now = null): void
    {
        try {
            $this->book($overrides, $now);
            self::fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($field, $exception->errors(), $exception->getMessage());
        }
        self::assertSame(0, (int) $this->value('SELECT COUNT(*) FROM reservations'), 'Nothing may be stored for a rejected booking.');
    }

    public function testValidFamilyBookingIsStoredWithServerComputedFee(): void
    {
        $result = $this->book();
        self::assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $result['reference_code']);
        self::assertSame(50000, $result['total_fee']);
        self::assertSame('Confirmed', $result['status']);
        self::assertSame('On-site', $result['payment_status']);
        $row = $this->pdo->query('SELECT * FROM reservations')->fetch();
        self::assertSame(['F1', '2026-11-18', '18:00:00', '20:00:00', 6, '500.00', '0.00'], [$row['table_id'], $row['reservation_date'], $row['start_time'], $row['end_time'], $row['number_of_guests'], $row['total_fee'], $row['extra_fee']]);
        self::assertSame('2026-11-17 09:00:00', $row['terms_accepted_at']);
        self::assertSame('09171234567', $this->value('SELECT phone_number FROM customers'));
    }

    public function testMondayIsRejected(): void
    {
        $this->assertRejected(['reservation_date' => '2026-11-23'], 'reservation_date');
    }

    public function testOpeningAndClosingHoursAreRejected(): void
    {
        $this->assertRejected(['start_time' => '09:00:00'], 'start_time');
        $this->assertRejected(['start_time' => '22:00:00'], 'start_time');
    }

    public function testNonSlotStartIsRejected(): void
    {
        $this->assertRejected(['start_time' => '11:00:00'], 'start_time');
    }

    public function testSixteenGuestsAtAFamilyTableIsRejected(): void
    {
        $this->assertRejected(['number_of_guests' => 16, 'table_id' => 'F2'], 'number_of_guests');
    }

    public function testTwentyOneGuestsAtVipIsRejected(): void
    {
        $this->assertRejected(['number_of_guests' => 21, 'table_id' => 'VIP1'], 'number_of_guests');
    }

    public function testOneGuestAtAStandardTableIsRejected(): void
    {
        $this->assertRejected(['number_of_guests' => 1, 'table_id' => 'S1'], 'number_of_guests');
    }

    public function testEighteenGuestsAtVipCostsThreeThousandFourHundred(): void
    {
        $result = $this->book(['number_of_guests' => 18, 'table_id' => 'VIP1']);
        self::assertSame(340000, $result['total_fee']);
        self::assertSame(['3400.00', '900.00'], array_values($this->pdo->query('SELECT total_fee, extra_fee FROM reservations')->fetch()));
    }

    public function testSameSlotTwiceFailsWithConflict(): void
    {
        $this->book();
        try {
            $this->book(['email' => 'someone.else@example.test']);
            self::fail('Expected SlotTakenException.');
        } catch (SlotTakenException $exception) {
            self::assertSame(409, $exception->status());
        }
        self::assertSame(1, (int) $this->value('SELECT COUNT(*) FROM reservations'));
    }

    public function testCancelledSlotIsBookableAgain(): void
    {
        $first = $this->book();
        ReservationService::make($this->pdo)->cancel($first['reference_code'], 'ana@example.test', self::at('2026-11-17 10:00'));
        $second = $this->book(['email' => 'next@example.test'], self::at('2026-11-17 10:05'));
        self::assertNotSame($first['reference_code'], $second['reference_code']);
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM reservations WHERE status <> 'Cancelled'"));
    }

    public function testStandardTableWithoutPreOrderIsAllowed(): void
    {
        $result = $this->book(['table_id' => 'S1', 'number_of_guests' => 2]);
        self::assertSame(0, $result['amount_due']);
        self::assertNull($result['order_id']);
    }

    public function testStandardPreOrderUnderMinimumIsRejected(): void
    {
        // Lumpiang Shanghai ₱220 × 4 = ₱880
        $this->assertRejected(['table_id' => 'S1', 'number_of_guests' => 2, 'cart' => [11 => 4]], 'cart');
        self::assertSame(0, (int) $this->value('SELECT COUNT(*) FROM orders'));
    }

    public function testStandardPreOrderAtMinimumIsAccepted(): void
    {
        // Halo-Halo ₱240 × 2 + Bulalo ₱450 + Atchara ₱90 = ₱1,020
        $result = $this->book(['table_id' => 'S2', 'number_of_guests' => 3, 'cart' => [41 => 2, 20 => 1, 33 => 1]]);
        self::assertSame(102000, $result['order_total']);
    }

    public function testClientSuppliedTotalsAndFeesAreIgnored(): void
    {
        $result = $this->book(['total_amount' => '1.00', 'total_fee' => '0.00', 'extra_fee' => '0.00', 'price' => 1, 'cart' => [21 => 2]]);
        self::assertSame(116000, $result['order_total']); // Beef Kare-Kare ₱580 × 2
        self::assertSame('1160.00', $this->value('SELECT total_amount FROM orders'));
        self::assertSame('580.00', $this->value('SELECT unit_price FROM order_items'));
        self::assertSame('500.00', $this->value('SELECT total_fee FROM reservations'));
    }

    public function testPreOrderAlwaysUsesTheReservationTable(): void
    {
        $this->book(['cart' => [23 => 1], 'order_table_id' => 'VIP2', 'order' => ['table_id' => 'VIP2']]);
        self::assertSame('F1', $this->value('SELECT table_id FROM orders'));
        self::assertSame('Scheduled', $this->value('SELECT order_status FROM orders'));
        self::assertSame('2026-11-18 18:00:00', $this->value('SELECT order_date FROM orders'));
    }

    public function testOrderTotalEqualsSumOfItemSubtotals(): void
    {
        $this->book(['cart' => [1 => 2, 11 => 1, 41 => 3]]);
        self::assertSame('1580.00', $this->value('SELECT total_amount FROM orders'));
        self::assertSame(
            $this->value('SELECT total_amount FROM orders'),
            $this->value('SELECT SUM(subtotal) FROM order_items')
        );
    }

    public function testUnavailableMenuItemRejectsTheWholeBooking(): void
    {
        $this->pdo->exec('UPDATE menu_items SET is_available = 0 WHERE item_id = 23');
        $this->assertRejected(['cart' => [23 => 1]], 'cart');
    }

    public function testManilaTimezoneBoundary(): void
    {
        // 15:30 UTC = 23:30 Manila on Tue 17 Nov: today's 20:00 slot has passed, tomorrow's is open.
        $utcNow = new DateTimeImmutable('2026-11-17 15:30', new DateTimeZone('UTC'));
        $this->assertRejected(['reservation_date' => '2026-11-17', 'start_time' => '20:00:00'], 'start_time', $utcNow);
        $result = $this->book(['reservation_date' => '2026-11-18', 'start_time' => '10:00:00'], $utcNow);
        self::assertSame('Confirmed', $result['status']);
        self::assertSame('2026-11-17 23:30:00', $this->value('SELECT terms_accepted_at FROM reservations'));
    }

    public function testTermsMustBeAccepted(): void
    {
        $this->assertRejected(['terms_accepted' => false], 'terms_accepted');
    }

    public function testInvalidContactDetailsAreRejectedTogether(): void
    {
        try {
            $this->book(['email' => 'not-an-email', 'phone_number' => 'abc', 'first_name' => '']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $exception) {
            self::assertEqualsCanonicalizing(['email', 'phone_number', 'first_name'], array_keys($exception->errors()));
        }
    }

    public function testUnknownOrInactiveTableIsRejected(): void
    {
        $this->assertRejected(['table_id' => 'X9'], 'table_id');
        $this->pdo->exec("UPDATE dining_tables SET is_active = 0 WHERE table_id = 'F3'");
        $this->assertRejected(['table_id' => 'F3'], 'table_id');
    }

    public function testOnlinePaymentOptionHoldsBookingAsPendingUnpaid(): void
    {
        $result = $this->book(['payment_option' => 'deposit']);
        self::assertSame(['Pending', 'Unpaid', 'deposit'], [$result['status'], $result['payment_status'], $result['payment_option']]);
    }

    public function testNothingToPayFallsBackToOnSite(): void
    {
        $result = $this->book(['table_id' => 'S1', 'number_of_guests' => 2, 'payment_option' => 'full']);
        self::assertSame(['Confirmed', 'On-site', 'onsite'], [$result['status'], $result['payment_status'], $result['payment_option']]);
    }
}
