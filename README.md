# emailsherlock/client

Official PHP client for the [EmailSherlock](https://emailsherlock.com) email-verification API. Verify one address or a batch over HTTPS with an API key.

PHP 8.1+. Requires `ext-curl` and `ext-json`. No other dependencies.

## Install

```bash
composer require emailsherlock/client
```

## Quick start

```php
<?php
use Emailsherlock\Client;

// reads the key from the environment, never hard-code it
$es = new Client(getenv('ES_KEY'));

$result = $es->verify->single(['email' => 'jane@acme.com']);

echo $result->result; // 'valid'
echo $result->score;  // 0.95
```

Called with `null`, `new Client(null)` reads `ES_KEY` (or
`EMAILSHERLOCK_API_KEY`) from the environment.

## Batch

Up to 100 addresses per call. Each item is either a `VerifyResult` or a
`BatchItemError`:

```php
use Emailsherlock\VerifyResult;

$batch = $es->verify->batch(['emails' => ['jane@acme.com', 'sales@acme.com']]);

foreach ($batch->results as $item) {
    if ($item instanceof VerifyResult) {
        echo "{$item->email}: {$item->result}\n";
    } else { // BatchItemError
        echo "{$item->email} failed: {$item->error}\n";
    }
}
```

## The result object

`VerifyResult` mirrors the API JSON:

| property     | type   | meaning                                                         |
|--------------|--------|-----------------------------------------------------------------|
| `email`      | string | the address you sent                                            |
| `result`     | string | `valid` · `invalid` · `catch_all` · `disposable` · `role` · `unknown` |
| `mx`         | bool   | the domain has reachable MX records                             |
| `disposable` | bool   | throwaway / temporary-mail provider                             |
| `role`       | bool   | role address such as `info@` or `sales@`                        |
| `catchAll`   | bool   | host accepts mail for any local part                            |
| `score`      | float  | 0–1 confidence, higher is safer to send to                      |
| `freshness`  | string | `fresh` · `cached_recent` · `cached_stale_refreshed`            |

## Credits and rate limits

After every call:

```php
$es->creditsRemaining;  // e.g. 41.0
$es->rateLimit;         // ['limit' => 60, 'remaining' => 59, 'reset' => 1700000000]
```

## Errors

Every failure throws a subclass of `Emailsherlock\Exception\EmailsherlockException`:

| class                          | HTTP | extras                                       |
|--------------------------------|------|----------------------------------------------|
| `AuthenticationException`      | 401  | -                                            |
| `ForbiddenException`           | 403  | `requiredScope`                              |
| `InsufficientCreditsException` | 402  | `creditsRequired`, `creditsRemaining`        |
| `RateLimitException`           | 429  | `retryAfter`, `limit`, `remaining`, `reset`  |
| `ValidationException`          | 400 / 422 | the request body was rejected           |
| `ServiceUnavailableException`  | 503  | credit auto-refunded                         |

Each exception exposes `->statusCode` and `->errorCode` (the API's string code,
e.g. `rate_limit_exceeded`).

```php
use Emailsherlock\Exception\RateLimitException;

try {
    $es->verify->single(['email' => 'jane@acme.com']);
} catch (RateLimitException $e) {
    echo "retry after {$e->retryAfter}s";
}
```

## Options

```php
new Client(
    apiKey: getenv('ES_KEY'),
    baseUrl: 'https://api.emailsherlock.com', // default
    timeout: 30.0,                             // default
);
```

## License

MIT. Full API reference: https://emailsherlock.com/api/docs
