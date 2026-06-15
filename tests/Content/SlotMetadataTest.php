<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Content;

use Croct\Plug\Content\ContentSource;
use Croct\Plug\Content\SlotMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlotMetadata::class)]
#[TestDox('Slot metadata')]
final class SlotMetadataTest extends TestCase
{
    #[TestDox('Can be created from the full response metadata.')]
    public function testCreatesFromFullMetadata(): void
    {
        $metadata = SlotMetadata::fromArray([
            'version' => '3',
            'contentSource' => 'experiment',
            'schema' => ['type' => 'structure'],
            'experience' => [
                'experienceId' => 'exp-1',
                'audienceId' => 'aud-1',
                'experiment' => ['experimentId' => 'e-1', 'variantId' => 'v-1'],
            ],
        ]);

        self::assertSame('3', $metadata->getVersion());
        self::assertSame(ContentSource::EXPERIMENT, $metadata->getContentSource());
        self::assertSame(['type' => 'structure'], $metadata->getSchema());

        $experience = $metadata->getExperience();

        self::assertSame('exp-1', $experience?->getExperienceId());
        self::assertSame('aud-1', $experience->getAudienceId());

        $experiment = $experience->getExperiment();

        self::assertSame('e-1', $experiment?->getExperimentId());
        self::assertSame('v-1', $experiment->getVariantId());
    }

    #[TestDox('Defaults to null when the optional fields are absent.')]
    public function testCreatesFromMinimalMetadata(): void
    {
        $metadata = SlotMetadata::fromArray([
            'version' => '1',
            'contentSource' => 'slot',
        ]);

        self::assertSame('1', $metadata->getVersion());
        self::assertSame(ContentSource::SLOT, $metadata->getContentSource());
        self::assertNull($metadata->getSchema());
        self::assertNull($metadata->getExperience());
    }

    /**
     * @return array<string, array{data: array<array-key, mixed>}>
     */
    public static function getTestsForInvalidData(): array
    {
        return [
            'missing version' => [
                'data' => ['contentSource' => 'slot'],
            ],
            'invalid version' => [
                'data' => ['version' => 3, 'contentSource' => 'slot'],
            ],
            'missing content source' => [
                'data' => ['version' => '1'],
            ],
            'invalid content source type' => [
                'data' => ['version' => '1', 'contentSource' => 3],
            ],
            'unknown content source' => [
                'data' => ['version' => '1', 'contentSource' => 'unknown'],
            ],
            'invalid experience' => [
                'data' => ['version' => '1', 'contentSource' => 'slot', 'experience' => 'x'],
            ],
            'invalid schema' => [
                'data' => ['version' => '1', 'contentSource' => 'slot', 'schema' => 'x'],
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('getTestsForInvalidData')]
    #[TestDox('Rejects fields that are present but invalid.')]
    public function testRejectsInvalidData(array $data): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SlotMetadata::fromArray($data);
    }
}
