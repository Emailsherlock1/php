<?php

declare(strict_types=1);

namespace Emailsherlock\Exception;

/** 429 - per-key sliding-window rate limit hit. */
final class RateLimitException extends EmailsherlockException
{
    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $errorCode = null,
        public readonly ?int $retryAfter = null,
        public readonly ?int $limit = null,
        public readonly ?int $remaining = null,
        public readonly ?int $reset = null,
    ) {
        parent::__construct($message, $statusCode, $errorCode);
    }
}
