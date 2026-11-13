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
        ];
    }
}
