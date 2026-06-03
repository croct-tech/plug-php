<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Content;

use Croct\Plug\Content\ExperienceMetadata;
use Croct\Plug\Content\ExperimentMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExperienceMetadata::class)]
#[TestDox('Experience metadata')]
final class ExperienceMetadataTest extends TestCase
{
    #[TestDox('Exposes the experience, audience, and experiment.')]
    public function testExposesValues(): void
    {
        $metadata = new ExperienceMetadata('exp-1', 'aud-1', new ExperimentMetadata('e-1', 'v-1'));

        self::assertSame('exp-1', $metadata->getExperienceId());
        self::assertSame('aud-1', $metadata->getAudienceId());
        self::assertSame('e-1', $metadata->getExperiment()?->getExperimentId());
    }

    #[TestDox('Can be created from the response metadata, including the experiment.')]
    public function testCreatesFromMetadata(): void
    {
        $metadata = ExperienceMetadata::fromArray([
            'experienceId' => 'exp-2',
            'audienceId' => 'aud-2',
            'experiment' => ['experimentId' => 'e-2', 'variantId' => 'v-2'],
        ]);

        self::assertSame('exp-2', $metadata->getExperienceId());
        self::assertSame('aud-2', $metadata->getAudienceId());

        $experiment = $metadata->getExperiment();

        self::assertSame('e-2', $experiment?->getExperimentId());
        self::assertSame('v-2', $experiment->getVariantId());
    }

    #[TestDox('Can be created without a running experiment.')]
    public function testCreatesWithoutExperiment(): void
    {
        $metadata = ExperienceMetadata::fromArray(['experienceId' => 'exp-2', 'audienceId' => 'aud-2']);

        self::assertSame('exp-2', $metadata->getExperienceId());
        self::assertSame('aud-2', $metadata->getAudienceId());
        self::assertNull($metadata->getExperiment());
    }

    /**
     * @return array<string, array{data: array<array-key, mixed>}>
     */
    public static function getTestsForInvalidData(): array
    {
        return [
            'missing experience ID' => [
                'data' => ['audienceId' => 'aud-1'],
            ],
            'missing audience ID' => [
                'data' => ['experienceId' => 'exp-1'],
            ],
            'invalid experiment' => [
                'data' => ['experienceId' => 'exp-1', 'audienceId' => 'aud-1', 'experiment' => 'x'],
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

        ExperienceMetadata::fromArray($data);
    }
}
