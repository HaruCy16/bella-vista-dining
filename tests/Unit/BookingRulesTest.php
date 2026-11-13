<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingRulesTest extends TestCase
{
    private static function manila(string $at): DateTimeImmutable
    {
        return new DateTimeImmutable($at, new DateTimeZone('Asia/Manila'));
    }

    /** @return array<string, array{string, string, string}> date, time, field that must fail */
    public static function invalidSlots(): array
    {
        return [
            'Monday is closed' => ['2026-11-23', '12:00:00', 'reservation_date'],
            '09:00 is before opening' => ['2026-11-18', '09:00:00', 'start_time'],
            '22:00 is closing time' => ['2026-11-18', '22:00:00', 'start_time'],
            '11:00 is not a slot start' => ['2026-11-18', '11:00:00', 'start_time'],
            '12:30 is not a slot start' => ['2026-11-18', '12:30:00', 'start_time'],
            'impossible date' => ['2026-02-30', '12:00:00', 'reservation_date'],
            'past date' => ['2026-11-15', '12:00:00', 'start_time'],
            'beyond the booking window' => ['2027-02-17', '12:00:00', 'reservation_date'],
        ];
    }

    #[DataProvider('invalidSlots')]
    public function testRejectsInvalidSlots(string $date, string $time, string $field): void
    {
        try {
            BookingRules::assertBookableSlot($date, $time, self::manila('2026-11-17 09:00'));
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($field, $exception->errors());
        }
    }

    public function testAcceptsEverySlotOnAnOpenDay(): void
    {
        foreach (['10:00:00', '12:00:00', '14:00:00', '16:00:00', '18:00:00', '20:00:00'] as $slot) {
            self::assertSame("2026-11-18 {$slot}", BookingRules::assertBookableSlot('2026-11-18', $slot, self::manila('2026-11-17 09:00'))->format('Y-m-d H:i:s'));
        }
    }

    public function testSlotEarlierTodayIsRejectedLaterTodayAccepted(): void
    {
        $now = self::manila('2026-11-17 13:05');
        BookingRules::assertBookableSlot('2026-11-17', '14:00:00', $now);
        $this->expectException(ValidationException::class);
        BookingRules::assertBookableSlot('2026-11-17', '12:00:00', $now);
    }

    public function testManilaBoundaryUsesManilaDateNotUtc(): void
    {
        // 15:30 UTC on 17 Nov is 23:30 on 17 Nov in Manila: "today" is the 17th and every slot today has passed.
        $now = new DateTimeImmutable('2026-11-17 15:30', new DateTimeZone('UTC'));
        BookingRules::assertBookableSlot('2026-11-18', '10:00:00', $now);
        $this->expectException(ValidationException::class);
        BookingRules::assertBookableSlot('2026-11-17', '20:00:00', $now);
    }

    /** @return array<string, array{string, string, bool}> start, cancelled at, refundable */
    public static function cancellations(): array
    {
        return [
            'three days before' => ['2026-11-20 18:00', '2026-11-17 18:00', true],
            'exactly 24 hours before, previous day' => ['2026-11-20 18:00', '2026-11-19 18:00', true],
            '23 hours before, previous day' => ['2026-11-21 10:00', '2026-11-20 11:00', false],
            'same day, morning of a dinner booking' => ['2026-11-20 20:00', '2026-11-20 08:00', false],
            'same day, one minute after midnight' => ['2026-11-20 20:00', '2026-11-20 00:01', false],
        ];
    }

    #[DataProvider('cancellations')]
    public function testFeeRefundability(string $start, string $cancelledAt, bool $refundable): void
    {
        self::assertSame($refundable, BookingRules::isFeeRefundable(self::manila($start), self::manila($cancelledAt)));
    }

    public function testGracePeriodIsFifteenMinutes(): void
    {
        self::assertSame('18:15', BookingRules::graceEndsAt(self::manila('2026-11-18 18:00'))->format('H:i'));
    }
}
