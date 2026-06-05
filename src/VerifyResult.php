<?php

declare(strict_types=1);

namespace Emailsherlock;

/** The result for one verified address. Mirrors the API JSON. */
final class VerifyResult
{
    public function __construct(
        public readonly string $email,
        public readonly string $result,   // valid|invalid|catch_all|disposable|role|unknown
        public readonly bool $mx,
        public readonly bool $disposable,
        public readonly bool $role,
        public readonly bool $catchAll,
        public readonly float $score,
        public readonly string $freshness, // fresh|cached_recent|cached_stale_refreshed
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) ($data['email'] ?? ''),
            result: (string) ($data['result'] ?? 'unknown'),
            mx: (bool) ($data['mx'] ?? false),
            disposable: (bool) ($data['disposable'] ?? false),
            role: (bool) ($data['role'] ?? false),
            catchAll: (bool) ($data['catch_all'] ?? false),
            score: (float) ($data['score'] ?? 0.0),
            freshness: (string) ($data['freshness'] ?? ''),
        );
    }
}
