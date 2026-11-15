<?php
declare(strict_types=1);

/** Escape for HTML text and attribute values. */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Public URL for a menu photo: the compressed copy in menu/web/ when it exists,
 * the original otherwise, the placeholder when the file is missing.
 */
function menu_image(?string $path): string
{
    $placeholder = '/public/assets/images/menu/placeholder.svg';
    if ($path === null || $path === '' || !str_starts_with($path, '/public/assets/images/')) {
        return $placeholder;
    }
    $root = dirname(__DIR__);
    $web = str_replace('/menu/items/', '/menu/web/', $path);
    if (is_file($root . $web)) {
        return $web;
    }
    return is_file($root . $path) ? $path : $placeholder;
}

/**
 * Restaurant photos (dining room, garden). Drop real photos into
 * /public/assets/images/venue/{name}.jpg and they replace the dish photo
 * that stands in for them until then.
 */
function venue_image(string $name, string $fallbackMenuPath): string
{
    $path = '/public/assets/images/venue/' . $name . '.jpg';
    return is_file(dirname(__DIR__) . $path) ? $path : menu_image($fallbackMenuPath);
}

/** Inline SVG icon (stroke icons, 24px grid). Decorative unless $label is given. */
function icon(string $name, string $class = 'icon', ?string $label = null): string
{
    $paths = [
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'users' => '<circle cx="9" cy="8.5" r="3.5"/><path d="M2.5 19.5c.8-3.4 3.3-5 6.5-5s5.7 1.6 6.5 5"/><path d="M16 5.2a3.5 3.5 0 010 6.6M18 14.8c1.8.7 3 2.2 3.5 4.7"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0114 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'alert' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v5.5M12 16.2v.3"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'arrow-up-right' => '<path d="M7 17L17 7M8 7h9v9"/>',
        'chevron-left' => '<path d="M15 6l-6 6 6 6"/>',
        'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'sprout' => '<path d="M12 21v-9"/><path d="M12 12c0-4 2.5-6.5 7-6.5 0 4.2-2.6 6.5-7 6.5z"/><path d="M12 14.5c0-3-2-5-5.5-5 0 3.2 2 5 5.5 5z"/>',
        'home' => '<path d="M4 10.5L12 4l8 6.5V20h-5.5v-5.5h-5V20H4z"/>',
        'utensils' => '<path d="M7 3v8M4.5 3v5a2.5 2.5 0 005 0V3M7 11v10"/><path d="M17 21V3c-2.2 1.2-3.5 3.6-3.5 7v3H17"/>',
        'ticket' => '<path d="M4 7.5A1.5 1.5 0 015.5 6h13A1.5 1.5 0 0120 7.5V10a2 2 0 000 4v2.5a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 16.5V14a2 2 0 000-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
        'cloche' => '<path d="M3 18h18M5 18a7 7 0 0114 0M12 8V6.5M10.5 6.5h3"/>',
        'chart' => '<path d="M4 19V5M4 19h16M8 16v-4M12 16V8M16 16v-7"/>',
        'lock' => '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
    ];
    $a11y = $label === null ? 'aria-hidden="true"' : 'role="img" aria-label="' . e($label) . '"';
    return '<svg class="' . e($class) . '" ' . $a11y . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'
        . ($paths[$name] ?? '') . '</svg>';
}

/**
 * The restaurant logo at a given display height (px). Served as WebP with a PNG
 * fallback from /public/assets/images/brand/ (built from restaurant-logo.png).
 */
function brand_logo(int $height, string $alt = 'Bella Vista Dining', string $class = 'brand-logo'): string
{
    $file = $height > 56 ? 'logo-176' : 'logo-112';
    $width = (int) round($height * 1407 / 911);
    $base = '/public/assets/images/brand/' . $file;
    return '<picture><source srcset="' . $base . '.webp" type="image/webp">'
        . '<img class="' . e($class) . '" src="' . $base . '.png" alt="' . e($alt) . '" width="' . $width . '" height="' . $height . '" style="height:' . $height . 'px;width:auto"></picture>';
}

/** Favicon, home-screen icon and social preview tags for every page. */
function brand_head_tags(): string
{
    $dir = '/public/assets/images/brand/';
    return '<link rel="icon" href="' . $dir . 'icon-32.png" sizes="32x32" type="image/png">'
        . '<link rel="icon" href="' . $dir . 'icon-512.png" sizes="512x512" type="image/png">'
        . '<link rel="apple-touch-icon" href="' . $dir . 'icon-180.png">'
        . '<meta property="og:image" content="' . e(app_url() . $dir . 'og-image.jpg') . '">';
}

/** Base URL for links that leave the site and come back (payment return URLs). */
function app_url(): string
{
    $configured = Env::get('APP_URL');
    if ($configured !== null) {
        return rtrim($configured, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https' : 'http') . '://' . $host;
}
