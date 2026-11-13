<?php
declare(strict_types=1);

final class Response
{
    /** @param array<string, mixed> $body */
    public static function json(array $body, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Runs a JSON endpoint: {"data": ...} on success, {"error": ..., "errors": {...}} on failure.
     * Only AppException messages reach the client; everything else is logged and hidden.
     */
    public static function api(callable $handler): void
    {
        try {
            $data = $handler();
            self::json(['data' => $data], 200);
        } catch (AppException $exception) {
            $body = ['error' => $exception->getMessage()];
            if ($exception->errors() !== []) {
                $body['errors'] = $exception->errors();
            }
            self::json($body, $exception->status());
        } catch (JsonException) {
            self::json(['error' => 'The request body is not valid JSON.'], 400);
        } catch (Throwable $exception) {
            error_log(sprintf('[%s] %s in %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()));
            self::json(['error' => 'Something went wrong on our side. Please try again.'], 500);
        }
    }

    public static function redirect(string $location, int $status = 303): never
    {
        header('Location: ' . $location, true, $status);
        exit;
    }
}
