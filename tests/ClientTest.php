<?php

declare(strict_types=1);

namespace Emailsherlock\Tests;

use Emailsherlock\BatchItemError;
use Emailsherlock\Client;
use Emailsherlock\Exception\AuthenticationException;
use Emailsherlock\Exception\EmailsherlockException;
use Emailsherlock\Exception\InsufficientCreditsException;
use Emailsherlock\Exception\RateLimitException;
use Emailsherlock\Http\Response;
use Emailsherlock\Http\Transport;
use Emailsherlock\VerifyResult;
use PHPUnit\Framework\TestCase;

final class FakeTransport implements Transport
{
    public function __construct(private Response $response)
    {
    }

    public function send(string $method, string $url, array $headers, string $body, float $timeout): Response
    {
        return $this->response;
    }
}

final class ClientTest extends TestCase
{
    private function client(int $status, array $body, array $headers = []): Client
    {
        $normalized = [];
        foreach ($headers as $k => $v) {
            $normalized[strtolower($k)] = (string) $v;
        }
        $response = new Response($status, json_encode($body), $normalized);

        return new Client('test-key', transport: new FakeTransport($response));
    }

    public function testSingleOk(): void
    {
        $es = $this->client(200, [
            'email' => 'jane@acme.com', 'result' => 'valid', 'mx' => true,
            'disposable' => false, 'role' => false, 'catch_all' => false,
            'score' => 0.95, 'freshness' => 'fresh',
        ], ['X-Credits-Remaining' => '41', 'X-RateLimit-Limit' => '60', 'X-RateLimit-Remaining' => '59', 'X-RateLimit-Reset' => '1700000000']);

        $result = $es->verify->single(['email' => 'jane@acme.com']);

        self::assertInstanceOf(VerifyResult::class, $result);
        self::assertSame('valid', $result->result);
        self::assertSame(0.95, $result->score);
        self::assertSame(41.0, $es->creditsRemaining);
        self::assertSame(60, $es->rateLimit['limit']);
    }

    public function testBatchMixed(): void
    {
        $es = $this->client(200, ['results' => [
            ['email' => 'jane@acme.com', 'result' => 'valid', 'mx' => true, 'score' => 0.9, 'freshness' => 'fresh'],
            ['email' => 'nope@', 'error' => 'invalid_email'],
        ]]);

        $batch = $es->verify->batch(['emails' => ['jane@acme.com', 'nope@']]);

        self::assertCount(2, $batch->results);
        self::assertInstanceOf(VerifyResult::class, $batch->results[0]);
        self::assertInstanceOf(BatchItemError::class, $batch->results[1]);
        self::assertSame('invalid_email', $batch->results[1]->error);
    }

    public function testUnauthorized(): void
    {
        $es = $this->client(401, ['error' => ['code' => 'unauthorized', 'message' => 'Invalid API key.']]);

        try {
            $es->verify->single(['email' => 'a@b.com']);
            self::fail('expected exception');
        } catch (AuthenticationException $e) {
            self::assertSame(401, $e->statusCode);
            self::assertSame('unauthorized', $e->errorCode);
        }
    }

    public function testInsufficientCredits(): void
    {
        $es = $this->client(402, ['error' => ['code' => 'insufficient_credits', 'message' => 'Not enough.']], ['X-Credits-Required' => '1', 'X-Credits-Remaining' => '0']);

        try {
            $es->verify->single(['email' => 'a@b.com']);
            self::fail('expected exception');
        } catch (InsufficientCreditsException $e) {
            self::assertSame(1.0, $e->creditsRequired);
            self::assertSame(0.0, $e->creditsRemaining);
        }
    }

    public function testRateLimited(): void
    {
        $es = $this->client(429, ['error' => ['code' => 'rate_limit_exceeded', 'message' => 'Slow down.']], ['Retry-After' => '30']);

        try {
            $es->verify->single(['email' => 'a@b.com']);
            self::fail('expected exception');
        } catch (RateLimitException $e) {
            self::assertSame(30, $e->retryAfter);
        }
    }

    public function testMissingKey(): void
    {
        putenv('ES_KEY');
        putenv('EMAILSHERLOCK_API_KEY');
        $this->expectException(EmailsherlockException::class);
        new Client(null);
    }
}
