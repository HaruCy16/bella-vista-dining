<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromPesosParsesToCentavos(): void
    {
        self::assertSame(100000, Money::fromPesos('1000.00'));
        self::assertSame(32050, Money::fromPesos('320.5'));
        self::assertSame(7, Money::fromPesos('0.07'));
    }

    public function testPesosIsExactWithoutFloats(): void
    {
        self::assertSame('1580.00', Money::pesos(158000));
        self::assertSame('0.07', Money::pesos(7));
        self::assertSame('-3.40', Money::pesos(-340));
        // A value that float division would print imprecisely at higher precision.
        self::assertSame('90071992547409.93', Money::pesos(9007199254740993));
    }

    public function testFormatForDisplay(): void
    {
        self::assertSame('₱1,000', Money::format(100000));
        self::assertSame('₱3,400', Money::format(340000));
        self::assertSame('₱320.50', Money::format(32050));
        self::assertSame('₱0', Money::format(0));
    }
}
