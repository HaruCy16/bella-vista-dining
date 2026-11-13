<?php
declare(strict_types=1);

/**
 * Time-based state changes. There is no cron on shared hosting, so this runs
 * lazily (on availability checks and on every admin dashboard refresh). Every
 * step is an idempotent UPDATE, so running it often is safe.
 *
 *  1. Online bookings left unpaid for HOLD_MINUTES are released (Cancelled), so
 *     an abandoned checkout does not block the table.
 *  2. Bookings not checked in GRACE_MINUTES after their start become No-show
 *     (client spec VI.1); the slot frees up and their unstarted pre-orders are cancelled.
 *  3. Scheduled pre-orders go to the kitchen queue (Pending) RELEASE_LEAD_MINUTES
 *     before their reservation starts, never earlier, so future orders stay out of the queue.
 */
final class HousekeepingService
{
    public const RELEASE_LEAD_MINUTES = 60;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{expired_unpaid: int, no_shows: int, released_orders: int} */
    public function run(?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? Config::now())->setTimezone(Config::timezone());
        $stamp = $now->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $expired = $this->pdo->prepare(
                "UPDATE reservations SET status = 'Cancelled', cancelled_at = :now, fee_refundable = 1
                 WHERE status = 'Pending' AND payment_status = 'Unpaid' AND created_at < :cutoff"
            );
            $expired->execute(['now' => $stamp, 'cutoff' => $now->modify('-' . PaymentService::HOLD_MINUTES . ' minutes')->format('Y-m-d H:i:s')]);

            $noShows = $this->pdo->prepare(
                "UPDATE reservations SET status = 'No-show'
                 WHERE status IN ('Pending', 'Confirmed')
                   AND TIMESTAMP(reservation_date, start_time) < :grace_cutoff"
            );
            $noShows->execute(['grace_cutoff' => $now->modify('-' . Config::GRACE_MINUTES . ' minutes')->format('Y-m-d H:i:s')]);

            // Orders of bookings that are no longer coming are not cooked.
            $this->pdo->exec(
                "UPDATE orders o INNER JOIN reservations r ON r.reservation_id = o.reservation_id
                 SET o.order_status = 'Cancelled'
                 WHERE r.status IN ('Cancelled', 'No-show') AND o.order_status IN ('Scheduled', 'Pending')"
            );
            $this->pdo->exec(
                "UPDATE payments p INNER JOIN reservations r ON r.reservation_id = p.reservation_id
                 SET p.payment_state = 'Failed'
                 WHERE r.status IN ('Cancelled', 'No-show') AND p.payment_state = 'Pending'"
            );

            $released = (new OrderRepository($this->pdo))->releaseScheduled($now, self::RELEASE_LEAD_MINUTES);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return ['expired_unpaid' => $expired->rowCount(), 'no_shows' => $noShows->rowCount(), 'released_orders' => $released];
    }
}
