<?php
declare(strict_types=1);

// POST {cart: {item_id: qty}, table_id?} -> server-priced cart and the minimum-order check.
require_once __DIR__ . '/../../includes/bootstrap.php';

Response::api(static function (): array {
    Request::requireMethod('POST');
    Request::requireCsrf();
    $input = Request::json();
    $v = new Validator($input);
    $cart = $v->cart('cart');
    $tableId = $v->string('table_id', 'Table', 10, required: false);
    $v->validate();

    $pdo = db();
    $orders = new OrderService(new MenuRepository($pdo), new OrderRepository($pdo));
    $priced = $orders->price($cart);

    $minimum = 0;
    if ($tableId !== '') {
        $statement = $pdo->prepare('SELECT t.minimum_order FROM dining_tables d JOIN table_types t ON t.table_type_id = d.table_type_id WHERE d.table_id = :id');
        $statement->execute(['id' => $tableId]);
        $minimum = Money::fromPesos((string) ($statement->fetchColumn() ?: '0'));
    }
    return $priced + [
        'minimum_order' => $minimum,
        'minimum_met' => $priced['total_centavos'] === 0 || $priced['total_centavos'] >= $minimum,
    ];
});
