<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

/**
 * Metadata describing the content served for a slot.
 */
final class SlotMetadata
{
    private ?string $version;

    private ?ContentSource $contentSource;

    private ?ExperienceMetadata $experience;

    /** @var array<string, mixed>|null */
    private ?array $schema;

    /**
     * @param array<string, mixed>|null $schema
     */
    public function __construct(
        ?string $version = null,
        ?ContentSource $contentSource = null,
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
     * @throws \InvalidArgumentException If a field is present but invalid.
     */
    public static function fromArray(array $data): self
    {
        $version = $data['version'] ?? null;

        if ($version !== null && !\is_string($version)) {
            throw new \InvalidArgumentException('The content version is invalid.');
        }

        $experience = $data['experience'] ?? null;

        if ($experience !== null && !\is_array($experience)) {
            throw new \InvalidArgumentException('The experience metadata is invalid.');
        }

        $schema = $data['schema'] ?? null;

        if ($schema !== null && !\is_array($schema)) {
            throw new \InvalidArgumentException('The content schema is invalid.');
        }

        return new self(
            $version,
            self::parseContentSource($data['contentSource'] ?? null),
            $experience !== null ? ExperienceMetadata::fromArray($experience) : null,
            $schema !== null ? self::stringifyKeys($schema) : null,
        );
    }

    /**
     * Parses the content source value.
     *
     * @throws \InvalidArgumentException If the value is present but not a known content source.
     */
    private static function parseContentSource(mixed $value): ?ContentSource
    {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException('The content source is invalid.');
        }

        return ContentSource::tryFrom($value)
            ?? throw new \InvalidArgumentException(\sprintf('Unknown content source "%s".', $value));
    }

    /**
     * Gets the content version.
     *
     * @return string|null The version, or null if unversioned.
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Gets the source the content was served from.
     *
     * @return ContentSource|null The content source, or null if unknown.
     */
    public function getContentSource(): ?ContentSource
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
     * @return array<string, mixed>|null The schema, or null if not requested.
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
