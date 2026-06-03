<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Immutable options for fetching the content of a slot.
 *
 * Build it through the fluent API, starting from the empty options and deriving copies with the
 * with-methods.
 */
final class FetchOptions
{
    private ?string $preferredLocale;

    private int|string|null $version;

    private bool $static;

    private bool $includeSchema;

    /** @var array<string, mixed> */
    private array $attributes;

    private bool $fallbackProvided;

    private mixed $fallback;

    /**
     * @param array<string, mixed> $attributes
     */
    private function __construct(
        ?string $preferredLocale,
        int|string|null $version,
        bool $static,
        bool $includeSchema,
        array $attributes,
        bool $fallbackProvided,
        mixed $fallback,
    ) {
        $this->preferredLocale = $preferredLocale;
        $this->version = $version;
        $this->static = $static;
        $this->includeSchema = $includeSchema;
        $this->attributes = $attributes;
        $this->fallbackProvided = $fallbackProvided;
        $this->fallback = $fallback;
    }

    /**
     * Creates an empty set of options.
     */
    public static function empty(): self
    {
        return new self(null, null, false, false, [], false, null);
    }

    /**
     * Returns a copy that requests content in the given locale.
     */
    public function withPreferredLocale(string $preferredLocale): self
    {
        return $this->copy(preferredLocale: $preferredLocale);
    }

    /**
     * Returns a copy that requests the given content version.
     */
    public function withVersion(int|string $version): self
    {
        return $this->copy(version: $version);
    }

    /**
     * Returns a copy that fetches statically generated content (server-side only).
     */
    public function withStatic(bool $static = true): self
    {
        return $this->copy(static: $static);
    }

    /**
     * Returns a copy that includes the content schema in the response metadata.
     */
    public function withSchema(bool $includeSchema = true): self
    {
        return $this->copy(includeSchema: $includeSchema);
    }

    /**
     * Returns a copy with the given custom attributes, replacing any existing ones.
     *
     * @param array<string, mixed> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return $this->copy(attributes: $attributes);
    }

    /**
     * Returns a copy with the given custom attribute added.
     */
    public function withAttribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return $this->copy(attributes: $attributes);
    }

    /**
     * Returns a copy with a fallback to return if the fetch fails.
     *
     * Without a fallback, a failed fetch throws an exception. The fallback may be any value,
     * including null, which is treated as a provided fallback rather than the absence of one.
     */
    public function withFallback(mixed $content): self
    {
        return new self(
            $this->preferredLocale,
            $this->version,
            $this->static,
            $this->includeSchema,
            $this->attributes,
            true,
            $content,
        );
    }

    /**
     * Gets the preferred content locale.
     *
     * @return string|null The locale, or null to use the default.
     */
    public function getPreferredLocale(): ?string
    {
        return $this->preferredLocale;
    }

    /**
     * Gets the requested content version.
     *
     * @return int|string|null The version, or null for the latest.
     */
    public function getVersion(): int|string|null
    {
        return $this->version;
    }

    /**
     * Checks whether statically generated content is requested.
     */
    public function isStatic(): bool
    {
        return $this->static;
    }

    /**
     * Checks whether the content schema is included in the response metadata.
     */
    public function includesSchema(): bool
    {
        return $this->includeSchema;
    }

    /**
     * Gets the custom attributes.
     *
     * @return array<string, mixed> The custom attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Checks whether a fallback was provided.
     */
    public function hasFallback(): bool
    {
        return $this->fallbackProvided;
    }

    /**
     * Gets the fallback content returned when the fetch fails.
     */
    public function getFallback(): mixed
    {
        return $this->fallback;
    }

    /**
     * Returns a copy with the given fields overridden, keeping the rest.
     *
     * @param array<string, mixed>|null $attributes
     */
    private function copy(
        ?string $preferredLocale = null,
        int|string|null $version = null,
        ?bool $static = null,
        ?bool $includeSchema = null,
        ?array $attributes = null,
    ): self {
        return new self(
            $preferredLocale ?? $this->preferredLocale,
            $version ?? $this->version,
            $static ?? $this->static,
            $includeSchema ?? $this->includeSchema,
            $attributes ?? $this->attributes,
            $this->fallbackProvided,
            $this->fallback,
        );
    }
}
