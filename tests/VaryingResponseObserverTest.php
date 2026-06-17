<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\EvaluationOptions;
use Croct\Plug\FetchOptions;
use Croct\Plug\FetchResponse;
use Croct\Plug\Plug;
use Croct\Plug\Token;
use Croct\Plug\VaryingResponseObserver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(VaryingResponseObserver::class)]
#[TestDox('A varying-response observer')]
final class VaryingResponseObserverTest extends TestCase
{
    #[TestDox('Runs the callback and delegates for every visitor-specific operation.')]
    public function testRunsCallbackAndDelegates(): void
    {
        $inner = $this->createPlug();

        $calls = 0;
        $plug = new VaryingResponseObserver($inner, static function () use (&$calls): void {
            ++$calls;
        });

        self::assertSame('cid', $plug->getClientId());
        self::assertTrue($plug->evaluate('user is returning'));
        self::assertSame(['title' => 'Hello'], $plug->fetchContent('home-hero')->getContent());

        $plug->identify('user-1');
        $plug->anonymize();

        self::assertSame(5, $calls);
        self::assertSame(
            [
                'getClientId',
                'evaluate',
                'fetchContent',
                'identify',
                'anonymize',
            ],
            $inner->calls,
        );
    }

    #[TestDox('Does not run the callback for visitor-independent reads.')]
    public function testDoesNotVaryOnStaticReads(): void
    {
        $inner = $this->createPlug();

        $calls = 0;
        $plug = new VaryingResponseObserver($inner, static function () use (&$calls): void {
            ++$calls;
        });

        self::assertSame('app', $plug->getAppId());
        self::assertSame(['appId' => 'app'], $plug->getPlugOptions());

        self::assertSame(0, $calls);
        self::assertSame(['getAppId', 'getPlugOptions'], $inner->calls);
    }

    #[TestDox('Does not run the callback for a static content fetch.')]
    public function testDoesNotVaryOnStaticContentFetch(): void
    {
        $inner = $this->createPlug();

        $calls = 0;
        $plug = new VaryingResponseObserver($inner, static function () use (&$calls): void {
            ++$calls;
        });

        self::assertSame(
            ['title' => 'Hello'],
            $plug->fetchContent('home-hero', FetchOptions::defaults()->withStaticContent())->getContent(),
        );

        self::assertSame(0, $calls);
        self::assertSame(['fetchContent'], $inner->calls);
    }

    #[TestDox('Reads the user token without running the callback.')]
    public function testDoesNotVaryOnUserTokenRead(): void
    {
        $inner = $this->createPlug();

        $calls = 0;
        $plug = new VaryingResponseObserver($inner, static function () use (&$calls): void {
            ++$calls;
        });

        // The token's only response effect is the session cookie, written based on whether it changed.
        self::assertSame($inner->token, $plug->getUserToken());

        self::assertSame(0, $calls);
        self::assertSame(['getUserToken'], $inner->calls);
    }

    /**
     * @return Plug&object{calls: list<string>, token: Token}
     */
    private function createPlug(): Plug
    {
        return new class implements Plug {
            /** @var list<string> */
            public array $calls = [];

            public Token $token;

            public function __construct()
            {
                $this->token = Token::issue(appId: '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a', now: 1000);
            }

            public function getAppId(): string
            {
                $this->calls[] = 'getAppId';

                return 'app';
            }

            public function getClientId(): string
            {
                $this->calls[] = 'getClientId';

                return 'cid';
            }

            public function getUserToken(): Token
            {
                $this->calls[] = 'getUserToken';

                return $this->token;
            }

            /**
             * @return array<string, mixed>
             */
            public function getPlugOptions(): array
            {
                $this->calls[] = 'getPlugOptions';

                return ['appId' => 'app'];
            }

            /**
             * @param EvaluationOptions<mixed>|null $options
             */
            public function evaluate(string $query, ?EvaluationOptions $options = null): mixed
            {
                $this->calls[] = 'evaluate';

                return true;
            }

            /**
             * @template F = never
             * @template S of bool = false
             *
             * @param FetchOptions<F, S>|null $options
             *
             * @return FetchResponse<array<string, mixed>, F, S>
             */
            public function fetchContent(string $slotId, ?FetchOptions $options = null): FetchResponse
            {
                $this->calls[] = 'fetchContent';

                /** @var FetchResponse<array<string, mixed>, F, S> $response */
                $response = new FetchResponse(['title' => 'Hello']);

                return $response;
            }

            public function identify(string $userId): void
            {
                $this->calls[] = 'identify';
            }

            public function anonymize(): void
            {
                $this->calls[] = 'anonymize';
            }
        };
    }
}
