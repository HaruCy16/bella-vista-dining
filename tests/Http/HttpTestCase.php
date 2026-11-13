<?php
declare(strict_types=1);

/**
 * End-to-end HTTP tests: real requests, real sessions/cookies, real CSRF, against
 * PHP's built-in server pointed at bella_vista_test (never the dev database).
 * The server starts once per test run with 4 workers so concurrency tests are real.
 */
abstract class HttpTestCase extends DatabaseTestCase
{
    protected const BASE = 'http://127.0.0.1:8099';
    /** @var resource|null */
    private static $server = null;
    private string $cookieJar;

    protected function setUp(): void
    {
        parent::setUp();
        self::startServer();
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'bv-cookies-');
    }

    protected function tearDown(): void
    {
        @unlink($this->cookieJar);
        parent::tearDown();
    }

    private static function startServer(): void
    {
        if (self::$server !== null) {
            return;
        }
        $env = getenv() + ['DB_NAME' => 'bella_vista_test'];
        $env['DB_NAME'] = 'bella_vista_test';
        $env['APP_ENV'] = 'testing';
        $env['PHP_CLI_SERVER_WORKERS'] = '4';
        $root = dirname(__DIR__, 2);
        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:8099', '-t', $root],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', sys_get_temp_dir() . '/bv-test-server.log', 'a']],
            $pipes,
            $root,
            $env
        );
        register_shutdown_function(static function (): void {
            if (is_resource(self::$server)) {
                proc_terminate(self::$server);
            }
        });
        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', 8099)) {
                return;
            }
            usleep(100_000);
        }
        self::fail('Test server did not start.');
    }

    /** @return array{status: int, body: array<string, mixed>|null, raw: string, headers: string} */
    protected function request(string $method, string $path, ?array $json = null, array $headers = [], ?string $rawBody = null): array
    {
        $curl = curl_init(self::BASE . $path);
        $sendHeaders = array_merge(['Accept: application/json'], $headers);
        if ($json !== null || $rawBody !== null) {
            $sendHeaders[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, $rawBody ?? json_encode($json));
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $sendHeaders,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = (string) curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $raw = substr($response, $headerSize);
        return ['status' => $status, 'body' => json_decode($raw, true), 'raw' => $raw, 'headers' => substr($response, 0, $headerSize)];
    }

    /** Loads a page in this "browser" and returns its CSRF token. */
    protected function csrf(string $page = '/index.php'): string
    {
        $html = $this->request('GET', $page, headers: ['Accept: text/html'])['raw'];
        self::assertMatchesRegularExpression('/name="csrf-token" content="([a-f0-9]{64})"/', $html);
        preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $html, $match);
        return $match[1];
    }

    protected function postJson(string $path, array $json, ?string $csrf = null): array
    {
        return $this->request('POST', $path, $json, $csrf === null ? [] : ["X-CSRF-Token: {$csrf}"]);
    }

    /** Signs in through the real login form. Returns the dashboard CSRF token. */
    protected function signIn(string $username, string $password = 'correct-horse-battery'): string
    {
        $loginCsrf = $this->hiddenCsrf('/admin/login.php');
        $curl = curl_init(self::BASE . '/admin/login.php');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['_csrf' => $loginCsrf, 'username' => $username, 'password' => $password]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
        ]);
        curl_exec($curl);
        self::assertSame(303, curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'Sign-in should redirect to the dashboard.');
        unset($curl); // since PHP 8 the cookie jar is written when the handle is destroyed, not on curl_close()
        return $this->csrf('/admin/index.php');
    }

    protected function hiddenCsrf(string $page): string
    {
        $html = $this->request('GET', $page, headers: ['Accept: text/html'])['raw'];
        preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $html, $match);
        self::assertNotEmpty($match, "No CSRF field on {$page}");
        return $match[1];
    }

    /** A booking request for a date that is always in the future relative to the real clock. */
    protected static function liveBookingInput(array $overrides = []): array
    {
        $date = Config::nextOpenDates(1, Config::now()->modify('+3 days'))[0]->format('Y-m-d');
        return $overrides + ['action' => 'create', 'reservation_date' => $date] + self::bookingInput();
    }
}
