<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram\Exception;

/**
 * 401 - invalid or missing API key.
 */
class UnauthorizedError extends GetSocialAPIError
{
    public function __construct(?int $httpStatus = null, array $headers = [], array $body = [])
    {
        parent::__construct('Invalid API key', $httpStatus, $headers, $body);
    }
}
