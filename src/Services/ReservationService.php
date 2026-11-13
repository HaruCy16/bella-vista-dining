<?php
declare(strict_types=1);

/**
 * Booking, lookup and cancellation. Every rule is enforced here on the server;
 * the browser only collects input.
 */
final class ReservationService
{
    public const PAYMENT_OPTIONS = ['onsite', 'deposit', 'full'];
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O, 1/I
    private const MYSQL_DUPLICATE_KEY = 1062;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ReservationRepository $reservations,
        private readonly OrderService $orders,
        private readonly OrderRepository $orderRepository,
        private readonly PaymentRepository $payments,
        private readonly FeeCalculator $fees,
    ) {
    }

    public static function make(PDO $pdo): self
    {
        $orderRepository = new OrderRepository($pdo);
        return new self(
            $pdo,
            new ReservationRepository($pdo),
            new OrderService(new MenuRepository($pdo), $orderRepository),
            $orderRepository,
            new PaymentRepository($pdo),
            new FeeCalculator(),
        );
    }

    /**
     * Books a table, with an optional pre-order, in one transaction.
     *
     * Input keys: first_name, last_name, email, phone_number, reservation_date, start_time,
     * number_of_guests, table_id, cart {item_id: qty}, terms_accepted, payment_option.
     * Any other key (a client total, a different table for the order, a fee) is ignored.
     *
     * @param array<string, mixed> $input
     * @return array{reservation_id: int, reference_code: string, order_id: int|null, total_fee: int,
     *               order_total: int, amount_due: int, status: string, payment_status: string, payment_option: string}
     */
    public function book(array $input, ?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? Config::now())->setTimezone(Config::timezone());

        $v = new Validator($input);
        $customer = [
            'first_name' => $v->string('first_name', 'First name', 50),
            'last_name' => $v->string('last_name', 'Last name', 50),
            'email' => $v->email('email'),
            'phone_number' => $v->phone('phone_number'),
        ];
        $date = $v->date('reservation_date');
        $startTime = $v->in('start_time', 'time', Config::reservationSlots());
        $guests = $v->int('number_of_guests', 'Number of guests', 1, 50);
        $tableId = $v->string('table_id', 'Table', 10);
        $cart = $v->cart('cart');
        $paymentOption = isset($input['payment_option']) ? $v->in('payment_option', 'payment option', self::PAYMENT_OPTIONS) : 'onsite';
        $v->accepted('terms_accepted', 'Please accept the booking policies to continue.');
        $v->validate();

        BookingRules::assertBookableSlot($date, $startTime, $now);
        $priced = $this->orders->price($cart);

        $this->pdo->beginTransaction();
        try {
            $table = $this->reservations->lockTableWithType($tableId);
            if ($table === null) {
                throw ValidationException::field('table_id', 'That table is not available. Please choose another.');
            }
            $fee = $this->fees->quote($table, $guests);
            $this->orders->assertMinimumMet($priced['total_centavos'], Money::fromPesos((string) $table['minimum_order']));

            $amountDue = $fee['total_fee'] + $priced['total_centavos'];
            if ($amountDue === 0) {
                $paymentOption = 'onsite'; // nothing to pay online
            }
            $online = $paymentOption !== 'onsite';
            $status = $online ? 'Pending' : 'Confirmed';
            $paymentStatus = $online ? 'Unpaid' : 'On-site';

            $customerId = $this->reservations->createCustomer($customer);
            [$reservationId, $reference] = $this->insertWithUniqueReference([
                'customer_id' => $customerId,
                'table_id' => $table['table_id'],
                'reservation_date' => $date,
                'start_time' => $startTime,
                'end_time' => BookingRules::startOf($date, $startTime)->modify('+' . Config::SLOT_HOURS . ' hours')->format('H:i:s'),
                'number_of_guests' => $guests,
                'extra_fee' => Money::pesos($fee['extra_fee']),
                'total_fee' => Money::pesos($fee['total_fee']),
                'payment_status' => $paymentStatus,
                'status' => $status,
                'terms_accepted_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $orderId = null;
            if ($priced['items'] !== []) {
                $orderId = $this->orders->createPreOrder($reservationId, "{$date} {$startTime}", $priced)['order_id'];
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'reservation_id' => $reservationId,
            'reference_code' => $reference,
            'order_id' => $orderId,
            'total_fee' => $fee['total_fee'],
            'order_total' => $priced['total_centavos'],
            'amount_due' => $amountDue,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_option' => $paymentOption,
        ];
    }

    /**
     * Booking details for the guest. Reference and email must both match; the
     * error never says which one was wrong.
     *
     * @return array<string, mixed>
     */
    public function lookup(string $reference, string $email): array
    {
        $reservation = $this->findOwned($reference, $email);
        return $this->present($reservation);
    }

    /**
     * Cancels a booking and its unstarted pre-orders. fee_refundable follows the
     * 24-hour / same-day rule. Returns the updated booking.
     *
     * @return array<string, mixed>
     */
    public function cancel(string $reference, string $email, ?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? Config::now())->setTimezone(Config::timezone());
        $this->pdo->beginTransaction();
        try {
            $reservation = $this->findOwned($reference, $email, forUpdate: true);
            if (!in_array($reservation['status'], ['Pending', 'Confirmed'], true)) {
                throw new ConflictException('This booking can no longer be cancelled online (status: ' . $reservation['status'] . ').');
            }
            $start = BookingRules::startOf($reservation['reservation_date'], $reservation['start_time']);
            if ($start <= $now) {
                throw new ConflictException('This booking has already started. Please speak to our staff.');
            }
            $refundable = BookingRules::isFeeRefundable($start, $now);
            $this->reservations->markCancelled((int) $reservation['reservation_id'], $refundable, $now);
            $this->orderRepository->cancelOpenForReservation((int) $reservation['reservation_id']);
            $this->payments->failPendingForReservation((int) $reservation['reservation_id']);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        return $this->lookup($reference, $email);
    }

    /** @return array<string, mixed> */
    public function present(array $reservation): array
    {
        $id = (int) $reservation['reservation_id'];
        $items = [];
        foreach ($this->orderRepository->forReservation($id) as $row) {
            if ($row['item_id'] !== null && $row['order_status'] !== 'Cancelled') {
                $items[] = ['name' => $row['item_name'], 'quantity' => (int) $row['quantity'], 'subtotal' => Money::fromPesos((string) $row['subtotal'])];
            }
        }
        $fee = Money::fromPesos((string) $reservation['total_fee']);
        $food = $this->orderRepository->activeTotalForReservation($id);
        $paid = $this->payments->paidTotal($id);
        $start = BookingRules::startOf($reservation['reservation_date'], $reservation['start_time']);

        return [
            'reference_code' => $reservation['reference_code'],
            'status' => $reservation['status'],
            'payment_status' => $reservation['payment_status'],
            'table_id' => $reservation['table_id'],
            'table_type' => $reservation['type_name'],
            'reservation_date' => $reservation['reservation_date'],
            'start_time' => $reservation['start_time'],
            'end_time' => $reservation['end_time'],
            'display_when' => $start->format('l, F j, Y') . ' · ' . Config::displayTime($reservation['start_time']) . ' to ' . Config::displayTime($reservation['end_time']),
            'number_of_guests' => (int) $reservation['number_of_guests'],
            'name' => $reservation['first_name'] . ' ' . $reservation['last_name'],
            'total_fee' => $fee,
            'order_total' => $food,
            'amount_due' => $fee + $food,
            'amount_paid' => $paid,
            'balance' => max(0, $fee + $food - $paid),
            'minimum_order' => Money::fromPesos((string) $reservation['minimum_order']),
            'items' => $items,
            'fee_refundable' => $reservation['fee_refundable'] === null ? null : (bool) $reservation['fee_refundable'],
            'cancellable' => in_array($reservation['status'], ['Pending', 'Confirmed'], true) && $start > Config::now(),
            'refundable_if_cancelled_now' => $fee > 0 ? BookingRules::isFeeRefundable($start, Config::now()) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function findOwned(string $reference, string $email, bool $forUpdate = false): array
    {
        $reference = strtoupper(trim($reference));
        $reservation = preg_match('/^[A-Z0-9]{8}$/', $reference) ? $this->reservations->findByReference($reference, $forUpdate) : null;
        if ($reservation === null || !hash_equals(strtolower($reservation['email']), strtolower(trim($email)))) {
            throw new NotFoundException('We could not find a booking with that reference and email.');
        }
        return $reservation;
    }

    /**
     * Inserts the reservation, retrying with a new reference code on the (rare)
     * reference collision. A slot collision means someone else just took the table.
     *
     * @param array<string, mixed> $row
     * @return array{int, string}
     */
    private function insertWithUniqueReference(array $row): array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = self::newReference();
            $this->pdo->exec('SAVEPOINT reservation_insert');
            try {
                return [$this->reservations->insert(['reference_code' => $reference] + $row), $reference];
            } catch (PDOException $exception) {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT reservation_insert');
                $driverCode = (int) ($exception->errorInfo[1] ?? 0);
                $message = $exception->getMessage();
                if ($driverCode === self::MYSQL_DUPLICATE_KEY && str_contains($message, 'uq_table_slot')) {
                    throw new SlotTakenException();
                }
                if ($driverCode !== self::MYSQL_DUPLICATE_KEY || !str_contains($message, 'uq_reservation_ref')) {
                    throw $exception;
                }
            }
        }
        throw new RuntimeException('Could not generate a unique booking reference.');
    }

    private static function newReference(): string
    {
        $alphabet = self::REFERENCE_ALPHABET;
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $code;
    }
}
