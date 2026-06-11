<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;
use Croct\Plug\Content\ArrayContentProvider;
use Croct\Plug\Content\ContentProvider;
use Croct\Plug\Content\ContentSource;
use Croct\Plug\Content\DefaultContentProvider;
use Croct\Plug\Exception\ContentException;
use Croct\Plug\FetchOptions;
use Croct\Plug\HttpContentFetcher;
use Croct\Plug\IdentityStore;
use Croct\Plug\InMemoryIdentityStore;
use Croct\Plug\PsrApiClient;
use Croct\Plug\RequestContext;
use Croct\Plug\Token;
use Croct\Plug\Uuid;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(HttpContentFetcher::class)]
#[TestDox('A content fetcher')]
final class HttpContentFetcherTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const CLIENT_ID = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Fetches a slot and returns its content and typed metadata.')]
    public function testFetchesContent(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody(
                $factory->createStream(
                    (string) \json_encode([
                        'content' => ['title' => 'Hello'],
                        'metadata' => [
                            'version' => '2',
                            'contentSource' => 'experiment',
                            'experience' => [
                                'experienceId' => 'exp-1',
                                'audienceId' => 'aud-1',
                                'experiment' => ['experimentId' => 'e-1', 'variantId' => 'v-1'],
                            ],
                        ],
                    ]),
                ),
            ),
        );

        $fetcher = $this->createFetcher($mock, $factory, new RequestContext(url: 'https://example.com/'));

        $response = $fetcher->fetch(
            'home-hero@2',
            FetchOptions::default()->withPreferredLocale('en-us'),
        );

        self::assertSame(['title' => 'Hello'], $response->getContent());

        $metadata = $response->getMetadata();

        self::assertSame('2', $metadata?->getVersion());
        self::assertSame(ContentSource::EXPERIMENT, $metadata->getContentSource());

        $experience = $metadata->getExperience();

        self::assertSame('exp-1', $experience?->getExperienceId());
        self::assertSame('v-1', $experience->getExperiment()?->getVariantId());

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('https://api.croct.io/external/web/content', (string) $request->getUri());
        self::assertSame(
            [
                'slotId' => 'home-hero',
                'version' => '2',
                'preferredLocale' => 'en-us',
                'context' => ['page' => ['url' => 'https://example.com/']],
            ],
            \json_decode((string) $request->getBody(), true),
        );
    }

    #[TestDox('Includes and exposes the content schema when requested.')]
    public function testIncludesSchemaWhenRequested(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody(
                $factory->createStream(
                    (string) \json_encode([
                        'content' => ['title' => 'Hello'],
                        'metadata' => ['version' => '1', 'schema' => ['type' => 'structure']],
                    ]),
                ),
            ),
        );

        $fetcher = $this->createFetcher($mock, $factory);

        $response = $fetcher->fetch('home-hero', FetchOptions::default()->withSchema());

        self::assertSame(['type' => 'structure'], $response->getMetadata()?->getSchema());

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame(
            ['slotId' => 'home-hero', 'includeSchema' => true],
            \json_decode((string) $request->getBody(), true),
        );
    }

    #[TestDox('Uses the static-content endpoint and omits the visitor signals for static fetches.')]
    public function testFetchesStaticContent(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody($factory->createStream((string) \json_encode(['content' => []]))),
        );

        // Static content is impersonal: neither the page context nor the visitor headers are sent.
        $context = new RequestContext(
            previewToken: 'preview-token',
            url: 'https://example.com/',
            clientAgent: 'Test/1.0',
            clientIp: '8.8.8.8',
        );
        $identity = new InMemoryIdentityStore(
            Uuid::parse(self::CLIENT_ID),
            Token::issue(appId: self::APP_ID, now: 1000),
        );

        $this->createFetcher($mock, $factory, $context, identity: $identity)
            ->fetch('home-hero', FetchOptions::default()->withStatic());

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('https://api.croct.io/external/web/static-content', (string) $request->getUri());
        self::assertSame(['slotId' => 'home-hero'], \json_decode((string) $request->getBody(), true));
        self::assertFalse($request->hasHeader('X-Client-Id'));
        self::assertFalse($request->hasHeader('X-Token'));
        self::assertFalse($request->hasHeader('X-Client-Ip'));
        self::assertFalse($request->hasHeader('X-Client-Agent'));
    }

    #[TestDox('Sends the visitor headers from the session and context for dynamic content.')]
    public function testSendsVisitorHeadersForDynamicContent(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody($factory->createStream((string) \json_encode(['content' => []]))),
        );

        $context = new RequestContext(clientAgent: 'Test/1.0', clientIp: '8.8.8.8');
        $token = Token::issue(appId: self::APP_ID, now: 1000);
        $identity = new InMemoryIdentityStore(Uuid::parse(self::CLIENT_ID), $token);

        $this->createFetcher($mock, $factory, $context, identity: $identity)->fetch('home-hero');

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame(self::CLIENT_ID, $request->getHeaderLine('X-Client-Id'));
        self::assertSame($token->toString(), $request->getHeaderLine('X-Token'));
        self::assertSame('8.8.8.8', $request->getHeaderLine('X-Client-Ip'));
        self::assertSame('Test/1.0', $request->getHeaderLine('X-Client-Agent'));
    }

    #[TestDox('Forwards the preview token from the request context.')]
    public function testForwardsPreviewToken(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody($factory->createStream((string) \json_encode(['content' => []]))),
        );

        $context = new RequestContext(previewToken: 'preview-token');

        $this->createFetcher($mock, $factory, $context)->fetch('home-hero');

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame(
            ['slotId' => 'home-hero', 'previewToken' => 'preview-token'],
            \json_decode((string) $request->getBody(), true),
        );
    }

    #[TestDox('Returns the fallback content when the fetch fails.')]
    public function testReturnsFallbackOnFailure(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(500));

        $response = $this->createFetcher($mock, $factory)
            ->fetch('home-hero', FetchOptions::default()->withFallback(['title' => 'Default']));

        self::assertSame(['title' => 'Default'], $response->getContent());
    }

    #[TestDox('Throws a content exception when the fetch fails without a fallback.')]
    public function testThrowsWithoutFallback(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(500)->withBody($factory->createStream((string) \json_encode(['title' => 'Boom']))),
        );

        $this->expectException(ContentException::class);
        $this->expectExceptionMessage('Boom');

        $this->createFetcher($mock, $factory)->fetch('home-hero');
    }

    #[TestDox('Falls back to the content provider when the fetch fails.')]
    public function testFallsBackToContentProvider(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(500));

        $provider = new ArrayContentProvider(['home-hero' => ['title' => 'Generated']]);

        $response = $this->createFetcher($mock, $factory, contentProvider: $provider)->fetch('home-hero');

        self::assertSame(['title' => 'Generated'], $response->getContent());
    }

    #[TestDox('Prefers an explicit fallback over the content provider.')]
    public function testExplicitFallbackWinsOverProvider(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(500));

        $provider = new ArrayContentProvider(['home-hero' => ['title' => 'Generated']]);

        $response = $this->createFetcher($mock, $factory, contentProvider: $provider)
            ->fetch('home-hero', FetchOptions::default()->withFallback(['title' => 'Explicit']));

        self::assertSame(['title' => 'Explicit'], $response->getContent());
    }

    #[TestDox('Looks up the content provider by the slot ID without its version.')]
    public function testFallsBackToContentProviderForVersionedSlot(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(500));

        $provider = new ArrayContentProvider(['home-hero' => ['title' => 'Generated']]);

        $response = $this->createFetcher($mock, $factory, contentProvider: $provider)->fetch('home-hero@2');

        self::assertSame(['title' => 'Generated'], $response->getContent());
    }

    #[TestDox('Forwards the preferred locale to the content provider on fallback.')]
    public function testForwardsPreferredLocaleToContentProvider(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse($factory->createResponse(500));

        $provider = new DefaultContentProvider(
            ['home-hero' => ['en' => ['title' => 'Hello'], 'pt-br' => ['title' => 'Olá']]],
            'en',
        );

        $response = $this->createFetcher($mock, $factory, contentProvider: $provider)
            ->fetch('home-hero@2', FetchOptions::default()->withPreferredLocale('pt-br'));

        self::assertSame(['title' => 'Olá'], $response->getContent());
    }

    #[TestDox('Rejects a malformed slot ID.')]
    public function testRejectsMalformedSlotId(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Malformed slot ID "home hero".');

        $this->createFetcher($mock, $factory)->fetch('home hero');
    }

    private function createFetcher(
        MockClient $client,
        Psr17Factory $factory,
        ?RequestContext $context = null,
        ?ContentProvider $contentProvider = null,
        ?IdentityStore $identity = null,
    ): HttpContentFetcher {
        $context ??= new RequestContext();

        return new HttpContentFetcher(
            new PsrApiClient(
                httpClient: $client,
                requestFactory: $factory,
                streamFactory: $factory,
                apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
                baseEndpointUrl: 'https://api.croct.io',
            ),
            $context,
            identity: $identity,
            contentProvider: $contentProvider,
        );
    }
}
