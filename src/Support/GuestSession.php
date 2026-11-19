<?php
declare(strict_types=1);

/**
 * Remembers which bookings this browser made or looked up, so the guest is not
 * asked for their email again on the same device (e.g. returning from checkout).
 */
final class GuestSession
{
    private const LIMIT = 25;

    public static function remember(string $reference, string $email): void
    {
        $bookings = $_SESSION['guest_bookings'] ?? [];
        $bookings[strtoupper($reference)] = strtolower(trim($email));
        $_SESSION['guest_bookings'] = array_slice($bookings, -self::LIMIT, null, true);
    }

    /** @return array<string, string> reference => email, newest last */
    public static function all(): array
    {
        return $_SESSION['guest_bookings'] ?? [];
    }

    public static function emailFor(string $reference): ?string
    {
        return $_SESSION['guest_bookings'][strtoupper(trim($reference))] ?? null;
    }

    /**
     * Reference + email from the request, falling back to the remembered email.
     *
     * @param array<string, mixed> $input
     * @return array{string, string}
     */
    public static function credentials(array $input): array
    {
        $reference = strtoupper(trim((string) ($input['reference_code'] ?? '')));
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '') {
            $email = self::emailFor($reference) ?? '';
        }
        if ($reference === '' || $email === '') {
            throw new ValidationException('Enter your booking reference and the email you booked with.');
        }
        return [$reference, $email];
    }
}
