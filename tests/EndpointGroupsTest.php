<?php

declare(strict_types=1);

namespace Emailsherlock\Tests;

use Emailsherlock\Client;
use Emailsherlock\Exception\EmailsherlockException;
use Emailsherlock\Exception\ForbiddenException;
use Emailsherlock\Exception\InsufficientCreditsException;
use Emailsherlock\Exception\RateLimitException;
use Emailsherlock\Exception\ServiceUnavailableException;
use Emailsherlock\Exception\ValidationException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/** The deliverability, dmarc, tlsRpt, domains and impersonation resources. */
final class EndpointGroupsTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    /** @param array<string, string> $headers */
    private function http(int $status, array $body, array $headers = []): GuzzleClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body)),
        ]));
        $stack->push(Middleware::history($this->history));

        return new GuzzleClient(['handler' => $stack]);
    }

    /** @param array<string, mixed> $body */
    private function client(array $body, int $status = 200, array $headers = []): Client
    {
        return new Client('test-key', httpClient: $this->http($status, $body, $headers));
    }

    private function request(): RequestInterface
    {
        self::assertCount(1, $this->history);

        return $this->history[0]['request'];
    }

    private function assertRequest(string $method, string $path, string $query = ''): void
    {
        $request = $this->request();
        self::assertSame($method, $request->getMethod());
        self::assertSame($path, $request->getUri()->getPath());
        self::assertSame($query, $request->getUri()->getQuery());
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $decoded = json_decode((string) $this->request()->getBody(), true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private const LIST_PAGE = ['domain' => 'acme.com', 'limit' => 10, 'offset' => 20, 'count' => 0, 'next_offset' => null];

    // ---- deliverability ------------------------------------------------

    public function testDeliverabilityListAddresses(): void
    {
        $client = $this->client(['addresses' => [
            ['id' => 45, 'label' => 'Newsletter', 'address' => 'check-45@inbox.example', 'active' => true, 'has_webhook' => false],
        ]]);
        $result = $client->deliverability->listAddresses();

        $this->assertRequest('GET', '/v1/deliverability/addresses');
        self::assertSame(45, $result->getAddresses()[0]->getId());
    }

    public function testDeliverabilityLatest(): void
    {
        $client = $this->client([
            'address_id' => 45, 'has_result' => true,
            'verdict' => ['check_id' => 9, 'score' => 82, 'verdict_band' => 'inbox_ready'],
        ]);
        $result = $client->deliverability->latest(45);

        $this->assertRequest('GET', '/v1/deliverability/addresses/45/latest');
        self::assertTrue($result->getHasResult());
        self::assertSame(82, $result->getVerdict()->getScore());
    }

    public function testDeliverabilityListChecksSendsPaging(): void
    {
        $client = $this->client(['address_id' => 45, 'limit' => 10, 'offset' => 20, 'count' => 1, 'checks' => [['id' => 9]]]);
        $result = $client->deliverability->listChecks(45, ['limit' => 10, 'offset' => 20]);

        $this->assertRequest('GET', '/v1/deliverability/addresses/45/checks', 'limit=10&offset=20');
        self::assertSame(9, $result->getChecks()[0]->getId());
    }

    public function testDeliverabilityListChecksSendsOnlyWhatIsSet(): void
    {
        $client = $this->client(['address_id' => 45, 'limit' => 50, 'offset' => 0, 'count' => 0, 'checks' => []]);
        $client->deliverability->listChecks(45);

        $this->assertRequest('GET', '/v1/deliverability/addresses/45/checks');
    }

    public function testDeliverabilityGetCheck(): void
    {
        $client = $this->client(['id' => 9, 'address_id' => 45, 'score' => 82, 'result' => ['spf' => 'pass']]);
        $result = $client->deliverability->getCheck(9);

        $this->assertRequest('GET', '/v1/deliverability/checks/9');
        self::assertSame(82, $result->getScore());
    }

    public function testDeliverabilitySetWebhookReturnsSecret(): void
    {
        $client = $this->client(['address_id' => 45, 'webhook' => ['url' => 'https://hooks.example.com/es', 'secret' => 'whsec_1']]);
        $result = $client->deliverability->setWebhook(45, 'https://hooks.example.com/es');

        $this->assertRequest('PUT', '/v1/deliverability/addresses/45/webhook');
        self::assertSame(['url' => 'https://hooks.example.com/es'], $this->jsonBody());
        self::assertSame('whsec_1', $result->getWebhook()->getSecret());
    }

    public function testDeliverabilityDeleteWebhook(): void
    {
        $client = $this->client(['address_id' => 45, 'webhook' => null]);
        $result = $client->deliverability->deleteWebhook(45);

        $this->assertRequest('DELETE', '/v1/deliverability/addresses/45/webhook');
        self::assertSame(45, $result->getAddressId());
    }

    // ---- dmarc -----------------------------------------------------------

    public function testDmarcStartEncodesDomain(): void
    {
        $client = $this->client(['domain' => 'bücher.de', 'status' => 'not_found', 'confirmed' => false]);
        $result = $client->dmarc->start('bücher.de');

        $this->assertRequest('POST', '/v1/domains/b%C3%BCcher.de/dmarc');
        self::assertSame('not_found', $result->getStatus());
    }

    public function testDmarcCheck(): void
    {
        $client = $this->client(['domain' => 'acme.com', 'status' => 'found', 'confirmed' => true]);
        $result = $client->dmarc->check('acme.com');

        $this->assertRequest('POST', '/v1/domains/acme.com/dmarc/check');
        self::assertTrue($result->getConfirmed());
    }

    public function testDmarcSummarySendsRange(): void
    {
        $client = $this->client(['domain' => 'acme.com', 'range_days' => 7, 'message_count' => 1200]);
        $result = $client->dmarc->summary('acme.com', ['range' => 7]);

        $this->assertRequest('GET', '/v1/dmarc/acme.com/summary', 'range=7');
        self::assertSame(1200, $result->getMessageCount());
    }

    public function testDmarcSummarySendsOnlyWhatIsSet(): void
    {
        $client = $this->client(['domain' => 'acme.com', 'range_days' => 30]);
        $client->dmarc->summary('acme.com');

        $this->assertRequest('GET', '/v1/dmarc/acme.com/summary');
    }

    public function testDmarcReportsSendsFilters(): void
    {
        $client = $this->client(self::LIST_PAGE + ['history_from' => '2026-10-28', 'reports' => [['reporter' => 'google.com']]]);
        $result = $client->dmarc->reports('acme.com', [
            'from' => new \DateTimeImmutable('2026-11-01'),
            'to' => '2026-11-27',
            'reporter' => 'google.com',
            'limit' => 10,
            'offset' => 20,
        ]);

        $this->assertRequest(
            'GET',
            '/v1/dmarc/acme.com/reports',
            'from=2026-11-01&to=2026-11-27&reporter=google.com&limit=10&offset=20',
        );
        self::assertSame('google.com', $result->getReports()[0]->getReporter());
    }

    public function testDmarcSourcesSendsFilters(): void
    {
        $client = $this->client(self::LIST_PAGE + ['range_days' => 7, 'sources' => [['label' => 'Google', 'message_count' => 800]]]);
        $result = $client->dmarc->sources('acme.com', [
            'range' => 7, 'result' => 'fail', 'check' => 'spf', 'limit' => 10, 'offset' => 20,
        ]);

        $this->assertRequest('GET', '/v1/dmarc/acme.com/sources', 'range=7&result=fail&check=spf&limit=10&offset=20');
        self::assertSame(800, $result->getSources()[0]->getMessageCount());
    }

    public function testDmarcSourcesSendsOnlyWhatIsSet(): void
    {
        $client = $this->client(self::LIST_PAGE + ['range_days' => 30, 'sources' => []]);
        $client->dmarc->sources('acme.com', ['result' => 'pass']);

        $this->assertRequest('GET', '/v1/dmarc/acme.com/sources', 'result=pass');
    }

    // ---- tlsRpt / domains ------------------------------------------------

    public function testTlsRptReports(): void
    {
        $client = $this->client(self::LIST_PAGE + ['range_days' => 7, 'reports' => []]);
        $result = $client->tlsRpt->reports('bücher.de', ['range' => 7, 'limit' => 10, 'offset' => 20]);

        $this->assertRequest('GET', '/v1/tls-rpt/b%C3%BCcher.de/reports', 'range=7&limit=10&offset=20');
        self::assertSame(7, $result->getRangeDays());
    }

    public function testDomainsChanges(): void
    {
        $client = $this->client(self::LIST_PAGE + ['history_from' => '2026-10-28', 'changes' => []]);
        $result = $client->domains->changes('acme.com', ['limit' => 10, 'offset' => 20]);

        $this->assertRequest('GET', '/v1/domains/acme.com/changes', 'limit=10&offset=20');
        self::assertSame('2026-10-28', $result->getHistoryFrom());
    }

    public function testDomainsNextSteps(): void
    {
        $client = $this->client([
            'domain' => 'acme.com',
            'ladder' => ['done' => 2, 'total' => 4, 'next_policy' => 'quarantine', 'ready_for_next_policy' => true],
        ]);
        $result = $client->domains->nextSteps('acme.com');

        $this->assertRequest('GET', '/v1/domains/acme.com/next-steps');
        self::assertTrue($result->getLadder()->getReadyForNextPolicy());
    }

    // ---- impersonation ---------------------------------------------------

    public function testImpersonationAnalyzeSendsBodyAndCapturesCredits(): void
    {
        $client = $this->client(
            ['risk' => 'high', 'score' => 91, 'identity' => 'Thomas Berger', 'reasons' => [['reason' => 'display_name', 'weight' => 40]]],
            headers: ['X-Credits-Remaining' => '12', 'X-RateLimit-Limit' => '60', 'X-RateLimit-Remaining' => '58', 'X-RateLimit-Reset' => '1700000000'],
        );
        $request = [
            'from' => 'thomas.berger.ceo@gmail.com',
            'display_name' => 'Thomas Berger',
            'protected' => [['name' => 'Thomas Berger', 'domains' => ['acme.de']]],
        ];
        $result = $client->impersonation->analyze($request);

        $this->assertRequest('POST', '/v1/impersonation/analyze');
        self::assertSame($request, $this->jsonBody());
        self::assertSame('high', $result->getRisk());
        self::assertSame(91, $result->getScore());
        self::assertSame(12.0, $client->creditsRemaining);
        self::assertSame(['limit' => 60, 'remaining' => 58, 'reset' => 1700000000], $client->rateLimit);
    }

    // ---- shared behaviour ------------------------------------------------

    /** @return array<string, array{int, callable(Client): mixed, class-string}> */
    public static function declaredErrors(): array
    {
        return [
            '400 dmarc summary' => [400, fn (Client $c) => $c->dmarc->summary('acme.com'), ValidationException::class],
            '402 impersonation' => [402, fn (Client $c) => $c->impersonation->analyze(['from' => 'a@b.com', 'protected' => []]), InsufficientCreditsException::class],
            '403 dmarc reports' => [403, fn (Client $c) => $c->dmarc->reports('acme.com'), ForbiddenException::class],
            '404 latest verdict' => [404, fn (Client $c) => $c->deliverability->latest(1), ValidationException::class],
            '409 dmarc start' => [409, fn (Client $c) => $c->dmarc->start('acme.com'), EmailsherlockException::class],
            '422 set webhook' => [422, fn (Client $c) => $c->deliverability->setWebhook(1, 'http://x'), ValidationException::class],
            '429 dmarc check' => [429, fn (Client $c) => $c->dmarc->check('acme.com'), RateLimitException::class],
            '503 tls-rpt' => [503, fn (Client $c) => $c->tlsRpt->reports('acme.com'), ServiceUnavailableException::class],
        ];
    }

    /** @param callable(Client): mixed $call */
    #[DataProvider('declaredErrors')]
    public function testDeclaredErrorStatusesMapToNamedExceptions(int $status, callable $call, string $class): void
    {
        $client = $this->client(['error' => ['code' => 'some_code', 'message' => 'Nope.']], $status);
        try {
            $call($client);
            self::fail('expected ' . $class);
        } catch (EmailsherlockException $e) {
            self::assertSame($class, $e::class);
            self::assertSame($status, $e->statusCode);
            self::assertSame('some_code', $e->errorCode);
            self::assertSame('Nope.', $e->getMessage());
        }
    }

    public function testInsufficientCreditsOnAnalyzeCarriesHeaders(): void
    {
        $client = $this->client(
            ['error' => ['code' => 'insufficient_credits', 'message' => 'Not enough.']],
            402,
            ['X-Credits-Required' => '1', 'X-Credits-Remaining' => '0'],
        );
        try {
            $client->impersonation->analyze(['from' => 'a@b.com', 'protected' => [['name' => 'A']]]);
            self::fail('expected InsufficientCreditsException');
        } catch (InsufficientCreditsException $e) {
            self::assertSame(1.0, $e->creditsRequired);
            self::assertSame(0.0, $e->creditsRemaining);
            self::assertSame(0.0, $client->creditsRemaining);
        }
    }

    public function testForbiddenOnDmarcReadCarriesRequiredScope(): void
    {
        $client = $this->client(
            ['error' => ['code' => 'forbidden', 'message' => 'Not your reports.']],
            403,
            ['X-Required-Scope' => 'reports:read'],
        );
        try {
            $client->dmarc->sources('acme.com');
            self::fail('expected ForbiddenException');
        } catch (ForbiddenException $e) {
            self::assertSame('reports:read', $e->requiredScope);
        }
    }

    public function testWithClientIdReachesNewEndpoint(): void
    {
        $tenant = (new Client('test-key', httpClient: $this->http(200, ['domain' => 'acme.com'])))->withClientId(77);
        $tenant->dmarc->summary('acme.com');

        self::assertSame(['77'], $this->request()->getHeader('X-Client-Id'));
        self::assertSame(['test-key'], $this->request()->getHeader('X-API-Key'));
    }

    public function testEnvKeyReachesNewEndpoint(): void
    {
        putenv('ES_KEY=env-key');
        try {
            $client = new Client(null, httpClient: $this->http(200, ['addresses' => []]));
            $client->deliverability->listAddresses();
        } finally {
            putenv('ES_KEY');
        }

        self::assertSame(['env-key'], $this->request()->getHeader('X-API-Key'));
        self::assertFalse($this->request()->hasHeader('X-Client-Id'));
    }
}
