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

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
