<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Generated\Api\GuardApi;
use Emailsherlock\Generated\Model\GuardEventsRequest;

/** Email-Guard event methods, reached as $client->guard. */
final class GuardResource
{
    public function __construct(
        private readonly Client $client,
        private readonly GuardApi $api,
    ) {
    }

    /**
     * Record a batch of Email-Guard decision events (free, no credits). The full
     * email address is never sent, only the domain.
     *
     * @param array<int, array<string, mixed>> $events
     */
    public function recordEvents(array $events): void
    {
        $this->client->call(fn () => $this->api->recordGuardEventsWithHttpInfo(
            new GuardEventsRequest(['events' => array_values($events)]),
        ));
    }
}
