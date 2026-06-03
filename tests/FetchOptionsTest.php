<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\FetchOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(FetchOptions::class)]
#[TestDox('Content fetch options')]
final class FetchOptionsTest extends TestCase
{
    #[TestDox('Default to empty.')]
    public function testEmptyHasNoOptions(): void
    {
        $options = FetchOptions::empty();

        self::assertNull($options->getPreferredLocale());
        self::assertNull($options->getVersion());
        self::assertFalse($options->isStatic());
        self::assertFalse($options->includesSchema());
        self::assertSame([], $options->getAttributes());
        self::assertFalse($options->hasFallback());
    }

    #[TestDox('Build up immutably through the fluent API.')]
    public function testBuildsOptionsFluently(): void
    {
        $options = FetchOptions::empty()
            ->withPreferredLocale('en-us')
            ->withVersion(2)
            ->withStatic()
            ->withSchema()
            ->withAttribute('plan', 'pro')
            ->withFallback(['headline' => 'Welcome']);

        self::assertSame('en-us', $options->getPreferredLocale());
        self::assertSame(2, $options->getVersion());
        self::assertTrue($options->isStatic());
        self::assertTrue($options->includesSchema());
        self::assertSame(['plan' => 'pro'], $options->getAttributes());
        self::assertTrue($options->hasFallback());
        self::assertSame(['headline' => 'Welcome'], $options->getFallback());
    }

    #[TestDox('Distinguish a null fallback from no fallback.')]
    public function testDistinguishesNullFallback(): void
    {
        self::assertFalse(FetchOptions::empty()->hasFallback());

        $options = FetchOptions::empty()->withFallback(null);

        self::assertTrue($options->hasFallback());
        self::assertNull($options->getFallback());
    }

    #[TestDox('Do not mutate the original instance.')]
    public function testWithMethodsAreImmutable(): void
    {
        $options = FetchOptions::empty();

        $options->withPreferredLocale('en-us')->withVersion(3)->withStatic()->withSchema();

        self::assertNull($options->getPreferredLocale());
        self::assertNull($options->getVersion());
        self::assertFalse($options->isStatic());
        self::assertFalse($options->includesSchema());
    }

    #[TestDox('Replace all attributes when set as a whole.')]
    public function testReplacesAttributes(): void
    {
        $options = FetchOptions::empty()
            ->withAttribute('a', 1)
            ->withAttributes(['b' => 2]);

        self::assertSame(['b' => 2], $options->getAttributes());
    }
}
