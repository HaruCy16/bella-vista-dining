<?php
declare(strict_types=1);

/**
 * Reservation fee for a table type and party size, in centavos.
 *
 * Rules (client spec, section III), read from table_types so nothing is hard-coded:
 *   Standard: no fee.            Family: ₱500.
 *   VIP: ₱2,500 covers base_capacity (15) guests, plus extra_charge (₱300) per guest above it, max 20.
 *   Example: VIP for 18 guests = 2,500 + 3 × 300 = ₱3,400.
 * Guests outside capacity_min..capacity_max are rejected.
 */
final class FeeCalculator
{
    /**
     * @param array{type_name: string, capacity_min: int|string, capacity_max: int|string, base_capacity: int|string,
     *              reservation_fee: string, extra_charge: string} $tableType
     * @return array{base_fee: int, extra_guests: int, extra_fee: int, total_fee: int}
     */
    public function quote(array $tableType, int $guests): array
    {
        $min = (int) $tableType['capacity_min'];
        $max = (int) $tableType['capacity_max'];
        if ($guests < $min || $guests > $max) {
            throw ValidationException::field(
                'number_of_guests',
                sprintf('A %s table seats %d to %d guests.', $tableType['type_name'], $min, $max)
            );
        }

        $baseFee = Money::fromPesos((string) $tableType['reservation_fee']);
        $extraGuests = max(0, $guests - (int) $tableType['base_capacity']);
        $extraFee = $extraGuests * Money::fromPesos((string) $tableType['extra_charge']);

        return [
            'base_fee' => $baseFee,
            'extra_guests' => $extraGuests,
            'extra_fee' => $extraFee,
            'total_fee' => $baseFee + $extraFee,
        ];
    }
}
