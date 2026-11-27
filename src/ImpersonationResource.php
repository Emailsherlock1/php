<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Generated\Api\ImpersonationApi;
use Emailsherlock\Generated\Model\ImpersonationAnalyzeRequest;
use Emailsherlock\Generated\Model\ImpersonationAnalyzeResponse;

/** Impersonation analysis, reached as $client->impersonation. */
final class ImpersonationResource
{
    public function __construct(
        private readonly Client $client,
        private readonly ImpersonationApi $api,
    ) {
    }

    /**
     * Score one message's sender header fields against the identities you
     * protect. Costs credits. The request has no subject or body field.
     *
     * @param array{
     *     from: string,
     *     display_name?: ?string,
     *     reply_to?: ?string,
     *     dmarc?: ?string,
     *     protected: array<int, array{name: string, domains?: array<int, string>, addresses?: array<int, string>}>
     * } $request
     */
    public function analyze(array $request): ImpersonationAnalyzeResponse
    {
        return $this->client->call(fn () => $this->api->analyzeImpersonationWithHttpInfo(
            new ImpersonationAnalyzeRequest($request),
        ));
    }
}
