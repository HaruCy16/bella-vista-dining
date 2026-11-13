<?php
require_once __DIR__ . '/bootstrap.php';
$pageTitle = $pageTitle ?? 'Bella Vista Dining · Filipino garden restaurant';
$pageDescription = $pageDescription ?? 'Filipino comfort food served slow in the Bella Vista garden. Book a 2-hour table online and pre-order your dishes. Open Tuesday to Sunday, 10 AM to 10 PM.';
$activePage = $activePage ?? 'home';
$flowPage = $flowPage ?? false; // booking flow: its own app bar on mobile, no tab bar
$nav = [
    'about' => ['Our story', '/public/about.php'],
    'menu' => ['Menu & preorder', '/public/menu.php'],
    'reserve' => ['Reserve a table', '/public/reserve.php'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#F6F2E9">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <title><?= e($pageTitle) ?></title>
    <?= brand_head_tags() ?>
    <script>document.documentElement.classList.add('js');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400..600;1,6..72,400..500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/assets/css/app.css?v=20261114">
</head>
<body class="<?= $flowPage ? 'is-flow' : 'has-tabbar' ?>">
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header" id="top">
        <nav class="wrap nav" aria-label="Main">
            <a href="/index.php" class="brand" aria-label="Bella Vista Dining, home"><?= brand_logo(48, '') ?></a>
            <div class="main-menu">
                <?php foreach ($nav as $key => [$label, $href]): ?>
                    <a href="<?= e($href) ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?><?= $key === 'reserve' ? ' class="main-menu-cta"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </nav>
    </header>
