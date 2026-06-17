<?php

declare(strict_types=1);

namespace Croct\Plug\Psalm;

use Psalm\Plugin\PluginEntryPointInterface as PluginEntryPoint;
use Psalm\Plugin\RegistrationInterface as PluginRegistration;
use SimpleXMLElement;

/**
 * Registers the CLI-generated content typing stub with Psalm.
 *
 * The stub is written to `slots.stub` at the project root by the Croct CLI. It
 * is registered only when present, so analysis keeps working in projects that
 * have not generated it yet.
 */
final class ContentStubPlugin implements PluginEntryPoint
{
    private const STUB_PATH = 'slots.stub';

    private ?string $baseDirectory;

    public function __construct(?string $baseDirectory = null)
    {
        if ($baseDirectory === null) {
            $directory = \getcwd();
            $baseDirectory = $directory === false ? null : $directory;
        }

        $this->baseDirectory = $baseDirectory;
    }

    public function __invoke(PluginRegistration $registration, ?SimpleXMLElement $config = null): void
    {
        if ($this->baseDirectory === null) {
            return;
        }

        $path = $this->baseDirectory . \DIRECTORY_SEPARATOR . self::STUB_PATH;

        if (\is_file($path)) {
            $registration->addStubFile($path);
        }
    }
}
