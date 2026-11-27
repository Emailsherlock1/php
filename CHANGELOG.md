# Changelog

## 0.5.0

### Added

- `$client->probeCreditsRemaining` and `$client->validationsRemaining`: the two balances the API reports, read from `X-Probe-Credits-Remaining` and `X-Validations-Remaining` (falling back to the older header names). `validationsRemaining` stays `null` on a paid plan, where standard verify is unlimited.

### Changed

- An enum value the SDK does not know no longer throws `InvalidArgumentException` while the response is read. The field reads `'unknown_default_open_api'` (the `…_UNKNOWN_DEFAULT_OPEN_API` constant on each model), and the rest of the response arrives. The same setter runs when you call one on a generated request model yourself (`setMode()` on `VerifySingleRequest`): a value it does not list is no longer refused locally and goes out as `'unknown_default_open_api'`.
- `InsufficientCreditsException::$creditsRemaining` reads `X-Probe-Credits-Remaining` first.

### Deprecated

- `$client->creditsRemaining`: holds the same value as `probeCreditsRemaining`.

## 0.4.0

Five new resources for the endpoint groups added to the spec. Each goes through the same layer as `verify` and `guard`: named exceptions, `creditsRemaining` / `rateLimit`, the env-var key fallback and `X-Client-Id`. Only `impersonation->analyze()` costs credits; the other new calls are free.

### Added

- `$client->deliverability`: `listAddresses()`, `latest($id)` (the pre-send gate), `listChecks($id, ['limit' => …, 'offset' => …])`, `getCheck($id)`, `setWebhook($id, $url)` (returns the signing secret once), `deleteWebhook($id)`.
- `$client->dmarc`: `start($domain)`, `check($domain)`, `summary($domain, ['range' => 7|30])`, `reports($domain, [...])` (from, to, reporter, limit, offset), `sources($domain, [...])` (range, result, check, limit, offset).
- `$client->tlsRpt`: `reports($domain, [...])` (range, limit, offset).
- `$client->domains`: `changes($domain, [...])` (limit, offset), `nextSteps($domain)`.
- `$client->impersonation`: `analyze($request)` scores a sender's header fields against the identities you protect.
- `clientId` constructor option and `$client->withClientId($id)`: a provider key sends `X-Client-Id` to work inside one of its tenants (usage, verify jobs, deliverability addresses and Guard events land there). `$client->clientId` exposes the current id.

### Changed

- The generated doc comments now point at https://www.hostsherlock.com/api/docs.

## 0.3.0

The client and models are now generated from the EmailSherlock OpenAPI spec
(namespace `Emailsherlock\Generated`), with a thin hand-maintained sugar layer
on top. Most of the v0.2.0 surface carries over; see the breaking notes.

### Added

- `$client->credits()` reads the account status (credit buckets, rate limit, plan, sandbox flag).
- `$client->verify->submitJob(['emails' => ...])` and `$client->verify->getJob($id)` for asynchronous verification jobs.
- `$client->guard->recordEvents($events)` records Email-Guard decision events.
- `getDecision()` result field. The v2 fields from 0.2.0 are read through getters now, see the breaking notes.
- `VerifyResource::isVerifyResult($item)` type guard for batch entries.

### Changed (breaking, acceptable on 0.x)

- **Now depends on `guzzlehttp/guzzle`** (was dependency-free, ext-curl only). Guzzle is the de-facto standard PHP HTTP client. This is the trade-off for generating the client from the spec.
- Results are generated models read with `get<Field>()` accessors (e.g. `$result->getResult()` instead of `$result->result`). The old `VerifyResult` / `BatchResponse` / `BatchItemError` value objects and the `Http\Transport` abstraction are removed.
- `$client->verify->single(...)` / `batch(...)` keep the array-param call convention; `batch()` now returns the generated `VerifyBatchResponse` (`->getResults()`).
- Constructor: the `transport:` parameter is replaced by `httpClient:` (inject a configured `GuzzleHttp\Client`).
- Named exception classes (`AuthenticationException`, `InsufficientCreditsException`, `RateLimitException`, …), `creditsRemaining`, `rateLimit`, and the `ES_KEY` / `EMAILSHERLOCK_API_KEY` env fallback are unchanged. `ValidationException` now also covers `404`.
- The wire stays snake_case (no field renaming).

## 0.2.1

Documentation and package metadata links now point at hostsherlock.com. The API host `api.emailsherlock.com` is unchanged. `Client::VERSION` (and so the User-Agent) reports the right version again; 0.2.0 still said 0.1.0.

## 0.2.0

Verify response v2 fields on the hand-written client. `VerifyResult` gains `deliverable`, `reason`, `mxRecord`, `freeEmail`, `checkedAt` and a `domain` object (`VerifyDomain`: types, trust score, SPF/DKIM/DMARC and other mail-security signals). The new fields are `null` on responses from API servers that do not send them yet.

## 0.1.0

Initial release: `verify->single` / `verify->batch`, named exception classes, credit and rate-limit accessors. Dependency-free (ext-curl + ext-json).
