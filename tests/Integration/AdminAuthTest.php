<?php
declare(strict_types=1);

final class AdminAuthTest extends DatabaseTestCase
{
    private function attempt(string $username, string $password, string $at = '2026-11-17 09:00', string $ip = '10.0.0.1'): array
    {
        return (new AdminAuthService($this->pdo))->attempt($username, $password, $ip, self::at($at));
    }

    public function testCorrectPasswordSignsIn(): void
    {
        $this->createAdmin('manager', 'Manager');
        self::assertSame('Manager', $this->attempt('manager', 'correct-horse-battery')['role']);
        self::assertSame('2026-11-17 09:00:00', $this->value("SELECT last_login FROM admin_users WHERE username = 'manager'"));
    }

    public function testWrongPasswordAndUnknownUserGiveTheSameError(): void
    {
        $this->createAdmin('manager', 'Manager');
        $messages = [];
        foreach ([['manager', 'wrong'], ['nobody', 'wrong']] as [$user, $password]) {
            try {
                $this->attempt($user, $password);
                self::fail('Expected AuthenticationException.');
            } catch (AuthenticationException $exception) {
                $messages[] = $exception->getMessage();
            }
        }
        self::assertSame(['Incorrect username or password.', 'Incorrect username or password.'], $messages);
    }

    public function testFiveFailuresLockTheAccountForThisIpEvenWithTheRightPassword(): void
    {
        $this->createAdmin('manager', 'Manager');
        for ($i = 0; $i < AdminAuthService::MAX_FAILURES; $i++) {
            try { $this->attempt('manager', 'wrong'); } catch (AuthenticationException) {}
        }
        try {
            $this->attempt('manager', 'correct-horse-battery', '2026-11-17 09:10');
            self::fail('Expected TooManyRequestsException.');
        } catch (TooManyRequestsException $exception) {
            self::assertSame(429, $exception->status());
        }
        // Another IP is not locked out, and the lock lifts after the window.
        self::assertSame('manager', $this->attempt('manager', 'correct-horse-battery', '2026-11-17 09:10', '10.0.0.2')['username']);
        self::assertSame('manager', $this->attempt('manager', 'correct-horse-battery', '2026-11-17 09:16')['username']);
    }

    public function testInactiveAdminCannotSignIn(): void
    {
        $this->createAdmin('kitchen', 'Kitchen Staff');
        $this->pdo->exec("UPDATE admin_users SET is_active = 0 WHERE username = 'kitchen'");
        $this->expectException(AuthenticationException::class);
        $this->attempt('kitchen', 'correct-horse-battery');
    }
}
