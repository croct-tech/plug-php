<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Exception\ApiException;

/**
 * Sends requests to the Croct API.
 *
 * Reports any transport or API failure as an exception, leaving higher-level services to map it
 * to a domain-specific error.
 */
interface ApiClient
{
    /**
     * Sends a request to the given API path and returns the decoded response.
     *
     * @param array<string, mixed>       $payload The request body.
     * @param array<string, string|null> $headers Request-specific headers. Null values are skipped.
     *
     * @throws ApiException If the request fails or the API returns an error.
     */
    public function send(string $path, array $payload, array $headers = []): mixed;
}
