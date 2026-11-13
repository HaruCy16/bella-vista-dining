<?php
declare(strict_types=1);

/**
 * Admin / kitchen dashboard actions (client spec VII). Roles:
 *   Manager:       everything, including the daily report.
 *   Kitchen Staff: table board, check-in / finish, kitchen queue. No report.
 */
final class OperationsService
{
    public const TABLE_STATUSES = ['Available', 'Reserved', 'Occupied', 'Clean-up'];

    /** Allowed kitchen order transitions: from => [to, ...]. Served and Cancelled are final. */
    public const ORDER_TRANSITIONS = [
        'Scheduled' => ['Pending', 'Cancelled'],
        'Pending' => ['Preparing', 'Cancelled'],
        'Preparing' => ['Served'],
        'Served' => [],
        'Cancelled' => [],
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly AdminRepository $admin,
        private readonly ReservationRepository $reservations,
        private readonly OrderRepository $orders,
    ) {
    }

    public static function make(PDO $pdo): self
    {
        return new self($pdo, new AdminRepository($pdo), new ReservationRepository($pdo), new OrderRepository($pdo));
    }

    /**
     * Today's board: every table with its live status and today's bookings for it.
     *
     * @return array{tables: list<array<string, mixed>>, reservations: list<array<string, mixed>>}
     */
    public function board(DateTimeImmutable $now): array
    {
        $today = $now->format('Y-m-d');
        $bookings = [];
        foreach ($this->reservations->forDate($today) as $row) {
            $bookings[] = [
                'reservation_id' => (int) $row['reservation_id'],
                'reference_code' => $row['reference_code'],
                'table_id' => $row['table_id'],
                'start_time' => substr($row['start_time'], 0, 5),
                'end_time' => substr($row['end_time'], 0, 5),
                'display_time' => Config::displayTime($row['start_time']),
                'guests' => (int) $row['number_of_guests'],
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'phone' => $row['phone_number'],
                'status' => $row['status'],
                'payment_status' => $row['payment_status'],
                'grace_ends' => BookingRules::graceEndsAt(BookingRules::startOf($row['reservation_date'], $row['start_time']))->format('H:i'),
            ];
        }
        $tables = array_map(static function (array $table) use ($bookings): array {
            $mine = array_values(array_filter($bookings, static fn (array $b): bool => $b['table_id'] === $table['table_id'] && !in_array($b['status'], ['Cancelled', 'No-show'], true)));
            return $table + ['bookings' => $mine];
        }, $this->admin->tables());

        return ['tables' => $tables, 'reservations' => $bookings];
    }

    public function setTableStatus(string $tableId, string $status): void
    {
        if (!in_array($status, self::TABLE_STATUSES, true)) {
            throw ValidationException::field('status', 'Unknown table status.');
        }
        if (!$this->admin->setTableStatus($tableId, $status)) {
            throw new NotFoundException('Table not found.');
        }
    }

    /** Guest arrived: booking Checked-in, table Occupied, their pre-order goes to the kitchen now. */
    public function checkIn(int $reservationId, DateTimeImmutable $now): void
    {
        $this->transaction(function () use ($reservationId, $now): void {
            $reservation = $this->reservations->findById($reservationId, forUpdate: true) ?? throw new NotFoundException('Booking not found.');
            if (!in_array($reservation['status'], ['Pending', 'Confirmed'], true)) {
                throw new ConflictException("This booking is {$reservation['status']} and cannot be checked in.");
            }
            if ($reservation['reservation_date'] !== $now->format('Y-m-d')) {
                throw new ConflictException('Only today\'s bookings can be checked in.');
            }
            $this->reservations->updateStatus($reservationId, 'Checked-in');
            $this->admin->setTableStatus($reservation['table_id'], 'Occupied');
            $this->pdo->prepare("UPDATE orders SET order_status = 'Pending' WHERE reservation_id = :id AND order_status = 'Scheduled'")
                ->execute(['id' => $reservationId]);
        });
    }

    /** Guests left: booking Completed, table needs Clean-up. */
    public function finish(int $reservationId): void
    {
        $this->transaction(function () use ($reservationId): void {
            $reservation = $this->reservations->findById($reservationId, forUpdate: true) ?? throw new NotFoundException('Booking not found.');
            if ($reservation['status'] !== 'Checked-in') {
                throw new ConflictException('Only checked-in bookings can be finished.');
            }
            $this->reservations->updateStatus($reservationId, 'Completed');
            $this->admin->setTableStatus($reservation['table_id'], 'Clean-up');
        });
    }

    public function setOrderStatus(int $orderId, string $status, DateTimeImmutable $now): void
    {
        $this->transaction(function () use ($orderId, $status, $now): void {
            $order = $this->orders->find($orderId, forUpdate: true) ?? throw new NotFoundException('Order not found.');
            $from = $order['order_status'];
            if (!in_array($status, self::ORDER_TRANSITIONS[$from] ?? [], true)) {
                throw new ConflictException("An order that is {$from} cannot be changed to {$status}.");
            }
            $this->orders->updateStatus($orderId, $status, $status === 'Served' ? $now : null);
        });
    }

    /** @return list<array<string, mixed>> */
    public function kitchenQueue(DateTimeImmutable $now): array
    {
        return array_map(static fn (array $order): array => [
            'order_id' => (int) $order['order_id'],
            'table_id' => $order['table_id'],
            'source' => $order['order_source'],
            'status' => $order['order_status'],
            'reference_code' => $order['reference_code'],
            'time' => $order['start_time'] ? Config::displayTime($order['start_time']) : (new DateTimeImmutable($order['order_date']))->format('g:i A'),
            'guests' => $order['number_of_guests'] !== null ? (int) $order['number_of_guests'] : null,
            'items' => $order['items'] ? explode("\n", $order['items']) : [],
            'total' => Money::fromPesos((string) $order['total_amount']),
            'next' => self::ORDER_TRANSITIONS[$order['order_status']] ?? [],
        ], $this->orders->kitchenQueue($now->format('Y-m-d')));
    }

    /** @return array<string, mixed> */
    public function dailyReport(string $date): array
    {
        $report = $this->admin->report($date);
        $r = $report['reservations'];
        $s = $report['sales'];
        return [
            'date' => $date,
            'reservations' => [
                'booked' => (int) $r['booked'], 'cancelled' => (int) $r['cancelled'], 'no_shows' => (int) $r['no_shows'],
                'seated' => (int) $r['seated'], 'guests' => (int) $r['guests'],
            ],
            'reservation_fees' => Money::fromPesos((string) $r['fees']),
            'orders' => (int) $s['orders'],
            'pre_orders' => (int) $s['pre_orders'],
            'walk_in_orders' => (int) $s['walk_in_orders'],
            'food_sales' => Money::fromPesos((string) $s['food_sales']),
            'served_sales' => Money::fromPesos((string) $s['served_sales']),
            'online_collected' => Money::fromPesos((string) $report['online_collected']),
            'top_items' => array_map(static fn (array $row): array => [
                'name' => $row['item_name'], 'quantity' => (int) $row['quantity'], 'sales' => Money::fromPesos((string) $row['sales']),
            ], $report['top_items']),
        ];
    }

    private function transaction(callable $work): void
    {
        $this->pdo->beginTransaction();
        try {
            $work();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
