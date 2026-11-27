# emailsherlock/client

Official PHP client for the [EmailSherlock](https://www.hostsherlock.com) email-verification API. Verify one address or a batch over HTTPS with an API key. Get an API key at https://www.hostsherlock.com/api. Want to try a single address by hand first? The free [email verification](https://www.hostsherlock.com/verify) tool runs the same checks in the browser.

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

## Deliverability checks

Read the check addresses of your account and their verdicts. `latest()` is the
pre-send gate: the last cached verdict, no live probe. Free, no credits.

```php
$addresses = $es->deliverability->listAddresses()->getAddresses();
$gate = $es->deliverability->latest($addresses[0]->getId());
$gate->getVerdict()?->getVerdictBand(); // 'inbox_ready' · 'at_risk' · 'likely_filtered'

$hook = $es->deliverability->setWebhook(45, 'https://hooks.example.com/emailsherlock');
$hook->getWebhook()->getSecret(); // shown once, store it now
```

`listChecks($id, ['limit' => 20])`, `getCheck($checkId)` and
`deleteWebhook($id)` cover the rest.

## DMARC

`start()` watches the domain and tells you the one DNS change that routes its
DMARC reports to your account; `check()` confirms the record once it is live.
The reads (`summary`, `reports`, `sources`) need your account's report address
in the domain's DMARC record. Watching a domain does not unlock its reports.
Free, no credits.

```php
$setup = $es->dmarc->start('acme.com');
$record = $setup->getRecord(); // null when the lookup could not run
$record?->getAction();          // 'create' · 'replace' · 'add_address'
$record?->getValue();           // what to publish at $record->getHost()

$summary = $es->dmarc->summary('acme.com', ['range' => 7]);
echo $summary->getAlignedPct();

$failing = $es->dmarc->sources('acme.com', ['result' => 'fail', 'check' => 'spf']);
$reports = $es->dmarc->reports('acme.com', ['from' => '2026-11-01', 'limit' => 20]);
```

## TLS-RPT

Needs your account's report address in the domain's TLS-RPT record. Free, no
credits.

```php
$page = $es->tlsRpt->reports('acme.com', ['range' => 30]);
foreach ($page->getReports() as $report) { /* ... */ }
```

## Domains

The change timeline of a domain you watch, and the next DMARC policy step.
`nextSteps()` needs your account's report address in the domain's DMARC record.
Free, no credits.

```php
$changes = $es->domains->changes('acme.com', ['limit' => 20])->getChanges();

$next = $es->domains->nextSteps('acme.com');
$next->getLadder()->getNextPolicy();         // 'quarantine' or 'reject'
$next->getLadder()->getReadyForNextPolicy(); // true while reports arrive and no sender fails DMARC
```

## Impersonation

Score a sender's header fields against the people and organisations you protect.
No subject or body is sent, and nothing is stored. Costs credits.

```php
$verdict = $es->impersonation->analyze([
    'from' => 'thomas.berger.ceo@gmail.com',
    'display_name' => 'Thomas Berger',
    'protected' => [['name' => 'Thomas Berger', 'domains' => ['acme.de']]],
]);
echo $verdict->getRisk(); // 'none' · 'low' · 'medium' · 'high'
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

Two balances apply. Standard verify draws on your monthly validations, deep verify
of catch-all and unknown addresses draws probe credits. After every call that
reports them:

```php
$es->probeCreditsRemaining;  // e.g. 41.0
$es->validationsRemaining;   // e.g. 950, or null on a paid plan (unlimited)
$es->rateLimit;              // ['limit' => 60, 'remaining' => 59, 'reset' => 1700000000]
```

`$es->creditsRemaining` still holds the probe-credit balance and is deprecated.

## Enum values this version does not know

When the API returns an enum value that is newer than your SDK version, the field
reads `'unknown_default_open_api'` (each model has it as a
`…_UNKNOWN_DEFAULT_OPEN_API` constant) and the rest of the response arrives as
usual. In a list of enum values, such as `getTypes()` on the verify domain block,
only the new entry reads that way. Update the SDK to read the new value.

## Errors

Every failure throws a subclass of `Emailsherlock\Exception\EmailsherlockException`:

| class                          | HTTP | extras                                       |
|--------------------------------|------|----------------------------------------------|
| `AuthenticationException`      | 401  | -                                            |
| `ForbiddenException`           | 403  | `requiredScope`                              |
| `InsufficientCreditsException` | 402  | `creditsRequired`, `creditsRemaining`        |
| `RateLimitException`           | 429  | `retryAfter`, `limit`, `remaining`, `reset`  |
| `ValidationException`          | 400 / 404 / 422 | the request was rejected, or what it names was not found |
| `ServiceUnavailableException`  | 503  | credit auto-refunded                         |

Each exception exposes `->statusCode` and `->errorCode` (the API's string code,
e.g. `rate_limit_exceeded`).
Any other status (for example `409` from `dmarc->start()`, or `500`) throws the
base `EmailsherlockException`.

```php
use Emailsherlock\Exception\RateLimitException;

try {
    $es->verify->single(['email' => 'jane@acme.com']);
} catch (RateLimitException $e) {
    echo "retry after {$e->retryAfter}s";
}
```

## Working for a tenant (`X-Client-Id`)

If your key belongs to a provider account, you can run calls inside one of its
tenants by naming the tenant's numeric id. Usage, verify jobs (and their
refunds), deliverability addresses and Guard events then land in that tenant.

```php
$es = new Client(apiKey: getenv('ES_KEY'), clientId: 4711);

// or derive a client for a tenant from an existing one (the original is unchanged)
$tenant = $es->withClientId(4711);
$tenant->verify->single(['email' => 'jane@acme.com']);
$tenant->clientId; // 4711
```

Poll a job with the client you submitted it with: a job belongs to the tenant it
was created in. Without `clientId` the key works in its own account. The id must
be greater than 0, otherwise the constructor throws `config_error`. Errors:

- `client_not_found` (404, `ValidationException`): the id does not exist or your key may not reach it.
- `forbidden` (403, `ForbiddenException`): the tenant is blocked from the API.
- `client_id_not_allowed` (400, `ValidationException`): the key is not a provider key; any `X-Client-Id` is rejected, even an empty one.

## Options

```php
new Client(
    apiKey: getenv('ES_KEY'),
    baseUrl: 'https://api.emailsherlock.com', // default
    httpClient: null,                          // inject a configured GuzzleHttp\Client
    timeout: 30.0,                             // default
    clientId: null,                            // provider keys: work inside this tenant
);
```

## License

MIT. Full API reference: https://www.hostsherlock.com/api/docs
