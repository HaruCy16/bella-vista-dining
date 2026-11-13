<?php
declare(strict_types=1);

final class PaymentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array{reservation_id: int, payment_type: string, amount: string, method: string, provider_reference: string} $payment */
    public function insertPending(array $payment): int
    {
        $this->pdo->prepare(
            "INSERT INTO payments (reservation_id, payment_type, amount, method, provider_reference, payment_state)
             VALUES (:reservation_id, :payment_type, :amount, :method, :provider_reference, 'Pending')"
        )->execute($payment);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByProviderReference(string $reference, bool $forUpdate = false): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM payments WHERE provider_reference = :reference' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['reference' => $reference]);
        return $statement->fetch() ?: null;
    }

    public function setState(int $paymentId, string $state): void
    {
        $this->pdo->prepare('UPDATE payments SET payment_state = :state WHERE payment_id = :id')
            ->execute(['id' => $paymentId, 'state' => $state]);
    }

    public function attachProviderReference(int $paymentId, string $reference): void
    {
        $this->pdo->prepare('UPDATE payments SET provider_reference = :reference WHERE payment_id = :id')
            ->execute(['id' => $paymentId, 'reference' => $reference]);
    }

    /** Sum of succeeded payments for a reservation, in centavos. */
    public function paidTotal(int $reservationId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE reservation_id = :id AND payment_state = 'Succeeded'"
        );
        $statement->execute(['id' => $reservationId]);
        return Money::fromPesos((string) $statement->fetchColumn());
    }

    /** @return list<array<string, mixed>> */
    public function forReservation(int $reservationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT payment_id, payment_type, amount, method, payment_state, created_at
             FROM payments WHERE reservation_id = :id ORDER BY payment_id'
        );
        $statement->execute(['id' => $reservationId]);
        return $statement->fetchAll();
    }

    public function failPendingForReservation(int $reservationId): void
    {
        $this->pdo->prepare("UPDATE payments SET payment_state = 'Failed' WHERE reservation_id = :id AND payment_state = 'Pending'")
            ->execute(['id' => $reservationId]);
    }
}
