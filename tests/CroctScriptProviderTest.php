<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\CroctScriptProvider;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

#[CoversClass(CroctScriptProvider::class)]
#[TestDox('The first-party script provider')]
final class CroctScriptProviderTest extends TestCase
{
    private const SCRIPT_URL = 'https://cdn.example/plug.js';

    private Psr17Factory $factory;

    private MockClient $httpClient;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->httpClient = new MockClient();
    }

    #[TestDox('Forwards the visitor headers upstream and relays the captured response verbatim.')]
    public function testForwardsHeadersAndRelaysResponse(): void
    {
        $this->httpClient->addResponse(
            $this->factory->createResponse(200)
                ->withHeader('Content-Type', 'text/javascript')
                ->withHeader('Content-Encoding', 'br')
                ->withBody($this->factory->createStream('// plug')),
        );

        $content = $this->provider()->load([
            'accept-encoding' => 'gzip, br',
            'user-agent' => 'Test/1.0',
        ]);

        self::assertSame(200, $content->getStatusCode());
        self::assertSame('// plug', $content->getContent());
        self::assertSame('text/javascript', $content->getHeaders()['Content-Type']);
        self::assertSame('br', $content->getHeaders()['Content-Encoding']);

        $request = $this->httpClient->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('gzip, br', $request->getHeaderLine('Accept-Encoding'));
        self::assertSame('Test/1.0', $request->getHeaderLine('User-Agent'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unforwardedRequestHeaders(): array
    {
        return self::createDataset([
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
        ]);
    }

    #[DataProvider('unforwardedRequestHeaders')]
    #[TestDox('Never forwards hop-by-hop, host or conditional request headers upstream.')]
    public function testDoesNotForwardRequestHeader(string $header): void
    {
        $this->httpClient->addResponse($this->createOkResponse('// plug'));

        $this->provider()->load([
            'accept-encoding' => 'gzip',
            $header => 'sentinel',
        ]);

        $request = $this->httpClient->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertNotSame('sentinel', $request->getHeaderLine($header));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unrelayedResponseHeaders(): array
    {
        return self::createDataset([
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
        ]);
    }

    #[DataProvider('unrelayedResponseHeaders')]
    #[TestDox('Never relays hop-by-hop, framework-managed or cookie response headers back.')]
    public function testDoesNotRelayResponseHeader(string $header): void
    {
        $this->httpClient->addResponse(
            $this->factory->createResponse(200)
                ->withHeader($header, 'sentinel')
                ->withBody($this->factory->createStream('// plug')),
        );

        $content = $this->provider()->load(['accept-encoding' => 'gzip']);

        self::assertArrayNotHasKey($header, \array_change_key_case($content->getHeaders()));
    }

    #[TestDox('Caches per Accept-Encoding and reuses the cached response.')]
    public function testCachesByAcceptEncoding(): void
    {
        $this->httpClient->addResponse($this->createOkResponse('brotli'));
        $this->httpClient->addResponse($this->createOkResponse('gzipped'));

        $provider = $this->provider();

        self::assertSame('brotli', $provider->load(['accept-encoding' => 'br'])->getContent());
        self::assertSame('brotli', $provider->load(['accept-encoding' => 'br'])->getContent());
        self::assertSame('gzipped', $provider->load(['accept-encoding' => 'gzip'])->getContent());
        self::assertCount(2, $this->httpClient->getRequests());
    }

    #[TestDox('Does not cache an unsuccessful upstream response.')]
    public function testDoesNotCacheErrors(): void
    {
        $error = $this->factory->createResponse(503)->withBody($this->factory->createStream('down'));

        $this->httpClient->addResponse($error);
        $this->httpClient->addResponse($this->createOkResponse('// plug'));

        $provider = $this->provider();

        self::assertSame(503, $provider->load(['accept-encoding' => 'br'])->getStatusCode());
        self::assertSame('// plug', $provider->load(['accept-encoding' => 'br'])->getContent());
        self::assertCount(2, $this->httpClient->getRequests());
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, array{string}>
     */
    private static function createDataset(array $headers): array
    {
        $datasets = [];

        foreach ($headers as $header) {
            $datasets[$header] = [$header];
        }

        return $datasets;
    }

    private function provider(): CroctScriptProvider
    {
        return new CroctScriptProvider(
            $this->httpClient,
            $this->factory,
            new Psr16Cache(new ArrayAdapter()),
            self::SCRIPT_URL,
        );
    }

    private function createOkResponse(string $body): ResponseInterface
    {
        return $this->factory->createResponse(200)->withBody($this->factory->createStream($body));
    }
}
