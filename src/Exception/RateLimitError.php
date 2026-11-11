<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram\Exception;

/**
 * 429 - key rate limit exceeded. Honors Retry-After.
 */
class RateLimitError extends GetSocialAPIError
{
    /** Seconds to wait before the next attempt (from Retry-After). */
    public int $retryAfter;

    public function __construct(
        ?int $httpStatus = null,
        array $headers = [],
        array $body = [],
        int $retryAfter = 1,
    ) {
        parent::__construct("Rate limit exceeded, retry after {$retryAfter}s", $httpStatus, $headers, $body);
        $this->retryAfter = $retryAfter;
    }
}
