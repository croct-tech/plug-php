<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Immutable options for fetching the content of a slot.
 *
 * Build it through the fluent API, starting from the default options and deriving copies with the
 * with-methods.
 *
 * @template-covariant TFallback The fallback content type, or `never` when no fallback is set.
 * @template-covariant TSchema of bool The schema flag: `true` once the schema is requested.
 */
final class FetchOptions
{
    private ?string $preferredLocale;

    private bool $static;

    private bool $includeSchema;

    /** @var array<string, mixed> */
    private array $attributes;

    private bool $fallbackProvided;

    /** @var TFallback */
    private mixed $fallback;

    /**
     * @param array<string, mixed> $attributes
     * @param TFallback            $fallback
     */
    private function __construct(
        ?string $preferredLocale,
        bool $static,
        bool $includeSchema,
        array $attributes,
        bool $fallbackProvided,
        mixed $fallback,
    ) {
        $this->preferredLocale = $preferredLocale;
        $this->static = $static;
        $this->includeSchema = $includeSchema;
        $this->attributes = $attributes;
        $this->fallbackProvided = $fallbackProvided;
        $this->fallback = $fallback;
    }

    /**
     * Creates the default set of options, with nothing set.
     *
     * @return self<never, false>
     */
    public static function defaults(): self
    {
        /** @var self<never, false> $options */
        $options = new self(null, false, false, [], false, null);

        return $options;
    }

    /**
     * Returns a copy that requests content in the given locale.
     *
     * @return self<TFallback, TSchema>
     */
    public function withPreferredLocale(string $preferredLocale): self
    {
        return $this->copy(preferredLocale: $preferredLocale);
    }

    /**
     * Returns a copy that fetches statically generated content (server-side only).
     *
     * @return self<TFallback, TSchema>
     */
    public function withStaticContent(bool $static = true): self
    {
        return $this->copy(static: $static);
    }

    /**
     * Returns a copy that includes (or excludes) the content schema in the response metadata.
     *
     * @return ($includeSchema is true ? self<TFallback, true> : self<TFallback, false>)
     */
    public function withSchema(bool $includeSchema = true): self
    {
        /** @var ($includeSchema is true ? self<TFallback, true> : self<TFallback, false>) $options */
        $options = new self(
            $this->preferredLocale,
            $this->static,
            $includeSchema,
            $this->attributes,
            $this->fallbackProvided,
            $this->fallback,
        );

        return $options;
    }

    /**
     * Returns a copy with the given custom attributes, replacing any existing ones.
     *
     * @param array<string, mixed> $attributes
     *
     * @return self<TFallback, TSchema>
     */
    public function withAttributes(array $attributes): self
    {
        return $this->copy(attributes: $attributes);
    }

    /**
     * Returns a copy with the given custom attribute added.
     *
     * @return self<TFallback, TSchema>
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
     *
     * @template T
     *
     * @param T $content
     *
     * @return self<T, TSchema>
     */
    public function withFallback(mixed $content): self
    {
        /** @var self<T, TSchema> $options */
        $options = new self(
            $this->preferredLocale,
            $this->static,
            $this->includeSchema,
            $this->attributes,
            true,
            $content,
        );

        return $options;
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
     * Checks whether statically generated content is requested.
     */
    public function isStaticContent(): bool
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
     *
     * @return TFallback
     */
    public function getFallback(): mixed
    {
        return $this->fallback;
    }

    /**
     * Returns a copy with the given fields overridden, keeping the rest.
     *
     * @param array<string, mixed>|null $attributes
     *
     * @return self<TFallback, TSchema>
     */
    private function copy(
        ?string $preferredLocale = null,
        ?bool $static = null,
        ?bool $includeSchema = null,
        ?array $attributes = null,
    ): self {
        /** @var self<TFallback, TSchema> $options */
        $options = new self(
            $preferredLocale ?? $this->preferredLocale,
            $static ?? $this->static,
            $includeSchema ?? $this->includeSchema,
            $attributes ?? $this->attributes,
            $this->fallbackProvided,
            $this->fallback,
        );

        return $options;
    }
}
