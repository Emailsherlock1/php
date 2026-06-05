<?php

declare(strict_types=1);

namespace Emailsherlock;

/** Verify-endpoint methods, reached as $client->verify. */
final class VerifyResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @param array{email: string} $params */
    public function single(array $params): VerifyResult
    {
        $data = $this->client->request('/v1/verify/single', ['email' => $params['email'] ?? '']);

        return VerifyResult::fromArray($data);
    }

    /** @param array{emails: array<int, string>} $params */
    public function batch(array $params): BatchResponse
    {
        $data = $this->client->request('/v1/verify/batch', ['emails' => array_values($params['emails'] ?? [])]);

        return BatchResponse::fromArray($data);
    }
}
