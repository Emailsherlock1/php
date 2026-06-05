<?php

declare(strict_types=1);

namespace Emailsherlock\Exception;

/** 402 - not enough credits on the wallet for this request. */
final class InsufficientCreditsException extends EmailsherlockException
{
    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $errorCode = null,
        public readonly ?float $creditsRequired = null,
        public readonly ?float $creditsRemaining = null,
    ) {
        parent::__construct($message, $statusCode, $errorCode);
    }
}
