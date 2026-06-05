<?php

declare(strict_types=1);

namespace Emailsherlock;

/** A per-address failure inside a batch response. */
final class BatchItemError
{
    public function __construct(
        public readonly ?string $email,
        public readonly string $error, // invalid_email|insufficient_credits|verify_unavailable
    ) {
    }
}
