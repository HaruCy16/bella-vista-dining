<?php
declare(strict_types=1);

final class Config
{
    public const TIMEZONE = 'Asia/Manila';

    /** ISO-8601 weekday the restaurant is closed (1 = Monday). */
    public const CLOSED_WEEKDAY = 1;
    public const OPENS_AT = '10:00';
    public const CLOSES_AT = '22:00';
    public const SLOT_HOURS = 2;
    public const GRACE_MINUTES = 15;
    public const CANCEL_NOTICE_HOURS = 24;

    /*
     * Restaurant location. SAMPLE DATA: the client spec gives no address, so these
     * point to a spot in Santo Tomas, Batangas for the demo. Replace with the real
     * address and coordinates before launch.
     */
    public const ADDRESS_LINES = ['Bella Vista Estate, Maharlika Highway', 'Brgy. San Roque, Santo Tomas, Batangas 4234'];
    public const LATITUDE = 14.0998613;
    public const LONGITUDE = 121.1580442;
    public const MAP_ZOOM = 17;

    /** @return list<string> */
    public static function reservationSlots(): array
    {
        return ['10:00:00', '12:00:00', '14:00:00', '16:00:00', '18:00:00', '20:00:00'];
    }

    public static function isReservationSlot(string $startTime): bool
    {
        return in_array($startTime, self::reservationSlots(), true);
    }

    public static function isOperatingDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, self::timezone());
        return $parsed !== false
            && $parsed->format('Y-m-d') === $date
            && (int) $parsed->format('N') !== self::CLOSED_WEEKDAY;
    }

    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone());
    }

    /**
     * Live open/closed state for the header and visit section.
     *
     * @return array{open: bool, label: string}
     */
    public static function openStatus(?DateTimeImmutable $at = null): array
    {
        $at = ($at ?? self::now())->setTimezone(self::timezone());
        $time = $at->format('H:i');
        $closedToday = (int) $at->format('N') === self::CLOSED_WEEKDAY;

        if (!$closedToday && $time >= self::OPENS_AT && $time < self::CLOSES_AT) {
            return ['open' => true, 'label' => 'Open now · until ' . self::displayTime(self::CLOSES_AT)];
        }
        if (!$closedToday && $time < self::OPENS_AT) {
            return ['open' => false, 'label' => 'Opens today at ' . self::displayTime(self::OPENS_AT)];
        }

        $next = self::nextOpenDates(1, $at->modify('+1 day'))[0];
        $day = $next->format('Y-m-d') === $at->modify('+1 day')->format('Y-m-d') ? 'tomorrow' : $next->format('l');
        return ['open' => false, 'label' => "Closed · opens {$day} at " . self::displayTime(self::OPENS_AT)];
    }

    /**
     * The next $count dates the restaurant is open, starting from $from (inclusive).
     *
     * @return list<DateTimeImmutable>
     */
    public static function nextOpenDates(int $count, ?DateTimeImmutable $from = null): array
    {
        $day = ($from ?? self::now())->setTimezone(self::timezone())->setTime(0, 0);
        $dates = [];
        while (count($dates) < $count) {
            if ((int) $day->format('N') !== self::CLOSED_WEEKDAY) {
                $dates[] = $day;
            }
            $day = $day->modify('+1 day');
        }
        return $dates;
    }

    public static function address(): string
    {
        return implode(', ', self::ADDRESS_LINES);
    }

    /**
     * Map iframe URL. Uses the Google Maps Embed API when GOOGLE_MAPS_EMBED_API_KEY
     * is set (the supported, free embed); otherwise Google's keyless embed so the
     * map still shows in development.
     */
    public static function mapEmbedUrl(): string
    {
        $coordinates = self::LATITUDE . ',' . self::LONGITUDE;
        $key = Env::get('GOOGLE_MAPS_EMBED_API_KEY');
        if ($key !== null) {
            return 'https://www.google.com/maps/embed/v1/place?' . http_build_query([
                'key' => $key, 'q' => $coordinates, 'zoom' => self::MAP_ZOOM, 'maptype' => 'roadmap',
            ]);
        }
        return 'https://maps.google.com/maps?' . http_build_query(['q' => $coordinates, 'z' => self::MAP_ZOOM, 'output' => 'embed']);
    }

    /** Opens turn-by-turn directions in Google Maps (official Maps URLs; no key needed). */
    public static function directionsUrl(): string
    {
        return 'https://www.google.com/maps/dir/?' . http_build_query(['api' => 1, 'destination' => self::LATITUDE . ',' . self::LONGITUDE]);
    }

    /** A two-hour slot as a range: "18:00:00" -> "6–8 PM", "10:00:00" -> "10 AM–12 PM". */
    public static function slotLabel(string $startTime): string
    {
        $start = DateTimeImmutable::createFromFormat('!H:i', substr($startTime, 0, 5), self::timezone());
        if ($start === false) {
            throw new InvalidArgumentException('Invalid time.');
        }
        $end = $start->modify('+' . self::SLOT_HOURS . ' hours');
        return $start->format('A') === $end->format('A')
            ? $start->format('g') . '–' . $end->format('g A')
            : $start->format('g A') . '–' . $end->format('g A');
    }

    /** "22:00" or "22:00:00" -> "10 PM"; "10:30" -> "10:30 AM". */
    public static function displayTime(string $time): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!H:i', substr($time, 0, 5), self::timezone());
        if ($parsed === false) {
            throw new InvalidArgumentException('Invalid time.');
        }
        return $parsed->format('i') === '00' ? $parsed->format('g A') : $parsed->format('g:i A');
    }
}
