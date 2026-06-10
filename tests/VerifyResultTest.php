<?php

declare(strict_types=1);

namespace Emailsherlock\Tests;

use Emailsherlock\VerifyDomain;
use Emailsherlock\VerifyResult;
use PHPUnit\Framework\TestCase;

final class VerifyResultTest extends TestCase
{
    public function testParsesV2Response(): void
    {
        $result = VerifyResult::fromArray([
            'email' => 'jane@acme.com',
            'result' => 'valid',
            'mx' => true,
            'disposable' => false,
            'role' => false,
            'catch_all' => false,
            'score' => 0.95,
            'freshness' => 'fresh',
            'deliverable' => true,
            'reason' => 'mailbox_accepts',
            'mx_record' => 'mx1.acme.com',
            'free_email' => false,
            'checked_at' => '2026-06-09T12:00:00+00:00',
            'domain' => [
                'name' => 'acme.com',
                'types' => ['company'],
                'score' => 87.5,
                'spf' => true,
                'dkim' => true,
                'dmarc' => true,
                'dmarc_policy' => 'reject',
                'mta_sts' => true,
                'tls_rpt' => false,
                'bimi' => false,
                'dane' => false,
                'blacklists' => 0,
                'dnssec' => 'secure',
                'caa' => true,
            ],
        ]);

        self::assertTrue($result->deliverable);
        self::assertSame('mailbox_accepts', $result->reason);
        self::assertSame('mx1.acme.com', $result->mxRecord);
        self::assertFalse($result->freeEmail);
        self::assertSame('2026-06-09T12:00:00+00:00', $result->checkedAt);
        self::assertSame(0.95, $result->score);

        self::assertInstanceOf(VerifyDomain::class, $result->domain);
        self::assertSame('acme.com', $result->domain->name);
        self::assertSame(['company'], $result->domain->types);
        self::assertSame(87.5, $result->domain->score);
        self::assertTrue($result->domain->spf);
        self::assertTrue($result->domain->dkim);
        self::assertTrue($result->domain->dmarc);
        self::assertSame('reject', $result->domain->dmarcPolicy);
        self::assertTrue($result->domain->mtaSts);
        self::assertFalse($result->domain->tlsRpt);
        self::assertFalse($result->domain->bimi);
        self::assertFalse($result->domain->dane);
        self::assertSame(0, $result->domain->blacklists);
        self::assertSame('secure', $result->domain->dnssec);
        self::assertTrue($result->domain->caa);
    }

    public function testToleratesV1ResponseWithoutNewFields(): void
    {
        $result = VerifyResult::fromArray([
            'email' => 'jane@acme.com',
            'result' => 'valid',
            'mx' => true,
            'disposable' => false,
            'role' => false,
            'catch_all' => false,
            'score' => 0.95,
            'freshness' => 'fresh',
        ]);

        self::assertSame('jane@acme.com', $result->email);
        self::assertSame(0.95, $result->score);
        self::assertNull($result->deliverable);
        self::assertNull($result->reason);
        self::assertNull($result->mxRecord);
        self::assertNull($result->freeEmail);
        self::assertNull($result->checkedAt);
        self::assertNull($result->domain);
    }

    public function testNullScoreOnUnknownVerdict(): void
    {
        $result = VerifyResult::fromArray([
            'email' => 'pending@acme.com',
            'result' => 'unknown',
            'mx' => true,
            'score' => null,
            'freshness' => 'fresh',
            'deliverable' => null,
            'reason' => 'verification_pending',
        ]);

        self::assertNull($result->score);
        self::assertNull($result->deliverable);
        self::assertSame('verification_pending', $result->reason);
    }

    public function testNullDomainStaysNull(): void
    {
        $result = VerifyResult::fromArray([
            'email' => 'jane@acme.com',
            'result' => 'invalid',
            'domain' => null,
        ]);

        self::assertNull($result->domain);
    }

    public function testSparseDomainObject(): void
    {
        $result = VerifyResult::fromArray([
            'email' => 'jane@acme.com',
            'result' => 'valid',
            'domain' => ['name' => 'acme.com'],
        ]);

        self::assertInstanceOf(VerifyDomain::class, $result->domain);
        self::assertSame('acme.com', $result->domain->name);
        self::assertNull($result->domain->types);
        self::assertNull($result->domain->score);
        self::assertNull($result->domain->spf);
        self::assertNull($result->domain->dmarcPolicy);
        self::assertNull($result->domain->dnssec);
        self::assertNull($result->domain->caa);
    }
}
