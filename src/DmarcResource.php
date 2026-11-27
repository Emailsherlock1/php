<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Generated\Api\DomainReportsApi;
use Emailsherlock\Generated\Api\DomainSetupApi;
use Emailsherlock\Generated\Model\DmarcSetupResource;
use Emailsherlock\Generated\Model\DmarcSummaryResource;
use Emailsherlock\Generated\Model\ListDmarcReports200Response;
use Emailsherlock\Generated\Model\ListDmarcSources200Response;

/**
 * DMARC setup and report methods, reached as $client->dmarc. The reads
 * (summary, reports, sources) need your account's report address in the
 * domain's DMARC record; watching a domain does not unlock its reports.
 */
final class DmarcResource
{
    public function __construct(
        private readonly Client $client,
        private readonly DomainSetupApi $setupApi,
        private readonly DomainReportsApi $reportsApi,
    ) {
    }

    /**
     * Watch the domain and get the one DNS change that routes its DMARC
     * reports to your account.
     */
    public function start(string $domain): DmarcSetupResource
    {
        return $this->client->call(fn () => $this->setupApi->startDmarcMonitoringWithHttpInfo($domain));
    }

    /**
     * Look up the domain's DMARC record again. Once the report address is
     * found, the domain is confirmed as yours and its reports unlock.
     */
    public function check(string $domain): DmarcSetupResource
    {
        return $this->client->call(fn () => $this->setupApi->checkDmarcSetupWithHttpInfo($domain));
    }

    /**
     * Totals of the domain's DMARC reports over 7 or 30 days.
     *
     * @param array{range?: 7|30} $params
     */
    public function summary(string $domain, array $params = []): DmarcSummaryResource
    {
        return $this->client->call(fn () => $this->reportsApi->getDmarcSummaryWithHttpInfo(
            $domain,
            $params['range'] ?? null,
        ));
    }

    /**
     * The domain's DMARC aggregate reports, newest first. `from` and `to` take
     * a YYYY-MM-DD string or a date object (sent as its Y-m-d date).
     *
     * @param array{from?: string|\DateTimeInterface, to?: string|\DateTimeInterface, reporter?: string, limit?: int, offset?: int} $params
     */
    public function reports(string $domain, array $params = []): ListDmarcReports200Response
    {
        return $this->client->call(fn () => $this->reportsApi->listDmarcReportsWithHttpInfo(
            $domain,
            self::day($params['from'] ?? null),
            self::day($params['to'] ?? null),
            $params['reporter'] ?? null,
            $params['limit'] ?? null,
            $params['offset'] ?? null,
        ));
    }

    /**
     * The senders seen in the domain's DMARC reports, ranked by volume.
     *
     * @param array{range?: 7|30, result?: 'pass'|'fail', check?: 'dmarc'|'spf'|'dkim', limit?: int, offset?: int} $params
     */
    public function sources(string $domain, array $params = []): ListDmarcSources200Response
    {
        return $this->client->call(fn () => $this->reportsApi->listDmarcSourcesWithHttpInfo(
            $domain,
            $params['range'] ?? null,
            $params['result'] ?? null,
            $params['check'] ?? null,
            $params['limit'] ?? null,
            $params['offset'] ?? null,
        ));
    }

    private static function day(string|\DateTimeInterface|null $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }
}
