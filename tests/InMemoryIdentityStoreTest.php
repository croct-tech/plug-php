<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\InMemoryIdentityStore;
use Croct\Plug\Token;
use Croct\Plug\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryIdentityStore::class)]
#[TestDox('The in-memory identity store')]
final class InMemoryIdentityStoreTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    #[TestDox('Defaults to empty.')]
    public function testDefaultsToEmpty(): void
    {
        $store = new InMemoryIdentityStore();

        self::assertNull($store->getClientId());
        self::assertNull($store->getUserToken());
    }

    #[TestDox('Returns the client ID and user token it holds.')]
    public function testReturnsHeldValues(): void
    {
        $clientId = Uuid::random();
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000);

        $store = new InMemoryIdentityStore($clientId, $token);

        self::assertSame($clientId, $store->getClientId());
        self::assertSame($token, $store->getUserToken());
    }

    #[TestDox('Keeps the most recently saved values.')]
    public function testSavesValues(): void
    {
        $clientId = Uuid::random();
        $token = Token::issue(appId: self::APP_ID, subject: 'user-2', now: 1000);

        $store = new InMemoryIdentityStore();
        $store->saveClientId($clientId);
        $store->saveUserToken($token);

        self::assertSame($clientId, $store->getClientId());
        self::assertSame($token, $store->getUserToken());
    }
}
