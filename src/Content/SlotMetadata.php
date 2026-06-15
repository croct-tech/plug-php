<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

/**
 * Metadata describing the content served for a slot.
 *
 * @template-covariant TSchema of bool The schema flag: `true` when the schema was requested.
 */
final class SlotMetadata
{
    private string $version;

    private ContentSource $contentSource;

    private ?ExperienceMetadata $experience;

    /** @var array<string, mixed>|null */
    private ?array $schema;

    /**
     * @param array<string, mixed>|null $schema
     */
    public function __construct(
        string $version,
        ContentSource $contentSource,
        ?ExperienceMetadata $experience = null,
        ?array $schema = null,
    ) {
        $this->version = $version;
        $this->contentSource = $contentSource;
        $this->experience = $experience;
        $this->schema = $schema;
    }

    /**
     * Creates an instance from the decoded slot metadata.
     *
     * @param array<array-key, mixed> $data
     *
     * @return self<never>
     *
     * @throws \InvalidArgumentException If a field is present but invalid.
     */
    public static function fromArray(array $data): self
    {
        $version = $data['version'] ?? null;

        if (!\is_string($version)) {
            throw new \InvalidArgumentException('The content version is missing or invalid.');
        }

        $experience = $data['experience'] ?? null;

        if ($experience !== null && !\is_array($experience)) {
            throw new \InvalidArgumentException('The experience metadata is invalid.');
        }

        $schema = $data['schema'] ?? null;

        if ($schema !== null && !\is_array($schema)) {
            throw new \InvalidArgumentException('The content schema is invalid.');
        }

        /** @var self<never> $metadata */
        $metadata = new self(
            $version,
            self::parseContentSource($data['contentSource'] ?? null),
            $experience !== null ? ExperienceMetadata::fromArray($experience) : null,
            $schema !== null ? self::stringifyKeys($schema) : null,
        );

        return $metadata;
    }

    /**
     * Parses the content source value.
     *
     * @throws \InvalidArgumentException If the value is missing or not a known content source.
     */
    private static function parseContentSource(mixed $value): ContentSource
    {
        if (!\is_string($value)) {
            throw new \InvalidArgumentException('The content source is missing or invalid.');
        }

        return ContentSource::tryFrom($value)
            ?? throw new \InvalidArgumentException(\sprintf('Unknown content source "%s".', $value));
    }

    /**
     * Gets the content version.
     *
     * @return string The version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Gets the source the content was served from.
     *
     * @return ContentSource The content source.
     */
    public function getContentSource(): ContentSource
    {
        return $this->contentSource;
    }

    /**
     * Gets the experience that served the content.
     *
     * @return ExperienceMetadata|null The experience metadata, or null if none applies.
     */
    public function getExperience(): ?ExperienceMetadata
    {
        return $this->experience;
    }

    /**
     * Gets the content schema, present only when the schema was requested.
     *
     * @return (TSchema is false ? array<string, mixed>|null : array<string, mixed>) The schema.
     */
    public function getSchema(): ?array
    {
        return $this->schema;
    }

    /**
     * Casts every key of the given array to a string.
     *
     * @param array<array-key, mixed> $data The array to normalize.
     *
     * @return array<string, mixed> The array with string keys.
     */
    private static function stringifyKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
