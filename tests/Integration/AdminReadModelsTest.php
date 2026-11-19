<?php
declare(strict_types=1);

/** The numbers and lists behind the admin screens, checked against bookings we created ourselves. */
final class AdminReadModelsTest extends DatabaseTestCase
{
    private function ops(): OperationsService
    {
        return OperationsService::make($this->pdo);
    }

    /** F1 at 6 PM (₱1,160 pre-order), F2 at noon (₱890), a cancelled booking, and two walk-in orders. */
    private function seedDay(): void
    {
        $this->book(['cart' => [21 => 2]]);
        $this->book(['table_id' => 'F2', 'email' => 'b@example.test', 'start_time' => '12:00:00', 'cart' => [23 => 1, 41 => 1]]);
        $cancelled = $this->book(['table_id' => 'F3', 'email' => 'c@example.test', 'start_time' => '14:00:00', 'cart' => [23 => 3]]);
        ReservationService::make($this->pdo)->cancel($cancelled['reference_code'], 'c@example.test', self::at('2026-11-17 10:00'));
        $this->pdo->exec("INSERT INTO orders (table_id, order_source, order_date, total_amount, order_status) VALUES
            ('S3', 'Walk-in', '2026-11-18 21:30:00', 300.00, 'Served'), ('S4', 'Walk-in', '2026-11-18 09:15:00', 120.00, 'Served')");
    }

    public function testSalesByBlockIsRealAndClampsWalkInsToTheOpeningHours(): void
    {
        $this->seedDay();
        $byHour = array_column($this->ops()->dailyReport('2026-11-18')['sales_by_block'], null, 'block_hour');

        self::assertSame([10, 12, 14, 16, 18, 20], array_keys($byHour));
        self::assertSame(12000, $byHour[10]['sales']);   // 9:15 walk-in counts in the first block
        self::assertSame(89000, $byHour[12]['sales']);   // pre-order counts in its booking's block
        self::assertSame(0, $byHour[14]['sales']);       // the cancelled booking's ₱1,950 is excluded
        self::assertSame(116000, $byHour[18]['sales']);
        self::assertSame(30000, $byHour[20]['sales']);   // 9:30 PM walk-in counts in the last block
        self::assertSame('6–8 PM', $byHour[18]['label']);
    }

    public function testSalesByCategoryJoinsOrderItemsToTheMenu(): void
    {
        $this->seedDay();
        $byName = array_column($this->ops()->dailyReport('2026-11-18')['sales_by_category'], null, 'name');

        self::assertSame(181000, $byName['Main Courses']['sales']);      // 2 × 580 + 650
        self::assertSame(24000, $byName['Desserts & Drinks']['sales']);
        self::assertSame(0, $byName['Appetizers & Soups']['sales']);     // listed, with zero
        self::assertSame(3, $byName['Main Courses']['quantity']);
    }

    public function testTableCategoryUsageAndStatusCountsExcludeCancelledBookings(): void
    {
        $this->seedDay();
        $report = $this->ops()->dailyReport('2026-11-18');
        $family = array_column($report['table_types'], null, 'type')['Family'];

        self::assertSame(2, $family['bookings']);
        self::assertSame(12, $family['guests']);
        self::assertSame(100000, $family['fees']);
        self::assertEquals(['Confirmed' => 2, 'Cancelled' => 1], $report['status_counts']);
        self::assertSame(['Standard', 'Family', 'VIP'], array_column($report['table_types'], 'type'));
    }

    public function testForfeitedAndExtraGuestFees(): void
    {
        $late = $this->book(['table_id' => 'VIP1', 'number_of_guests' => 17, 'reservation_date' => '2026-11-18', 'start_time' => '20:00:00']);
        ReservationService::make($this->pdo)->cancel($late['reference_code'], 'ana@example.test', self::at('2026-11-18 10:00')); // same day: fee forfeited
        $report = $this->ops()->dailyReport('2026-11-18');

        self::assertSame(60000, $report['extra_guest_fees']);    // 2 guests above 15 × ₱300
        self::assertSame(310000, $report['forfeited_fees']);     // ₱2,500 + ₱600
    }

    public function testReservationListFiltersAndCountsIgnoreTheFilters(): void
    {
        $this->seedDay();
        $list = fn (array $f) => $this->ops()->reservationList(['date' => '2026-11-18'] + $f);

        self::assertSame(3, $list([])['total']);
        self::assertEquals(['Cancelled' => 1, 'Confirmed' => 2], $list(['status' => 'Cancelled'])['counts']);
        self::assertCount(1, $list(['status' => 'Cancelled'])['rows']);
        self::assertCount(1, $list(['block' => '12:00:00'])['rows']);
        self::assertCount(3, $list(['type' => 'Family'])['rows']);
        self::assertCount(0, $list(['type' => 'VIP'])['rows']);
        self::assertCount(3, $list(['payment' => 'On-site'])['rows']);
        self::assertCount(1, $list(['q' => 'b@example'])['rows']);
        self::assertCount(0, $list(['q' => '%'])['rows'], 'a literal % must not match everything');
        self::assertSame(['F2', 'F3', 'F1'], array_column($list([])['rows'], 'table_id'));  // ordered by time
        self::assertSame(116000, $list(['block' => '18:00:00'])['rows'][0]['food_total']);
    }

    public function testReservationDetailHasAmountsItemsAndPayments(): void
    {
        $booking = $this->book(['cart' => [21 => 2], 'payment_option' => 'deposit']);
        $id = $booking['reservation_id'];
        $this->pdo->exec("INSERT INTO payments (reservation_id, payment_type, amount, method, payment_state) VALUES ({$id}, 'Downpayment', 830.00, 'Mock', 'Succeeded')");

        $d = $this->ops()->reservationDetail($id, self::at('2026-11-18 10:00'));
        self::assertSame(['Ana Cruz', 'ana@example.test', 'F1', 6], [$d['name'], $d['email'], $d['table_id'], $d['number_of_guests']]);
        self::assertSame([['name' => 'Beef Kare-Kare', 'quantity' => 2, 'unit_price' => 58000, 'subtotal' => 116000]], $d['items']);
        self::assertSame([50000, 116000, 166000, 83000, 83000], [$d['total_fee'], $d['order_total'], $d['amount_due'], $d['amount_paid'], $d['balance']]);
        self::assertSame(['Downpayment', 83000, 'Mock', 'Succeeded'], [$d['payments'][0]['type'], $d['payments'][0]['amount'], $d['payments'][0]['method'], $d['payments'][0]['state']]);
        self::assertTrue($d['is_today']);
        self::assertTrue($d['can_check_in'], 'staff may seat a guest whose deposit has not arrived: a Pending booking today can be checked in');
    }

    public function testCheckInIsOnlyOfferedToday(): void
    {
        $id = $this->book(['payment_option' => 'onsite'])['reservation_id'];
        self::assertTrue($this->ops()->reservationDetail($id, self::at('2026-11-18 10:00'))['can_check_in']);
        self::assertFalse($this->ops()->reservationDetail($id, self::at('2026-11-17 10:00'))['can_check_in']);
        $this->ops()->checkIn($id, self::at('2026-11-18 18:05'));
        $d = $this->ops()->reservationDetail($id, self::at('2026-11-18 18:06'));
        self::assertSame([false, true], [$d['can_check_in'], $d['can_finish']]);
    }

    public function testUnknownBookingIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->ops()->reservationDetail(999, self::at());
    }

    public function testCustomersAreGroupedByEmailAndSpendCountsServedFoodOnly(): void
    {
        // The same guest booked three times (each booking stores its own customer row) plus another guest.
        $first = $this->book(['cart' => [21 => 1], 'reservation_date' => '2026-11-18']);
        $second = $this->book(['cart' => [23 => 1], 'reservation_date' => '2026-11-19', 'email' => 'ANA@example.test']);
        $this->book(['reservation_date' => '2026-11-20', 'email' => 'ana@example.test']);
        $this->book(['table_id' => 'F2', 'email' => 'bo@example.test', 'first_name' => 'Bo', 'last_name' => 'Reyes']);
        $this->pdo->exec("UPDATE reservations SET status = 'Completed' WHERE reservation_id IN ({$first['reservation_id']}, {$second['reservation_id']})");
        $this->pdo->exec("UPDATE orders SET order_status = 'Served' WHERE reservation_id = {$first['reservation_id']}");  // only the first is settled

        $directory = $this->ops()->customerDirectory(self::at('2026-11-21 09:00'));
        $rows = array_column($directory['rows'], null, 'email');

        self::assertCount(2, $directory['rows']);
        self::assertSame([3, 2, 58000], [$rows['ana@example.test']['reservations'], $rows['ana@example.test']['completed'], $rows['ana@example.test']['food_spend']]);
        self::assertSame(['total' => 2, 'returning' => 1, 'upcoming' => 0, 'food_spend' => 58000], $directory['summary']);
    }

    public function testCustomerRecordHasStatsOutcomesAndHistory(): void
    {
        $a = $this->book(['cart' => [21 => 1], 'reservation_date' => '2026-11-18']);
        $b = $this->book(['cart' => [23 => 1], 'reservation_date' => '2026-11-19']);
        $c = $this->book(['reservation_date' => '2026-11-20']);
        $this->pdo->exec("UPDATE reservations SET status = 'Completed' WHERE reservation_id IN ({$a['reservation_id']}, {$b['reservation_id']})");
        $this->pdo->exec("UPDATE orders SET order_status = 'Served' WHERE reservation_id IN ({$a['reservation_id']}, {$b['reservation_id']})");
        ReservationService::make($this->pdo)->cancel($c['reference_code'], 'ana@example.test', self::at('2026-11-17 10:00'));

        $customerId = (int) $this->value('SELECT MIN(customer_id) FROM customers');
        $record = $this->ops()->customerRecord($customerId);

        self::assertSame(['Ana Cruz', 'ana@example.test', 'Family'], [$record['name'], $record['email'], $record['preferred_seating']]);
        self::assertSame(['reservations' => 3, 'completed' => 2, 'food_spend' => 123000, 'average_spend' => 61500], $record['stats']);
        self::assertEquals(['Completed' => 2, 'Cancelled' => 1], $record['outcomes']);
        self::assertSame([58000, 65000], array_column($record['spend_by_visit'], 'amount'));   // oldest first
        self::assertSame('2026-11-20', $record['history'][0]['date']);                          // newest first
        self::assertSame('1× Beef Kare-Kare', $record['history'][2]['items']);
        $this->expectException(NotFoundException::class);
        $this->ops()->customerRecord(999);
    }
}
