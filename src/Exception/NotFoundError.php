<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram\Exception;

/**
 * 404 - target object does not exist. Do not retry.
 */
class NotFoundError extends GetSocialAPIError
{
    public function __construct(?int $httpStatus = null, array $headers = [], array $body = [])
    {
        parent::__construct(self::reason($body, 'Not found'), $httpStatus, $headers, $body);
    }
}
