<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Fixtures;

/**
 * An in-memory filesystem exposed through a stream wrapper.
 */
final class VirtualFilesystem
{
    public const PROTOCOL = 'croct-vfs';

    /**
     * Seeded files, keyed by normalized path. A null value marks a file that
     * exists but cannot be opened, used to exercise read-failure handling.
     *
     * @var array<string, string|null>
     */
    private static array $files = [];

    /** @var resource|null */
    public $context;

    private string $contents = '';

    private int $position = 0;

    public static function setUp(): void
    {
        self::$files = [];

        if (!\in_array(self::PROTOCOL, \stream_get_wrappers(), true)) {
            \stream_wrapper_register(self::PROTOCOL, self::class);
        }
    }

    public static function tearDown(): void
    {
        self::$files = [];

        if (\in_array(self::PROTOCOL, \stream_get_wrappers(), true)) {
            \stream_wrapper_unregister(self::PROTOCOL);
        }
    }

    /**
     * Resolves a path within the virtual filesystem, defaulting to its root.
     */
    public static function path(string $path = ''): string
    {
        $root = self::PROTOCOL . '://root';

        return $path === '' ? $root : $root . '/' . \ltrim($path, '/');
    }

    public static function write(string $path, string $contents): void
    {
        self::$files[self::normalize($path)] = $contents;
    }

    public static function writeUnreadable(string $path): void
    {
        self::$files[self::normalize($path)] = null;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $contents = self::$files[self::normalize($path)] ?? null;

        if ($contents === null) {
            return false;
        }

        $this->contents = $contents;
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = \substr($this->contents, $this->position, $count);
        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen($this->contents);
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return self::stat(\strlen($this->contents));
    }

    public function stream_close(): void
    {
        // Nothing to release: the contents live in memory for the stream's lifetime.
    }

    /**
     * @return array<string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $normalized = self::normalize($path);

        if (!\array_key_exists($normalized, self::$files)) {
            return false;
        }

        $contents = self::$files[$normalized];

        return self::stat($contents === null ? 0 : \strlen($contents));
    }

    private static function normalize(string $path): string
    {
        return \str_replace('/./', '/', $path);
    }

    /**
     * @return array<string, int>
     */
    private static function stat(int $size): array
    {
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100644,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => $size,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => -1,
            'blocks' => -1,
        ];
    }
}
