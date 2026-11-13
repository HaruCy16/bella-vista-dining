<?php
declare(strict_types=1);

final class AvailabilityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Active tables with no live reservation (anything but Cancelled / No-show) in the slot.
     * Future availability always comes from reservations, never from dining_tables.status.
     *
     * @return list<array<string, mixed>>
     */
    public function tablesForSlot(string $date, string $startTime): array
    {
        $statement = $this->pdo->prepare(
            "SELECT d.table_id, t.type_name, t.capacity_min, t.capacity_max, t.base_capacity,
                    t.reservation_fee, t.minimum_order, t.extra_charge
             FROM dining_tables d
             INNER JOIN table_types t ON t.table_type_id = d.table_type_id
             WHERE d.is_active = 1
               AND NOT EXISTS (
                   SELECT 1 FROM reservations r
                   WHERE r.table_id = d.table_id
                     AND r.reservation_date = :reservation_date
                     AND r.start_time = :start_time
                     AND r.status NOT IN ('Cancelled', 'No-show')
               )
             ORDER BY t.capacity_min, d.table_id"
        );
        $statement->execute(['reservation_date' => $date, 'start_time' => $startTime]);
        return $statement->fetchAll();
    }
}
