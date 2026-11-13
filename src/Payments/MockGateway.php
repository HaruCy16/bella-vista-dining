<?php
declare(strict_types=1);

/**
 * Sandbox stand-in for a real provider: sends the guest to /public/pay/mock.php,
 * where they choose "succeed" or "fail". That page calls PaymentService::complete(),
 * exactly as a provider webhook would. Never enabled when APP_ENV=production.
 */
final class MockGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'Mock';
    }

    public function createCheckout(array $checkout): array
    {
        $reference = 'mock_' . bin2hex(random_bytes(12));
        return [
            'provider_reference' => $reference,
            'checkout_url' => '/public/pay/mock.php?session=' . rawurlencode($reference),
        ];
    }

    public static function enabled(): bool
    {
        return Env::get('APP_ENV', 'development') !== 'production';
    }
}
