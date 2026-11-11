<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram;

/**
 * HTTP transport contract (mock it in tests).
 */
interface TransportInterface
{
    /**
     * Perform an API request and return the decoded JSON body.
     *
     * @param array<string, mixed> $params query parameters
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $params = [], bool $auth = true): array;
}
