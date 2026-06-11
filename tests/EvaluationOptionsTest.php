<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\EvaluationOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(EvaluationOptions::class)]
#[TestDox('Query evaluation options')]
final class EvaluationOptionsTest extends TestCase
{
    #[TestDox('Default to no attributes or a fallback.')]
    public function testEmptyHasNoAttributes(): void
    {
        $options = EvaluationOptions::default();

        self::assertSame([], $options->getAttributes());
        self::assertFalse($options->hasFallback());
    }

    #[TestDox('Carry a fallback distinct from an unset one, even when null.')]
    public function testCarriesFallback(): void
    {
        /** @var mixed $fallback */
        $fallback = null;

        $options = EvaluationOptions::default()->withFallback($fallback);

        self::assertTrue($options->hasFallback());
        self::assertNull($options->getFallback());
    }

    #[TestDox('Add attributes one at a time.')]
    public function testAddsAttributes(): void
    {
        $options = EvaluationOptions::default()
            ->withAttribute('plan', 'pro')
            ->withAttribute('seats', 5);

        self::assertSame(['plan' => 'pro', 'seats' => 5], $options->getAttributes());
    }

    #[TestDox('Replace all attributes when set as a whole.')]
    public function testReplacesAttributes(): void
    {
        $options = EvaluationOptions::default()
            ->withAttribute('plan', 'pro')
            ->withAttributes(['seats' => 5]);

        self::assertSame(['seats' => 5], $options->getAttributes());
    }

    #[TestDox('Do not mutate the original instance.')]
    public function testWithMethodsAreImmutable(): void
    {
        $options = EvaluationOptions::default();

        $options->withAttribute('plan', 'pro');

        self::assertSame([], $options->getAttributes());
    }
}
