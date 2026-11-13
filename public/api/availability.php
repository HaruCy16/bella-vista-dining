<?php
declare(strict_types=1);

// GET ?date=Y-m-d&start_time=HH:MM:SS[&guests=N] -> tables free in that slot.
require_once __DIR__ . '/../../includes/bootstrap.php';

Response::api(static function (): array {
    Request::requireMethod('GET');
    $v = new Validator(Request::query());
    $date = $v->date('date');
    $time = $v->string('start_time', 'Time', 8);
    $guests = isset($_GET['guests']) && $_GET['guests'] !== '' ? $v->int('guests', 'Guests', 1, 50) : null;
    $v->validate();

    (new HousekeepingService(db()))->run(); // frees abandoned / no-show slots before answering
    return (new AvailabilityService(new AvailabilityRepository(db())))->forSlot($date, $time, $guests);
});
