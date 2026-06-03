<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

/**
 * Metadata about the experiment that served the content.
 */
final class ExperimentMetadata
{
    private string $experimentId;

    private string $variantId;

    public function __construct(string $experimentId, string $variantId)
    {
        $this->experimentId = $experimentId;
        $this->variantId = $variantId;
    }

    /**
     * Creates an instance from the decoded experiment metadata.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws \InvalidArgumentException If a required field is missing or invalid.
     */
    public static function fromArray(array $data): self
    {
        $experimentId = $data['experimentId'] ?? null;
        $variantId = $data['variantId'] ?? null;

        if (!\is_string($experimentId)) {
            throw new \InvalidArgumentException('The experiment ID is missing or invalid.');
        }

        if (!\is_string($variantId)) {
            throw new \InvalidArgumentException('The variant ID is missing or invalid.');
        }

        return new self($experimentId, $variantId);
    }

    /**
     * Gets the experiment ID.
     */
    public function getExperimentId(): string
    {
        return $this->experimentId;
    }

    /**
     * Gets the ID of the variant served to the visitor.
     */
    public function getVariantId(): string
    {
        return $this->variantId;
    }
}
