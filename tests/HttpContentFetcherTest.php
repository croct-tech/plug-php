<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;
use Croct\Plug\Content\ArrayContentProvider;
use Croct\Plug\Content\ContentProvider;
use Croct\Plug\Content\ContentSource;
use Croct\Plug\Exception\ContentException;
use Croct\Plug\FetchOptions;
use Croct\Plug\HttpContentFetcher;
use Croct\Plug\PsrApiClient;
use Croct\Plug\RequestContext;
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
            'home-hero',
            FetchOptions::empty()->withPreferredLocale('en-us')->withVersion(2),
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

        $response = $fetcher->fetch('home-hero', FetchOptions::empty()->withSchema());

        self::assertSame(['type' => 'structure'], $response->getMetadata()?->getSchema());

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame(
            ['slotId' => 'home-hero', 'includeSchema' => true],
            \json_decode((string) $request->getBody(), true),
        );
    }

    #[TestDox('Uses the static-content endpoint for static fetches.')]
    public function testFetchesStaticContent(): void
    {
        $factory = new Psr17Factory();
        $mock = new MockClient();
        $mock->addResponse(
            $factory->createResponse(200)->withBody($factory->createStream((string) \json_encode(['content' => []]))),
        );

        $this->createFetcher($mock, $factory)
            ->fetch('home-hero', FetchOptions::empty()->withStatic());

        $request = $mock->getLastRequest();

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('https://api.croct.io/external/web/static-content', (string) $request->getUri());
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
            ->fetch('home-hero', FetchOptions::empty()->withFallback(['title' => 'Default']));

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
            ->fetch('home-hero', FetchOptions::empty()->withFallback(['title' => 'Explicit']));

        self::assertSame(['title' => 'Explicit'], $response->getContent());
    }

    private function createFetcher(
        MockClient $client,
        Psr17Factory $factory,
        ?RequestContext $context = null,
        ?ContentProvider $contentProvider = null,
    ): HttpContentFetcher {
        $context ??= new RequestContext();

        return new HttpContentFetcher(
            new PsrApiClient(
                httpClient: $client,
                requestFactory: $factory,
                streamFactory: $factory,
                apiKey: ApiKey::of(EcKeyFactory::IDENTIFIER),
                logger: null,
                baseEndpointUrl: 'https://api.croct.io',
            ),
            $context,
            $contentProvider,
        );
    }
}
