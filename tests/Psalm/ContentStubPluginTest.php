<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Psalm;

use Croct\Plug\Psalm\ContentStubPlugin;
use Croct\Plug\Tests\Fixtures\VirtualFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psalm\Plugin\RegistrationInterface as PluginRegistration;

#[CoversClass(ContentStubPlugin::class)]
#[TestDox('The Psalm content stub plugin')]
final class ContentStubPluginTest extends TestCase
{
    private string $originalWorkingDirectory;

    protected function setUp(): void
    {
        $cwd = \getcwd();

        self::assertNotFalse($cwd);

        $this->originalWorkingDirectory = $cwd;

        VirtualFilesystem::setUp();
    }

    protected function tearDown(): void
    {
        \chdir($this->originalWorkingDirectory);

        VirtualFilesystem::tearDown();
    }

    #[TestDox('Registers the generated stub when it exists in the base directory.')]
    public function testRegistersTheStubWhenPresent(): void
    {
        $stub = VirtualFilesystem::path('slots.php');

        VirtualFilesystem::write($stub, '<?php');

        $registration = $this->createMock(PluginRegistration::class);
        $registration->expects($this->once())
            ->method('addStubFile')
            ->with($stub);

        (new ContentStubPlugin(VirtualFilesystem::path()))($registration);
    }

    #[TestDox('Registers nothing when the stub is absent.')]
    public function testRegistersNothingWhenAbsent(): void
    {
        $registration = $this->createMock(PluginRegistration::class);
        $registration->expects($this->never())
            ->method('addStubFile');

        (new ContentStubPlugin(VirtualFilesystem::path()))($registration);
    }

    #[TestDox('Defaults the base directory to the current working directory.')]
    public function testDefaultsToTheCurrentWorkingDirectory(): void
    {
        // With no explicit directory it resolves to the process working directory,
        // which holds no generated stub, so nothing is registered.
        $registration = $this->createMock(PluginRegistration::class);
        $registration->expects($this->never())
            ->method('addStubFile');

        (new ContentStubPlugin())($registration);
    }

    #[TestDox('Registers nothing when the working directory cannot be resolved.')]
    public function testRegistersNothingWhenWorkingDirectoryIsUnavailable(): void
    {
        // getcwd() only fails when the working directory no longer exists, which
        // cannot be simulated virtually, so an empty directory is removed underfoot.
        $removed = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . \uniqid('croct-psalm-gone-', true);

        \mkdir($removed, 0777, true);
        \chdir($removed);
        \rmdir($removed);

        $plugin = new ContentStubPlugin();

        \chdir($this->originalWorkingDirectory);

        $registration = $this->createMock(PluginRegistration::class);
        $registration->expects($this->never())
            ->method('addStubFile');

        $plugin($registration);
    }
}
