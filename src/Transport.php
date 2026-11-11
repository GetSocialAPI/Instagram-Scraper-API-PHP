<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram;

use GetSocialAPI\Instagram\Exception\BadRequestError;
use GetSocialAPI\Instagram\Exception\GetSocialAPIError;
use GetSocialAPI\Instagram\Exception\InstagramError;
use GetSocialAPI\Instagram\Exception\NotFoundError;
use GetSocialAPI\Instagram\Exception\RateLimitError;
use GetSocialAPI\Instagram\Exception\ServiceUnavailableError;
use GetSocialAPI\Instagram\Exception\UnauthorizedError;

/**
 * HTTP transport: auth, JSON parsing, retries with Retry-After.
 */
final class Transport implements TransportInterface
{
    public const DEFAULT_BASE_URL = 'https://api.getsocialapi.com/v1';

    private const RETRYABLE_STATUS = [429, 500, 502, 503, 504];

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $maxRetries;

    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeout = 30,
        int $maxRetries = 3,
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = \rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    public function request(string $method, string $path, array $params = [], bool $auth = true): array
    {
        $url = $this->baseUrl . $path;
        if ($params !== []) {
            $url .= '?' . \http_build_query($params);
        }
        $headers = \array_merge(['Accept: application/json'], $auth ? ['X-API-Key: ' . $this->apiKey] : []);
        $attempts = $this->maxRetries + 1;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            [$status, $respHeaders, $raw] = $this->send($method, $url, $headers);

            if ($status === null) {
                if ($attempt < $attempts - 1) {
                    \usleep((int) (\min(2 ** $attempt, 10) * 1_000_000));
                    continue;
                }
                throw new GetSocialAPIError('Network error: request failed');
            }

            $retryable = \in_array($status, self::RETRYABLE_STATUS, true);
            if ($retryable && $attempt < $attempts - 1) {
                \sleep(self::retryAfter($respHeaders));
                continue;
            }

            return self::parse($status, $respHeaders, $raw);
        }

        throw new GetSocialAPIError('Request failed after retries'); // @codeCoverageIgnore
    }

    /**
     * Map an HTTP response to a decoded body or a typed exception.
     *
     * @param array<string, string> $headers lowercase header names
     *
     * @return array<string, mixed>
     */
    public static function parse(int $status, array $headers, string $raw): array
    {
        $body = \json_decode($raw, true);
        $body = \is_array($body) ? $body : ['raw' => $raw];

        if ($status === 401) {
            throw new UnauthorizedError($status, $headers, $body);
        }
        if ($status === 400) {
            throw new BadRequestError($status, $headers, $body);
        }
        if ($status === 404) {
            throw new NotFoundError($status, $headers, $body);
        }
        if ($status === 429) {
            throw new RateLimitError($status, $headers, $body, self::retryAfter($headers));
        }
        if ($status === 503) {
            throw new ServiceUnavailableError($status, $headers, $body, self::retryAfter($headers));
        }
        if ($status >= 400) {
            // Instagram errors in raw mode: {"message": "...", "status": "fail"}
            if (($body['status'] ?? null) === 'fail') {
                throw new InstagramError(
                    $status,
                    (string) ($body['message'] ?? 'unknown'),
                    $status,
                    $headers,
                    $body
                );
            }
            throw new GetSocialAPIError("HTTP {$status}: {$raw}", $status, $headers, $body);
        }

        return $body;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function retryAfter(array $headers): int
    {
        $value = $headers['retry-after'] ?? null;
        if ($value === null || !\is_numeric($value)) {
            return 1;
        }

        return (int) \min(\max((float) $value, 0.0), 30.0);
    }

    /**
     * Send one HTTP request via cURL.
     *
     * @param list<string> $headers
     *
     * @return array{0: ?int, 1: array<string, string>, 2: string}
     */
    private function send(string $method, string $url, array $headers): array
    {
        $responseHeaders = [];
        $ch = \curl_init($url);
        if ($ch === false) {
            return [null, [], ''];
        }

        \curl_setopt_array($ch, [
            \CURLOPT_CUSTOMREQUEST => \strtoupper($method),
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_CONNECTTIMEOUT => $this->timeout,
            \CURLOPT_TIMEOUT => $this->timeout,
            \CURLOPT_HTTPHEADER => $headers,
            \CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
                $len = \strlen($line);
                $parts = \explode(':', $line, 2);
                if (\count($parts) === 2) {
                    $responseHeaders[\strtolower(\trim($parts[0]))] = \trim($parts[1]);
                }

                return $len;
            },
        ]);

        $raw = \curl_exec($ch);
        if ($raw === false || !\is_string($raw)) {
            \curl_close($ch);

            return [null, [], ''];
        }

        $status = (int) \curl_getinfo($ch, \CURLOPT_RESPONSE_CODE);
        \curl_close($ch);

        return [$status, $responseHeaders, $raw];
    }
}
