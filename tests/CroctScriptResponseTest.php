<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\CroctScriptResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(CroctScriptResponse::class)]
#[TestDox('The captured script response')]
final class CroctScriptResponseTest extends TestCase
{
    #[TestDox('Exposes the status code, headers and content.')]
    public function testExposesResponse(): void
    {
        $response = new CroctScriptResponse(200, ['Content-Type' => 'text/javascript'], '// plug');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['Content-Type' => 'text/javascript'], $response->getHeaders());
        self::assertSame('// plug', $response->getContent());
    }
}
