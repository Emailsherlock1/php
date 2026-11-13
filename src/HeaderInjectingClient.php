<?php

declare(strict_types=1);

namespace Emailsherlock;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Wraps any Guzzle client and sets one fixed header on every request, so the
 * header also reaches calls made through a caller-supplied $httpClient.
 *
 * @internal
 */
final class HeaderInjectingClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly string $name,
        private readonly string $value,
    ) {
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->inner->send($request->withHeader($this->name, $this->value), $options);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->inner->sendAsync($request->withHeader($this->name, $this->value), $options);
    }

    public function request(string $method, $uri, array $options = []): ResponseInterface
    {
        $options['headers'] = [$this->name => $this->value] + ($options['headers'] ?? []);

        return $this->inner->request($method, $uri, $options);
    }

    public function requestAsync(string $method, $uri, array $options = []): PromiseInterface
    {
        $options['headers'] = [$this->name => $this->value] + ($options['headers'] ?? []);

        return $this->inner->requestAsync($method, $uri, $options);
    }

    public function getConfig(?string $option = null)
    {
        return $this->inner->getConfig($option);
    }
}
