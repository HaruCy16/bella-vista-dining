<?php

declare(strict_types=1);

require_once __DIR__ . '/data.php';
$pageTitle = $pageTitle ?? $brand['name'];
$activePage = $activePage ?? '';
$bodyClass = $bodyClass ?? '';
$navItems = [
    ['label' => 'Home', 'href' => '/', 'key' => 'home'],
    ['label' => 'About', 'href' => '/pages/about.php', 'key' => 'about'],
    ['label' => 'Menu', 'href' => '/pages/menu.php', 'key' => 'menu'],
    ['label' => 'Reservations', 'href' => '/pages/reservations.php', 'key' => 'reservations'],
    ['label' => 'Gallery', 'href' => '/pages/gallery.php', 'key' => 'gallery'],
    ['label' => 'Login', 'href' => '/pages/login.php', 'key' => 'login'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Bella Vista Dining — farm-to-table Filipino comfort food and continental specialties in a garden-side setting.">
    <meta name="theme-color" content="#183B2E">
    <title><?= htmlspecialchars($pageTitle) ?> · Bella Vista Dining</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        forest: '#183B2E', sage: '#6B8F7B', cream: '#F8F6EE', beige: '#E6D8BC', terracotta: '#C9754B', walnut: '#4B3629'
                    },
                    fontFamily: { display: ['Playfair Display', 'serif'], sans: ['Poppins', 'sans-serif'] },
                    boxShadow: { soft: '0 18px 50px rgba(24, 59, 46, 0.10)', card: '0 12px 36px rgba(75, 54, 41, 0.10)' }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-cream text-walnut antialiased <?= htmlspecialchars($bodyClass) ?>">
    <div class="site-noise" aria-hidden="true"></div>
    <header class="site-header" data-header>
        <div class="max-w-[1320px] mx-auto px-5 sm:px-8 lg:px-12">
            <div class="flex items-center justify-between gap-5 py-5 lg:py-6">
                <a href="/" class="brand-lockup" aria-label="Bella Vista Dining home">
                    <span class="brand-mark" aria-hidden="true"><img src="/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-1.webp" alt=""></span>
                    <span class="brand-copy"><span class="brand-name">Bella Vista</span><span class="brand-subtitle">Dining</span></span>
                </a>
                <nav class="hidden lg:flex items-center gap-7" aria-label="Primary navigation">
                    <?php foreach ($navItems as $item): ?>
                        <a class="nav-link <?= $activePage === $item['key'] ? 'is-active' : '' ?>" href="<?= $item['href'] ?>"><?= $item['label'] ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="flex items-center gap-3">
                    <a href="/pages/reservations.php" class="hidden sm:inline-flex btn btn-primary btn-small">Reserve a table <span aria-hidden="true">↗</span></a>
                    <button class="mobile-menu-toggle lg:hidden" type="button" data-mobile-menu-toggle aria-expanded="false" aria-controls="mobile-menu">
                        <span class="sr-only">Open navigation</span><span></span><span></span>
                    </button>
                </div>
            </div>
            <div id="mobile-menu" class="mobile-menu lg:hidden" data-mobile-menu hidden>
                <nav class="flex flex-col gap-1 pb-5" aria-label="Mobile navigation">
                    <?php foreach ($navItems as $item): ?>
                        <a class="mobile-nav-link <?= $activePage === $item['key'] ? 'is-active' : '' ?>" href="<?= $item['href'] ?>"><?= $item['label'] ?></a>
                    <?php endforeach; ?>
                    <a href="/pages/reservations.php" class="btn btn-primary mt-3">Reserve a table <span aria-hidden="true">↗</span></a>
                </nav>
            </div>
        </div>
    </header>
    <main>
