<?php

declare(strict_types=1);

namespace Emailsherlock\Http;

/** A minimal HTTP response DTO passed back from a Transport. */
final class Response
{
    /**
     * @param array<string, string> $headers lower-cased header name => value
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
