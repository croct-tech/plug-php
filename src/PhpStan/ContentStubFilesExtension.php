<?php

declare(strict_types=1);

namespace Croct\Plug\PhpStan;

use PHPStan\PhpDoc\StubFilesExtension;

/**
 * Registers the CLI-generated content typing stub with PHPStan.
 *
 * The stub is written to `.croct/types.php` at the project root by the Croct
 * CLI. It is provided to PHPStan only when present, so analysis keeps working
 * in projects that have not generated it yet.
 */
final class ContentStubFilesExtension implements StubFilesExtension
{
    private const STUB_PATH = '.croct' . \DIRECTORY_SEPARATOR . 'types.php';

    /**
     * @return list<string>
     */
    public function getFiles(): array
    {
        $directory = \getcwd();

        if ($directory === false) {
            return [];
        }

        $path = $directory . \DIRECTORY_SEPARATOR . self::STUB_PATH;

        return \is_file($path) ? [$path] : [];
    }
}
