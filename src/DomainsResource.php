<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Generated\Api\DomainReportsApi;
use Emailsherlock\Generated\Model\ListDomainChanges200Response;
use Emailsherlock\Generated\Model\NextStepsResource;

/** Methods for domains your account watches, reached as $client->domains. */
final class DomainsResource
{
    public function __construct(
        private readonly Client $client,
        private readonly DomainReportsApi $api,
    ) {
    }

    /**
     * The timeline of a domain you watch: DNS, record and mail changes, newest
     * first.
     *
     * @param array{limit?: int, offset?: int} $params
     */
    public function changes(string $domain, array $params = []): ListDomainChanges200Response
    {
        return $this->client->call(fn () => $this->api->listDomainChangesWithHttpInfo(
            $domain,
            $params['limit'] ?? null,
            $params['offset'] ?? null,
        ));
    }

    /**
     * What to change next on a domain you own: the steps towards p=reject,
     * the sources that still fail DMARC, and the open findings. Needs your
     * account's report address in the domain's DMARC record.
     */
    public function nextSteps(string $domain): NextStepsResource
    {
        return $this->client->call(fn () => $this->api->getDomainNextStepsWithHttpInfo($domain));
    }
}
