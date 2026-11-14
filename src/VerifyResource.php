<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Generated\Api\VerifyApi;
use Emailsherlock\Generated\Model\VerifyBatchRequest;
use Emailsherlock\Generated\Model\VerifyBatchResponse;
use Emailsherlock\Generated\Model\VerifyBatchResponseResultsInner;
use Emailsherlock\Generated\Model\VerifyJobRequest;
use Emailsherlock\Generated\Model\VerifyJobResponse;
use Emailsherlock\Generated\Model\VerifyResultResponse;
use Emailsherlock\Generated\Model\VerifySingleRequest;

/** Verify-endpoint methods, reached as $client->verify. */
final class VerifyResource
{
    public function __construct(
        private readonly Client $client,
        private readonly VerifyApi $api,
    ) {
    }

    /** Verify a single address. @param array{email: string} $params */
    public function single(array $params): VerifyResultResponse
    {
        return $this->client->call(fn () => $this->api->verifySingleWithHttpInfo(
            new VerifySingleRequest(['email' => $params['email'] ?? '']),
        ));
    }

    /** Verify a batch of addresses in one call. @param array{emails: array<int, string>} $params */
    public function batch(array $params): VerifyBatchResponse
    {
        return $this->client->call(fn () => $this->api->verifyBatchWithHttpInfo(
            new VerifyBatchRequest(['emails' => array_values($params['emails'] ?? [])]),
        ));
    }

    /**
     * Submit a list of addresses for asynchronous verification. Poll the
     * returned job's id with getJob until its status is "completed".
     *
     * @param array{emails: array<int, string>} $params
     */
    public function submitJob(array $params): VerifyJobResponse
    {
        return $this->client->call(fn () => $this->api->submitVerifyJobWithHttpInfo(
            new VerifyJobRequest(['emails' => array_values($params['emails'] ?? [])]),
        ));
    }

    /** Read the status and results of a verification job. */
    public function getJob(string $id): VerifyJobResponse
    {
        return $this->client->call(fn () => $this->api->getVerifyJobWithHttpInfo($id));
    }

    /**
     * Type guard for a batch entry: did this address verify (true) or fail
     * (false)? A failed entry carries a non-null error code.
     */
    public static function isVerifyResult(VerifyBatchResponseResultsInner $item): bool
    {
        return $item->getError() === null;
    }
}
