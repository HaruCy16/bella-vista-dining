<?php
declare(strict_types=1);

/** Phase 0 freeze: the constraints and seed data the rest of the app relies on. */
final class SchemaTest extends DatabaseTestCase
{
    public function testSeedMatchesBusinessRules(): void
    {
        $types = $this->pdo->query(
            'SELECT type_name, capacity_min, capacity_max, base_capacity, reservation_fee, minimum_order, extra_charge
             FROM table_types ORDER BY table_type_id'
        )->fetchAll();
        self::assertSame([
            ['type_name' => 'Standard', 'capacity_min' => 2, 'capacity_max' => 4, 'base_capacity' => 4, 'reservation_fee' => '0.00', 'minimum_order' => '1000.00', 'extra_charge' => '0.00'],
            ['type_name' => 'Family', 'capacity_min' => 5, 'capacity_max' => 8, 'base_capacity' => 8, 'reservation_fee' => '500.00', 'minimum_order' => '0.00', 'extra_charge' => '0.00'],
            ['type_name' => 'VIP', 'capacity_min' => 9, 'capacity_max' => 20, 'base_capacity' => 15, 'reservation_fee' => '2500.00', 'minimum_order' => '0.00', 'extra_charge' => '300.00'],
        ], $types);

        $tables = $this->pdo->query('SELECT table_id FROM dining_tables ORDER BY table_id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['F1', 'F2', 'F3', 'S1', 'S2', 'S3', 'S4', 'VIP1', 'VIP2'], $tables);
    }

    public function testMenuHasThreeSpecCategoriesWithAtLeastThreeItems(): void
    {
        $rows = $this->pdo->query(
            'SELECT c.category_name, COUNT(i.item_id) AS items
             FROM menu_categories c LEFT JOIN menu_items i ON i.category_id = c.category_id
             GROUP BY c.category_id, c.category_name ORDER BY c.sort_order'
        )->fetchAll();
        self::assertSame(['Main Courses', 'Appetizers & Soups', 'Desserts & Drinks'], array_column($rows, 'category_name'));
        foreach ($rows as $row) {
            self::assertGreaterThanOrEqual(3, (int) $row['items'], $row['category_name']);
        }
    }

    public function testMenuItemPriceMustBePositive(): void
    {
        $this->expectCheckViolation();
        $this->pdo->exec("INSERT INTO menu_items (category_id, item_name, price) VALUES (1, 'Free lunch', 0.00)");
    }

    public function testOrderItemUnitPriceCannotBeNegative(): void
    {
        $orderId = $this->insertWalkInOrder();
        $this->expectCheckViolation();
        $this->pdo->exec(
            "INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity)
             VALUES ({$orderId}, 1, 'Tapsilog', -1.00, 1)"
        );
    }

    public function testOrderItemSubtotalIsGenerated(): void
    {
        $orderId = $this->insertWalkInOrder();
        $this->pdo->exec(
            "INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity)
             VALUES ({$orderId}, 1, 'Tapsilog', 320.00, 3)"
        );
        self::assertSame('960.00', $this->pdo->query("SELECT subtotal FROM order_items WHERE order_id = {$orderId}")->fetchColumn());
    }

    public function testDatabaseSessionUsesManilaTime(): void
    {
        self::assertSame('+08:00', $this->pdo->query('SELECT @@session.time_zone')->fetchColumn());
    }

    private function insertWalkInOrder(): int
    {
        $this->pdo->exec("INSERT INTO orders (table_id, order_source) VALUES ('S1', 'Walk-in')");
        return (int) $this->pdo->lastInsertId();
    }

    private function expectCheckViolation(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/3819|Check constraint/');
    }
}
