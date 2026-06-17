<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

/**
 * Metadata about the experience that served the content.
 */
final class ExperienceMetadata
{
    private string $experienceId;

    private string $audienceId;

    private ?ExperimentMetadata $experiment;

    public function __construct(string $experienceId, string $audienceId, ?ExperimentMetadata $experiment = null)
    {
        $this->experienceId = $experienceId;
        $this->audienceId = $audienceId;
        $this->experiment = $experiment;
    }

    /**
     * Creates an instance from the decoded experience metadata.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws \InvalidArgumentException If a required field is missing or invalid.
     */
    public static function fromArray(array $data): self
    {
        $experienceId = $data['experienceId'] ?? null;
        $audienceId = $data['audienceId'] ?? null;
        $experiment = $data['experiment'] ?? null;

        if (!\is_string($experienceId)) {
            throw new \InvalidArgumentException('The experience ID is missing or invalid.');
        }

        if (!\is_string($audienceId)) {
            throw new \InvalidArgumentException('The audience ID is missing or invalid.');
        }

        if ($experiment !== null && !\is_array($experiment)) {
            throw new \InvalidArgumentException('The experiment metadata is invalid.');
        }

        return new self(
            $experienceId,
            $audienceId,
            $experiment !== null ? ExperimentMetadata::fromArray($experiment) : null,
        );
    }

    /**
     * Gets the experience ID.
     */
    public function getExperienceId(): string
    {
        return $this->experienceId;
    }

    /**
     * Gets the audience ID.
     */
    public function getAudienceId(): string
    {
        return $this->audienceId;
    }

    /**
     * Gets the experiment running within the experience.
     *
     * @return ExperimentMetadata|null The experiment metadata, or null if none is running.
     */
    public function getExperiment(): ?ExperimentMetadata
    {
        return $this->experiment;
    }
}
