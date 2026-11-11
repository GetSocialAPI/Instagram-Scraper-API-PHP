<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram\Exception;

/**
 * 503 - no live accounts or the service is stopped.
 */
class ServiceUnavailableError extends GetSocialAPIError
{
    /** Seconds to wait before the next attempt, when provided. */
    public ?int $retryAfter;

    public function __construct(
        ?int $httpStatus = null,
        array $headers = [],
        array $body = [],
        ?int $retryAfter = null,
    ) {
        $msg = 'Service unavailable';
        if ($retryAfter !== null) {
            $msg .= ", retry after {$retryAfter}s";
        }
        parent::__construct($msg, $httpStatus, $headers, $body);
        $this->retryAfter = $retryAfter;
    }
}
