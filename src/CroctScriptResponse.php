<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * A captured upstream response for the client-side SDK, relayed verbatim for first-party serving.
 */
final class CroctScriptResponse
{
    private int $statusCode;

    /** @var array<string, string> */
    private array $headers;

    private string $content;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $statusCode, array $headers, string $content)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->content = $content;
    }

    /**
     * Gets the HTTP status code of the captured response.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Gets the response headers.
     *
     * @return array<string, string> The headers, keyed by name.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Gets the raw response body relayed to the client.
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
