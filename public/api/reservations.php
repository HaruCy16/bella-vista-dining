<?php
declare(strict_types=1);

/*
 * POST {action: "create", ...booking fields}            -> new booking (+ checkout_url when paying online)
 * POST {action: "lookup", reference_code, email}         -> booking details
 * POST {action: "cancel", reference_code, email}         -> cancels, applies the 24-hour fee rule
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

Response::api(static function (): array {
    Request::requireMethod('POST');
    Request::requireCsrf();
    $input = Request::json();
    $service = ReservationService::make(db());

    switch ($input['action'] ?? 'create') {
        case 'create':
            (new HousekeepingService(db()))->run();
            $booking = $service->book($input);
            GuestSession::remember($booking['reference_code'], (string) $input['email']);
            if ($booking['payment_option'] !== 'onsite') {
                try {
                    $checkout = PaymentService::make(db())->start($booking['reservation_id'], $booking['payment_option'], app_url());
                    $booking['checkout_url'] = $checkout['checkout_url'];
                    $booking['charge'] = $checkout['amount'];
                } catch (Throwable $exception) {
                    // The booking is saved; only the checkout failed. The guest can retry from the booking page
                    // within the payment hold, or choose to pay at the restaurant.
                    error_log('[checkout] ' . $exception->getMessage());
                    $booking['payment_error'] = 'Your table is held for ' . PaymentService::HOLD_MINUTES . ' minutes, but online payment is unavailable right now. Retry from your booking page or choose to pay at the restaurant.';
                }
            }
            unset($booking['reservation_id']);
            return $booking;

        case 'lookup':
            [$reference, $email] = GuestSession::credentials($input);
            $details = $service->lookup($reference, $email);
            GuestSession::remember($reference, $email);
            return $details;

        case 'cancel':
            [$reference, $email] = GuestSession::credentials($input);
            return $service->cancel($reference, $email);

        default:
            throw new ValidationException('Unknown action.');
    }
});
