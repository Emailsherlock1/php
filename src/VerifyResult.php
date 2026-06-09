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
        public readonly ?float $score,    // null when the API reports an unknown verdict
        public readonly string $freshness, // fresh|cached_recent|cached_stale_refreshed
        public readonly ?bool $deliverable = null,
        // bad_syntax|no_mx|mailbox_accepts|mailbox_not_found|disposable_provider|role_address|
        // catch_all_domain|greylisted|smtp_timeout|smtp_unreachable|verification_pending
        public readonly ?string $reason = null,
        public readonly ?string $mxRecord = null,
        public readonly ?bool $freeEmail = null,
        public readonly ?string $checkedAt = null, // ISO 8601
        public readonly ?VerifyDomain $domain = null,
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
            score: isset($data['score']) ? (float) $data['score'] : null,
            freshness: (string) ($data['freshness'] ?? ''),
            deliverable: isset($data['deliverable']) ? (bool) $data['deliverable'] : null,
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            mxRecord: isset($data['mx_record']) ? (string) $data['mx_record'] : null,
            freeEmail: isset($data['free_email']) ? (bool) $data['free_email'] : null,
            checkedAt: isset($data['checked_at']) ? (string) $data['checked_at'] : null,
            domain: isset($data['domain']) && is_array($data['domain'])
                ? VerifyDomain::fromArray($data['domain'])
                : null,
        );
    }
}
