<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram\Exception;

/**
 * Non-404 error returned by the Instagram private API (raw mode).
 */
class InstagramError extends GetSocialAPIError
{
    /** Upstream Instagram status code. */
    public int $status;

    public function __construct(
        int $status,
        string $message,
        ?int $httpStatus = null,
        array $headers = [],
        array $body = [],
    ) {
        parent::__construct("Instagram API error {$status}: {$message}", $httpStatus, $headers, $body);
        $this->status = $status;
    }
}
