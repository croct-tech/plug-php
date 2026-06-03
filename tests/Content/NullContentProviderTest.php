<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Content;

use Croct\Plug\Content\NullContentProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullContentProvider::class)]
#[TestDox('A null content provider')]
final class NullContentProviderTest extends TestCase
{
    #[TestDox('Has no content for any slot.')]
    public function testHasNoContent(): void
    {
        self::assertNull((new NullContentProvider())->getContent('home-hero'));
    }
}
