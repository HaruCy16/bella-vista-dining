<?php
declare(strict_types=1);

/**
 * Date, time and cancellation rules. Pure functions of their inputs plus "now",
 * so tests can pin the clock. All times are Asia/Manila.
 */
final class BookingRules
{
    public const BOOKING_WINDOW_DAYS = 60;

    /**
     * Throws ValidationException unless $date/$startTime is a bookable slot at $now:
     * a real date, not a Monday, one of the configured slot starts, not in the past,
     * and within the booking window.
     */
    public static function assertBookableSlot(string $date, string $startTime, DateTimeImmutable $now): DateTimeImmutable
    {
        $now = $now->setTimezone(Config::timezone());
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, Config::timezone());
        if ($day === false || $day->format('Y-m-d') !== $date) {
            throw ValidationException::field('reservation_date', 'Choose a valid date.');
        }
        if (!Config::isOperatingDate($date)) {
            throw ValidationException::field('reservation_date', "We're closed on Mondays. Please choose Tuesday to Sunday.");
        }
        if (!Config::isReservationSlot($startTime)) {
            $slots = implode(', ', array_map([Config::class, 'displayTime'], Config::reservationSlots()));
            throw ValidationException::field('start_time', "Choose one of our seating times: {$slots}.");
        }

        $start = self::startOf($date, $startTime);
        if ($start <= $now) {
            throw ValidationException::field('start_time', 'That time has already passed. Please choose a later time.');
        }
        if ($day > $now->setTime(0, 0)->modify('+' . self::BOOKING_WINDOW_DAYS . ' days')) {
            throw ValidationException::field('reservation_date', 'Bookings open ' . self::BOOKING_WINDOW_DAYS . ' days ahead.');
        }
        return $start;
    }

    public static function startOf(string $date, string $startTime): DateTimeImmutable
    {
        return new DateTimeImmutable("{$date} {$startTime}", Config::timezone());
    }

    /**
     * Client spec VI.2: cancel at least 24 hours before. Fees are refundable only then;
     * a later cancellation, or one made on the reservation day itself, forfeits the fee.
     */
    public static function isFeeRefundable(DateTimeImmutable $start, DateTimeImmutable $cancelledAt): bool
    {
        $cancelledAt = $cancelledAt->setTimezone(Config::timezone());
        $sameDay = $cancelledAt->format('Y-m-d') === $start->format('Y-m-d');
        $secondsBefore = $start->getTimestamp() - $cancelledAt->getTimestamp();
        return !$sameDay && $secondsBefore >= Config::CANCEL_NOTICE_HOURS * 3600;
    }

    /** The table is released to walk-ins GRACE_MINUTES after the start time. */
    public static function graceEndsAt(DateTimeImmutable $start): DateTimeImmutable
    {
        return $start->modify('+' . Config::GRACE_MINUTES . ' minutes');
    }
}
