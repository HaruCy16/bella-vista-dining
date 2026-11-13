<?php
declare(strict_types=1);

/**
 * Admin sign-in with throttling: after MAX_FAILURES failed attempts for the same
 * username from the same IP within WINDOW_MINUTES, further attempts are refused
 * until the window passes. Unknown usernames are throttled the same way and get
 * the same error message, so the form does not reveal which usernames exist.
 */
final class AdminAuthService
{
    public const MAX_FAILURES = 5;
    public const WINDOW_MINUTES = 15;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> the admin row on success */
    public function attempt(string $username, string $password, string $ip, ?DateTimeImmutable $now = null): array
    {
        $now ??= Config::now();
        $username = mb_substr(trim($username), 0, 50);
        if ($username === '' || $password === '') {
            throw new ValidationException('Enter your username and password.');
        }
        if ($this->recentFailures($username, $ip, $now) >= self::MAX_FAILURES) {
            throw new TooManyRequestsException('Too many failed sign-in attempts. Try again in ' . self::WINDOW_MINUTES . ' minutes.');
        }

        $statement = $this->pdo->prepare(
            'SELECT admin_id, username, password_hash, full_name, role
             FROM admin_users WHERE username = :username AND is_active = 1'
        );
        $statement->execute(['username' => $username]);
        $admin = $statement->fetch();

        // Verify against a dummy hash when the user is unknown so timing does not leak it.
        static $dummyHash = null;
        $dummyHash ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $hash = $admin === false ? $dummyHash : $admin['password_hash'];
        $valid = password_verify($password, $hash) && $admin !== false;
        $this->record($username, $ip, $valid, $now);

        if (!$valid) {
            throw new AuthenticationException('Incorrect username or password.');
        }
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $this->pdo->prepare('UPDATE admin_users SET password_hash = :hash WHERE admin_id = :id')
                ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $admin['admin_id']]);
        }
        $this->pdo->prepare('UPDATE admin_users SET last_login = :now WHERE admin_id = :id')
            ->execute(['now' => $now->format('Y-m-d H:i:s'), 'id' => $admin['admin_id']]);
        return $admin;
    }

    private function recentFailures(string $username, string $ip, DateTimeImmutable $now): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM admin_login_attempts
             WHERE username = :username AND ip_address = :ip AND succeeded = 0 AND attempted_at > :since'
        );
        $statement->execute([
            'username' => $username,
            'ip' => $ip,
            'since' => $now->modify('-' . self::WINDOW_MINUTES . ' minutes')->format('Y-m-d H:i:s'),
        ]);
        return (int) $statement->fetchColumn();
    }

    private function record(string $username, string $ip, bool $succeeded, DateTimeImmutable $now): void
    {
        $this->pdo->prepare(
            'INSERT INTO admin_login_attempts (username, ip_address, succeeded, attempted_at)
             VALUES (:username, :ip, :succeeded, :at)'
        )->execute(['username' => $username, 'ip' => $ip, 'succeeded' => (int) $succeeded, 'at' => $now->format('Y-m-d H:i:s')]);
        if ($succeeded) {
            $this->pdo->prepare('DELETE FROM admin_login_attempts WHERE username = :username AND ip_address = :ip AND succeeded = 0')
                ->execute(['username' => $username, 'ip' => $ip]);
        }
    }
}
