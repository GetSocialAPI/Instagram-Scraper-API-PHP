<?php

declare(strict_types=1);

namespace GetSocialAPI\Instagram\Exception;

/**
 * Base class for all GetSocialAPI Instagram SDK failures.
 */
class GetSocialAPIError extends \Exception
{
    /** HTTP status code of the failing response (null for network errors). */
    public ?int $httpStatus;

    /** Headers of the failing response. */
    public array $headers;

    /** Decoded JSON body of the failing response (or ["raw" => ...]). */
    public array $body;

    public function __construct(
        string $message,
        ?int $httpStatus = null,
        array $headers = [],
        array $body = [],
    ) {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * Extract a human-readable reason:
     * {"error": "..."} | {"error": {"message": "..."}} | {"message": "..."}.
     */
    final protected static function reason(array $body, string $fallback): string
    {
        $err = $body['error'] ?? null;
        if (\is_array($err)) {
            $err = $err['message'] ?? null;
        }
        if (\is_string($err) && $err !== '') {
            return $err;
        }
        $msg = $body['message'] ?? null;
        if (\is_string($msg) && $msg !== '') {
            return $msg;
        }

        return $fallback;
    }
}
