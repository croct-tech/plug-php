<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\CookieConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookieConfiguration::class)]
#[TestDox('The cookie configuration')]
final class CookieConfigurationTest extends TestCase
{
    #[TestDox('Defaults to the standard cookie names and lifetimes.')]
    public function testProvidesDefaults(): void
    {
        $configuration = new CookieConfiguration();

        self::assertSame('ct.client_id', $configuration->getClientIdName());
        self::assertSame('ct.user_token', $configuration->getUserTokenName());
        self::assertSame(31536000, $configuration->getClientIdDuration());
        self::assertSame(604800, $configuration->getUserTokenDuration());
        self::assertNull($configuration->getDomain());
        self::assertTrue($configuration->isSecure());
        self::assertSame('None', $configuration->getSameSite());
    }

    #[TestDox('Exposes the configured values.')]
    public function testExposesConfiguredValues(): void
    {
        $configuration = new CookieConfiguration(
            clientIdName: 'cid',
            userTokenName: 'tok',
            clientIdDuration: 10,
            userTokenDuration: 20,
            domain: 'example.com',
            secure: false,
            sameSite: 'Lax',
        );

        self::assertSame('cid', $configuration->getClientIdName());
        self::assertSame('tok', $configuration->getUserTokenName());
        self::assertSame(10, $configuration->getClientIdDuration());
        self::assertSame(20, $configuration->getUserTokenDuration());
        self::assertSame('example.com', $configuration->getDomain());
        self::assertFalse($configuration->isSecure());
        self::assertSame('Lax', $configuration->getSameSite());
    }

    #[TestDox('Builds the browser cookie settings, lower-casing the SameSite policy.')]
    public function testConvertsToBrowserCookies(): void
    {
        self::assertSame(
            [
                'clientId' => [
                    'name' => 'ct.client_id',
                    'maxAge' => 31536000,
                    'path' => '/',
                    'secure' => true,
                    'sameSite' => 'none',
                ],
                'userToken' => [
                    'name' => 'ct.user_token',
                    'maxAge' => 604800,
                    'path' => '/',
                    'secure' => true,
                    'sameSite' => 'none',
                ],
            ],
            (new CookieConfiguration())->toBrowserCookies(),
        );
    }

    #[TestDox('Includes the domain in the browser cookie settings when configured.')]
    public function testIncludesDomainInBrowserCookies(): void
    {
        $cookies = (new CookieConfiguration(domain: 'example.com', sameSite: 'Lax'))->toBrowserCookies();

        self::assertSame('example.com', $cookies['clientId']['domain']);
        self::assertSame('example.com', $cookies['userToken']['domain']);
        self::assertSame('lax', $cookies['clientId']['sameSite']);
    }
}
