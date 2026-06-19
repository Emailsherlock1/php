# Changelog

## 0.2.0

The client and models are now generated from the EmailSherlock OpenAPI spec
(namespace `Emailsherlock\Generated`), with a thin hand-maintained sugar layer
on top. Most of the v0.1.0 surface carries over; see the breaking notes.

### Added

- `$client->credits()` reads the account status (credit buckets, rate limit, plan, sandbox flag).
- `$client->verify->submitJob(['emails' => ...])` and `$client->verify->getJob($id)` for asynchronous verification jobs.
- `$client->guard->recordEvents($events)` records Email-Guard decision events.
- Richer result fields via getters: `getDeliverable()`, `getReason()`, `getMxRecord()`, `getFreeEmail()`, `getCheckedAt()`, `getDomain()` (full domain intelligence), `getDecision()`.
- `VerifyResource::isVerifyResult($item)` type guard for batch entries.

### Changed (breaking, acceptable on 0.x)

- **Now depends on `guzzlehttp/guzzle`** (was dependency-free, ext-curl only). Guzzle is the de-facto standard PHP HTTP client. This is the trade-off for generating the client from the spec.
- Results are generated models read with `get<Field>()` accessors (e.g. `$result->getResult()` instead of `$result->result`). The old `VerifyResult` / `BatchResponse` / `BatchItemError` value objects and the `Http\Transport` abstraction are removed.
- `$client->verify->single(...)` / `batch(...)` keep the array-param call convention; `batch()` now returns the generated `VerifyBatchResponse` (`->getResults()`).
- Constructor: the `transport:` parameter is replaced by `httpClient:` (inject a configured `GuzzleHttp\Client`).
- Named exception classes (`AuthenticationException`, `InsufficientCreditsException`, `RateLimitException`, …), `creditsRemaining`, `rateLimit`, and the `ES_KEY` / `EMAILSHERLOCK_API_KEY` env fallback are unchanged. `ValidationException` now also covers `404`.
- The wire stays snake_case (no field renaming).

## 0.1.0

Initial release: `verify->single` / `verify->batch`, named exception classes, credit and rate-limit accessors. Dependency-free (ext-curl + ext-json).
