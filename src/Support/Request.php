<?php
declare(strict_types=1);

final class Request
{
    private const MAX_BODY_BYTES = 65536;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function requireMethod(string ...$methods): void
    {
        if (!in_array(self::method(), $methods, true)) {
            header('Allow: ' . implode(', ', $methods));
            throw new MethodNotAllowedException();
        }
    }

    /** CSRF check for state-changing JSON requests (token in the X-CSRF-Token header). */
    public static function requireCsrf(): void
    {
        Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }

    /** @return array<string, mixed> */
    public static function json(): array
    {
        $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            throw new ValidationException('The request is too large.');
        }
        if (trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded) && $decoded !== []) {
            throw new ValidationException('The request body must be a JSON object.');
        }
        return $decoded;
    }

    /** @return array<string, mixed> */
    public static function query(): array
    {
        return $_GET;
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }
}
