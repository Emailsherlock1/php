<?php

declare(strict_types=1);

namespace Emailsherlock\Exception;

/** Base class for every exception this client throws. */
class EmailsherlockException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
