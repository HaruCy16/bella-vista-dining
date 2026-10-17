<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);
$file = __DIR__ . $path;

if ($path === '/manus-routes.json') {
    header('Content-Type: application/json; charset=utf-8');
    readfile(__DIR__ . '/public/manus-routes.json');
    return true;
}

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

if (is_file($file)) {
    return false;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/index.php';
