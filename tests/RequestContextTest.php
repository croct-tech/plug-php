<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\RequestContext;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestContext::class)]
#[TestDox('A request context')]
final class RequestContextTest extends TestCase
{
    #[TestDox('Reads the request signals from a PSR-7 server request.')]
    public function testReadsSignalsFromServerRequest(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', 'https://example.com/pricing', ['REMOTE_ADDR' => '8.8.8.8'])
            ->withHeader('User-Agent', 'Test/1.0')
            ->withHeader('Referer', 'https://google.com');

        $context = RequestContext::fromServerRequest($request);

        self::assertSame('https://example.com/pricing', $context->getUrl());
        self::assertSame('Test/1.0', $context->getClientAgent());
        self::assertSame('https://google.com', $context->getReferrer());
        self::assertSame('8.8.8.8', $context->getClientIp());
    }

    #[TestDox('Prefers the first X-Forwarded-For address as the client IP.')]
    public function testPrefersForwardedForClientIp(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', 'https://example.com/')
            ->withHeader('X-Forwarded-For', '1.2.3.4, 5.6.7.8');

        self::assertSame('1.2.3.4', RequestContext::fromServerRequest($request)->getClientIp());
    }

    #[TestDox('Builds the evaluation context from the page and custom attributes.')]
    public function testBuildsEvaluationContext(): void
    {
        $context = new RequestContext(url: 'https://example.com/y', referrer: 'https://ref.example');

        self::assertSame(
            [
                'page' => [
                    'url' => 'https://example.com/y',
                    'referrer' => 'https://ref.example',
                ],
                'attributes' => ['plan' => 'pro'],
            ],
            $context->toEvaluationContext(['plan' => 'pro']),
        );
    }

    #[TestDox('Omits the page context when the URL is unknown, even with a referrer.')]
    public function testOmitsPageWithoutUrl(): void
    {
        $context = new RequestContext(referrer: 'https://ref.example');

        self::assertSame(
            ['attributes' => ['plan' => 'pro']],
            $context->toEvaluationContext(['plan' => 'pro']),
        );
    }

    #[TestDox('Reads the request signals from the superglobals.')]
    public function testReadsSignalsFromGlobals(): void
    {
        $context = self::withServer(
            [
                'HTTPS' => 'off',
                'SERVER_PORT' => '443',
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/pricing',
                'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 5.6.7.8',
                'HTTP_REFERER' => 'https://google.com',
                'HTTP_USER_AGENT' => 'Test/1.0',
            ],
            static fn (): RequestContext => RequestContext::fromGlobals(),
        );

        self::assertSame('https://example.com/pricing', $context->getUrl());
        self::assertSame('1.2.3.4', $context->getClientIp());
        self::assertSame('https://google.com', $context->getReferrer());
        self::assertSame('Test/1.0', $context->getClientAgent());
    }

    #[TestDox('Builds an insecure URL and reads the remote address without proxy headers.')]
    public function testReadsPlainGlobals(): void
    {
        $context = self::withServer(
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '9.9.9.9'],
            static fn (): RequestContext => RequestContext::fromGlobals(),
        );

        self::assertSame('http://example.com/', $context->getUrl());
        self::assertSame('9.9.9.9', $context->getClientIp());
        self::assertNull($context->getReferrer());
    }

    #[TestDox('Exposes the preview token and preferred locale.')]
    public function testExposesPreviewTokenAndLocale(): void
    {
        $context = new RequestContext(previewToken: 'preview', preferredLocale: 'en-us');

        self::assertSame('preview', $context->getPreviewToken());
        self::assertSame('en-us', $context->getPreferredLocale());
    }

    #[TestDox('Reads the preview token from the query parameter of the superglobals.')]
    public function testReadsPreviewTokenFromGlobals(): void
    {
        $context = self::withGlobals(
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/'],
            ['croct-preview' => 'preview-jwt'],
            static fn (): RequestContext => RequestContext::fromGlobals(),
        );

        self::assertSame('preview-jwt', $context->getPreviewToken());
    }

    #[TestDox('Treats the preview-exit sentinel as no preview.')]
    public function testIgnoresPreviewExitSentinel(): void
    {
        $context = self::withGlobals(
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/'],
            ['croct-preview' => 'exit'],
            static fn (): RequestContext => RequestContext::fromGlobals(),
        );

        self::assertNull($context->getPreviewToken());
    }

    #[TestDox('Reads the preview token from the query parameters of a PSR-7 server request.')]
    public function testReadsPreviewTokenFromServerRequest(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', 'https://example.com/')
            ->withQueryParams(['croct-preview' => 'preview-jwt']);

        self::assertSame('preview-jwt', RequestContext::fromServerRequest($request)->getPreviewToken());
    }

    #[TestDox('Resolves a raw preview value, treating the exit sentinel and an absent value as no preview.')]
    public function testResolvesPreviewToken(): void
    {
        self::assertSame('preview-jwt', RequestContext::resolvePreviewToken('preview-jwt'));
        self::assertNull(RequestContext::resolvePreviewToken('exit'));
        self::assertNull(RequestContext::resolvePreviewToken(null));
    }

    /**
     * @param array<string, string>      $server
     * @param callable(): RequestContext $callback
     */
    private static function withServer(array $server, callable $callback): RequestContext
    {
        return self::withGlobals($server, [], $callback);
    }

    /**
     * @param array<string, string>      $server
     * @param array<string, string>      $query
     * @param callable(): RequestContext $callback
     */
    private static function withGlobals(array $server, array $query, callable $callback): RequestContext
    {
        $_SERVER = $server;
        $_GET = $query;

        return $callback();
    }
}
