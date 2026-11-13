<?php
declare(strict_types=1);

/**
 * A payment provider (Mock now, PayMongo test mode next). The provider only
 * creates the hosted checkout; PaymentService owns amounts and state.
 */
interface PaymentGateway
{
    /** Stored in payments.method, e.g. "Mock", "PayMongo". */
    public function name(): string;

    /**
     * Starts a hosted checkout for a pending payment.
     *
     * @param array{payment_id: int, amount_centavos: int, description: string, reference_code: string,
     *              customer_name: string, customer_email: string, success_url: string, cancel_url: string} $checkout
     * @return array{provider_reference: string, checkout_url: string}
     */
    public function createCheckout(array $checkout): array;
}
