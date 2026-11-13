<?php
declare(strict_types=1);

final class OrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Pre-order for a reservation. table_id is copied from the reservation row in SQL,
     * so a client can never point an order at another table.
     */
    public function insertPreOrder(int $reservationId, string $orderDate): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO orders (reservation_id, table_id, order_source, order_date, total_amount, order_status)
             SELECT r.reservation_id, r.table_id, 'Pre-order', :order_date, 0.00, 'Scheduled'
             FROM reservations r WHERE r.reservation_id = :reservation_id"
        );
        $statement->execute(['reservation_id' => $reservationId, 'order_date' => $orderDate]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Pre-order could not be attached to the reservation.');
        }
        return (int) $this->pdo->lastInsertId();
    }

    /** @param list<array{item_id: int, item_name: string, unit_price_centavos: int, quantity: int}> $items */
    public function insertItems(int $orderId, array $items): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity)
             VALUES (:order_id, :item_id, :item_name, :unit_price, :quantity)'
        );
        foreach ($items as $item) {
            $statement->execute([
                'order_id' => $orderId,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'unit_price' => Money::pesos($item['unit_price_centavos']),
                'quantity' => $item['quantity'],
            ]);
        }
    }

    /** Sets total_amount from the stored item subtotals and returns it in centavos. */
    public function refreshTotal(int $orderId): int
    {
        $this->pdo->prepare(
            'UPDATE orders o
             SET o.total_amount = (SELECT COALESCE(SUM(oi.subtotal), 0) FROM order_items oi WHERE oi.order_id = o.order_id)
             WHERE o.order_id = :order_id'
        )->execute(['order_id' => $orderId]);
        $statement = $this->pdo->prepare('SELECT total_amount FROM orders WHERE order_id = :order_id');
        $statement->execute(['order_id' => $orderId]);
        return Money::fromPesos((string) $statement->fetchColumn());
    }

    /** @return list<array<string, mixed>> orders with their items for a reservation */
    public function forReservation(int $reservationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT o.order_id, o.order_status, o.total_amount, oi.item_id, oi.item_name, oi.unit_price, oi.quantity, oi.subtotal
             FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.order_id
             WHERE o.reservation_id = :id ORDER BY o.order_id, oi.order_item_id'
        );
        $statement->execute(['id' => $reservationId]);
        return $statement->fetchAll();
    }

    /** Live (not cancelled) food total for a reservation, in centavos. */
    public function activeTotalForReservation(int $reservationId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE reservation_id = :id AND order_status <> 'Cancelled'"
        );
        $statement->execute(['id' => $reservationId]);
        return Money::fromPesos((string) $statement->fetchColumn());
    }

    /** Cancels every order of a reservation that the kitchen has not started. Returns the count. */
    public function cancelOpenForReservation(int $reservationId): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE orders SET order_status = 'Cancelled'
             WHERE reservation_id = :id AND order_status IN ('Scheduled', 'Pending')"
        );
        $statement->execute(['id' => $reservationId]);
        return $statement->rowCount();
    }

    /** @return array<string, mixed>|null */
    public function find(int $orderId, bool $forUpdate = false): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM orders WHERE order_id = :id' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $orderId]);
        return $statement->fetch() ?: null;
    }

    public function updateStatus(int $orderId, string $status, ?DateTimeImmutable $servedAt = null): void
    {
        $this->pdo->prepare('UPDATE orders SET order_status = :status, served_at = COALESCE(:served_at, served_at) WHERE order_id = :id')
            ->execute(['id' => $orderId, 'status' => $status, 'served_at' => $servedAt?->format('Y-m-d H:i:s')]);
    }

    /**
     * Kitchen queue: live orders for one business day mapped to tables. Scheduled
     * pre-orders are excluded until housekeeping releases them to Pending.
     *
     * @return list<array<string, mixed>>
     */
    public function kitchenQueue(string $date): array
    {
        $statement = $this->pdo->prepare(
            "SELECT o.order_id, o.table_id, o.order_source, o.order_status, o.order_date, o.total_amount, o.served_at,
                    r.reference_code, r.start_time, r.number_of_guests,
                    GROUP_CONCAT(CONCAT(oi.quantity, '× ', oi.item_name) ORDER BY oi.order_item_id SEPARATOR '\n') AS items
             FROM orders o
             LEFT JOIN reservations r ON r.reservation_id = o.reservation_id
             LEFT JOIN order_items oi ON oi.order_id = o.order_id
             WHERE o.order_date >= :day_start AND o.order_date < :day_end
               AND o.order_status IN ('Pending', 'Preparing', 'Served')
             GROUP BY o.order_id
             ORDER BY FIELD(o.order_status, 'Preparing', 'Pending', 'Served'), o.order_date, o.order_id"
        );
        $statement->execute(['day_start' => "{$date} 00:00:00", 'day_end' => self::nextDay($date) . ' 00:00:00']);
        return $statement->fetchAll();
    }

    /**
     * Releases Scheduled pre-orders to the kitchen once their reservation starts
     * within $leadMinutes. Returns how many were released.
     */
    public function releaseScheduled(DateTimeImmutable $now, int $leadMinutes): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE orders o
             INNER JOIN reservations r ON r.reservation_id = o.reservation_id
             SET o.order_status = 'Pending'
             WHERE o.order_status = 'Scheduled'
               AND r.status IN ('Pending', 'Confirmed', 'Checked-in')
               AND o.order_date <= :release_until"
        );
        $statement->execute(['release_until' => $now->modify("+{$leadMinutes} minutes")->format('Y-m-d H:i:s')]);
        return $statement->rowCount();
    }

    private static function nextDay(string $date): string
    {
        return (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
    }
}
