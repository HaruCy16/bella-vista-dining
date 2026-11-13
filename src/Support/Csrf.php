<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function validate(mixed $token): void
    {
        $expected = $_SESSION['_csrf'] ?? '';
        if (!is_string($token) || $token === '' || !is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
            throw new CsrfException();
        }
    }
}
