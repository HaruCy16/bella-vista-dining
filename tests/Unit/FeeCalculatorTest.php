<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeeCalculatorTest extends TestCase
{
    private const TYPES = [
        'Standard' => ['type_name' => 'Standard', 'capacity_min' => 2, 'capacity_max' => 4, 'base_capacity' => 4, 'reservation_fee' => '0.00', 'extra_charge' => '0.00'],
        'Family' => ['type_name' => 'Family', 'capacity_min' => 5, 'capacity_max' => 8, 'base_capacity' => 8, 'reservation_fee' => '500.00', 'extra_charge' => '0.00'],
        'VIP' => ['type_name' => 'VIP', 'capacity_min' => 9, 'capacity_max' => 20, 'base_capacity' => 15, 'reservation_fee' => '2500.00', 'extra_charge' => '300.00'],
    ];

    /** @return array<string, array{string, int, int}> */
    public static function fees(): array
    {
        return [
            'Standard, 2 guests, no fee' => ['Standard', 2, 0],
            'Standard, 4 guests, no fee' => ['Standard', 4, 0],
            'Family, 5 guests' => ['Family', 5, 50000],
            'Family, 8 guests' => ['Family', 8, 50000],
            'VIP, 15 guests, base fee only' => ['VIP', 15, 250000],
            'VIP, 16 guests, one extra' => ['VIP', 16, 280000],
            'VIP, 18 guests = ₱3,400 (spec example)' => ['VIP', 18, 340000],
            'VIP, 20 guests, maximum' => ['VIP', 20, 400000],
        ];
    }

    #[DataProvider('fees')]
    public function testQuote(string $type, int $guests, int $expectedCentavos): void
    {
        self::assertSame($expectedCentavos, (new FeeCalculator())->quote(self::TYPES[$type], $guests)['total_fee']);
    }

    public function testVipBreakdown(): void
    {
        self::assertSame(
            ['base_fee' => 250000, 'extra_guests' => 3, 'extra_fee' => 90000, 'total_fee' => 340000],
            (new FeeCalculator())->quote(self::TYPES['VIP'], 18)
        );
    }

    /** @return array<string, array{string, int}> */
    public static function outOfRange(): array
    {
        return [
            'Standard, 1 guest (spec: 2 to 4)' => ['Standard', 1],
            'Standard, 5 guests' => ['Standard', 5],
            'Family, 4 guests' => ['Family', 4],
            'Family, 16 guests' => ['Family', 16],
            'VIP, 21 guests (max 20)' => ['VIP', 21],
        ];
    }

    #[DataProvider('outOfRange')]
    public function testRejectsGuestsOutsideCapacity(string $type, int $guests): void
    {
        try {
            (new FeeCalculator())->quote(self::TYPES[$type], $guests);
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('number_of_guests', $exception->errors());
            self::assertSame(422, $exception->status());
        }
    }
}
