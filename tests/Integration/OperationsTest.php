<?php
declare(strict_types=1);

/** Admin / kitchen dashboard, housekeeping and the daily report. */
final class OperationsTest extends DatabaseTestCase
{
    private function ops(): OperationsService
    {
        return OperationsService::make($this->pdo);
    }

    private function housekeeping(string $at): array
    {
        return (new HousekeepingService($this->pdo))->run(self::at($at));
    }

    public function testFuturePreOrdersStayOutOfTheKitchenQueue(): void
    {
        $this->book(['cart' => [21 => 1]]); // Wed 18:00
        $this->housekeeping('2026-11-18 12:00');
        self::assertSame([], $this->ops()->kitchenQueue(self::at('2026-11-18 12:00')));
        self::assertSame('Scheduled', $this->value('SELECT order_status FROM orders'));
    }

    public function testPreOrderIsReleasedAnHourBeforeTheBooking(): void
    {
        $this->book(['cart' => [21 => 1]]);
        self::assertSame(1, $this->housekeeping('2026-11-18 17:00')['released_orders']);
        $queue = $this->ops()->kitchenQueue(self::at('2026-11-18 17:00'));
        self::assertCount(1, $queue);
        self::assertSame(['F1', 'Pending', ['1× Beef Kare-Kare'], ['Preparing', 'Cancelled']], [$queue[0]['table_id'], $queue[0]['status'], $queue[0]['items'], $queue[0]['next']]);
    }

    public function testCheckInSeatsTheTableAndSendsThePreOrderNow(): void
    {
        $this->book(['cart' => [21 => 1]]);
        $id = (int) $this->value('SELECT reservation_id FROM reservations');
        $this->ops()->checkIn($id, self::at('2026-11-18 16:30'));
        self::assertSame(['Checked-in', 'Occupied', 'Pending'], [
            $this->value('SELECT status FROM reservations'),
            $this->value("SELECT status FROM dining_tables WHERE table_id = 'F1'"),
            $this->value('SELECT order_status FROM orders'),
        ]);
        $this->ops()->finish($id);
        self::assertSame(['Completed', 'Clean-up'], [$this->value('SELECT status FROM reservations'), $this->value("SELECT status FROM dining_tables WHERE table_id = 'F1'")]);
    }

    public function testCheckInOnlyForTodaysBookings(): void
    {
        $this->book();
        $this->expectException(ConflictException::class);
        $this->ops()->checkIn((int) $this->value('SELECT reservation_id FROM reservations'), self::at('2026-11-17 18:00'));
    }

    public function testOrderStatusFollowsTheKitchenFlow(): void
    {
        $this->book(['cart' => [21 => 1]]);
        $orderId = (int) $this->value('SELECT order_id FROM orders');
        $now = self::at('2026-11-18 18:10');
        foreach (['Pending', 'Preparing', 'Served'] as $status) {
            $this->ops()->setOrderStatus($orderId, $status, $now);
        }
        self::assertSame(['Served', '2026-11-18 18:10:00'], array_values($this->pdo->query('SELECT order_status, served_at FROM orders')->fetch()));
    }

    public function testInvalidOrderTransitionsAreRejected(): void
    {
        $this->book(['cart' => [21 => 1]]);
        $orderId = (int) $this->value('SELECT order_id FROM orders');
        $now = self::at('2026-11-18 18:10');
        foreach (['Served', 'Preparing'] as $skip) { // Scheduled cannot jump ahead
            try {
                $this->ops()->setOrderStatus($orderId, $skip, $now);
                self::fail("Scheduled -> {$skip} must be rejected.");
            } catch (ConflictException) {
                self::assertSame('Scheduled', $this->value('SELECT order_status FROM orders'));
            }
        }
        $this->ops()->setOrderStatus($orderId, 'Cancelled', $now);
        $this->expectException(ConflictException::class);
        $this->ops()->setOrderStatus($orderId, 'Pending', $now); // Cancelled is final
    }

    public function testTableStatusWhitelist(): void
    {
        $this->ops()->setTableStatus('S1', 'Clean-up');
        self::assertSame('Clean-up', $this->value("SELECT status FROM dining_tables WHERE table_id = 'S1'"));
        $this->ops()->setTableStatus('S1', 'Clean-up'); // same value again is fine
        try {
            $this->ops()->setTableStatus('S1', 'Broken');
            self::fail('Unknown status must be rejected.');
        } catch (ValidationException) {
        }
        $this->expectException(NotFoundException::class);
        $this->ops()->setTableStatus('X9', 'Available');
    }

    public function testNoShowAfterGracePeriodFreesTheSlotAndCancelsTheOrder(): void
    {
        $this->book(['cart' => [21 => 1]]);
        self::assertSame(0, $this->housekeeping('2026-11-18 18:14')['no_shows']);
        self::assertSame(1, $this->housekeeping('2026-11-18 18:16')['no_shows']);
        self::assertSame(['No-show', 'Cancelled'], [$this->value('SELECT status FROM reservations'), $this->value('SELECT order_status FROM orders')]);
        // slot_lock is NULL once released, so the unique slot key no longer blocks the table.
        self::assertNull($this->value('SELECT slot_lock FROM reservations'));
    }

    public function testCheckedInGuestsAreNotMarkedNoShow(): void
    {
        $this->book();
        $this->ops()->checkIn((int) $this->value('SELECT reservation_id FROM reservations'), self::at('2026-11-18 18:05'));
        self::assertSame(0, $this->housekeeping('2026-11-18 18:30')['no_shows']);
    }

    public function testAbandonedOnlineCheckoutIsReleasedAfterTheHold(): void
    {
        $this->book(['payment_option' => 'full'], self::at('2026-11-17 09:00'));
        self::assertSame(0, $this->housekeeping('2026-11-17 09:29')['expired_unpaid']);
        self::assertSame(1, $this->housekeeping('2026-11-17 09:31')['expired_unpaid']);
        self::assertSame('Cancelled', $this->value('SELECT status FROM reservations'));
        $this->book(['email' => 'next@example.test'], self::at('2026-11-17 09:32')); // slot is free again
    }

    public function testDailyReportExcludesCancelledAndIncludesWalkIns(): void
    {
        $this->book(['cart' => [21 => 2]]);                                                   // ₱1,160 pre-order
        $cancelled = $this->book(['table_id' => 'F2', 'email' => 'b@example.test', 'cart' => [23 => 1]]);
        ReservationService::make($this->pdo)->cancel($cancelled['reference_code'], 'b@example.test', self::at('2026-11-17 10:00'));
        $this->pdo->exec("INSERT INTO orders (table_id, order_source, order_date, total_amount, order_status) VALUES ('S3', 'Walk-in', '2026-11-18 12:30:00', 300.00, 'Served')");

        $report = $this->ops()->dailyReport('2026-11-18');
        self::assertSame(1, $report['reservations']['booked']);
        self::assertSame(1, $report['reservations']['cancelled']);
        self::assertSame(146000, $report['food_sales']);  // 1,160 + 300, cancelled ₱650 excluded
        self::assertSame(30000, $report['served_sales']);
        self::assertSame([1, 1], [$report['pre_orders'], $report['walk_in_orders']]);
        self::assertSame(50000, $report['reservation_fees']); // the refundable cancelled fee is not revenue
    }
}
