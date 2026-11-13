<?php
declare(strict_types=1);

// POST only, with CSRF, so another site cannot sign staff out with a link.
require_once __DIR__ . '/../includes/bootstrap.php';

if (Request::method() !== 'POST') {
    Response::redirect('/admin/index.php');
}
try {
    Csrf::validate($_POST['_csrf'] ?? null);
} catch (CsrfException) {
    Response::redirect('/admin/index.php');
}
Auth::signOut();
Response::redirect('/admin/login.php');
