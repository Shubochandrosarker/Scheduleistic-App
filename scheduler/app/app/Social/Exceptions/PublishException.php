<?php

namespace App\Social\Exceptions;

use RuntimeException;

/**
 * Thrown by a provider driver when a publish attempt fails. The engine
 * decides whether to retry based on $retryable.
 */
class PublishException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
