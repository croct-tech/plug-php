<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;
use Croct\Plug\Exception\ApiException;
use Croct\Plug\PsrApiClient;
use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(PsrApiClient::class)]
#[TestDox('A PSR-based API client')]
final class PsrApiClientTest extends TestCase
{
    #[TestDox('Sends a request with the given headers and decodes the response.')]
    public function testSendsRequestWithHeaders(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody($factory->createStream((string) \json_encode(['ok' => true]))),
        );

        $apiKey = ApiKey::of(EcKeyFactory::IDENTIFIER);

        $client = new PsrApiClient(
            httpClient: $mock,
            requestFactory: $factory,
            streamFactory: $factory,
            apiKey: $apiKey,
            baseEndpointUrl: 'https://api.croct.io',
            version: '1.0.0',
        );

        $result = $client->send(
            'external/web/evaluate',
            ['query' => 'true'],
            ['X-Client-Id' => 'client-1', 'X-Client-Ip' => '8.8.8.8'],
        );

        self::assertSame(['ok' => true], $result);

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.croct.io/external/web/evaluate', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $request->getHeaderLine('Cache-Control'));
        self::assertSame('Croct SDK PHP v1.0.0', $request->getHeaderLine('X-Client-Library'));
        self::assertSame($apiKey->getIdentifier(), $request->getHeaderLine('X-Api-Key'));
        self::assertSame('client-1', $request->getHeaderLine('X-Client-Id'));
        self::assertSame('8.8.8.8', $request->getHeaderLine('X-Client-Ip'));
        self::assertSame(['query' => 'true'], \json_decode((string) $request->getBody(), true));
    }

    #[TestDox('Skips headers with a null value while keeping the application headers.')]
    public function testSkipsNullHeaders(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(200));

        $apiKey = ApiKey::of(EcKeyFactory::IDENTIFIER);

        $client = new PsrApiClient(
            httpClient: $mock,
            requestFactory: $factory,
            streamFactory: $factory,
            apiKey: $apiKey,
        );

        $result = $client->send(
            'external/web/static-content',
            [],
            ['X-Client-Id' => null, 'X-Token' => null, 'X-Client-Ip' => '8.8.8.8'],
        );

        self::assertNull($result);

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertFalse($request->hasHeader('X-Client-Id'));
        self::assertFalse($request->hasHeader('X-Token'));
        self::assertSame('8.8.8.8', $request->getHeaderLine('X-Client-Ip'));
        // The library and key headers identify the application, not the visitor, so they remain.
        self::assertSame('Croct SDK PHP', $request->getHeaderLine('X-Client-Library'));
        self::assertSame($apiKey->getIdentifier(), $request->getHeaderLine('X-Api-Key'));
    }

    #[TestDox('Sends only the application headers when none are given.')]
    public function testSendsWithoutRequestHeaders(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(200));

        $client = new PsrApiClient(
            httpClient: $mock,
            requestFactory: $factory,
            streamFactory: $factory,
            apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
        );

        $client->send('external/web/evaluate', []);

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('Croct SDK PHP', $request->getHeaderLine('X-Client-Library'));
        self::assertFalse($request->hasHeader('X-Client-Id'));
        self::assertFalse($request->hasHeader('X-Token'));
        self::assertFalse($request->hasHeader('X-Client-Ip'));
        self::assertFalse($request->hasHeader('X-Client-Agent'));
    }

    #[TestDox('Reports a suspended service as an exception.')]
    public function testReportsSuspendedService(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(202));

        $client = new PsrApiClient(
            httpClient: $mock,
            requestFactory: $factory,
            streamFactory: $factory,
            apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
        );

        $this->expectException(ApiException::class);

        $client->send('external/web/evaluate', []);
    }

    #[TestDox('Reports an error status with the problem title.')]
    public function testReportsErrorStatus(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(400)->withBody($factory->createStream((string) \json_encode(['title' => 'Bad']))),
        );

        $client = new PsrApiClient(
            httpClient: $mock,
            requestFactory: $factory,
            streamFactory: $factory,
            apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
        );

        try {
            $client->send('external/web/evaluate', []);
            self::fail('Expected an ApiException.');
        } catch (ApiException $exception) {
            self::assertSame('Bad', $exception->getMessage());
            self::assertSame(400, $exception->getStatusCode());
        }
    }

    #[TestDox('Reports a transport error as an exception.')]
    public function testReportsTransportError(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addException(
            new NetworkException('Connection failed', $factory->createRequest('POST', 'https://api.croct.io')),
        );

        $client = new PsrApiClient(
            httpClient: $mock,
            requestFactory: $factory,
            streamFactory: $factory,
            apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
        );

        $this->expectException(ApiException::class);

        $client->send('external/web/evaluate', []);
    }

    #[TestDox('Reports an invalid response body as an exception.')]
    public function testReportsInvalidResponse(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(200)->withBody($factory->createStream('not json')));

        $client = new PsrApiClient(
            httpClient: $mock,
            requestFactory: $factory,
            streamFactory: $factory,
            apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
        );

        $this->expectException(ApiException::class);

        $client->send('external/web/evaluate', []);
    }

    #[TestDox('Reports an unencodable payload as an exception.')]
    public function testReportsUnencodablePayload(): void
    {
        $factory = new Psr17Factory();
        $client = new PsrApiClient(
            httpClient: new MockClient(),
            requestFactory: $factory,
            streamFactory: $factory,
            apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
        );

        $this->expectException(ApiException::class);

        $client->send('external/web/evaluate', ['value' => "\xB1\x31"]);
    }
}
