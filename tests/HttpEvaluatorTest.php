<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;
use Croct\Plug\EvaluationOptions;
use Croct\Plug\Exception\EvaluationException;
use Croct\Plug\HttpEvaluator;
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

#[CoversClass(HttpEvaluator::class)]
#[TestDox('A CQL evaluator')]
final class HttpEvaluatorTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const CLIENT_ID = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Sends the query with the visitor context and returns the result.')]
    public function testEvaluatesQuery(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody($factory->createStream((string) \json_encode(true))),
        );

        $evaluator = new HttpEvaluator(
            new PsrApiClient(
                httpClient: $mock,
                requestFactory: $factory,
                streamFactory: $factory,
                apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
                logger: null,
                baseEndpointUrl: 'https://api.croct.io',
            ),
            new RequestContext(url: 'https://example.com/y'),
        );

        $result = $evaluator->evaluate(
            'user is returning',
            EvaluationOptions::default()->withAttribute('plan', 'pro'),
        );

        self::assertTrue($result);

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('https://api.croct.io/external/web/evaluate', (string) $request->getUri());
        self::assertSame(
            [
                'query' => 'user is returning',
                'context' => [
                    'page' => ['url' => 'https://example.com/y'],
                    'attributes' => ['plan' => 'pro'],
                ],
            ],
            \json_decode((string) $request->getBody(), true),
        );
    }

    #[TestDox('Sends the visitor headers from the session and context.')]
    public function testSendsVisitorHeaders(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody($factory->createStream((string) \json_encode(true))),
        );

        $token = Token::issue(appId: self::APP_ID, now: 1000);

        $evaluator = new HttpEvaluator(
            new PsrApiClient(
                httpClient: $mock,
                requestFactory: $factory,
                streamFactory: $factory,
                apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
            ),
            new RequestContext(clientAgent: 'Test/1.0', clientIp: '8.8.8.8'),
            new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID), $token),
        );

        $evaluator->evaluate('user is returning');

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame(self::CLIENT_ID, $request->getHeaderLine('X-Client-Id'));
        self::assertSame($token->toString(), $request->getHeaderLine('X-Token'));
        self::assertSame('8.8.8.8', $request->getHeaderLine('X-Client-Ip'));
        self::assertSame('Test/1.0', $request->getHeaderLine('X-Client-Agent'));
    }

    #[TestDox('Rejects a query longer than the maximum length before sending a request.')]
    public function testRejectsOverlongQuery(): void
    {
        $factory = new Psr17Factory();
        $evaluator = new HttpEvaluator(
            new PsrApiClient(
                httpClient: new MockClient(),
                requestFactory: $factory,
                streamFactory: $factory,
                apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
            ),
            new RequestContext(),
        );

        $this->expectException(EvaluationException::class);
        $this->expectExceptionMessage('The query must be at most 500 characters long, but it is 501 characters long.');

        $evaluator->evaluate(\str_repeat('a', 501));
    }

    #[TestDox('Accepts a query at the maximum length.')]
    public function testAcceptsMaxLengthQuery(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody($factory->createStream((string) \json_encode(true))),
        );

        $evaluator = new HttpEvaluator(
            new PsrApiClient(
                httpClient: $mock,
                requestFactory: $factory,
                streamFactory: $factory,
                apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
            ),
            new RequestContext(),
        );

        self::assertTrue($evaluator->evaluate(\str_repeat('a', 500)));
    }

    #[TestDox('Maps an error response to an evaluation exception.')]
    public function testMapsErrorResponseToException(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(400)
                ->withBody($factory->createStream((string) \json_encode(['title' => 'Invalid query']))),
        );

        $evaluator = new HttpEvaluator(
            new PsrApiClient(
                httpClient: $mock,
                requestFactory: $factory,
                streamFactory: $factory,
                apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
                logger: null,
                baseEndpointUrl: 'https://api.croct.io',
            ),
            new RequestContext(),
        );

        $this->expectException(EvaluationException::class);
        $this->expectExceptionMessage('Invalid query');

        $evaluator->evaluate('???');
    }

    #[TestDox('Maps a transport error to an evaluation exception.')]
    public function testMapsTransportErrorToException(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addException(
            new NetworkException('Connection failed', $factory->createRequest('POST', 'https://api.croct.io')),
        );

        $evaluator = new HttpEvaluator(
            new PsrApiClient(
                httpClient: $mock,
                requestFactory: $factory,
                streamFactory: $factory,
                apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
                logger: null,
                baseEndpointUrl: 'https://api.croct.io',
            ),
            new RequestContext(),
        );

        $this->expectException(EvaluationException::class);

        $evaluator->evaluate('user is returning');
    }

    #[TestDox('Returns the fallback result when the evaluation fails.')]
    public function testReturnsFallbackOnFailure(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(422));

        $evaluator = new HttpEvaluator(
            new PsrApiClient(
                httpClient: $mock,
                requestFactory: $factory,
                streamFactory: $factory,
                apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
                logger: null,
                baseEndpointUrl: 'https://api.croct.io',
            ),
            new RequestContext(),
        );

        $result = $evaluator->evaluate('???', EvaluationOptions::default()->withFallback(false));

        self::assertFalse($result);
    }
}
