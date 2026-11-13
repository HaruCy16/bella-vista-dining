<?php
declare(strict_types=1);

/**
 * Exceptions whose message is safe to show to the user. Response::api() maps each
 * one to its HTTP status. Anything else is logged and becomes a generic 500.
 */
abstract class AppException extends RuntimeException
{
    abstract public function status(): int;

    /** @return array<string, string> field => message, for form errors */
    public function errors(): array
    {
        return [];
    }
}

final class ValidationException extends AppException
{
    /** @param array<string, string> $fieldErrors */
    public function __construct(string $message, private readonly array $fieldErrors = [])
    {
        parent::__construct($message);
    }

    /** One-field shortcut: ValidationException::field('number_of_guests', 'Too many guests.') */
    public static function field(string $field, string $message): self
    {
        return new self($message, [$field => $message]);
    }

    public function status(): int { return 422; }
    public function errors(): array { return $this->fieldErrors; }
}

class ConflictException extends AppException
{
    public function status(): int { return 409; }
}

final class SlotTakenException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Sorry, that table was just booked for this time. Please choose another table or time.');
    }
}

final class NotFoundException extends AppException
{
    public function status(): int { return 404; }
}

final class AuthenticationException extends AppException
{
    public function __construct(string $message = 'Please sign in to continue.')
    {
        parent::__construct($message);
    }

    public function status(): int { return 401; }
}

final class AuthorizationException extends AppException
{
    public function __construct(string $message = 'Your role does not have access to this.')
    {
        parent::__construct($message);
    }

    public function status(): int { return 403; }
}

final class MethodNotAllowedException extends AppException
{
    public function __construct()
    {
        parent::__construct('Method not allowed.');
    }

    public function status(): int { return 405; }
}

final class CsrfException extends AppException
{
    public function __construct()
    {
        parent::__construct('Your session expired. Please refresh the page and try again.');
    }

    public function status(): int { return 419; }
}

final class TooManyRequestsException extends AppException
{
    public function status(): int { return 429; }
}
