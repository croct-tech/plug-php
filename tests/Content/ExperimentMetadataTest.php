<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Content;

use Croct\Plug\Content\ExperimentMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExperimentMetadata::class)]
#[TestDox('Experiment metadata')]
final class ExperimentMetadataTest extends TestCase
{
    #[TestDox('Exposes the experiment and variant.')]
    public function testExposesValues(): void
    {
        $metadata = new ExperimentMetadata('e-1', 'v-1');

        self::assertSame('e-1', $metadata->getExperimentId());
        self::assertSame('v-1', $metadata->getVariantId());
    }

    #[TestDox('Can be created from the response metadata.')]
    public function testCreatesFromMetadata(): void
    {
        $metadata = ExperimentMetadata::fromArray(['experimentId' => 'e-2', 'variantId' => 'v-2']);

        self::assertSame('e-2', $metadata->getExperimentId());
        self::assertSame('v-2', $metadata->getVariantId());
    }

    /**
     * @return array<string, array{data: array<array-key, mixed>}>
     */
    public static function getTestsForInvalidData(): array
    {
        return [
            'missing experiment ID' => [
                'data' => ['variantId' => 'v-1'],
            ],
            'non-string experiment ID' => [
                'data' => ['experimentId' => 42, 'variantId' => 'v-1'],
            ],
            'missing variant ID' => [
                'data' => ['experimentId' => 'e-1'],
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('getTestsForInvalidData')]
    #[TestDox('Rejects missing or invalid fields.')]
    public function testRejectsInvalidData(array $data): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ExperimentMetadata::fromArray($data);
    }
}
