<?php
declare(strict_types=1);

/**
 * Collects every field error first, then throws once, so a form can show all
 * problems together.
 *
 *   $v = new Validator($input);
 *   $email = $v->email('email', 'Email');
 *   $v->validate();   // throws ValidationException with ['email' => '...']
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $input */
    public function __construct(private readonly array $input)
    {
    }

    public function string(string $field, string $label, int $max = 255, bool $required = true): string
    {
        $value = $this->input[$field] ?? '';
        if (!is_string($value) && !is_int($value)) {
            return $this->fail($field, "{$label} is invalid.", '');
        }
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ($value === '' && $required) {
            return $this->fail($field, "{$label} is required.", '');
        }
        if (mb_strlen($value) > $max) {
            return $this->fail($field, "{$label} must be {$max} characters or fewer.", $value);
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            return $this->fail($field, "{$label} contains invalid characters.", '');
        }
        return $value;
    }

    public function email(string $field, string $label = 'Email'): string
    {
        $value = $this->string($field, $label, 100);
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return $this->fail($field, 'Enter a valid email address.', $value);
        }
        return strtolower($value);
    }

    /** Philippine mobile numbers: 09XXXXXXXXX or +639XXXXXXXXX (spaces/dashes allowed). Returns 09XXXXXXXXX. */
    public function phone(string $field, string $label = 'Mobile number'): string
    {
        $value = $this->string($field, $label, 20);
        if ($value === '') {
            return '';
        }
        $digits = preg_replace('/[\s\-()]/', '', $value) ?? '';
        if (preg_match('/^\+639\d{9}$/', $digits)) {
            return '0' . substr($digits, 3);
        }
        if (preg_match('/^09\d{9}$/', $digits)) {
            return $digits;
        }
        return $this->fail($field, 'Enter a mobile number like 0917 123 4567.', $value);
    }

    public function int(string $field, string $label, int $min, int $max): int
    {
        $value = filter_var($this->input[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false) {
            return $this->fail($field, "{$label} is required.", 0);
        }
        if ($value < $min || $value > $max) {
            return $this->fail($field, "{$label} must be between {$min} and {$max}.", $value);
        }
        return $value;
    }

    /** Y-m-d calendar date. */
    public function date(string $field, string $label = 'Date'): string
    {
        $value = $this->input[$field] ?? '';
        $parsed = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, Config::timezone()) : false;
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            return $this->fail($field, "Choose a valid {$label}.", '');
        }
        return $value;
    }

    /** @param list<string> $allowed */
    public function in(string $field, string $label, array $allowed): string
    {
        $value = $this->input[$field] ?? '';
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            return $this->fail($field, "Choose a valid {$label}.", '');
        }
        return $value;
    }

    public function accepted(string $field, string $message): bool
    {
        $value = $this->input[$field] ?? false;
        if (!in_array($value, [true, 1, '1', 'on', 'yes'], true)) {
            $this->fail($field, $message, false);
            return false;
        }
        return true;
    }

    /**
     * Cart map {item_id: quantity}.
     *
     * @return array<int, int>
     */
    public function cart(string $field, int $maxLines = 30, int $maxQuantity = 50): array
    {
        $value = $this->input[$field] ?? [];
        if ($value === [] || $value === null) {
            return [];
        }
        if (!is_array($value) || count($value) > $maxLines) {
            return $this->fail($field, 'Your pre-order is invalid.', []);
        }
        $cart = [];
        foreach ($value as $itemId => $quantity) {
            $itemId = filter_var($itemId, FILTER_VALIDATE_INT);
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($itemId === false || $itemId < 1 || $quantity === false || $quantity < 1 || $quantity > $maxQuantity) {
                return $this->fail($field, "Each dish quantity must be between 1 and {$maxQuantity}.", []);
            }
            $cart[$itemId] = $quantity;
        }
        return $cart;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function validate(string $summary = 'Please check the highlighted fields.'): void
    {
        if ($this->errors !== []) {
            throw new ValidationException(count($this->errors) === 1 ? reset($this->errors) : $summary, $this->errors);
        }
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @template T
     * @param T $fallback
     * @return T
     */
    private function fail(string $field, string $message, mixed $fallback): mixed
    {
        $this->addError($field, $message);
        return $fallback;
    }
}
