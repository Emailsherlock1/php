<?php

declare(strict_types=1);

namespace Emailsherlock\Tests;

use Emailsherlock\Client;
use Emailsherlock\Exception\AuthenticationException;
use Emailsherlock\Exception\EmailsherlockException;
use Emailsherlock\Exception\InsufficientCreditsException;
use Emailsherlock\Exception\RateLimitException;
use Emailsherlock\VerifyResource;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Emailsherlock\Exception\ForbiddenException;
use Emailsherlock\Exception\ValidationException;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @param array<string, string> $headers */
    private function client(int $status, array $body, array $headers = []): Client
    {
        $mock = new MockHandler([
            new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body)),
        ]);
        $http = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

        return new Client('test-key', httpClient: $http);
    }

    private const WIRE = [
        'email' => 'jane@acme.com', 'result' => 'valid', 'mx' => true, 'mx_record' => 'mx1.acme.com',
        'disposable' => false, 'role' => false, 'catch_all' => true, 'free_email' => false,
        'score' => 0.95, 'freshness' => 'fresh', 'checked_at' => '2026-06-09T14:32:00+00:00',
        'decision' => ['recommendation' => 'allow', 'reasons' => ['mailbox_accepts']],
    ];

    public function testSingleReturnsResultAndCapturesMeta(): void
    {
        $client = $this->client(200, self::WIRE, [
            'X-Credits-Remaining' => '41',
            'X-RateLimit-Limit' => '60',
            'X-RateLimit-Remaining' => '59',
            'X-RateLimit-Reset' => '1700000000',
        ]);
        $result = $client->verify->single(['email' => 'jane@acme.com']);

        self::assertSame('valid', $result->getResult());
        self::assertSame(0.95, $result->getScore());
        self::assertSame(41.0, $client->creditsRemaining);
        self::assertSame(60, $client->rateLimit['limit']);
    }

    public function testSingleKeepsWireFieldNames(): void
    {
        // Regression (EM-1034): snake_case wire decodes onto the generated getters.
        $result = $this->client(200, self::WIRE)->verify->single(['email' => 'jane@acme.com']);

        self::assertTrue($result->getCatchAll());
        self::assertSame('mx1.acme.com', $result->getMxRecord());
        self::assertFalse($result->getFreeEmail());
        self::assertSame('2026-06-09T14:32:00+00:00', $result->getCheckedAt());
    }

    public function testBatchResultsAndTypeGuard(): void
    {
        $client = $this->client(200, ['results' => [self::WIRE, ['email' => 'nope@', 'error' => 'invalid_email']]]);
        $batch = $client->verify->batch(['emails' => ['jane@acme.com', 'nope@']]);

        $results = $batch->getResults();
        self::assertCount(2, $results);
        self::assertTrue(VerifyResource::isVerifyResult($results[0]));
        self::assertFalse(VerifyResource::isVerifyResult($results[1]));
        self::assertSame('invalid_email', $results[1]->getError());
    }

    public function testCreditsReturnsAccountStatus(): void
    {
        $client = $this->client(200, [
            'credits' => ['total' => 1240, 'purchased' => 1000, 'gifted' => 240],
            'rate_limit' => ['limit' => 60, 'remaining' => 59, 'reset' => 1700000000],
            'plan' => 'Free', 'sandbox' => false,
        ]);
        $status = $client->credits();

        self::assertSame(1240, $status->getCredits()->getTotal());
        self::assertFalse($status->getSandbox());
    }

    public function testSubmitJobReturnsProcessing(): void
    {
        $client = $this->client(202, [
            'id' => 'job-1', 'status' => 'processing', 'total' => 2,
            'progress' => ['total' => 2, 'done' => 0],
            'created_at' => '2026-06-10T14:32:00+00:00', 'expires_at' => '2026-06-17T14:32:00+00:00',
        ]);
        $job = $client->verify->submitJob(['emails' => ['a@b.com', 'c@d.com']]);

        self::assertSame('job-1', $job->getId());
        self::assertSame('processing', $job->getStatus());
    }

    public function testUnauthorizedThrowsAuthenticationException(): void
    {
        $client = $this->client(401, ['error' => ['code' => 'unauthorized', 'message' => 'Invalid API key.']]);
        $this->expectException(AuthenticationException::class);
        $client->verify->single(['email' => 'a@b.com']);
    }

    public function testInsufficientCreditsCarriesHeaders(): void
    {
        $client = $this->client(
            402,
            ['error' => ['code' => 'insufficient_credits', 'message' => 'Not enough.']],
            ['X-Credits-Required' => '1', 'X-Credits-Remaining' => '0'],
        );
        try {
            $client->verify->single(['email' => 'a@b.com']);
            self::fail('expected InsufficientCreditsException');
        } catch (InsufficientCreditsException $e) {
            self::assertSame(1.0, $e->creditsRequired);
            self::assertSame(0.0, $e->creditsRemaining);
        }
    }

    public function testRateLimitedCarriesRetryAfter(): void
    {
        $client = $this->client(
            429,
            ['error' => ['code' => 'rate_limit_exceeded', 'message' => 'Slow down.']],
            ['Retry-After' => '30'],
        );
        try {
            $client->verify->single(['email' => 'a@b.com']);
            self::fail('expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(30, $e->retryAfter);
        }
    }

    public function testMissingKeyThrowsAtConstruction(): void
    {
        putenv('ES_KEY');
        putenv('EMAILSHERLOCK_API_KEY');
        $this->expectException(EmailsherlockException::class);
        new Client('');
    }

    /**
     * @param list<Response> $responses
     * @param array<int, array<string, mixed>> $history
     */
    private function recording(array $responses, array &$history): GuzzleClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new GuzzleClient(['handler' => $stack]);
    }

    private function ok(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(self::WIRE));
    }

    public function testClientIdHeaderSentWhenSet(): void
    {
        $history = [];
        $client = new Client('k', httpClient: $this->recording([$this->ok()], $history), clientId: 42);
        $client->verify->single(['email' => 'jane@acme.com']);

        self::assertSame(42, $client->clientId);
        self::assertSame(['42'], $history[0]['request']->getHeader('X-Client-Id'));
    }

    public function testClientIdHeaderAbsentWhenNotSet(): void
    {
        $history = [];
        $client = new Client('k', httpClient: $this->recording([$this->ok()], $history));
        $client->verify->single(['email' => 'jane@acme.com']);

        self::assertNull($client->clientId);
        self::assertFalse($history[0]['request']->hasHeader('X-Client-Id'));
    }

    public function testClientIdWithoutInjectedHttpClient(): void
    {
        self::assertSame(7, (new Client('k', clientId: 7))->clientId);
    }

    public function testWithClientIdSendsNewIdAndLeavesOriginalUntouched(): void
    {
        $history = [];
        $http = $this->recording([$this->ok(), $this->ok(), $this->ok()], $history);
        $original = new Client('k', httpClient: $http);
        $tenant = $original->withClientId(9);
        $other = $tenant->withClientId(10);

        $tenant->verify->single(['email' => 'jane@acme.com']);
        $original->verify->single(['email' => 'jane@acme.com']);
        $other->verify->single(['email' => 'jane@acme.com']);

        self::assertNull($original->clientId);
        self::assertSame(9, $tenant->clientId);
        self::assertSame(['9'], $history[0]['request']->getHeader('X-Client-Id'));
        self::assertFalse($history[1]['request']->hasHeader('X-Client-Id'));
        self::assertSame(['10'], $history[2]['request']->getHeader('X-Client-Id'));
        self::assertSame(['k'], $history[0]['request']->getHeader('X-API-Key'));
    }

    public function testWithClientIdKeepsSeparateMeta(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json', 'X-Credits-Remaining' => '5'], json_encode(self::WIRE)),
        ]);
        $original = new Client('k', httpClient: new GuzzleClient(['handler' => HandlerStack::create($mock)]));
        $tenant = $original->withClientId(3);
        $tenant->verify->single(['email' => 'jane@acme.com']);

        self::assertSame(5.0, $tenant->creditsRemaining);
        self::assertNull($original->creditsRemaining);
    }

    /** @return array<string, array{int}> */
    public static function invalidIds(): array
    {
        return ['zero' => [0], 'negative' => [-5]];
    }

    #[DataProvider('invalidIds')]
    public function testInvalidClientIdThrowsAtConstruction(int $id): void
    {
        try {
            new Client('k', clientId: $id);
            self::fail('expected exception');
        } catch (EmailsherlockException $e) {
            self::assertSame('config_error', $e->errorCode);
        }
    }

    public function testWithClientIdRejectsInvalidId(): void
    {
        $this->expectException(EmailsherlockException::class);
        (new Client('k'))->withClientId(0);
    }

    #[DataProvider('tenantErrors')]
    public function testTenantErrorCodesAreReadable(int $status, string $code, string $class): void
    {
        $client = $this->client($status, ['error' => ['code' => $code, 'message' => 'x']]);
        try {
            $client->verify->single(['email' => 'jane@acme.com']);
            self::fail('expected exception');
        } catch (EmailsherlockException $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($status, $e->statusCode);
            self::assertSame($code, $e->errorCode);
        }
    }

    /** @return array<string, array{int, string, string}> */
    public static function tenantErrors(): array
    {
        return [
            'not found' => [404, 'client_not_found', ValidationException::class],
            'not allowed' => [400, 'client_id_not_allowed', ValidationException::class],
            'blocked' => [403, 'forbidden', ForbiddenException::class],
        ];
    }
}
