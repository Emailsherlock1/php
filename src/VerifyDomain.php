<?php

declare(strict_types=1);

namespace Emailsherlock;

/** Domain-level intelligence attached to a verify result. Mirrors the API JSON. */
final class VerifyDomain
{
    /** @param list<string>|null $types */
    public function __construct(
        public readonly string $name,
        // list of: freemail, disposable, custom, company, government, education, public, isp
        public readonly ?array $types = null,
        public readonly ?float $score = null, // 0-100
        public readonly ?bool $spf = null,
        public readonly ?bool $dkim = null,
        public readonly ?bool $dmarc = null,
        public readonly ?string $dmarcPolicy = null, // none|quarantine|reject
        public readonly ?bool $mtaSts = null,
        public readonly ?bool $tlsRpt = null,
        public readonly ?bool $bimi = null,
        public readonly ?bool $dane = null,
        public readonly ?int $blacklists = null,
        public readonly ?string $dnssec = null, // secure|insecure|bogus
        public readonly ?bool $caa = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            types: isset($data['types']) && is_array($data['types'])
                ? array_values(array_map(strval(...), $data['types']))
                : null,
            score: isset($data['score']) ? (float) $data['score'] : null,
            spf: isset($data['spf']) ? (bool) $data['spf'] : null,
            dkim: isset($data['dkim']) ? (bool) $data['dkim'] : null,
            dmarc: isset($data['dmarc']) ? (bool) $data['dmarc'] : null,
            dmarcPolicy: isset($data['dmarc_policy']) ? (string) $data['dmarc_policy'] : null,
            mtaSts: isset($data['mta_sts']) ? (bool) $data['mta_sts'] : null,
            tlsRpt: isset($data['tls_rpt']) ? (bool) $data['tls_rpt'] : null,
            bimi: isset($data['bimi']) ? (bool) $data['bimi'] : null,
            dane: isset($data['dane']) ? (bool) $data['dane'] : null,
            blacklists: isset($data['blacklists']) ? (int) $data['blacklists'] : null,
            dnssec: isset($data['dnssec']) ? (string) $data['dnssec'] : null,
            caa: isset($data['caa']) ? (bool) $data['caa'] : null,
        );
    }
}
