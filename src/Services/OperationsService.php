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
            'sales_by_block' => array_map(static fn (array $row): array => [
                'block_hour' => $row['block_hour'], 'label' => Config::slotLabel(sprintf('%02d:00', $row['block_hour'])),
                'orders' => $row['orders'], 'sales' => Money::fromPesos($row['sales']),
            ], $report['sales_by_block']),
            'sales_by_category' => array_map(static fn (array $row): array => [
                'name' => $row['category_name'], 'quantity' => (int) $row['quantity'], 'sales' => Money::fromPesos((string) $row['sales']),
            ], $report['sales_by_category']),
            'status_counts' => $report['status_counts'],
            'table_types' => array_map(static fn (array $row): array => [
                'type' => $row['type_name'], 'bookings' => (int) $row['bookings'], 'guests' => (int) $row['guests'],
                'fees' => Money::fromPesos((string) $row['fees']), 'food_sales' => Money::fromPesos((string) $row['food_sales']),
            ], $report['table_types']),
            'extra_guest_fees' => Money::fromPesos((string) $report['fee_breakdown']['extra_guest']),
            'forfeited_fees' => Money::fromPesos((string) $report['fee_breakdown']['forfeited']),
        ];
    }

    /**
     * Bookings for a service date with the day's status counts (counts ignore the other filters).
     *
     * @param array{date: string, status?: ?string, block?: ?string, type?: ?string, payment?: ?string, q?: ?string} $filters
     * @return array{date: string, counts: array<string, int>, total: int, rows: list<array<string, mixed>>}
     */
    public function reservationList(array $filters): array
    {
        $counts = $this->admin->statusCounts($filters['date']);
        $rows = array_map(static fn (array $r): array => [
            'reservation_id' => (int) $r['reservation_id'],
            'reference_code' => $r['reference_code'],
            'name' => $r['first_name'] . ' ' . $r['last_name'],
            'phone' => $r['phone_number'],
            'email' => $r['email'],
            'table_id' => $r['table_id'],
            'table_type' => $r['type_name'],
            'start_time' => substr($r['start_time'], 0, 5),
            'display_time' => Config::slotLabel($r['start_time']),
            'guests' => (int) $r['number_of_guests'],
            'status' => $r['status'],
            'payment_status' => $r['payment_status'],
            'fee' => Money::fromPesos((string) $r['total_fee']),
            'food_total' => Money::fromPesos((string) $r['food_total']),
        ], $this->admin->searchReservations($filters));
        return ['date' => $filters['date'], 'counts' => $counts, 'total' => array_sum($counts), 'rows' => $rows];
    }

    /**
     * Everything staff need about one booking, in centavos. Reuses the guest-facing presenter so the
     * amounts, refund rule and balance can never differ between the two screens.
     *
     * @return array<string, mixed>
     */
    public function reservationDetail(int $reservationId, DateTimeImmutable $now): array
    {
        $reservation = $this->reservations->findById($reservationId) ?? throw new NotFoundException('Booking not found.');
        $detail = ReservationService::make($this->pdo)->present($reservation);
        $payments = (new PaymentRepository($this->pdo))->forReservation($reservationId);
        $orders = [];
        foreach ($this->orders->forReservation($reservationId) as $row) {
            $orders[$row['order_id']] = $row['order_status'];
        }
        $items = [];
        foreach ($this->orders->forReservation($reservationId) as $row) {
            if ($row['item_id'] !== null && $row['order_status'] !== 'Cancelled') {
                $unit = Money::fromPesos((string) $row['unit_price']);
                $items[] = ['name' => $row['item_name'], 'quantity' => (int) $row['quantity'], 'unit_price' => $unit, 'subtotal' => $unit * (int) $row['quantity']];
            }
        }
        $start = BookingRules::startOf($reservation['reservation_date'], $reservation['start_time']);
        return [
            'reservation_id' => $reservationId,
            'email' => $reservation['email'],
            'phone' => $reservation['phone_number'],
            'customer_id' => (int) $reservation['customer_id'],
            'created_at' => $reservation['created_at'],
            'extra_fee' => Money::fromPesos((string) $reservation['extra_fee']),
            'base_fee' => Money::fromPesos((string) $reservation['total_fee']) - Money::fromPesos((string) $reservation['extra_fee']),
            'grace_ends' => BookingRules::graceEndsAt($start)->format('g:i A'),
            'is_today' => $reservation['reservation_date'] === $now->format('Y-m-d'),
            'can_check_in' => in_array($reservation['status'], ['Pending', 'Confirmed'], true) && $reservation['reservation_date'] === $now->format('Y-m-d'),
            'can_finish' => $reservation['status'] === 'Checked-in',
            'order_statuses' => array_values(array_unique(array_values($orders))),
            'payments' => array_map(static fn (array $p): array => [
                'type' => $p['payment_type'], 'amount' => Money::fromPesos((string) $p['amount']), 'method' => $p['method'],
                'state' => $p['payment_state'], 'at' => $p['created_at'],
            ], $payments),
        ] + ['items' => $items] + $detail;
    }

    /**
     * The guest directory with headline numbers. Spend counts settled (served) food only.
     *
     * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    public function customerDirectory(DateTimeImmutable $now): array
    {
        $rows = array_map(static fn (array $c): array => [
            'customer_id' => (int) $c['customer_id'],
            'name' => $c['name'], 'email' => $c['email'], 'phone' => $c['phone'],
            'reservations' => (int) $c['reservations'], 'completed' => (int) $c['completed'], 'upcoming' => (int) $c['upcoming'],
            'last_visit' => $c['last_visit'], 'food_spend' => Money::fromPesos((string) $c['food_spend']),
        ], $this->admin->customerDirectory($now->format('Y-m-d')));
        return [
            'summary' => [
                'total' => count($rows),
                'returning' => count(array_filter($rows, static fn (array $c): bool => $c['reservations'] >= 2)),
                'upcoming' => array_sum(array_column($rows, 'upcoming')),
                'food_spend' => array_sum(array_column($rows, 'food_spend')),
            ],
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    public function customerRecord(int $customerId): array
    {
        $record = $this->admin->customerRecord($customerId) ?? throw new NotFoundException('Customer not found.');
        $history = [];
        $outcomes = [];
        $types = [];
        $visits = [];
        $spend = 0;
        foreach ($record['reservations'] as $r) {
            $served = Money::fromPesos((string) $r['served_total']);
            $outcomes[$r['status']] = ($outcomes[$r['status']] ?? 0) + 1;
            if ($r['status'] !== 'Cancelled' && $r['status'] !== 'No-show') {
                $types[$r['type_name']] = ($types[$r['type_name']] ?? 0) + 1;
            }
            if ($r['status'] === 'Completed') {
                $spend += $served;
                $visits[] = ['date' => $r['reservation_date'], 'reference_code' => $r['reference_code'], 'amount' => $served];
            }
            $history[] = [
                'reservation_id' => (int) $r['reservation_id'], 'reference_code' => $r['reference_code'], 'date' => $r['reservation_date'],
                'time' => Config::slotLabel($r['start_time']), 'table_id' => $r['table_id'], 'table_type' => $r['type_name'],
                'guests' => (int) $r['number_of_guests'], 'status' => $r['status'], 'payment_status' => $r['payment_status'],
                'food_total' => Money::fromPesos((string) $r['food_total']), 'items' => $r['items'] ?? '',
            ];
        }
        arsort($types);
        $completed = count($visits);
        return [
            'customer_id' => (int) $record['profile']['customer_id'],
            'name' => $record['profile']['name'], 'email' => $record['profile']['email'], 'phone' => $record['profile']['phone'],
            'member_since' => $record['profile']['member_since'],
            'preferred_seating' => array_key_first($types),
            'stats' => [
                'reservations' => count($history), 'completed' => $completed, 'food_spend' => $spend,
                'average_spend' => $completed ? intdiv($spend, $completed) : 0,
            ],
            'spend_by_visit' => array_slice(array_reverse($visits), -6),
            'outcomes' => $outcomes,
            'history' => $history,
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
