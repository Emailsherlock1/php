<?php

declare(strict_types=1);

namespace Emailsherlock\Exception;

/** 403 - the key lacks the scope this endpoint needs. */
final class ForbiddenException extends EmailsherlockException
{
    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $errorCode = null,
        public readonly ?string $requiredScope = null,
    ) {
        parent::__construct($message, $statusCode, $errorCode);
    }
}
