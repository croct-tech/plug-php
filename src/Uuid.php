<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * An RFC 4122 universally unique identifier.
 *
 * Validation is case-insensitive and the canonical string form is lowercase.
 */
final class Uuid implements \Stringable
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Generates a random version 4 UUID.
     */
    public static function random(): self
    {
        $bytes = \random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        return new self(\vsprintf('%s%s-%s-%s-%s-%s%s%s', \str_split(\bin2hex($bytes), 4)));
    }

    /**
     * Parses a UUID from its canonical string form, normalizing it to lowercase.
     *
     * @throws \InvalidArgumentException If the value is not a valid UUID.
     */
    public static function parse(string $value): self
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException(\sprintf('The value "%s" is not a valid UUID.', $value));
        }

        return new self(\strtolower($value));
    }

    /**
     * Checks whether the given value is a valid UUID.
     */
    public static function isValid(string $value): bool
    {
        return \preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Checks whether this UUID is equal to the given one.
     */
    public function equals(self $uuid): bool
    {
        return $this->value === $uuid->value;
    }

    /**
     * Gets the canonical lowercase string representation.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Gets the canonical lowercase string representation.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
