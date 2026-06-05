<?php

declare(strict_types=1);

namespace Emailsherlock;

/** The response of a batch call: a list of results and/or per-item errors. */
final class BatchResponse
{
    /** @param array<int, VerifyResult|BatchItemError> $results */
    public function __construct(
        public readonly array $results,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $items = [];
        foreach ($data['results'] ?? [] as $item) {
            if (is_array($item) && isset($item['error'])) {
                $items[] = new BatchItemError(
                    email: isset($item['email']) ? (string) $item['email'] : null,
                    error: (string) $item['error'],
                );
            } else {
                $items[] = VerifyResult::fromArray(is_array($item) ? $item : []);
            }
        }

        return new self($items);
    }
}
