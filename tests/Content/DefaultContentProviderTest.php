<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Content;

use Croct\Plug\Content\DefaultContentProvider;
use Croct\Plug\Tests\Fixtures\VirtualFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultContentProvider::class)]
#[TestDox('A default content provider')]
final class DefaultContentProviderTest extends TestCase
{
    protected function setUp(): void
    {
        VirtualFilesystem::setUp();
    }

    protected function tearDown(): void
    {
        VirtualFilesystem::tearDown();
    }

    #[TestDox('Falls back to the installed root package when no directory is given.')]
    public function testDefaultsToTheInstalledRootPackage(): void
    {
        // The library's own root has no croct.json, so discovery returns null.
        self::assertNull(DefaultContentProvider::fromProject());
    }

    #[TestDox('Returns null when the configuration file is missing.')]
    public function testReturnsNullWhenConfigurationIsMissing(): void
    {
        self::assertNull(DefaultContentProvider::fromProject(VirtualFilesystem::path()));
    }

    #[TestDox('Returns null when the configuration is not a JSON object.')]
    public function testReturnsNullWhenConfigurationIsNotAnObject(): void
    {
        $this->write('croct.json', '42');

        self::assertNull(DefaultContentProvider::fromProject(VirtualFilesystem::path()));
    }

    #[TestDox('Returns null when the content file is missing.')]
    public function testReturnsNullWhenContentIsMissing(): void
    {
        $this->write('croct.json', '{}');

        self::assertNull(DefaultContentProvider::fromProject(VirtualFilesystem::path()));
    }

    #[TestDox('Returns null when the configuration cannot be read.')]
    public function testReturnsNullWhenConfigurationCannotBeRead(): void
    {
        VirtualFilesystem::writeUnreadable(VirtualFilesystem::path('croct.json'));

        // The file reports as readable but fails to open; the resulting warning
        // is silenced so the failure path can be asserted.
        $provider = @DefaultContentProvider::fromProject(VirtualFilesystem::path());

        self::assertNull($provider);
    }

    #[TestDox('Resolves the latest version of each slot in the default locale.')]
    public function testResolvesLatestVersionInDefaultLocale(): void
    {
        $this->write('croct.json', [
            'defaultLocale' => 'en',
            'paths' => ['content' => 'content'],
        ]);

        $this->write('content/slots.json', [
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
        ]);

        $provider = DefaultContentProvider::fromProject(VirtualFilesystem::path());

        self::assertNotNull($provider);
        // Defaults to the project's default locale when no language is requested.
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

    #[TestDox('Skips malformed entries and resolves without a default locale.')]
    public function testSkipsMalformedEntriesWithoutDefaultLocale(): void
    {
        $this->write('croct.json', '{}');

        $this->write('slots.json', [
            'not-versions' => 'i am not a list',
            'no-valid-latest' => [
                'not-an-entry',
                [
                    'version' => 'not-an-int',
                    'content' => [
                        'en' => ['k' => 'v'],
                    ],
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
                'not-an-entry',
                [
                    'version' => 1,
                    'content' => [
                        'en' => ['k' => 'v1'],
                    ],
                ],
                [
                    'version' => 2,
                    'content' => [
                        'en' => ['k' => 'v2'],
                    ],
                ],
            ],
        ]);

        $provider = DefaultContentProvider::fromProject(VirtualFilesystem::path());

        self::assertNotNull($provider);
        self::assertNull($provider->getSlotContent('not-versions'));
        self::assertNull($provider->getSlotContent('no-valid-latest'));
        self::assertNull($provider->getSlotContent('no-localized-array'));
        // Resolves the latest valid version for the requested language.
        self::assertSame(['k' => 'v2'], $provider->getSlotContent('valid', 'en'));
        // Without a default locale, an unspecified language resolves to nothing.
        self::assertNull($provider->getSlotContent('valid'));
    }

    /**
     * @param array<string, mixed>|string $data
     */
    private function write(string $relativePath, array|string $data): void
    {
        VirtualFilesystem::write(
            VirtualFilesystem::path($relativePath),
            \is_string($data) ? $data : (string) \json_encode($data),
        );
    }
}
