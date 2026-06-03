<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Immutable options for evaluating a CQL query.
 *
 * Build it through the fluent API, starting from the empty options and deriving copies with the
 * with-methods.
 */
final class EvaluationOptions
{
    /** @var array<string, mixed> */
    private array $attributes;

    private mixed $fallback;

    private bool $fallbackProvided;

    /**
     * @param array<string, mixed> $attributes
     */
    private function __construct(array $attributes, mixed $fallback, bool $fallbackProvided)
    {
        $this->attributes = $attributes;
        $this->fallback = $fallback;
        $this->fallbackProvided = $fallbackProvided;
    }

    /**
     * Creates an empty set of options.
     */
    public static function empty(): self
    {
        return new self([], null, false);
    }

    /**
     * Returns a copy with the given custom attributes, replacing any existing ones.
     *
     * @param array<string, mixed> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self($attributes, $this->fallback, $this->fallbackProvided);
    }

    /**
     * Returns a copy with the given custom attribute added.
     */
    public function withAttribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self($attributes, $this->fallback, $this->fallbackProvided);
    }

    /**
     * Returns a copy with a fallback result to return if the evaluation fails.
     *
     * Without a fallback, a failed evaluation throws an exception.
     */
    public function withFallback(mixed $fallback): self
    {
        return new self($this->attributes, $fallback, true);
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
     * Checks whether a fallback result was provided.
     */
    public function hasFallback(): bool
    {
        return $this->fallbackProvided;
    }

    /**
     * Gets the fallback result returned when the evaluation fails.
     */
    public function getFallback(): mixed
    {
        return $this->fallback;
    }
}
