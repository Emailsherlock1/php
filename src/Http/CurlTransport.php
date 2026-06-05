<?php

declare(strict_types=1);

namespace Emailsherlock\Http;

use Emailsherlock\Exception\EmailsherlockException;

/** Default Transport using ext-curl. */
final class CurlTransport implements Transport
{
    public function send(string $method, string $url, array $headers, string $body, float $timeout): Response
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new EmailsherlockException('Could not initialise cURL.', errorCode: 'network_error');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ceil($timeout),
            CURLOPT_HEADERFUNCTION => function ($_ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new EmailsherlockException('Request failed: ' . $error, errorCode: 'network_error');
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response($status, (string) $responseBody, $responseHeaders);
    }
}
