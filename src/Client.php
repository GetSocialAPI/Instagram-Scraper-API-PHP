<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram;

/**
 * GetSocialAPI client.
 *
 * Usage:
 *     $client = new Client(apiKey: 'gsa_live_...');
 *     $client->instagram->userByUsername('durov');
 */
final class Client
{
    public readonly InstagramAPI $instagram;

    private readonly Transport $transport;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        int $timeout = 30,
        int $maxRetries = 3,
    ) {
        $apiKey ??= (\getenv('GETSOCIALAPI_API_KEY') ?: null);
        if ($apiKey === null || $apiKey === '') {
            throw new \InvalidArgumentException(
                'api_key is required (pass $apiKey or set GETSOCIALAPI_API_KEY)'
            );
        }
        $this->transport = new Transport(
            $apiKey,
            $baseUrl ?? Transport::DEFAULT_BASE_URL,
            $timeout,
            $maxRetries,
        );
        $this->instagram = new InstagramAPI($this->transport);
    }

    /**
     * Upstream liveness probe: {"ok": true, "state": "ready"}.
     */
    public function health(): array
    {
        return $this->transport->request('GET', '/health', auth: false);
    }
}
