# emailsherlock/client

Official PHP client for the [EmailSherlock](https://emailsherlock.com) email-verification API. Verify one address or a batch over HTTPS with an API key. Get an API key at https://emailsherlock.com/api. Want to try a single address by hand first? The free [email verification](https://emailsherlock.com/verify) tool runs the same checks in the browser.

PHP 8.1+. The client and models are generated from the OpenAPI spec (namespace `Emailsherlock\Generated`), with a thin hand-maintained layer for the ergonomics below. Built on `guzzlehttp/guzzle`.

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

echo $result->getResult(); // 'valid'
echo $result->getScore();  // 0.95
```

Called with `null`, `new Client(null)` reads `ES_KEY` (or
`EMAILSHERLOCK_API_KEY`) from the environment.

## Batch

Up to 100 addresses per call. Each entry verified, or carries a per-address
error. Tell them apart with `VerifyResource::isVerifyResult`:

```php
use Emailsherlock\VerifyResource;

$batch = $es->verify->batch(['emails' => ['jane@acme.com', 'sales@acme.com']]);

foreach ($batch->getResults() as $item) {
    if (VerifyResource::isVerifyResult($item)) {
        echo "{$item->getEmail()}: {$item->getResult()}\n";
    } else {
        echo "{$item->getEmail()} failed: {$item->getError()}\n";
    }
}
```

## Async jobs

For large lists, submit a job and poll it. Every address runs the full pipeline
including the SMTP probe, so the results carry definitive inbox verdicts:

```php
$job = $es->verify->submitJob(['emails' => ['a@acme.com', 'b@acme.com']]);
while ($job->getStatus() !== 'completed') {
    sleep(2);
    $job = $es->verify->getJob($job->getId());
}
```

## Account status

```php
$account = $es->credits();
$account->getCredits()->getTotal(); // spendable credits
$account->getSandbox();             // true on an es_test_ key
```

## Email-Guard events

Record Email-Guard decision events (free, no credits). The full address is never
sent, only the domain:

```php
$es->guard->recordEvents([
    ['domain' => 'mailinator.com', 'verdict' => 'disposable', 'action' => 'deny',
     'reasons' => ['disposable_provider'], 'degraded' => false, 'source' => 'local'],
]);
```

## The result object

The result is a generated model; read fields with `get<Field>()` accessors:

| accessor          | meaning                                                         |
|-------------------|-----------------------------------------------------------------|
| `getEmail()`      | the address you sent                                            |
| `getResult()`     | `valid` · `invalid` · `catch_all` · `disposable` · `role` · `unknown` |
| `getMx()`         | the domain has reachable MX records                             |
| `getDisposable()` · `getRole()` · `getCatchAll()` | throwaway / role / catch-all flags |
| `getScore()`      | 0–1 confidence, higher is safer to send to                      |
| `getFreshness()`  | `fresh` · `cached_recent` · `cached_stale_refreshed`            |
| `getDeliverable()`| proven via SMTP (true accepted, false provably bad, null unproven) |
| `getReason()`     | why the pipeline decided (`mailbox_accepts`, `greylisted`, …)   |
| `getMxRecord()` · `getFreeEmail()` · `getCheckedAt()` | primary MX host · freemail flag · ISO 8601 check time |
| `getDomain()`     | domain-level intelligence (SPF, DKIM, DMARC, score, blacklists, …) |
| `getDecision()`   | `getRecommendation()` (allow · deny · review) + `getReasons()`  |

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
| `ValidationException`          | 400 / 404 / 422 | the request was rejected, or the job was not found |
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
    httpClient: null,                          // inject a configured GuzzleHttp\Client
    timeout: 30.0,                             // default
);
```

## License

MIT. Full API reference: https://emailsherlock.com/api/docs
