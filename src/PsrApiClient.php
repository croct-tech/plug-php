<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Exception\ApiException;
use Psr\Http\Client\ClientExceptionInterface as ClientException;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface as RequestFactory;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\StreamFactoryInterface as StreamFactory;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;

/**
 * API client built on PSR-18 and PSR-17.
 *
 * Builds the authenticated request, sends it, and reports any transport or API failure as an
 * exception for higher-level services to translate.
 */
final class PsrApiClient implements ApiClient
{
    private const CLIENT_LIBRARY = 'Croct SDK PHP';

    private HttpClient $httpClient;

    private RequestFactory $requestFactory;

    private StreamFactory $streamFactory;

    private ApiKey $apiKey;

    private string $baseEndpointUrl;

    private string $clientLibrary;

    private Logger $logger;

    private ?IdentityStore $identity;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        StreamFactory $streamFactory,
        ApiKey $apiKey,
        ?Logger $logger = null,
        string $baseEndpointUrl = Croct::DEFAULT_BASE_ENDPOINT_URL,
        ?string $version = null,
        ?IdentityStore $identity = null,
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->apiKey = $apiKey;
        $this->baseEndpointUrl = $baseEndpointUrl;
        $this->clientLibrary = $version === null || $version === ''
            ? self::CLIENT_LIBRARY
            : self::CLIENT_LIBRARY . ' v' . $version;
        $this->logger = $logger ?? new NullLogger();
        $this->identity = $identity;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function send(string $path, array $payload, RequestContext $context): mixed
    {
        $url = \rtrim($this->baseEndpointUrl, '/') . '/' . $path;

        try {
            $body = \json_encode($payload, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ApiException::fromReason('Failed to encode the request payload.', $exception);
        }

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            // Responses are per-visitor; never let a shared HTTP cache store them.
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader(HttpHeader::CLIENT_LIBRARY->value, $this->clientLibrary)
            ->withHeader(HttpHeader::API_KEY->value, $this->apiKey->getIdentifier())
            ->withBody($this->streamFactory->createStream($body));

        $request = $this->withClientHeaders($request, $context);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientException $exception) {
            $this->logger->error(\sprintf('Croct request to "%s" failed: %s', $path, $exception->getMessage()));

            throw ApiException::fromReason('Failed to communicate with the Croct API.', $exception);
        }

        $status = $response->getStatusCode();
        $content = (string) $response->getBody();

        try {
            $data = $content === '' ? null : \json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ApiException::fromReason('The Croct API returned an invalid response.', $exception);
        }

        if ($status === 202) {
            throw new ApiException('The Croct service is temporarily unavailable. Please retry shortly.', $status);
        }

        if ($status >= 400) {
            throw ApiException::fromProblem($status, \is_array($data) ? $data : null);
        }

        return $data;
    }

    /**
     * Adds the available visitor-identifying headers to the request.
     */
    private function withClientHeaders(Request $request, RequestContext $context): Request
    {
        $headers = [
            HttpHeader::CLIENT_ID->value => $this->identity?->getClientId()?->toString(),
            HttpHeader::TOKEN->value => $this->identity?->getUserToken()?->toString(),
            HttpHeader::CLIENT_IP->value => $context->getClientIp(),
            HttpHeader::CLIENT_AGENT->value => $context->getClientAgent(),
        ];

        foreach ($headers as $name => $value) {
            if ($value !== null) {
                $request = $request->withHeader($name, $value);
            }
        }

        return $request;
    }
}
