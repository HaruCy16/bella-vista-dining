<?php
declare(strict_types=1);

/*
 * A realistic service day for the admin screens: every booking status, all six dining blocks,
 * orders at each kitchen stage, a late cancellation, a no-show and a returning guest.
 *
 *   docker compose exec -T -e DB_NAME=bella_vista_test app php tests/fixtures/demo-day.php
 *
 * Writes through the real ReservationService (so every rule applies), then moves statuses with SQL.
 * Refuses to run unless the database name ends in _test. PHPUnit wipes this database on every run.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$name = (string) getenv('DB_NAME');
if (!str_ends_with($name, '_test')) {
    fwrite(STDERR, "Refusing to run against '{$name}': this script only fills databases whose name ends in _test.\n");
    exit(1);
}

$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['payments', 'order_items', 'orders', 'reservations', 'customers'] as $table) {
    $pdo->exec("TRUNCATE TABLE {$table}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$pdo->exec("UPDATE dining_tables SET status = 'Available'");

$service = ReservationService::make($pdo);
$today = Config::now();
$day = static fn (int $offset): string => $today->modify(($offset >= 0 ? '+' : '') . "{$offset} days")->format('Y-m-d');
$set = static fn (string $sql, array $params = []) => $pdo->prepare($sql)->execute($params);

$guests = [
    'elena' => ['Elena', 'Santos', 'elena.santos@example.test', '0917 555 0126'],
    'miguel' => ['Miguel', 'Reyes', 'miguel.reyes@example.test', '0918 222 3344'],
    'carla' => ['Carla', 'Dizon', 'carla.dizon@example.test', '0917 810 4421'],
    'paolo' => ['Paolo', 'Lim', 'paolo.lim@example.test', '0920 331 9087'],
    'isabel' => ['Isabel', 'Navarro', 'isabel.navarro@example.test', '0917 100 2288'],
    'jun' => ['Jun', 'Aquino', 'jun.aquino@example.test', '0915 667 1020'],
    'rosa' => ['Rosa', 'Villanueva', 'rosa.villanueva@example.test', '0919 442 7010'],
    'ben' => ['Ben', 'Castillo', 'ben.castillo@example.test', '0916 903 5512'],
    'tess' => ['Tess', 'Ocampo', 'tess.ocampo@example.test', '0917 774 6035'],
    'noel' => ['Noel', 'Bautista', 'noel.bautista@example.test', '0922 118 4090'],
];

/** Books through the real service. $at is the pretend "now" so earlier slots and past days are still bookable. */
$book = static function (string $guest, string $date, string $time, string $table, int $people, array $cart = [], string $pay = 'onsite') use ($service, $guests, $today): array {
    [$first, $last, $email, $phone] = $guests[$guest];
    $at = (new DateTimeImmutable($date . ' 05:00', Config::timezone()))->modify('-3 days');
    return $service->book([
        'first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone_number' => $phone,
        'reservation_date' => $date, 'start_time' => $time, 'number_of_guests' => $people, 'table_id' => $table,
        'cart' => $cart, 'payment_option' => $pay, 'terms_accepted' => true,
    ], $at);
};

/** Moves a booking and its orders to a state; $paid records an online payment in pesos. */
$state = static function (array $b, string $status, ?string $orders = null, ?string $payment = null, ?float $paid = null, ?bool $refundable = null) use ($set): void {
    $set('UPDATE reservations SET status = ?, payment_status = COALESCE(?, payment_status), fee_refundable = ? WHERE reservation_id = ?',
        [$status, $payment, $refundable === null ? null : (int) $refundable, $b['reservation_id']]);
    if ($status === 'Cancelled') {
        $set('UPDATE reservations SET cancelled_at = NOW() WHERE reservation_id = ?', [$b['reservation_id']]);
    }
    if ($orders !== null) {
        $set('UPDATE orders SET order_status = ? WHERE reservation_id = ? AND order_status <> ?', [$orders, $b['reservation_id'], 'Cancelled']);
    }
    if ($paid !== null) {
        $set("INSERT INTO payments (reservation_id, payment_type, amount, method, payment_state, provider_reference) VALUES (?, ?, ?, 'Mock', 'Succeeded', ?)",
            [$b['reservation_id'], $payment === 'Paid' ? 'Full' : 'Downpayment', $paid, 'mock_' . bin2hex(random_bytes(6))]);
    }
};

// ---- Elena: a returning guest with several visits and one cancellation ----
foreach ([[-40, '18:00:00', 'F1', 6, [21 => 3, 25 => 2], 'Completed'], [-21, '12:00:00', 'VIP1', 12, [23 => 2, 29 => 2, 41 => 6], 'Completed'],
          [-9, '18:00:00', 'F2', 5, [21 => 2, 41 => 4], 'Completed'], [-4, '20:00:00', 'F3', 6, [], 'Cancelled']] as [$offset, $time, $table, $people, $cart, $status]) {
    $b = $book('elena', $day($offset), $time, $table, $people, $cart);
    $state($b, $status, $status === 'Completed' ? 'Served' : null, null, null, $status === 'Cancelled' ? true : null);
}
$book('elena', $day(5), '18:00:00', 'F1', 6, [21 => 2]);   // and one booked ahead
foreach ([['miguel', -30, 'S1', 2], ['miguel', -12, 'S2', 3], ['carla', -18, 'F2', 6], ['paolo', -6, 'S3', 4], ['isabel', -25, 'VIP2', 15]] as [$g, $offset, $table, $people]) {
    $cart = str_starts_with($table, 'S') ? [28 => 2] : [21 => 2, 23 => 1];
    $b = $book($g, $day($offset), '14:00:00', $table, $people, $cart);
    $state($b, 'Completed', 'Served');
}

// ---- Today's service ----
$d = $day(0);
$b = $book('miguel', $d, '10:00:00', 'S1', 2, [28 => 2]);                     $state($b, 'Completed', 'Served');
$b = $book('carla', $d, '10:00:00', 'F1', 6, [21 => 2, 25 => 1, 41 => 3], 'deposit'); $state($b, 'Completed', 'Served', 'Downpayment', 1045.00);
$b = $book('paolo', $d, '12:00:00', 'S2', 4, [23 => 1, 22 => 1]);             $state($b, 'Completed', 'Served');
$b = $book('isabel', $d, '12:00:00', 'VIP1', 12, [21 => 3, 29 => 3, 11 => 4, 41 => 6], 'full'); $state($b, 'Completed', 'Served', 'Paid', 6020.00);
$b = $book('rosa', $d, '14:00:00', 'F2', 5, [21 => 2, 25 => 2, 20 => 1]);     $state($b, 'Checked-in', 'Preparing'); $set("UPDATE dining_tables SET status = 'Occupied' WHERE table_id = 'F2'");
$b = $book('jun', $d, '14:00:00', 'S3', 3, [28 => 1, 23 => 1]);               $state($b, 'Checked-in', 'Pending'); $set("UPDATE dining_tables SET status = 'Occupied' WHERE table_id = 'S3'");
$b = $book('ben', $d, '16:00:00', 'F1', 7, [21 => 2, 25 => 1], 'deposit');      $state($b, 'Confirmed', 'Scheduled', 'Downpayment', 775.00);
$b = $book('tess', $d, '16:00:00', 'VIP2', 18, [21 => 4, 23 => 3, 29 => 3], 'deposit'); $state($b, 'Confirmed', 'Scheduled', 'Downpayment', 3750.00);
$b = $book('noel', $d, '18:00:00', 'S1', 2);
$b = $book('elena', $d, '18:00:00', 'F3', 6, [21 => 2], 'deposit');            // left Pending: deposit not paid yet
$b = $book('rosa', $d, '20:00:00', 'S4', 4);                                    $state($b, 'Cancelled', null, null, null, true);
$b = $book('jun', $d, '20:00:00', 'F2', 6);                                     $state($b, 'Cancelled', null, null, null, false); // same-day cancellation: fee forfeited
$b = $book('ben', $d, '20:00:00', 'S2', 2);                                     $state($b, 'No-show');
$set("INSERT INTO orders (table_id, order_source, order_date, total_amount, order_status, served_at) VALUES ('S4', 'Walk-in', ?, 540.00, 'Served', NOW())", ["{$d} 13:10:00"]);
$set("UPDATE dining_tables SET status = 'Clean-up' WHERE table_id = 'S1'");
$set("UPDATE dining_tables SET status = 'Reserved' WHERE table_id IN ('F1', 'VIP2')");

foreach (['orders' => 'SELECT COUNT(*) FROM orders', 'reservations' => 'SELECT COUNT(*) FROM reservations', 'customers (by email)' => 'SELECT COUNT(DISTINCT LOWER(email)) FROM customers'] as $label => $sql) {
    printf("%-22s %d\n", $label, (int) $pdo->query($sql)->fetchColumn());
}
echo "Demo day ready for {$d} in {$name}.\n";
