<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testCollectsAllErrorsBeforeThrowing(): void
    {
        $v = new Validator(['email' => 'nope', 'phone_number' => '123', 'number_of_guests' => 'x']);
        $v->email('email');
        $v->phone('phone_number');
        $v->int('number_of_guests', 'Guests', 1, 20);
        $v->string('first_name', 'First name');
        try {
            $v->validate();
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(['email', 'phone_number', 'number_of_guests', 'first_name'], array_keys($exception->errors()));
        }
    }

    public function testNormalisesPhilippineMobileNumbers(): void
    {
        foreach (['09171234567', '0917 123 4567', '0917-123-4567', '+639171234567', '+63 917 123 4567'] as $input) {
            self::assertSame('09171234567', (new Validator(['p' => $input]))->phone('p'));
        }
    }

    public function testRejectsBadPhoneNumbers(): void
    {
        foreach (['abc', '12345', '0817 123 4567', '+1 415 555 0100'] as $input) {
            $v = new Validator(['p' => $input]);
            $v->phone('p');
            self::assertArrayHasKey('p', $v->errors(), $input);
        }
    }

    public function testStringsAreTrimmedAndLimited(): void
    {
        $v = new Validator(['name' => "  Ana   Maria \n ", 'long' => str_repeat('a', 51), 'ctrl' => "a\x07b", 'arr' => ['x']]);
        self::assertSame('Ana Maria', $v->string('name', 'Name', 50));
        $v->string('long', 'Long', 50);
        $v->string('ctrl', 'Ctrl', 50);
        $v->string('arr', 'Arr', 50);
        self::assertSame(['long', 'ctrl', 'arr'], array_keys($v->errors()));
    }

    public function testCartAcceptsOnlyPositiveIntegerQuantities(): void
    {
        self::assertSame([21 => 2, 41 => 1], (new Validator(['cart' => ['21' => '2', '41' => 1]]))->cart('cart'));
        foreach ([['21' => 0], ['21' => -1], ['21' => 51], ['abc' => 1], ['21' => 1.5], 'not-a-map'] as $bad) {
            $v = new Validator(['cart' => $bad]);
            $v->cart('cart');
            self::assertArrayHasKey('cart', $v->errors(), json_encode($bad));
        }
    }

    public function testTermsMustBeExplicitlyAccepted(): void
    {
        foreach ([true, 1, '1', 'on'] as $yes) {
            self::assertTrue((new Validator(['t' => $yes]))->accepted('t', 'Accept'));
        }
        foreach ([false, 0, '0', '', null, 'false'] as $no) {
            self::assertFalse((new Validator(['t' => $no]))->accepted('t', 'Accept'));
        }
    }
}
