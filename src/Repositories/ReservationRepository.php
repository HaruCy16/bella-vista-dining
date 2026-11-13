<?php
declare(strict_types=1);

final class ReservationRepository
{
    private const DETAIL_SELECT =
        'SELECT r.reservation_id, r.reference_code, r.table_id, r.reservation_date, r.start_time, r.end_time,
                r.number_of_guests, r.extra_fee, r.total_fee, r.payment_status, r.status, r.terms_accepted_at,
                r.cancelled_at, r.fee_refundable, r.created_at,
                c.customer_id, c.first_name, c.last_name, c.email, c.phone_number,
                t.type_name, t.minimum_order
         FROM reservations r
         INNER JOIN customers c ON c.customer_id = r.customer_id
         INNER JOIN dining_tables d ON d.table_id = r.table_id
         INNER JOIN table_types t ON t.table_type_id = d.table_type_id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Locks the table row for the rest of the transaction so two bookings for the
     * same table are serialized (the unique slot key is the final guard).
     *
     * @return array<string, mixed>|null
     */
    public function lockTableWithType(string $tableId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.table_id, t.type_name, t.capacity_min, t.capacity_max, t.base_capacity,
                    t.reservation_fee, t.minimum_order, t.extra_charge
             FROM dining_tables d
             INNER JOIN table_types t ON t.table_type_id = d.table_type_id
             WHERE d.table_id = :table_id AND d.is_active = 1
             FOR UPDATE'
        );
        $statement->execute(['table_id' => $tableId]);
        return $statement->fetch() ?: null;
    }

    /** @param array{first_name: string, last_name: string, email: string, phone_number: string} $customer */
    public function createCustomer(array $customer): int
    {
        $this->pdo->prepare(
            'INSERT INTO customers (first_name, last_name, email, phone_number)
             VALUES (:first_name, :last_name, :email, :phone_number)'
        )->execute($customer);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $reservation */
    public function insert(array $reservation): int
    {
        $this->pdo->prepare(
            'INSERT INTO reservations
               (reference_code, customer_id, table_id, reservation_date, start_time, end_time, number_of_guests,
                extra_fee, total_fee, payment_status, status, terms_accepted_at, created_at)
             VALUES
               (:reference_code, :customer_id, :table_id, :reservation_date, :start_time, :end_time, :number_of_guests,
                :extra_fee, :total_fee, :payment_status, :status, :terms_accepted_at, :created_at)'
        )->execute($reservation);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference, bool $forUpdate = false): ?array
    {
        $statement = $this->pdo->prepare(self::DETAIL_SELECT . ' WHERE r.reference_code = :reference' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['reference' => $reference]);
        return $statement->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $reservationId, bool $forUpdate = false): ?array
    {
        $statement = $this->pdo->prepare(self::DETAIL_SELECT . ' WHERE r.reservation_id = :id' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $reservationId]);
        return $statement->fetch() ?: null;
    }

    public function markCancelled(int $reservationId, bool $feeRefundable, DateTimeImmutable $at): void
    {
        $this->pdo->prepare(
            "UPDATE reservations
             SET status = 'Cancelled', cancelled_at = :at, fee_refundable = :refundable
             WHERE reservation_id = :id"
        )->execute(['id' => $reservationId, 'refundable' => (int) $feeRefundable, 'at' => $at->format('Y-m-d H:i:s')]);
    }

    public function updateStatus(int $reservationId, string $status): void
    {
        $this->pdo->prepare('UPDATE reservations SET status = :status WHERE reservation_id = :id')
            ->execute(['id' => $reservationId, 'status' => $status]);
    }

    public function updatePaymentStatus(int $reservationId, string $paymentStatus, ?string $status = null): void
    {
        $sql = 'UPDATE reservations SET payment_status = :payment_status' . ($status !== null ? ', status = :status' : '') . ' WHERE reservation_id = :id';
        $params = ['id' => $reservationId, 'payment_status' => $paymentStatus];
        if ($status !== null) {
            $params['status'] = $status;
        }
        $this->pdo->prepare($sql)->execute($params);
    }

    /** @return list<array<string, mixed>> live reservations for one day, for the admin board */
    public function forDate(string $date): array
    {
        $statement = $this->pdo->prepare(self::DETAIL_SELECT . " WHERE r.reservation_date = :date ORDER BY r.start_time, r.table_id");
        $statement->execute(['date' => $date]);
        return $statement->fetchAll();
    }
}
