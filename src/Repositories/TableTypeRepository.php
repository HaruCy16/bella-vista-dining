<?php
declare(strict_types=1);

final class TableTypeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> Table types with their count of active tables. */
    public function allWithTableCounts(): array
    {
        return $this->pdo->query(
            'SELECT t.table_type_id, t.type_name, t.capacity_min, t.capacity_max, t.base_capacity,
                    t.reservation_fee, t.minimum_order, t.extra_charge,
                    COUNT(d.table_id) AS table_count
             FROM table_types t
             LEFT JOIN dining_tables d ON d.table_type_id = t.table_type_id AND d.is_active = 1
             GROUP BY t.table_type_id
             ORDER BY t.capacity_min'
        )->fetchAll();
    }

    /** @return list<array{table_id: string, type_name: string}> Every active table, for the seating chips. */
    public function tables(): array
    {
        return $this->pdo->query(
            'SELECT d.table_id, t.type_name
             FROM dining_tables d
             JOIN table_types t ON t.table_type_id = d.table_type_id
             WHERE d.is_active = 1
             ORDER BY t.capacity_min, LENGTH(d.table_id), d.table_id'
        )->fetchAll();
    }
}
