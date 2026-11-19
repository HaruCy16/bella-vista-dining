<?php
declare(strict_types=1);

/** Read models and updates for the admin / kitchen dashboard. */
final class AdminRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function tables(): array
    {
        return $this->pdo->query(
            'SELECT d.table_id, d.status, t.type_name, t.capacity_min, t.capacity_max
             FROM dining_tables d INNER JOIN table_types t ON t.table_type_id = d.table_type_id
             WHERE d.is_active = 1
             ORDER BY t.capacity_min, d.table_id'
        )->fetchAll();
    }

    public function setTableStatus(string $tableId, string $status): bool
    {
        $statement = $this->pdo->prepare('UPDATE dining_tables SET status = :status WHERE table_id = :table_id AND is_active = 1');
        $statement->execute(['table_id' => $tableId, 'status' => $status]);
        return $statement->rowCount() === 1 || $this->tableExists($tableId);
    }

    public function tableExists(string $tableId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM dining_tables WHERE table_id = :table_id AND is_active = 1');
        $statement->execute(['table_id' => $tableId]);
        return (bool) $statement->fetchColumn();
    }

    /** @return array<string, mixed> */
    public function report(string $date): array
    {
        $reservations = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status <> 'Cancelled'), 0) AS booked,
                    COALESCE(SUM(status = 'Cancelled'), 0) AS cancelled,
                    COALESCE(SUM(status = 'No-show'), 0) AS no_shows,
                    COALESCE(SUM(status IN ('Checked-in', 'Completed')), 0) AS seated,
                    COALESCE(SUM(CASE WHEN status NOT IN ('Cancelled', 'No-show') THEN number_of_guests END), 0) AS guests,
                    COALESCE(SUM(CASE WHEN status <> 'Cancelled' OR fee_refundable = 0 THEN total_fee END), 0) AS fees
             FROM reservations WHERE reservation_date = :date"
        );
        $reservations->execute(['date' => $date]);

        $range = ['day_start' => "{$date} 00:00:00", 'day_end' => (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'];
        $sales = $this->pdo->prepare(
            "SELECT COUNT(*) AS orders,
                    COALESCE(SUM(total_amount), 0) AS food_sales,
                    COALESCE(SUM(CASE WHEN order_status = 'Served' THEN total_amount END), 0) AS served_sales,
                    COALESCE(SUM(order_source = 'Pre-order'), 0) AS pre_orders,
                    COALESCE(SUM(order_source <> 'Pre-order'), 0) AS walk_in_orders
             FROM orders
             WHERE order_date >= :day_start AND order_date < :day_end AND order_status <> 'Cancelled'"
        );
        $sales->execute($range);

        $top = $this->pdo->prepare(
            "SELECT oi.item_name, SUM(oi.quantity) AS quantity, SUM(oi.subtotal) AS sales
             FROM order_items oi INNER JOIN orders o ON o.order_id = oi.order_id
             WHERE o.order_date >= :day_start AND o.order_date < :day_end AND o.order_status <> 'Cancelled'
             GROUP BY oi.item_name ORDER BY quantity DESC, sales DESC LIMIT 5"
        );
        $top->execute($range);

        $collected = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE payment_state = 'Succeeded' AND created_at >= :day_start AND created_at < :day_end"
        );
        $collected->execute($range);

        return [
            'reservations' => $reservations->fetch(),
            'sales' => $sales->fetch(),
            'top_items' => $top->fetchAll(),
            'online_collected' => $collected->fetchColumn(),
            'sales_by_block' => $this->salesByBlock($date),
            'sales_by_category' => $this->salesByCategory($date),
            'status_counts' => $this->statusCounts($date),
            'table_types' => $this->tableTypeUsage($date),
            'fee_breakdown' => $this->feeBreakdown($date),
        ];
    }

    /** @return array{day_start: string, day_end: string} */
    private static function dayRange(string $date): array
    {
        return ['day_start' => "{$date} 00:00:00", 'day_end' => (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'];
    }

    /**
     * Food sales per two-hour dining block. An order that belongs to a booking counts in that booking's
     * block; an order without one counts in the block its order time falls in (clamped to 10 AM to 8 PM).
     *
     * @return list<array{block_hour: int, orders: int, sales: string}>
     */
    public function salesByBlock(string $date): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(HOUR(r.start_time), LEAST(20, GREATEST(10, 10 + 2 * FLOOR((HOUR(o.order_date) - 10) / 2)))) AS block_hour,
                    COUNT(*) AS orders, SUM(o.total_amount) AS sales
             FROM orders o LEFT JOIN reservations r ON r.reservation_id = o.reservation_id
             WHERE o.order_date >= :day_start AND o.order_date < :day_end AND o.order_status <> 'Cancelled'
             GROUP BY block_hour"
        );
        $statement->execute(self::dayRange($date));
        $byHour = [];
        foreach ($statement->fetchAll() as $row) {
            $byHour[(int) $row['block_hour']] = $row;
        }
        return array_map(static fn (int $hour): array => [
            'block_hour' => $hour,
            'orders' => (int) ($byHour[$hour]['orders'] ?? 0),
            'sales' => (string) ($byHour[$hour]['sales'] ?? '0.00'),
        ], Config::blockHours());
    }

    /** @return list<array{category_name: string, quantity: string, sales: string}> */
    public function salesByCategory(string $date): array
    {
        $statement = $this->pdo->prepare(
            "SELECT mc.category_name, COALESCE(SUM(oi.quantity), 0) AS quantity, COALESCE(SUM(oi.subtotal), 0) AS sales
             FROM menu_categories mc
             LEFT JOIN menu_items mi ON mi.category_id = mc.category_id
             LEFT JOIN order_items oi ON oi.item_id = mi.item_id
             LEFT JOIN orders o ON o.order_id = oi.order_id
                  AND o.order_date >= :day_start AND o.order_date < :day_end AND o.order_status <> 'Cancelled'
             WHERE oi.order_item_id IS NULL OR o.order_id IS NOT NULL
             GROUP BY mc.category_id, mc.category_name, mc.sort_order
             ORDER BY mc.sort_order, mc.category_id"
        );
        $statement->execute(self::dayRange($date));
        return $statement->fetchAll();
    }

    /** @return array<string, int> reservation count per status for the day */
    public function statusCounts(string $date): array
    {
        $statement = $this->pdo->prepare('SELECT status, COUNT(*) FROM reservations WHERE reservation_date = :date GROUP BY status');
        $statement->execute(['date' => $date]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** @return list<array<string, mixed>> bookings, guests, fees and food per table category (cancelled bookings excluded) */
    public function tableTypeUsage(string $date): array
    {
        $statement = $this->pdo->prepare(
            "SELECT t.type_name, COUNT(r.reservation_id) AS bookings, COALESCE(SUM(r.number_of_guests), 0) AS guests,
                    COALESCE(SUM(r.total_fee), 0) AS fees, COALESCE(SUM(f.amount), 0) AS food_sales
             FROM table_types t
             LEFT JOIN dining_tables d ON d.table_type_id = t.table_type_id
             LEFT JOIN reservations r ON r.table_id = d.table_id AND r.reservation_date = :date AND r.status <> 'Cancelled'
             LEFT JOIN (SELECT reservation_id, SUM(total_amount) AS amount FROM orders WHERE order_status <> 'Cancelled' GROUP BY reservation_id) f
                    ON f.reservation_id = r.reservation_id
             GROUP BY t.table_type_id, t.type_name, t.capacity_min
             ORDER BY t.capacity_min"
        );
        $statement->execute(['date' => $date]);
        return $statement->fetchAll();
    }

    /** @return array{extra_guest: string, forfeited: string} */
    public function feeBreakdown(string $date): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN status <> 'Cancelled' OR fee_refundable = 0 THEN extra_fee END), 0) AS extra_guest,
                    COALESCE(SUM(CASE WHEN status = 'Cancelled' AND fee_refundable = 0 THEN total_fee END), 0) AS forfeited
             FROM reservations WHERE reservation_date = :date"
        );
        $statement->execute(['date' => $date]);
        return $statement->fetch();
    }

    /**
     * Bookings for one service date, optionally narrowed. Every filter is a bound parameter.
     *
     * @param array{date: string, status?: ?string, block?: ?string, type?: ?string, payment?: ?string, q?: ?string} $filters
     * @return list<array<string, mixed>>
     */
    public function searchReservations(array $filters): array
    {
        $where = ['r.reservation_date = :date'];
        $params = ['date' => $filters['date']];
        foreach (['status' => 'r.status', 'block' => 'r.start_time', 'type' => 't.type_name', 'payment' => 'r.payment_status'] as $key => $column) {
            if (($filters[$key] ?? null) !== null && $filters[$key] !== '') {
                $where[] = "{$column} = :{$key}";
                $params[$key] = $filters[$key];
            }
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(r.reference_code LIKE :q1 OR CONCAT(c.first_name, ' ', c.last_name) LIKE :q2 OR c.phone_number LIKE :q3 OR c.email LIKE :q4)";
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $statement = $this->pdo->prepare(
            'SELECT r.reservation_id, r.reference_code, r.table_id, r.start_time, r.end_time, r.number_of_guests, r.status, r.payment_status,
                    r.total_fee, c.first_name, c.last_name, c.email, c.phone_number, t.type_name, COALESCE(f.amount, 0) AS food_total
             FROM reservations r
             INNER JOIN customers c ON c.customer_id = r.customer_id
             INNER JOIN dining_tables d ON d.table_id = r.table_id
             INNER JOIN table_types t ON t.table_type_id = d.table_type_id
             LEFT JOIN (SELECT reservation_id, SUM(total_amount) AS amount FROM orders WHERE order_status <> \'Cancelled\' GROUP BY reservation_id) f
                    ON f.reservation_id = r.reservation_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY r.start_time, r.table_id'
        );
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /**
     * One row per guest (grouped by email, since every booking stores its own customer row),
     * with reservation counts and settled food spend.
     *
     * @return list<array<string, mixed>>
     */
    public function customerDirectory(string $today): array
    {
        $statement = $this->pdo->prepare(
            "SELECT MIN(c.customer_id) AS customer_id,
                    SUBSTRING_INDEX(GROUP_CONCAT(CONCAT(c.first_name, ' ', c.last_name) ORDER BY c.customer_id DESC SEPARATOR '|'), '|', 1) AS name,
                    LOWER(c.email) AS email,
                    SUBSTRING_INDEX(GROUP_CONCAT(c.phone_number ORDER BY c.customer_id DESC SEPARATOR '|'), '|', 1) AS phone,
                    COUNT(r.reservation_id) AS reservations,
                    COALESCE(SUM(r.status = 'Completed'), 0) AS completed,
                    COALESCE(SUM(r.status IN ('Pending', 'Confirmed') AND r.reservation_date >= :today), 0) AS upcoming,
                    MAX(r.reservation_date) AS last_visit,
                    COALESCE(SUM(f.amount), 0) AS food_spend
             FROM customers c
             LEFT JOIN reservations r ON r.customer_id = c.customer_id
             LEFT JOIN (SELECT reservation_id, SUM(total_amount) AS amount FROM orders WHERE order_status = 'Served' GROUP BY reservation_id) f
                    ON f.reservation_id = r.reservation_id
             GROUP BY LOWER(c.email)
             ORDER BY last_visit IS NULL, last_visit DESC, name"
        );
        $statement->execute(['today' => $today]);
        return $statement->fetchAll();
    }

    /**
     * Everything about one guest, found through any of their customer ids.
     *
     * @return array{profile: array<string, mixed>, reservations: list<array<string, mixed>>}|null
     */
    public function customerRecord(int $customerId): ?array
    {
        $statement = $this->pdo->prepare('SELECT LOWER(email) FROM customers WHERE customer_id = :id');
        $statement->execute(['id' => $customerId]);
        $email = $statement->fetchColumn();
        if ($email === false) {
            return null;
        }
        $profile = $this->pdo->prepare(
            'SELECT MIN(customer_id) AS customer_id, MIN(created_at) AS member_since,
                    SUBSTRING_INDEX(GROUP_CONCAT(CONCAT(first_name, \' \', last_name) ORDER BY customer_id DESC SEPARATOR \'|\'), \'|\', 1) AS name,
                    SUBSTRING_INDEX(GROUP_CONCAT(phone_number ORDER BY customer_id DESC SEPARATOR \'|\'), \'|\', 1) AS phone
             FROM customers WHERE LOWER(email) = :email'
        );
        $profile->execute(['email' => $email]);
        $reservations = $this->pdo->prepare(
            "SELECT r.reservation_id, r.reference_code, r.reservation_date, r.start_time, r.table_id, r.number_of_guests, r.status,
                    r.payment_status, r.total_fee, t.type_name, COALESCE(f.amount, 0) AS food_total, COALESCE(f.served, 0) AS served_total,
                    (SELECT GROUP_CONCAT(CONCAT(oi.quantity, '× ', oi.item_name) ORDER BY oi.order_item_id SEPARATOR ', ')
                       FROM orders o2 INNER JOIN order_items oi ON oi.order_id = o2.order_id
                      WHERE o2.reservation_id = r.reservation_id AND o2.order_status <> 'Cancelled') AS items
             FROM reservations r
             INNER JOIN customers c ON c.customer_id = r.customer_id
             INNER JOIN dining_tables d ON d.table_id = r.table_id
             INNER JOIN table_types t ON t.table_type_id = d.table_type_id
             LEFT JOIN (SELECT reservation_id, SUM(total_amount) AS amount,
                               SUM(CASE WHEN order_status = 'Served' THEN total_amount END) AS served
                          FROM orders WHERE order_status <> 'Cancelled' GROUP BY reservation_id) f ON f.reservation_id = r.reservation_id
             WHERE LOWER(c.email) = :email
             ORDER BY r.reservation_date DESC, r.start_time DESC"
        );
        $reservations->execute(['email' => $email]);
        return ['profile' => $profile->fetch() + ['email' => $email], 'reservations' => $reservations->fetchAll()];
    }
}
