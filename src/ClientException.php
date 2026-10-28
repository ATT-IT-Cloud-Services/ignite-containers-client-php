<?php

declare(strict_types=1);

namespace IgniteContainers;

/**
 * Error returned by the Ignite Containers control plane.
 *
 * Clients should branch on $errorCode, never on the message text.
 */
final class ClientException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?array $details = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    public function isConflict(): bool
    {
        return $this->status === 409;
    }

    public function isUnauthorized(): bool
    {
        return $this->status === 401;
    }
}
