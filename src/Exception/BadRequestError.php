<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram\Exception;

/**
 * 400 - invalid parameters (body: {"error", "field"}).
 */
class BadRequestError extends GetSocialAPIError
{
    /** Name of the invalid parameter, when the API reports one. */
    public ?string $field;

    public function __construct(?int $httpStatus = null, array $headers = [], array $body = [])
    {
        $field = $body['field'] ?? null;
        $field = \is_string($field) ? $field : null;
        $msg = self::reason($body, 'Bad request');
        if ($field !== null) {
            $msg .= " (field: {$field})";
        }
        parent::__construct($msg, $httpStatus, $headers, $body);
        $this->field = $field;
    }
}
