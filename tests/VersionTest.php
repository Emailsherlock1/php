<?php

declare(strict_types=1);

namespace Emailsherlock\Tests;

use Emailsherlock\Client;
use PHPUnit\Framework\TestCase;

/**
 * Client::VERSION goes out in the User-Agent, so a release that forgets to
 * bump it reports the previous version (v0.2.0 shipped as 0.1.0, EM-4735).
 * The newest CHANGELOG heading is the release being prepared.
 */
final class VersionTest extends TestCase
{
    public function testVersionMatchesNewestChangelogHeading(): void
    {
        $changelog = file_get_contents(__DIR__ . '/../CHANGELOG.md');
        self::assertIsString($changelog);

        $found = preg_match('/^## \[?v?(\d+\.\d+\.\d+[^\]\s]*)/m', $changelog, $match);
        self::assertSame(1, $found, 'CHANGELOG.md has no "## x.y.z" heading.');

        self::assertSame(
            $match[1],
            Client::VERSION,
            'Client::VERSION does not match the newest CHANGELOG.md heading. Bump one of them.',
        );
    }
}
