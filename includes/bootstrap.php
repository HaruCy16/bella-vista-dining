<?php
declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/src/Support/Env.php';
Env::load($root . '/.env');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/view.php';

foreach (['Exceptions', 'Config', 'Money', 'Csrf', 'Auth', 'Response', 'Request', 'Validator', 'GuestSession'] as $support) {
    require_once $root . "/src/Support/{$support}.php";
}

require_once $root . '/src/Payments/PaymentGateway.php';

// Repositories and services are small and have no side effects; load them all once.
foreach (['Repositories', 'Services', 'Payments'] as $layer) {
    foreach (glob($root . "/src/{$layer}/*.php") ?: [] as $file) {
        require_once $file;
    }
}

unset($root, $support, $layer, $file);
