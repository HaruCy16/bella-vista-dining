<?php
declare(strict_types=1);

final class Auth
{
    public const MANAGER = 'Manager';
    public const KITCHEN = 'Kitchen Staff';
    private const IDLE_SECONDS = 2 * 60 * 60;

    /** The signed-in admin, or null. Expires the session after IDLE_SECONDS without activity. */
    public static function user(): ?array
    {
        $admin = $_SESSION['admin'] ?? null;
        if (!is_array($admin)) {
            return null;
        }
        if (time() - (int) ($_SESSION['admin_seen_at'] ?? 0) > self::IDLE_SECONDS) {
            self::signOut();
            return null;
        }
        $_SESSION['admin_seen_at'] = time();
        return $admin;
    }

    /** For JSON endpoints: 401 when signed out, 403 when the role is not allowed. */
    public static function requireRole(string ...$roles): array
    {
        $admin = self::user();
        if ($admin === null) {
            throw new AuthenticationException();
        }
        if ($roles !== [] && !in_array($admin['role'], $roles, true)) {
            throw new AuthorizationException();
        }
        return $admin;
    }

    /** For admin pages: redirect to login when signed out, plain 403 page when the role is not allowed. */
    public static function guardPage(string ...$roles): array
    {
        $admin = self::user();
        if ($admin === null) {
            Response::redirect('/admin/login.php');
        }
        if ($roles !== [] && !in_array($admin['role'], $roles, true)) {
            http_response_code(403);
            echo '<!doctype html><meta charset="utf-8"><title>Not allowed</title><p style="font:16px system-ui;padding:2rem">Your role does not have access to this page. <a href="/admin/index.php">Back to the dashboard</a></p>';
            exit;
        }
        return $admin;
    }

    public static function signIn(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'admin_id' => (int) $admin['admin_id'],
            'username' => (string) $admin['username'],
            'full_name' => (string) $admin['full_name'],
            'role' => (string) $admin['role'],
        ];
        $_SESSION['admin_seen_at'] = time();
        unset($_SESSION['_csrf']); // fresh token for the new privilege level
    }

    public static function signOut(): void
    {
        unset($_SESSION['admin'], $_SESSION['admin_seen_at'], $_SESSION['_csrf']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function isManager(array $admin): bool
    {
        return $admin['role'] === self::MANAGER;
    }
}
