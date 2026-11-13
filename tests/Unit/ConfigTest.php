<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private static function manila(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('Asia/Manila'));
    }

    /** @return array<string, array{string, bool, string}> */
    public static function statusCases(): array
    {
        // 2026-11-17 is a Tuesday, 2026-11-16 a Monday, 2026-11-15 a Sunday.
        return [
            'before opening' => ['2026-11-17 08:30', false, 'Opens today at 10 AM'],
            'at opening' => ['2026-11-17 10:00', true, 'Open now · until 10 PM'],
            'last minute' => ['2026-11-17 21:59', true, 'Open now · until 10 PM'],
            'at closing' => ['2026-11-17 22:00', false, 'Closed · opens tomorrow at 10 AM'],
            'Sunday night skips Monday' => ['2026-11-15 22:30', false, 'Closed · opens Tuesday at 10 AM'],
            'Monday midday' => ['2026-11-16 12:00', false, 'Closed · opens tomorrow at 10 AM'],
        ];
    }

    #[DataProvider('statusCases')]
    public function testOpenStatus(string $at, bool $open, string $label): void
    {
        self::assertSame(['open' => $open, 'label' => $label], Config::openStatus(self::manila($at)));
    }

    public function testOpenStatusUsesManilaTimeForUtcInput(): void
    {
        // 2026-11-17 02:30 UTC = 10:30 in Manila (UTC+8): open.
        $utc = new DateTimeImmutable('2026-11-17 02:30', new DateTimeZone('UTC'));
        self::assertTrue(Config::openStatus($utc)['open']);
    }

    public function testNextOpenDatesSkipMondays(): void
    {
        $dates = array_map(
            static fn (DateTimeImmutable $d): string => $d->format('D Y-m-d'),
            Config::nextOpenDates(3, self::manila('2026-11-15 12:00'))
        );
        self::assertSame(['Sun 2026-11-15', 'Tue 2026-11-17', 'Wed 2026-11-18'], $dates);
    }

    public function testDisplayTime(): void
    {
        self::assertSame('10 AM', Config::displayTime('10:00:00'));
        self::assertSame('10 PM', Config::displayTime('22:00'));
        self::assertSame('12 PM', Config::displayTime('12:00:00'));
        self::assertSame('6:30 PM', Config::displayTime('18:30'));
    }

    public function testIsOperatingDateRejectsMondaysAndBadDates(): void
    {
        self::assertFalse(Config::isOperatingDate('2026-11-16'));
        self::assertTrue(Config::isOperatingDate('2026-11-17'));
        self::assertFalse(Config::isOperatingDate('2026-02-30'));
    }

    public function testSlotLabelShowsTheTwoHourRange(): void
    {
        self::assertSame('10 AM–12 PM', Config::slotLabel('10:00:00'));
        self::assertSame('12–2 PM', Config::slotLabel('12:00:00'));
        self::assertSame('6–8 PM', Config::slotLabel('18:00'));
        self::assertSame('8–10 PM', Config::slotLabel('20:00:00'));
    }
}
