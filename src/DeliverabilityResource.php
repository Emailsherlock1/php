<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Generated\Api\DeliverabilityApi;
use Emailsherlock\Generated\Model\DeliverabilityLatestVerdictResource;
use Emailsherlock\Generated\Model\DeliverabilityWebhookResource;
use Emailsherlock\Generated\Model\GetCheck200Response;
use Emailsherlock\Generated\Model\ListCheckAddresses200Response;
use Emailsherlock\Generated\Model\ListChecks200Response;
use Emailsherlock\Generated\Model\SetCheckWebhookRequest;

/** Deliverability check-address methods, reached as $client->deliverability. */
final class DeliverabilityResource
{
    public function __construct(
        private readonly Client $client,
        private readonly DeliverabilityApi $api,
    ) {
    }

    /** List your check addresses, each with its latest verdict. */
    public function listAddresses(): ListCheckAddresses200Response
    {
        return $this->client->call(fn () => $this->api->listCheckAddressesWithHttpInfo());
    }

    /**
     * The pre-send gate: the latest cached verdict for an address. The API runs
     * no live probe for this call.
     */
    public function latest(int $id): DeliverabilityLatestVerdictResource
    {
        return $this->client->call(fn () => $this->api->getLatestVerdictWithHttpInfo($id));
    }

    /**
     * The check log for one address, newest first.
     *
     * @param array{limit?: int, offset?: int} $params
     */
    public function listChecks(int $id, array $params = []): ListChecks200Response
    {
        return $this->client->call(fn () => $this->api->listChecksWithHttpInfo(
            $id,
            $params['limit'] ?? null,
            $params['offset'] ?? null,
        ));
    }

    /** One check with its full audit breakdown. */
    public function getCheck(int $id): GetCheck200Response
    {
        return $this->client->call(fn () => $this->api->getCheckWithHttpInfo($id));
    }

    /**
     * Wire (or replace) the check.completed webhook for an address. The
     * response carries the signing secret; the API returns it only on this call.
     */
    public function setWebhook(int $id, string $url): DeliverabilityWebhookResource
    {
        return $this->client->call(fn () => $this->api->setCheckWebhookWithHttpInfo(
            $id,
            new SetCheckWebhookRequest(['url' => $url]),
        ));
    }

    /** Remove the check.completed webhook for an address. */
    public function deleteWebhook(int $id): DeliverabilityWebhookResource
    {
        return $this->client->call(fn () => $this->api->deleteCheckWebhookWithHttpInfo($id));
    }
}
