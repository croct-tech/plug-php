<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Content;

use Croct\Plug\Content\SlotsContentProvider;
use Croct\Plug\Tests\Fixtures\VirtualFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlotsContentProvider::class)]
#[TestDox('A slots content provider')]
final class SlotsContentProviderTest extends TestCase
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
        self::assertNull(SlotsContentProvider::fromProject());
    }

    #[TestDox('Returns null when the configuration file is missing.')]
    public function testReturnsNullWhenConfigurationIsMissing(): void
    {
        self::assertNull(SlotsContentProvider::fromProject(VirtualFilesystem::path()));
    }

    #[TestDox('Returns null when the configuration is not a JSON object.')]
    public function testReturnsNullWhenConfigurationIsNotAnObject(): void
    {
        $this->write('croct.json', '42');

        self::assertNull(SlotsContentProvider::fromProject(VirtualFilesystem::path()));
    }

    #[TestDox('Returns null when the content file is missing.')]
    public function testReturnsNullWhenContentIsMissing(): void
    {
        $this->write('croct.json', '{}');

        self::assertNull(SlotsContentProvider::fromProject(VirtualFilesystem::path()));
    }

    #[TestDox('Returns null when the configuration cannot be read.')]
    public function testReturnsNullWhenConfigurationCannotBeRead(): void
    {
        VirtualFilesystem::writeUnreadable(VirtualFilesystem::path('croct.json'));

        // The file reports as readable but fails to open; the resulting warning
        // is silenced so the failure path can be asserted.
        $provider = @SlotsContentProvider::fromProject(VirtualFilesystem::path());

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

        $provider = SlotsContentProvider::fromProject(VirtualFilesystem::path());

        self::assertNotNull($provider);
        self::assertSame(['title' => 'Latest'], $provider->getContent('home-hero'));
        // Falls back to the first available locale when the default is absent.
        self::assertSame(['label' => 'Comprar'], $provider->getContent('cta'));
        self::assertNull($provider->getContent('missing'));
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

        $provider = SlotsContentProvider::fromProject(VirtualFilesystem::path());

        self::assertNotNull($provider);
        self::assertNull($provider->getContent('not-versions'));
        self::assertNull($provider->getContent('no-valid-latest'));
        self::assertNull($provider->getContent('no-localized-array'));
        self::assertSame(['k' => 'v2'], $provider->getContent('valid'));
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
