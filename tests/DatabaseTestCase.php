<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Rebuilds bella_vista_test from database/schema.sql + seed.sql once per run,
 * then empties the transactional tables before every test.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static bool $built = false;
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = db();
        if (getenv('DB_NAME') !== 'bella_vista_test') {
            self::fail('Refusing to run database tests outside bella_vista_test.');
        }
        if (!self::$built) {
            $this->rebuild();
            self::$built = true;
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['payments', 'order_items', 'orders', 'reservations', 'customers', 'admin_users', 'admin_login_attempts'] as $table) {
            $this->pdo->exec("TRUNCATE TABLE {$table}");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        // Reference rows that tests may change: restore them so tests stay independent.
        $this->pdo->exec('UPDATE menu_items SET is_available = 1');
        $this->pdo->exec("UPDATE dining_tables SET is_active = 1, status = 'Available'");
    }

    private function rebuild(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->pdo->exec("DROP TABLE `{$table}`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        foreach (['schema.sql', 'seed.sql'] as $file) {
            foreach (self::statements(__DIR__ . '/../database/' . $file) as $sql) {
                $this->pdo->exec($sql);
            }
        }
    }

    /** Splits a SQL file on semicolons that end a line; comments are dropped. @return list<string> */
    private static function statements(string $path): array
    {
        $lines = array_filter(
            explode("\n", (string) file_get_contents($path)),
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );
        $parts = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
    }

    /** Fixed clock for tests: Tuesday 17 Nov 2026, Manila time. */
    protected static function at(string $dateTime = '2026-11-17 09:00'): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('Asia/Manila'));
    }

    /** A valid booking request; override any field. Wednesday 18 Nov 2026 at 6 PM, Family table F1. */
    protected static function bookingInput(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => 'ana@example.test', 'phone_number' => '0917 123 4567',
            'reservation_date' => '2026-11-18', 'start_time' => '18:00:00', 'number_of_guests' => 6, 'table_id' => 'F1',
            'cart' => [], 'terms_accepted' => true, 'payment_option' => 'onsite',
        ];
    }

    protected function book(array $overrides = [], ?DateTimeImmutable $now = null): array
    {
        return ReservationService::make($this->pdo)->book(self::bookingInput($overrides), $now ?? self::at());
    }

    protected function value(string $sql, array $params = []): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    protected function createAdmin(string $username, string $role, string $password = 'correct-horse-battery'): void
    {
        $this->pdo->prepare('INSERT INTO admin_users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)')
            ->execute([$username, password_hash($password, PASSWORD_DEFAULT), ucfirst($username), $role]);
    }
}
