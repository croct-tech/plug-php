<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\Content\SlotMetadata;
use Croct\Plug\FetchResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(FetchResponse::class)]
#[TestDox('A fetch response')]
final class FetchResponseTest extends TestCase
{
    #[TestDox('Exposes the content and metadata given to the constructor.')]
    public function testExposesContentAndMetadata(): void
    {
        $response = new FetchResponse(['title' => 'Hello'], new SlotMetadata('1'));

        self::assertSame(['title' => 'Hello'], $response->getContent());
        self::assertSame('1', $response->getMetadata()?->getVersion());
    }

    #[TestDox('Defaults to no metadata for a bare content value.')]
    public function testDefaultsToNoMetadata(): void
    {
        $response = new FetchResponse('fallback');

        self::assertSame('fallback', $response->getContent());
        self::assertNull($response->getMetadata());
    }

    #[TestDox('Can be built from the decoded response payload.')]
    public function testBuildsFromResponse(): void
    {
        $response = FetchResponse::fromResponse([
            'content' => ['title' => 'Hello'],
            'metadata' => ['version' => '2'],
        ]);

        self::assertSame(['title' => 'Hello'], $response->getContent());
        self::assertSame('2', $response->getMetadata()?->getVersion());
    }

    #[TestDox('Falls back to empty content for a non-array payload.')]
    public function testHandlesNonArrayPayload(): void
    {
        $response = FetchResponse::fromResponse('unexpected');

        self::assertSame([], $response->getContent());
        self::assertNull($response->getMetadata());
    }

    #[TestDox('Ignores content and metadata of the wrong type.')]
    public function testIgnoresWrongTypes(): void
    {
        $response = FetchResponse::fromResponse(['content' => 'not-an-array', 'metadata' => 'not-an-array']);

        self::assertSame([], $response->getContent());
        self::assertNull($response->getMetadata());
    }
}
