<?php
declare(strict_types=1);

final class Money
{
    public static function fromPesos(string|int $amount): int
    {
        $normalized = trim((string) $amount);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException('Invalid peso amount.');
        }
        [$pesos, $centavos] = array_pad(explode('.', $normalized, 2), 2, '0');
        return ((int) $pesos * 100) + (int) str_pad($centavos, 2, '0');
    }

    public static function pesos(int $centavos): string
    {
        $sign = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);
        return $sign . intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Display format: 100000 -> "₱1,000", 32050 -> "₱320.50". Integer math only. */
    public static function format(int $centavos): string
    {
        $sign = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);
        $whole = number_format(intdiv($centavos, 100), 0, '.', ',');
        $cents = $centavos % 100;
        return $sign . '₱' . $whole . ($cents === 0 ? '' : '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT));
    }
}
