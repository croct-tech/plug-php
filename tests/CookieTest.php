<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\Cookie;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cookie::class)]
#[TestDox('A cookie')]
final class CookieTest extends TestCase
{
    #[TestDox('Exposes all of its attributes.')]
    public function testExposesAllFields(): void
    {
        $cookie = new Cookie(
            name: 'name',
            value: 'value',
            expiration: 5,
            path: '/path',
            domain: 'domain',
            secure: false,
            httpOnly: true,
            sameSite: 'Lax',
        );

        self::assertSame('name', $cookie->getName());
        self::assertSame('value', $cookie->getValue());
        self::assertSame(5, $cookie->getExpiration());
        self::assertSame('/path', $cookie->getPath());
        self::assertSame('domain', $cookie->getDomain());
        self::assertFalse($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('Lax', $cookie->getSameSite());
    }

    #[TestDox('Serializes a minimal HTTP-only cookie without optional attributes.')]
    public function testSerializesMinimalHttpOnlyCookie(): void
    {
        $header = (new Cookie(
            name: 'ct.x',
            value: 'v',
            expiration: null,
            path: '/',
            domain: null,
            secure: false,
            httpOnly: true,
            sameSite: null,
        ))->toSetCookieHeader();

        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringNotContainsString('Domain', $header);
        self::assertStringNotContainsString('Secure', $header);
        self::assertStringNotContainsString('SameSite', $header);
    }

    #[TestDox('Serializes to a Set-Cookie header.')]
    public function testSerializesToSetCookieHeader(): void
    {
        $cookie = new Cookie(
            name: 'ct_user_token',
            value: 'abc.def',
            expiration: 1000,
            path: '/',
            domain: 'example.com',
            secure: true,
            httpOnly: false,
            sameSite: 'None',
        );

        $header = $cookie->toSetCookieHeader(900);

        self::assertStringContainsString('ct_user_token=abc.def', $header);
        self::assertStringContainsString('Expires=Thu, 01 Jan 1970 00:16:40 GMT', $header);
        self::assertStringContainsString('Max-Age=100', $header);
        self::assertStringContainsString('Domain=example.com', $header);
        self::assertStringContainsString('Path=/', $header);
        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('SameSite=None', $header);
        self::assertStringNotContainsString('HttpOnly', $header);
    }

    #[TestDox('Has no expiry when it is a session cookie.')]
    public function testSessionCookieHasNoExpiry(): void
    {
        $header = (new Cookie(name: 'ct.preview_token', value: 'value', expiration: null))->toSetCookieHeader();

        self::assertStringNotContainsString('Max-Age', $header);
        self::assertStringNotContainsString('Expires', $header);
    }
}
