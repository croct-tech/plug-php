<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Content;

use Croct\Plug\Content\ArrayContentProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayContentProvider::class)]
#[TestDox('An array content provider')]
final class ArrayContentProviderTest extends TestCase
{
    #[TestDox('Returns the content mapped to a slot ID.')]
    public function testReturnsMappedContent(): void
    {
        $provider = new ArrayContentProvider(['home-hero' => ['title' => 'Hello']]);

        self::assertSame(['title' => 'Hello'], $provider->getContent('home-hero'));
    }

    #[TestDox('Returns null for an unknown slot ID.')]
    public function testReturnsNullForUnknownSlot(): void
    {
        self::assertNull((new ArrayContentProvider([]))->getContent('missing'));
    }
}
