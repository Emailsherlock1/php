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
use Emailsherlock\Http\Response;

/** Maps an error response to the right exception subclass. */
final class ErrorFactory
{
    /** @param array<string, mixed>|null $body */
    public static function fromResponse(Response $response, ?array $body): EmailsherlockException
    {
        $envelope = is_array($body['error'] ?? null) ? $body['error'] : [];
        $code = isset($envelope['code']) ? (string) $envelope['code'] : null;
        $message = isset($envelope['message']) ? (string) $envelope['message'] : 'HTTP ' . $response->status;
        $status = $response->status;

        return match ($status) {
            401 => new AuthenticationException($message, $status, $code),
            403 => new ForbiddenException(
                $message,
                $status,
                $code,
                requiredScope: $envelope['required_scope'] ?? $response->header('X-Required-Scope'),
            ),
            402 => new InsufficientCreditsException(
                $message,
                $status,
                $code,
                creditsRequired: self::num($response->header('X-Credits-Required')),
                creditsRemaining: self::num($response->header('X-Credits-Remaining')),
            ),
            429 => new RateLimitException(
                $message,
                $status,
                $code,
                retryAfter: self::int($response->header('Retry-After')),
                limit: self::int($response->header('X-RateLimit-Limit')),
                remaining: self::int($response->header('X-RateLimit-Remaining')),
                reset: self::int($response->header('X-RateLimit-Reset')),
            ),
            400, 422 => new ValidationException($message, $status, $code),
            503 => new ServiceUnavailableException($message, $status, $code),
            default => new EmailsherlockException($message, $status, $code),
        };
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
