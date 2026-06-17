<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Content;

use Croct\Plug\Content\ArrayContentProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayContentProvider::class)]
#[TestDox('An array content provider')]
final class ArrayContentProviderTest extends TestCase
{
    #[TestDox('Serves the latest version of a slot, resolving the language.')]
    public function testServesLatestVersionAndResolvesLanguage(): void
    {
        $provider = new ArrayContentProvider(
            [
                'home-hero' => [
                    [
                        'version' => 1,
                        'content' => [
                            'en' => ['title' => 'Old'],
                        ],
                    ],
                    [
                        'version' => 3,
                        'content' => [
                            'en' => ['title' => 'Latest'],
                            'pt' => ['title' => 'Recente'],
                        ],
                    ],
                    [
                        'version' => 2,
                        'content' => [
                            'en' => ['title' => 'Middle'],
                        ],
                    ],
                ],
                'cta' => [
                    [
                        'version' => 1,
                        'content' => [
                            'pt' => ['label' => 'Comprar'],
                        ],
                    ],
                ],
            ],
            'en',
        );

        // Defaults to the configured default locale when no language is requested.
        self::assertSame(['title' => 'Latest'], $provider->getSlotContent('home-hero'));
        // Serves the requested language when available.
        self::assertSame(['title' => 'Recente'], $provider->getSlotContent('home-hero', 'pt'));
        // Falls back to the default locale when the requested language is absent.
        self::assertSame(['title' => 'Latest'], $provider->getSlotContent('home-hero', 'fr'));
        // Serves an explicitly requested language even when the default locale is absent.
        self::assertSame(['label' => 'Comprar'], $provider->getSlotContent('cta', 'pt'));
        // Gives up when neither the requested language nor the default locale is available.
        self::assertNull($provider->getSlotContent('cta'));
        self::assertNull($provider->getSlotContent('missing'));
    }

    #[TestDox('Resolves nothing without a default locale when no language is requested.')]
    public function testRequiresLanguageWithoutDefaultLocale(): void
    {
        $provider = new ArrayContentProvider([
            'home-hero' => [
                ['version' => 1, 'content' => ['en' => ['title' => 'Hello']]],
            ],
        ]);

        self::assertSame(['title' => 'Hello'], $provider->getSlotContent('home-hero', 'en'));
        self::assertNull($provider->getSlotContent('home-hero'));
    }

    #[TestDox('Skips malformed versions and locale entries.')]
    public function testSkipsMalformedEntries(): void
    {
        $provider = new ArrayContentProvider(
            [
                'not-versions' => 'i am not a list',
                'no-valid-latest' => [
                    'not-an-entry',
                    [
                        'version' => 'not-an-int',
                        'content' => ['en' => ['k' => 'v']],
                    ],
                    ['version' => 5, 'content' => 'not-an-array'],
                ],
                'no-localized-array' => [
                    [
                        'version' => 1,
                        'content' => ['en' => 'not-an-array'],
                    ],
                ],
                'valid' => [
                    ['version' => 1, 'content' => ['en' => ['k' => 'v1']]],
                    ['version' => 2, 'content' => ['en' => ['k' => 'v2']]],
                ],
            ],
            'en',
        );

        self::assertNull($provider->getSlotContent('not-versions'));
        self::assertNull($provider->getSlotContent('no-valid-latest'));
        self::assertNull($provider->getSlotContent('no-localized-array'));
        // Resolves the latest valid version.
        self::assertSame(['k' => 'v2'], $provider->getSlotContent('valid'));
    }
}
