<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown by API-backed enrichment services (YouTubeMomentService, etc.) when an
 * HTTP call fails, carrying the normalized fetch_status + HTTP status so the
 * enricher can record them on the run row without re-parsing the response.
 */
class HttpFailureException extends RuntimeException
{
    public function __construct(
        public readonly string $status,
        public readonly ?int $httpStatus,
        string $message = '',
    ) {
        parent::__construct($message ?: "HTTP failure: {$status} ({$httpStatus})");
    }
}