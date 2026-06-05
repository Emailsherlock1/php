<?php

declare(strict_types=1);

namespace Emailsherlock\Http;

/** Abstracts the HTTP layer so it can be swapped (e.g. faked in tests). */
interface Transport
{
    /**
     * @param array<string, string> $headers
     */
    public function send(string $method, string $url, array $headers, string $body, float $timeout): Response;
}
