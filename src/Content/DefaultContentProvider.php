<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

use Composer\InstalledVersions;

/**
 * A content provider backed by the `slots.json` file written by the Croct CLI.
 */
final class DefaultContentProvider extends ArrayContentProvider
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

        return new self($content, self::getDefaultLocale($configuration));
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
