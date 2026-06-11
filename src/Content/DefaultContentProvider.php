<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

use Composer\InstalledVersions;

/**
 * A content provider backed by the `slots.json` file written by the Croct CLI.
 *
 * Serves the latest version of each slot's content as a fallback, resolving the language at
 * lookup time the same way as the CLI-generated resolver: the requested language, then the
 * project's default locale, then nothing.
 */
final class DefaultContentProvider implements ContentProvider
{
    private const CONFIG_FILE = 'croct.json';

    private const CONTENT_FILE = 'slots.json';

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $slots;

    private ?string $defaultLocale;

    /**
     * @param array<string, array<string, array<string, mixed>>> $slots The latest content of each
     *                                                                   slot, keyed by ID then locale.
     */
    public function __construct(array $slots, ?string $defaultLocale = null)
    {
        $this->slots = $slots;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Builds a provider from the project's committed content files.
     *
     * Reads from the given project directory, defaulting to the root package's
     * install path. Returns null when the content files cannot be located, so the
     * caller can fall back to another provider.
     */
    public static function fromProject(?string $projectDirectory = null): ?self
    {
        $root = $projectDirectory ?? self::getProjectDirectory();

        $configuration = self::readJson($root . \DIRECTORY_SEPARATOR . self::CONFIG_FILE);

        if ($configuration === null) {
            return null;
        }

        $content = self::readJson(
            $root
            . \DIRECTORY_SEPARATOR
            . self::getContentDirectory($configuration)
            . \DIRECTORY_SEPARATOR
            . self::CONTENT_FILE,
        );

        if ($content === null) {
            return null;
        }

        return new self(self::resolve($content), self::getDefaultLocale($configuration));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContent(string $id, ?string $language = null): ?array
    {
        $localized = $this->slots[$id] ?? null;

        if ($localized === null) {
            return null;
        }

        // Mirror the CLI-generated resolver: the requested language, then the default, then nothing.
        foreach ([$language ?? $this->defaultLocale, $this->defaultLocale] as $candidate) {
            if ($candidate !== null && isset($localized[$candidate])) {
                return $localized[$candidate];
            }
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $content
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private static function resolve(array $content): array
    {
        $resolved = [];

        foreach ($content as $id => $versions) {
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

    /**
     * @param array<array-key, mixed> $configuration
     */
    private static function getContentDirectory(array $configuration): string
    {
        $paths = $configuration['paths'] ?? null;

        if (\is_array($paths) && \is_string($paths['content'] ?? null)) {
            return $paths['content'];
        }

        return '.';
    }

    /**
     * @param array<array-key, mixed> $configuration
     */
    private static function getDefaultLocale(array $configuration): ?string
    {
        $locale = $configuration['defaultLocale'] ?? null;

        return \is_string($locale) ? $locale : null;
    }

    private static function getProjectDirectory(): string
    {
        return \rtrim(InstalledVersions::getRootPackage()['install_path'], \DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function readJson(string $path): ?array
    {
        if (!\is_file($path)) {
            return null;
        }

        $contents = \file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $data = \json_decode($contents, true);

        return \is_array($data) ? $data : null;
    }
}
