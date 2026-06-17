<?php

declare(strict_types=1);

namespace Croct\Plug\Exception;

/**
 * Reports a failure while communicating with the Croct API.
 *
 * This is the transport-level error, raised when a request cannot be sent or the API
 * returns an error response.
 */
final class ApiException extends \RuntimeException implements CroctException
{
    private ?int $statusCode;

    public function __construct(string $message, ?int $statusCode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->statusCode = $statusCode;
    }

    /**
     * Creates an exception from an RFC 7807 problem response.
     *
     * @param array<array-key, mixed>|null $problem
     */
    public static function fromProblem(int $status, ?array $problem): self
    {
        $title = $problem['title'] ?? null;

        return new self(
            \is_string($title) && $title !== ''
                ? $title
                : \sprintf('The Croct API responded with status %d.', $status),
            $status,
        );
    }

    /**
     * Creates an exception for a failure to reach or exchange data with the API.
     */
    public static function fromReason(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, null, $previous);
    }

    /**
     * Gets the HTTP status code of the failed response.
     *
     * @return int|null The status code, or null for a transport-level failure.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
