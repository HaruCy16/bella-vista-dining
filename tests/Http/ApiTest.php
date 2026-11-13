<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

#[Group('http')]
final class ApiTest extends HttpTestCase
{
    /* ---------- Public booking API ---------- */

    public function testAvailabilityListsTablesAndFiltersByGuests(): void
    {
        $date = self::liveBookingInput()['reservation_date'];
        $all = $this->request('GET', "/public/api/availability.php?date={$date}&start_time=12:00:00");
        self::assertSame(200, $all['status']);
        self::assertCount(9, $all['body']['data']);
        $vip = $this->request('GET', "/public/api/availability.php?date={$date}&start_time=12:00:00&guests=18")['body']['data'];
        self::assertSame(['VIP1', 'VIP2'], array_column($vip, 'table_id'));
        self::assertSame(250000, $vip[0]['reservation_fee']);
    }

    public function testAvailabilityRejectsMondayWithFieldError(): void
    {
        $monday = (new DateTimeImmutable('next monday', Config::timezone()))->format('Y-m-d');
        $response = $this->request('GET', "/public/api/availability.php?date={$monday}&start_time=12:00:00");
        self::assertSame(422, $response['status']);
        self::assertArrayHasKey('reservation_date', $response['body']['errors']);
    }

    public function testStateChangingRequestsNeedCsrf(): void
    {
        self::assertSame(419, $this->postJson('/public/api/reservations.php', self::liveBookingInput())['status']);
        self::assertSame(419, $this->postJson('/public/api/reservations.php', self::liveBookingInput(), str_repeat('a', 64))['status']);
        self::assertSame(0, (int) $this->value('SELECT COUNT(*) FROM reservations'));
    }

    public function testBookLookupAndCancelOverHttp(): void
    {
        $csrf = $this->csrf();
        $booked = $this->postJson('/public/api/reservations.php', self::liveBookingInput(['cart' => ['21' => 2]]), $csrf);
        self::assertSame(200, $booked['status'], $booked['raw']);
        $reference = $booked['body']['data']['reference_code'];
        self::assertSame(166000, $booked['body']['data']['amount_due']);   // ₱500 fee + 2 × ₱580
        self::assertArrayNotHasKey('reservation_id', $booked['body']['data'], 'Internal ids are not exposed.');

        // Same browser: the session remembers the booking, no email needed.
        $lookup = $this->postJson('/public/api/reservations.php', ['action' => 'lookup', 'reference_code' => $reference], $csrf);
        self::assertSame(200, $lookup['status']);
        self::assertSame([['name' => 'Beef Kare-Kare', 'quantity' => 2, 'subtotal' => 116000]], $lookup['body']['data']['items']);

        $cancel = $this->postJson('/public/api/reservations.php', ['action' => 'cancel', 'reference_code' => $reference, 'email' => 'ana@example.test'], $csrf);
        self::assertSame(200, $cancel['status']);
        self::assertSame('Cancelled', $cancel['body']['data']['status']);
    }

    public function testSameSlotTwiceReturns409(): void
    {
        $csrf = $this->csrf();
        self::assertSame(200, $this->postJson('/public/api/reservations.php', self::liveBookingInput(), $csrf)['status']);
        $second = $this->postJson('/public/api/reservations.php', self::liveBookingInput(['email' => 'b@example.test']), $csrf);
        self::assertSame(409, $second['status']);
        self::assertStringContainsString('just booked', $second['body']['error']);
    }

    public function testConcurrentBookingsForOneSlotLetExactlyOneThrough(): void
    {
        $csrf = $this->csrf();
        $cookies = file_get_contents((new ReflectionProperty(HttpTestCase::class, 'cookieJar'))->getValue($this));
        $multi = curl_multi_init();
        $handles = [];
        for ($i = 0; $i < 6; $i++) {
            $curl = curl_init(self::BASE . '/public/api/reservations.php');
            $jar = tempnam(sys_get_temp_dir(), 'bv-c');
            file_put_contents($jar, $cookies);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(self::liveBookingInput(['email' => "guest{$i}@example.test"])),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', "X-CSRF-Token: {$csrf}"],
                CURLOPT_COOKIEFILE => $jar,
                CURLOPT_RETURNTRANSFER => true,
            ]);
            curl_multi_add_handle($multi, $curl);
            $handles[] = $curl;
        }
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi);
        } while ($running > 0);
        $codes = array_map(static fn ($h) => curl_getinfo($h, CURLINFO_RESPONSE_CODE), $handles);
        sort($codes);
        self::assertSame([200, 409, 409, 409, 409, 409], $codes);
        self::assertSame(1, (int) $this->value('SELECT COUNT(*) FROM reservations'));
    }

    public function testValidationErrorsComeBackPerField(): void
    {
        $response = $this->postJson('/public/api/reservations.php', self::liveBookingInput(['email' => 'x', 'phone_number' => '1', 'terms_accepted' => false]), $this->csrf());
        self::assertSame(422, $response['status']);
        self::assertEqualsCanonicalizing(['email', 'phone_number', 'terms_accepted'], array_keys($response['body']['errors']));
    }

    public function testOnlineDepositThroughMockCheckout(): void
    {
        $csrf = $this->csrf();
        $booked = $this->postJson('/public/api/reservations.php', self::liveBookingInput(['payment_option' => 'deposit', 'cart' => ['23' => 1]]), $csrf)['body']['data'];
        self::assertSame(57500, $booked['charge']);
        self::assertSame('Pending', $booked['status']);
        parse_str((string) parse_url($booked['checkout_url'], PHP_URL_QUERY), $query);

        $checkout = $this->request('GET', $booked['checkout_url'], headers: ['Accept: text/html']);
        self::assertSame(200, $checkout['status']);
        self::assertStringContainsString('₱575', $checkout['raw']);

        $paid = $this->postJson('/public/api/payments.php', ['action' => 'mock-complete', 'session' => $query['session'], 'outcome' => 'success'], $csrf);
        self::assertSame(200, $paid['status'], $paid['raw']);
        self::assertStringContainsString('payment=success', $paid['body']['data']['redirect']);
        $replay = $this->postJson('/public/api/payments.php', ['action' => 'mock-complete', 'session' => $query['session'], 'outcome' => 'success'], $csrf);
        self::assertFalse($replay['body']['data']['changed']);
        self::assertSame(['Confirmed', 'Downpayment'], array_values($this->pdo->query('SELECT status, payment_status FROM reservations')->fetch()));
    }

    public function testCartEndpointPricesOnTheServer(): void
    {
        $response = $this->postJson('/public/api/cart.php', ['cart' => ['11' => 4], 'table_id' => 'S1', 'total' => 1], $this->csrf());
        self::assertSame(200, $response['status']);
        self::assertSame([88000, 100000, false], [$response['body']['data']['total_centavos'], $response['body']['data']['minimum_order'], $response['body']['data']['minimum_met']]);
    }

    /* ---------- Hardening ---------- */

    public function testInjectionAttemptsAreHandledAsNotFound(): void
    {
        $csrf = $this->csrf();
        foreach (["' OR '1'='1", 'ABC" OR 1=1 --', 'X; DROP TABLE reservations'] as $evil) {
            $response = $this->postJson('/public/api/reservations.php', ['action' => 'lookup', 'reference_code' => $evil, 'email' => "a@b.co' OR 1=1 --"], $csrf);
            self::assertSame(404, $response['status'], $evil);
        }
        self::assertSame(9, (int) $this->value('SELECT COUNT(*) FROM dining_tables'));
    }

    public function testScriptInNamesIsStoredAsTextAndEscapedOnPages(): void
    {
        $csrf = $this->csrf();
        $booking = $this->postJson('/public/api/reservations.php', self::liveBookingInput(['first_name' => '<script>alert(1)</script>']), $csrf)['body']['data'];
        $page = $this->request('GET', '/public/booking.php?ref=' . $booking['reference_code'], headers: ['Accept: text/html'])['raw'];
        self::assertStringNotContainsString('<script>alert(1)</script>', $page);
        self::assertStringContainsString('<script>', $page); // JSON_HEX_TAG in the embedded booking data
    }

    public function testMalformedRequestsGetClearErrorsNotStackTraces(): void
    {
        $csrf = $this->csrf();
        $badJson = $this->request('POST', '/public/api/reservations.php', headers: ["X-CSRF-Token: {$csrf}"], rawBody: '{"action": ');
        self::assertSame(400, $badJson['status']);
        $wrongMethod = $this->request('GET', '/public/api/reservations.php');
        self::assertSame(405, $wrongMethod['status']);
        $list = $this->request('POST', '/public/api/reservations.php', headers: ["X-CSRF-Token: {$csrf}"], rawBody: '[1,2]');
        self::assertSame(422, $list['status']);
        foreach ([$badJson, $wrongMethod, $list] as $response) {
            self::assertStringNotContainsString('.php', $response['raw']);
            self::assertStringNotContainsString('Stack trace', $response['raw']);
        }
    }

    public function testMockCheckoutRejectsForeignReferences(): void
    {
        $response = $this->postJson('/public/api/payments.php', ['action' => 'mock-complete', 'session' => 'pi_real_stripe_like', 'outcome' => 'success'], $this->csrf());
        self::assertSame(404, $response['status']);
    }

    /* ---------- Admin API and roles ---------- */

    public function testAdminApiRequiresSignIn(): void
    {
        self::assertSame(401, $this->request('GET', '/admin/api.php?action=dashboard')['status']);
        $page = $this->request('GET', '/admin/index.php', headers: ['Accept: text/html']);
        self::assertSame(303, $page['status']);
        self::assertStringContainsString('Location: /admin/login.php', $page['headers']);
    }

    public function testKitchenStaffCannotSeeReports(): void
    {
        $this->createAdmin('kitchen', 'Kitchen Staff');
        $this->signIn('kitchen');
        $dashboard = $this->request('GET', '/admin/api.php?action=dashboard');
        self::assertSame(200, $dashboard['status']);
        self::assertNull($dashboard['body']['data']['report']);
        self::assertSame(403, $this->request('GET', '/admin/api.php?action=report&date=2026-11-18')['status']);
    }

    public function testManagerSeesReportAndCanRunTheFloor(): void
    {
        $this->createAdmin('manager', 'Manager');
        $csrf = $this->signIn('manager');
        self::assertSame(200, $this->request('GET', '/admin/api.php?action=report&date=2026-11-18')['status']);
        $update = $this->postJson('/admin/api.php', ['action' => 'table-status', 'table_id' => 'S2', 'status' => 'Clean-up'], $csrf);
        self::assertSame(200, $update['status']);
        self::assertSame('Clean-up', $this->value("SELECT status FROM dining_tables WHERE table_id = 'S2'"));
        self::assertSame(422, $this->postJson('/admin/api.php', ['action' => 'table-status', 'table_id' => 'S2', 'status' => "Clean-up'; --"], $csrf)['status']);
        self::assertSame(419, $this->postJson('/admin/api.php', ['action' => 'table-status', 'table_id' => 'S2', 'status' => 'Available'])['status']);
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        $this->createAdmin('manager', 'Manager');
        for ($i = 0; $i < AdminAuthService::MAX_FAILURES; $i++) {
            $csrf = $this->hiddenCsrf('/admin/login.php');
            $curl = curl_init(self::BASE . '/admin/login.php');
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => http_build_query(['_csrf' => $csrf, 'username' => 'manager', 'password' => 'nope']),
                CURLOPT_COOKIEFILE => (new ReflectionProperty(HttpTestCase::class, 'cookieJar'))->getValue($this),
                CURLOPT_COOKIEJAR => (new ReflectionProperty(HttpTestCase::class, 'cookieJar'))->getValue($this)]);
            curl_exec($curl);
            self::assertSame(401, curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
            curl_close($curl);
        }
        $csrf = $this->hiddenCsrf('/admin/login.php');
        $curl = curl_init(self::BASE . '/admin/login.php');
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query(['_csrf' => $csrf, 'username' => 'manager', 'password' => 'correct-horse-battery']),
            CURLOPT_COOKIEFILE => (new ReflectionProperty(HttpTestCase::class, 'cookieJar'))->getValue($this)]);
        $html = (string) curl_exec($curl);
        self::assertSame(429, curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        self::assertStringContainsString('Too many failed sign-in attempts', $html);
    }

    public function testSessionCookieIsHttpOnlyAndSameSite(): void
    {
        $headers = $this->request('GET', '/index.php', headers: ['Accept: text/html'])['headers'];
        self::assertMatchesRegularExpression('/Set-Cookie: bvsid=[^;]+;.*HttpOnly/i', $headers);
        self::assertMatchesRegularExpression('/Set-Cookie: bvsid=.*SameSite=Lax/i', $headers);
    }
}
