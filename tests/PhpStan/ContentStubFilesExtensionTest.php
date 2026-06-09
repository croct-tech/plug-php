<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\PhpStan;

use Croct\Plug\PhpStan\ContentStubFilesExtension;
use Croct\Plug\Tests\Fixtures\VirtualFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentStubFilesExtension::class)]
#[TestDox('The PHPStan content stub extension')]
final class ContentStubFilesExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        VirtualFilesystem::setUp();
    }

    protected function tearDown(): void
    {
        VirtualFilesystem::tearDown();
    }

    #[TestDox('Provides the generated stub when it exists in the working directory.')]
    public function testProvidesTheStubWhenPresent(): void
    {
        $stub = VirtualFilesystem::path('.croct/types.php');

        VirtualFilesystem::write($stub, '<?php');

        $extension = new ContentStubFilesExtension(VirtualFilesystem::path());

        self::assertSame([$stub], $extension->getFiles());
    }

    #[TestDox('Provides no files when the stub is absent.')]
    public function testProvidesNothingWhenAbsent(): void
    {
        $extension = new ContentStubFilesExtension(VirtualFilesystem::path());

        self::assertSame([], $extension->getFiles());
    }
}
