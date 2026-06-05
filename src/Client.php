<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Exception\EmailsherlockException;
use Emailsherlock\Http\CurlTransport;
use Emailsherlock\Http\Transport;

/**
 * Client for the EmailSherlock verify API.
 *
 * The API key is read from the constructor or, if null, from the ES_KEY /
 * EMAILSHERLOCK_API_KEY environment variables.
 */
final class Client
{
    public const VERSION = '0.1.0';
    private const DEFAULT_BASE_URL = 'https://api.emailsherlock.com';

    public readonly VerifyResource $verify;

    /** Credits left after the most recent request (X-Credits-Remaining). */
    public ?float $creditsRemaining = null;

    /** @var array{limit: ?int, remaining: ?int, reset: ?int} */
    public array $rateLimit = ['limit' => null, 'remaining' => null, 'reset' => null];

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly Transport $transport;
    private readonly float $timeout;

    public function __construct(
        ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?Transport $transport = null,
        float $timeout = 30.0,
    ) {
        $key = $apiKey ?? getenv('ES_KEY') ?: getenv('EMAILSHERLOCK_API_KEY');
        if (!is_string($key) || $key === '') {
            throw new EmailsherlockException(
                'No API key provided. Pass it to the constructor or set ES_KEY.',
                errorCode: 'config_error',
            );
        }

        $this->apiKey = $key;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport ?? new CurlTransport();
        $this->timeout = $timeout;
        $this->verify = new VerifyResource($this);
    }

    /**
     * Low-level POST. Most callers should use $client->verify instead.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function request(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->transport->send(
            'POST',
            $this->baseUrl . $path,
            [
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'emailsherlock-php/' . self::VERSION,
            ],
            $body,
            $this->timeout,
        );

        $this->captureMeta($response);

        $decoded = $response->body !== '' ? json_decode($response->body, true) : null;
        $decoded = is_array($decoded) ? $decoded : null;

        if ($response->status >= 400) {
            throw ErrorFactory::fromResponse($response, $decoded);
        }

        return $decoded ?? [];
    }

    private function captureMeta(Http\Response $response): void
    {
        $credits = $response->header('X-Credits-Remaining');
        if ($credits !== null && is_numeric($credits)) {
            $this->creditsRemaining = (float) $credits;
        }
        if ($response->header('X-RateLimit-Limit') !== null) {
            $this->rateLimit = [
                'limit' => $this->intOrNull($response->header('X-RateLimit-Limit')),
                'remaining' => $this->intOrNull($response->header('X-RateLimit-Remaining')),
                'reset' => $this->intOrNull($response->header('X-RateLimit-Reset')),
            ];
        }
    }

    private function intOrNull(?string $value): ?int
    {
        return $value !== null && is_numeric($value) ? (int) $value : null;
    }
}
