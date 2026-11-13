<?php
declare(strict_types=1);

final class AvailabilityService
{
    public function __construct(private readonly AvailabilityRepository $repository)
    {
    }

    /**
     * Tables free in a slot, optionally only those that seat $guests.
     *
     * @return list<array<string, mixed>>
     */
    public function forSlot(string $date, string $startTime, ?int $guests = null, ?DateTimeImmutable $now = null): array
    {
        BookingRules::assertBookableSlot($date, $startTime, $now ?? Config::now());
        $tables = $this->repository->tablesForSlot($date, $startTime);
        if ($guests !== null) {
            $tables = array_values(array_filter(
                $tables,
                static fn (array $table): bool => $guests >= (int) $table['capacity_min'] && $guests <= (int) $table['capacity_max']
            ));
        }
        return array_map(static fn (array $table): array => [
            'table_id' => $table['table_id'],
            'type_name' => $table['type_name'],
            'capacity_min' => (int) $table['capacity_min'],
            'capacity_max' => (int) $table['capacity_max'],
            'base_capacity' => (int) $table['base_capacity'],
            'reservation_fee' => Money::fromPesos((string) $table['reservation_fee']),
            'extra_charge' => Money::fromPesos((string) $table['extra_charge']),
            'minimum_order' => Money::fromPesos((string) $table['minimum_order']),
        ], $tables);
    }
}
