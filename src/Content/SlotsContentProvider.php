<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

use Composer\InstalledVersions;

/**
 * A content provider backed by the `slots.json` file written by the Croct CLI.
 *
 * Reads the project's `croct.json` to locate `slots.json` and determine the
 * default locale, then serves the latest version of each slot's content as a
 * fallback. It requires no code generation: the data files are committed to the
 * project and read at runtime.
 */
final class SlotsContentProvider extends ArrayContentProvider
{
    private const CONFIG_FILE = 'croct.json';

    private const CONTENT_FILE = 'slots.json';

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

        return new self(self::resolve($content, self::getDefaultLocale($configuration)));
    }

    /**
     * Reduces the versioned, localized content to the latest version of each slot
     * in the given locale, keyed by slot ID.
     *
     * @param array<array-key, mixed> $content
     *
     * @return array<string, array<string, mixed>>
     */
    private static function resolve(array $content, ?string $locale): array
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

            $value = self::getLocalizedContent($localized, $locale);

            if ($value !== null) {
                $resolved[(string) $id] = $value;
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
     * @param array<array-key, mixed> $localized
     *
     * @return array<string, mixed>|null
     */
    private static function getLocalizedContent(array $localized, ?string $locale): ?array
    {
        $value = null;

        if ($locale !== null && \is_array($localized[$locale] ?? null)) {
            $value = $localized[$locale];
        } else {
            foreach ($localized as $candidate) {
                if (\is_array($candidate)) {
                    $value = $candidate;

                    break;
                }
            }
        }

        if (!\is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $content */
        $content = $value;

        return $content;
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
