<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Exception\EmailsherlockException;
use Emailsherlock\Generated\Api\AccountApi;
use Emailsherlock\Generated\Api\GuardApi;
use Emailsherlock\Generated\Api\VerifyApi;
use Emailsherlock\Generated\ApiException;
use Emailsherlock\Generated\Configuration;
use Emailsherlock\Generated\Model\AccountStatusResponse;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Client for the EmailSherlock verify API.
 *
 * Thin sugar over the generated raw client in Emailsherlock\Generated: named
 * exception classes, creditsRemaining / rateLimit accessors, an env-var key
 * fallback, and the $verify / $guard resources.
 *
 * The API key is read from the constructor or, if null, from the ES_KEY /
 * EMAILSHERLOCK_API_KEY environment variables.
 */
final class Client
{
    public const VERSION = '0.2.0';
    private const DEFAULT_BASE_URL = 'https://api.emailsherlock.com';

    public readonly VerifyResource $verify;
    public readonly GuardResource $guard;

    /** Credits left after the most recent request (X-Credits-Remaining). */
    public ?float $creditsRemaining = null;

    /** @var array{limit: ?int, remaining: ?int, reset: ?int} */
    public array $rateLimit = ['limit' => null, 'remaining' => null, 'reset' => null];

    private readonly VerifyApi $verifyApi;
    private readonly AccountApi $accountApi;
    private readonly GuardApi $guardApi;

    public function __construct(
        ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?GuzzleClient $httpClient = null,
        float $timeout = 30.0,
    ) {
        $key = $apiKey ?? getenv('ES_KEY') ?: getenv('EMAILSHERLOCK_API_KEY');
        if (!is_string($key) || $key === '') {
            throw new EmailsherlockException(
                'No API key provided. Pass it to the constructor or set ES_KEY.',
                errorCode: 'config_error',
            );
        }

        $config = new Configuration();
        $config->setHost(rtrim($baseUrl, '/'));
        $config->setApiKey('X-API-Key', $key);
        $config->setUserAgent('emailsherlock-php/' . self::VERSION);

        $http = $httpClient ?? new GuzzleClient(['timeout' => $timeout]);
        $this->verifyApi = new VerifyApi($http, $config);
        $this->accountApi = new AccountApi($http, $config);
        $this->guardApi = new GuardApi($http, $config);

        $this->verify = new VerifyResource($this, $this->verifyApi);
        $this->guard = new GuardResource($this, $this->guardApi);
    }

    /** Read the credit balance and rate-limit status. Free: consumes no credits. */
    public function credits(): AccountStatusResponse
    {
        return $this->call(fn () => $this->accountApi->getCreditsWithHttpInfo());
    }

    /**
     * Run a raw-client *WithHttpInfo call, capture the meta headers, and map any
     * ApiException to one of our named exceptions. The closure returns the
     * generated [$model, $statusCode, $headers] tuple.
     *
     * @template T
     * @param callable():array{0: T, 1: int, 2: array<string, string[]>} $fn
     * @return T
     */
    public function call(callable $fn): mixed
    {
        try {
            [$model, , $headers] = $fn();
        } catch (ApiException $e) {
            $this->captureMeta($e->getResponseHeaders());
            throw ErrorFactory::fromApiException($e);
        }
        $this->captureMeta($headers);

        return $model;
    }

    /** @param array<string, string[]>|null $headers */
    private function captureMeta(?array $headers): void
    {
        if ($headers === null) {
            return;
        }
        $get = static function (string $name) use ($headers): ?string {
            foreach ($headers as $key => $values) {
                if (strcasecmp($key, $name) === 0) {
                    return is_array($values) ? ($values[0] ?? null) : (string) $values;
                }
            }
            return null;
        };

        $credits = $get('X-Credits-Remaining');
        if ($credits !== null && is_numeric($credits)) {
            $this->creditsRemaining = (float) $credits;
        }
        if ($get('X-RateLimit-Limit') !== null) {
            $this->rateLimit = [
                'limit' => $this->intOrNull($get('X-RateLimit-Limit')),
                'remaining' => $this->intOrNull($get('X-RateLimit-Remaining')),
                'reset' => $this->intOrNull($get('X-RateLimit-Reset')),
            ];
        }
    }

    private function intOrNull(?string $value): ?int
    {
        return $value !== null && is_numeric($value) ? (int) $value : null;
    }
}
