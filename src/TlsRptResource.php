<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Generated\Api\DomainReportsApi;
use Emailsherlock\Generated\Model\ListTlsRptReports200Response;

/**
 * TLS-RPT report methods, reached as $client->tlsRpt. The reads need your
 * account's report address in the domain's TLS-RPT record.
 */
final class TlsRptResource
{
    public function __construct(
        private readonly Client $client,
        private readonly DomainReportsApi $api,
    ) {
    }

    /**
     * The domain's TLS-RPT reports, newest first.
     *
     * @param array{range?: 7|30, limit?: int, offset?: int} $params
     */
    public function reports(string $domain, array $params = []): ListTlsRptReports200Response
    {
        return $this->client->call(fn () => $this->api->listTlsRptReportsWithHttpInfo(
            $domain,
            $params['range'] ?? null,
            $params['limit'] ?? null,
            $params['offset'] ?? null,
        ));
    }
}
