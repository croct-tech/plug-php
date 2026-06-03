<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;
use Croct\Plug\EvaluationOptions;
use Croct\Plug\Exception\EvaluationException;
use Croct\Plug\HttpEvaluator;
use Croct\Plug\PsrApiClient;
use Croct\Plug\RequestContext;
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
            EvaluationOptions::empty()->withAttribute('plan', 'pro'),
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

        $result = $evaluator->evaluate('???', EvaluationOptions::empty()->withFallback(false));

        self::assertFalse($result);
    }
}
