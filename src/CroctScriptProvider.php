<?php

declare(strict_types=1);

namespace Croct\Plug;

use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface as RequestFactory;
use Psr\SimpleCache\CacheInterface as Cache;

/**
 * Fetches the client-side SDK from its upstream source and caches it for first-party serving.
 *
 * It behaves as a transparent cache: the visitor's request headers are forwarded upstream and the
 * upstream response is cached and relayed back verbatim, so content type, encoding and validators
 * are preserved without any special handling. Responses are cached per Accept-Encoding, which is
 * what the upstream varies on.
 */
final class CroctScriptProvider
{
    private const TTL = 3600;

    /**
     * Headers that must not be forwarded upstream.
     */
    private const UNFORWARDED_REQUEST_HEADERS = [
        'host',
        'connection',
        'keep-alive',
        'proxy-authenticate',
        'proxy-authorization',
        'te',
        'trailer',
        'transfer-encoding',
        'upgrade',
        'content-length',
        'if-none-match',
        'if-modified-since',
        'if-match',
        'if-unmodified-since',
        'if-range',
    ];

    /**
     * Headers that must not be relayed back.
     */
    private const UNRELAYED_RESPONSE_HEADERS = [
        'connection',
        'keep-alive',
        'proxy-authenticate',
        'proxy-authorization',
        'te',
        'trailer',
        'transfer-encoding',
        'upgrade',
        'content-length',
        'set-cookie',
    ];

    private HttpClient $httpClient;

    private RequestFactory $requestFactory;

    private Cache $cache;

    private string $scriptUrl;

    public function __construct(HttpClient $httpClient, RequestFactory $requestFactory, Cache $cache, string $scriptUrl)
    {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->cache = $cache;
        $this->scriptUrl = $scriptUrl;
    }

    /**
     * @param array<string, string> $requestHeaders The visitor request headers, keyed by lower-case name.
     */
    public function load(array $requestHeaders): CroctScriptResponse
    {
        $encoding = $requestHeaders['accept-encoding'] ?? '';
        $key = 'croct.plug_script.' . \hash('xxh128', $this->scriptUrl . '|' . $encoding);

        $cached = $this->cache->get($key);

        if ($cached instanceof CroctScriptResponse) {
            return $cached;
        }

        $request = $this->requestFactory->createRequest('GET', $this->scriptUrl);

        foreach ($requestHeaders as $name => $value) {
            if (!\in_array(\strtolower($name), self::UNFORWARDED_REQUEST_HEADERS, true)) {
                $request = $request->withHeader($name, $value);
            }
        }

        $response = $this->httpClient->sendRequest($request);

        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            // PSR-7 types header names loosely; they are strings at runtime.
            $header = (string) $name;

            if (!\in_array(\strtolower($header), self::UNRELAYED_RESPONSE_HEADERS, true)) {
                $headers[$header] = \implode(', ', $values);
            }
        }

        $content = new CroctScriptResponse($response->getStatusCode(), $headers, (string) $response->getBody());

        // Only cache successful responses so a transient upstream error is not pinned for the whole TTL.
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->cache->set($key, $content, self::TTL);
        }

        return $content;
    }
}
