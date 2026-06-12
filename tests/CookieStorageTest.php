<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\CookieConfiguration;
use Croct\Plug\CookieStorage;
use Croct\Plug\Token;
use Croct\Plug\Uuid;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookieStorage::class)]
#[TestDox('The cookie storage')]
final class CookieStorageTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const CLIENT_ID = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Holds the client ID and user token it is given.')]
    public function testHoldsGivenValues(): void
    {
        $clientId = Uuid::random();
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000);

        $storage = new CookieStorage($clientId, $token);

        self::assertSame($clientId, $storage->getClientId());
        self::assertSame($token, $storage->getUserToken());
    }

    #[TestDox('Exposes the cookie configuration it was given.')]
    public function testExposesConfiguration(): void
    {
        $configuration = new CookieConfiguration();

        $storage = new CookieStorage(configuration: $configuration);

        self::assertSame($configuration, $storage->getConfiguration());
    }

    #[TestDox('Reads and parses the client ID and user token from a cookie map.')]
    public function testReadsFromCookies(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000);

        $storage = CookieStorage::fromArray([
            'ct_client_id' => self::CLIENT_ID,
            'ct_user_token' => $token->toString(),
        ]);

        self::assertSame(self::CLIENT_ID, $storage->getClientId()?->toString());
        self::assertSame($token->toString(), $storage->getUserToken()?->toString());
    }

    #[TestDox('Ignores an unparseable client ID or user token.')]
    public function testIgnoresUnparseableValues(): void
    {
        $storage = CookieStorage::fromArray([
            'ct_client_id' => 'not-a-uuid',
            'ct_user_token' => 'garbage',
        ]);

        self::assertNull($storage->getClientId());
        self::assertNull($storage->getUserToken());
    }

    #[TestDox('Ignores absent cookies.')]
    public function testIgnoresAbsentCookies(): void
    {
        $storage = CookieStorage::fromArray([]);

        self::assertNull($storage->getClientId());
        self::assertNull($storage->getUserToken());
    }

    #[TestDox('Exposes the saved values as response cookies.')]
    public function testSavesAndExposesCookies(): void
    {
        $clientId = Uuid::random();
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000);

        $storage = new CookieStorage(now: 1000);
        $storage->saveClientId($clientId);
        $storage->saveUserToken($token);

        self::assertSame($clientId, $storage->getClientId());
        self::assertSame($token, $storage->getUserToken());

        [$clientIdCookie, $userTokenCookie] = $storage->getResponseCookies();

        self::assertSame('ct_client_id', $clientIdCookie->getName());
        self::assertSame($clientId->toString(), $clientIdCookie->getValue());
        self::assertSame('ct_user_token', $userTokenCookie->getName());
        self::assertSame($token->toString(), $userTokenCookie->getValue());
    }

    #[TestDox('Emits the response cookies through the given emitter.')]
    public function testEmitsResponseCookies(): void
    {
        $clientId = Uuid::random();
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000);

        $configuration = new CookieConfiguration(
            clientIdDuration: 100,
            userTokenDuration: 50,
            domain: 'example.com',
            secure: true,
            sameSite: 'Lax',
        );

        $storage = new CookieStorage(configuration: $configuration, now: 1000);
        $storage->saveClientId($clientId);
        $storage->saveUserToken($token);

        $calls = [];
        $storage->emit(static function (string $name, string $value, array $options) use (&$calls): bool {
            $calls[] = ['name' => $name, 'value' => $value, 'options' => $options];

            return true;
        });

        self::assertSame(
            [
                [
                    'name' => 'ct_client_id',
                    'value' => $clientId->toString(),
                    'options' => [
                        'expires' => 1100,
                        'path' => '/',
                        'domain' => 'example.com',
                        'secure' => true,
                        'httponly' => false,
                        'samesite' => 'Lax',
                    ],
                ],
                [
                    'name' => 'ct_user_token',
                    'value' => $token->toString(),
                    'options' => [
                        'expires' => 1050,
                        'path' => '/',
                        'domain' => 'example.com',
                        'secure' => true,
                        'httponly' => false,
                        'samesite' => 'Lax',
                    ],
                ],
            ],
            $calls,
        );
    }

    #[TestDox('Reads the cookies from the request superglobals.')]
    public function testReadsFromGlobals(): void
    {
        $originalCookie = $_COOKIE;
        $_COOKIE = ['ct_client_id' => self::CLIENT_ID];

        try {
            $storage = CookieStorage::fromGlobals();

            self::assertSame(self::CLIENT_ID, $storage->getClientId()?->toString());
        } finally {
            $_COOKIE = $originalCookie;
        }
    }

    #[TestDox('Reads the cookies from a server request.')]
    public function testReadsFromServerRequest(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', 'https://example.com/')
            ->withCookieParams(['ct_client_id' => self::CLIENT_ID]);

        $storage = CookieStorage::fromServerRequest($request);

        self::assertSame(self::CLIENT_ID, $storage->getClientId()?->toString());
    }

    #[TestDox('Reuses the process-wide instance built from the request cookies.')]
    public function testGlobalReturnsMemoizedInstance(): void
    {
        $originalCookie = $_COOKIE;
        $_COOKIE = ['ct_client_id' => self::CLIENT_ID];

        try {
            CookieStorage::reset();

            $first = CookieStorage::global();

            self::assertSame($first, CookieStorage::global());
            self::assertSame(self::CLIENT_ID, $first->getClientId()?->toString());
        } finally {
            CookieStorage::reset();
            $_COOKIE = $originalCookie;
        }
    }

    #[TestDox('Rebuilds the process-wide instance after a reset.')]
    public function testResetClearsTheGlobalInstance(): void
    {
        $originalCookie = $_COOKIE;
        $_COOKIE = [];

        try {
            CookieStorage::reset();

            $first = CookieStorage::global();
            CookieStorage::reset();

            self::assertNotSame($first, CookieStorage::global());
        } finally {
            CookieStorage::reset();
            $_COOKIE = $originalCookie;
        }
    }
}
