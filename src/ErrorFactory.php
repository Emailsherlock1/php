<?php

declare(strict_types=1);

namespace Emailsherlock;

use Emailsherlock\Exception\AuthenticationException;
use Emailsherlock\Exception\EmailsherlockException;
use Emailsherlock\Exception\ForbiddenException;
use Emailsherlock\Exception\InsufficientCreditsException;
use Emailsherlock\Exception\RateLimitException;
use Emailsherlock\Exception\ServiceUnavailableException;
use Emailsherlock\Exception\ValidationException;
use Emailsherlock\Generated\ApiException;

/** Maps the generated ApiException to the right named exception subclass. */
final class ErrorFactory
{
    public static function fromApiException(ApiException $e): EmailsherlockException
    {
        $status = $e->getCode();
        $headers = $e->getResponseHeaders() ?? [];
        $header = static function (string $name) use ($headers): ?string {
            foreach ($headers as $key => $values) {
                if (strcasecmp((string) $key, $name) === 0) {
                    return is_array($values) ? ($values[0] ?? null) : (string) $values;
                }
            }
            return null;
        };

        $body = self::decodeBody($e->getResponseBody());
        $envelope = is_array($body['error'] ?? null) ? $body['error'] : [];
        $code = isset($envelope['code']) ? (string) $envelope['code'] : null;
        $message = isset($envelope['message']) ? (string) $envelope['message'] : 'HTTP ' . $status;

        return match ($status) {
            401 => new AuthenticationException($message, $status, $code),
            403 => new ForbiddenException(
                $message,
                $status,
                $code,
                requiredScope: $envelope['required_scope'] ?? $header('X-Required-Scope'),
            ),
            402 => new InsufficientCreditsException(
                $message,
                $status,
                $code,
                creditsRequired: self::num($header('X-Credits-Required')),
                creditsRemaining: self::num($header('X-Credits-Remaining')),
            ),
            429 => new RateLimitException(
                $message,
                $status,
                $code,
                retryAfter: self::int($header('Retry-After')),
                limit: self::int($header('X-RateLimit-Limit')),
                remaining: self::int($header('X-RateLimit-Remaining')),
                reset: self::int($header('X-RateLimit-Reset')),
            ),
            400, 404, 422 => new ValidationException($message, $status, $code),
            503 => new ServiceUnavailableException($message, $status, $code),
            default => new EmailsherlockException($message, $status, $code),
        };
    }

    /**
     * The raw client hands back the error body as a string or a decoded object.
     *
     * @return array<string, mixed>|null
     */
    private static function decodeBody(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }
        if (is_object($raw)) {
            $decoded = json_decode((string) json_encode($raw), true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($raw) ? $raw : null;
    }

    private static function num(?string $value): ?float
    {
        return $value === null || !is_numeric($value) ? null : (float) $value;
    }

    private static function int(?string $value): ?int
    {
        return $value === null || !is_numeric($value) ? null : (int) $value;
    }
}
