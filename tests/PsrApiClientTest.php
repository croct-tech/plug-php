<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;
use Croct\Plug\Exception\ApiException;
use Croct\Plug\InMemoryIdentityStore;
use Croct\Plug\PsrApiClient;
use Croct\Plug\RequestContext;
use Croct\Plug\Token;
use Croct\Plug\Uuid;
use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

#[CoversClass(PsrApiClient::class)]
#[TestDox('A PSR-based API client')]
final class PsrApiClientTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const CLIENT_ID = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Sends an authenticated request with the visitor headers and decodes the response.')]
    public function testSendsAuthenticatedRequest(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody($factory->createStream((string) \json_encode(['ok' => true]))),
        );

        $apiKey = ApiKey::of(EcKeyFactory::IDENTIFIER);
        $clientId = Uuid::parse(self::CLIENT_ID);
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000);

        $client = new PsrApiClient(
            httpClient: $mock,
            requestFactory: $factory,
            streamFactory: $factory,
            apiKey: $apiKey,
            logger: new NullLogger(),
            baseEndpointUrl: 'https://api.croct.io',
            version: '1.0.0',
            identity: new InMemoryIdentityStore($clientId, $token),
        );

        $context = new RequestContext(
            clientAgent: 'Test/1.0',
            clientIp: '8.8.8.8',
        );

        $result = $client->send('external/web/evaluate', ['query' => 'true'], $context);

        self::assertSame(['ok' => true], $result);

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.croct.io/external/web/evaluate', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $request->getHeaderLine('Cache-Control'));
        self::assertSame('Croct SDK PHP v1.0.0', $request->getHeaderLine('X-Client-Library'));
        self::assertSame($apiKey->getIdentifier(), $request->getHeaderLine('X-Api-Key'));
        self::assertSame($clientId->toString(), $request->getHeaderLine('X-Client-Id'));
        self::assertSame($token->toString(), $request->getHeaderLine('X-Token'));
        self::assertSame('8.8.8.8', $request->getHeaderLine('X-Client-Ip'));
        self::assertSame('Test/1.0', $request->getHeaderLine('X-Client-Agent'));
        self::assertSame(['query' => 'true'], \json_decode((string) $request->getBody(), true));
    }

    #[TestDox('Omits absent visitor headers and the version when none is set.')]
    public function testOmitsAbsentHeaders(): void
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

        $result = $client->send('external/web/evaluate', [], new RequestContext());

        self::assertNull($result);

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

        $client->send('external/web/evaluate', [], new RequestContext());
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
            $client->send('external/web/evaluate', [], new RequestContext());
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

        $client->send('external/web/evaluate', [], new RequestContext());
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

        $client->send('external/web/evaluate', [], new RequestContext());
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

        $client->send(
            'external/web/evaluate',
            ['value' => "\xB1\x31"],
            new RequestContext(),
        );
    }
}
