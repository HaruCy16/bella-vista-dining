<?php
declare(strict_types=1);

/*
 * POST {action: "start", reference_code, email?, option: "deposit"|"full"} -> {checkout_url}
 * POST {action: "onsite", reference_code, email?}                          -> pay at the restaurant instead
 * POST {action: "mock-complete", session, outcome: "success"|"failure"}    -> sandbox only (Mock gateway)
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

Response::api(static function (): array {
    Request::requireMethod('POST');
    Request::requireCsrf();
    $input = Request::json();
    $pdo = db();

    switch ($input['action'] ?? '') {
        case 'start':
        case 'onsite':
            [$reference, $email] = GuestSession::credentials($input);
            $booking = ReservationService::make($pdo)->lookup($reference, $email); // ownership check
            $reservation = (new ReservationRepository($pdo))->findByReference($booking['reference_code']);
            if ($input['action'] === 'onsite') {
                PaymentService::make($pdo)->chooseOnsite((int) $reservation['reservation_id']);
                return ReservationService::make($pdo)->lookup($reference, $email);
            }
            $v = new Validator($input);
            $option = $v->in('option', 'payment option', ['deposit', 'full']);
            $v->validate();
            return PaymentService::make($pdo)->start((int) $reservation['reservation_id'], $option, app_url());

        case 'mock-complete':
            if (!MockGateway::enabled()) {
                throw new NotFoundException('Not found.');
            }
            $v = new Validator($input);
            $session = $v->string('session', 'Payment session', 100);
            $outcome = $v->in('outcome', 'outcome', ['success', 'failure']);
            $v->validate();
            if (!str_starts_with($session, 'mock_')) {
                throw new NotFoundException('Payment not found.');
            }
            $result = PaymentService::make($pdo, new MockGateway())->complete($session, $outcome === 'success');
            $reference = rawurlencode($result['reference_code']);
            return $result + ['redirect' => $result['state'] === 'Succeeded'
                ? "/public/confirmation.php?ref={$reference}&payment=success"
                : "/public/booking.php?ref={$reference}&payment=failed"];

        default:
            throw new ValidationException('Unknown action.');
    }
});
