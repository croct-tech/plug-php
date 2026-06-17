<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

/**
 * A content provider backed by an in-memory map of versioned, localized content.
 *
 * The map mirrors the `slots.json` file written by the Croct CLI. Each slot ID points to a list
 * of versions, and each version holds its content keyed by locale.
 *
 * Lookups serve the latest version of the slot and resolve the language the same way as the
 * CLI-generated resolver: the requested language, then the default locale, then nothing.
 *
 * Malformed entries are skipped, so partial or hand-edited data never breaks a lookup.
 */
class ArrayContentProvider implements ContentProvider
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $slots;

    private ?string $defaultLocale;

    /**
     * @param array<array-key, mixed> $slots         The versioned content of each slot, keyed by
     *                                                ID. Each value is a list of
     *                                                `{version, content}` entries whose `content`
     *                                                maps a locale to its content object.
     * @param string|null             $defaultLocale The locale to serve when no language is
     *                                                requested or the requested one is unavailable.
     */
    public function __construct(array $slots, ?string $defaultLocale = null)
    {
        $this->slots = self::resolve($slots);
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSlotContent(string $id, ?string $language = null): ?array
    {
        $localized = $this->slots[$id] ?? null;

        if ($localized === null) {
            return null;
        }

        // Resolve the locale: the requested language, then the default, then nothing.
        foreach ([$language ?? $this->defaultLocale, $this->defaultLocale] as $locale) {
            if ($locale !== null && isset($localized[$locale])) {
                return $localized[$locale];
            }
        }

        return null;
    }

    /**
     * Reduces the versioned map to the latest localized content of each slot.
     *
     * @param array<array-key, mixed> $slots
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private static function resolve(array $slots): array
    {
        $resolved = [];

        foreach ($slots as $id => $versions) {
            if (!\is_array($versions)) {
                continue;
            }

            $localized = self::getLatestContent($versions);

            if ($localized === null) {
                continue;
            }

            $byLocale = self::filterLocalized($localized);

            if ($byLocale !== []) {
                $resolved[(string) $id] = $byLocale;
            }
        }

        return $resolved;
    }

    /**
     * Returns the localized content of the highest version, or null when none is valid.
     *
     * @param array<array-key, mixed> $versions
     *
     * @return array<array-key, mixed>|null
     */
    private static function getLatestContent(array $versions): ?array
    {
        $latest = null;
        $latestVersion = -1;

        foreach ($versions as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $version = $entry['version'] ?? null;
            $localized = $entry['content'] ?? null;

            if (\is_int($version) && \is_array($localized) && $version > $latestVersion) {
                $latest = $localized;
                $latestVersion = $version;
            }
        }

        return $latest;
    }

    /**
     * Keeps only the locales mapped to a content object, keyed by locale.
     *
     * @param array<array-key, mixed> $localized
     *
     * @return array<string, array<string, mixed>>
     */
    private static function filterLocalized(array $localized): array
    {
        $result = [];

        foreach ($localized as $locale => $value) {
            if (\is_array($value)) {
                /** @var array<string, mixed> $content */
                $content = $value;
                $result[(string) $locale] = $content;
            }
        }

        return $result;
    }
}
