<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;
use Croct\Plug\IdentityResolver;
use Croct\Plug\InMemoryIdentityStore;
use Croct\Plug\Session;
use Croct\Plug\Token;
use Croct\Plug\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
#[TestDox('A visitor session')]
final class SessionTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const CLIENT_ID = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Generates a client ID when none is present.')]
    public function testGeneratesClientIdWhenMissing(): void
    {
        $session = $this->createSession(null);

        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            $session->getClientId()->toString(),
        );
    }

    #[TestDox('Reuses the stored client ID.')]
    public function testReusesStoredClientId(): void
    {
        $clientId = Uuid::parse(self::CLIENT_ID);

        $session = $this->createSession($clientId);

        self::assertSame($clientId, $session->getClientId());
    }

    #[TestDox('Issues an anonymous, unsigned token without a prior token.')]
    public function testIssuesAnonymousUnsignedTokenWithoutToken(): void
    {
        $session = $this->createSession(null);

        self::assertTrue($session->getUserToken()->isAnonymous());
        self::assertFalse($session->getUserToken()->isSigned());
    }

    #[TestDox('Signs the token when the API key carries a private key.')]
    public function testSignsTokenWhenKeyHasPrivateKey(): void
    {
        [$apiKey] = EcKeyFactory::create();

        $session = $this->createSession(null, null, $apiKey);

        self::assertTrue($session->getUserToken()->isSigned());
        self::assertTrue($session->getUserToken()->matchesKeyId($apiKey));
    }

    #[TestDox('Keeps a valid token untouched.')]
    public function testKeepsValidToken(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'user-7', now: 1000)->withDuration(86400, 1000);

        $session = $this->createSession(null, $token);

        self::assertSame($token->toString(), $session->getUserToken()->toString());
    }

    #[TestDox('Anonymizes an expired token when no user is resolved.')]
    public function testAnonymizesExpiredTokenWithoutResolver(): void
    {
        $expired = Token::issue(appId: self::APP_ID, subject: 'user-9', now: 100)->withDuration(86400, 100);

        $session = $this->createSession(null, $expired, now: 200000);

        self::assertTrue($session->getUserToken()->isAnonymous());
        self::assertTrue($session->getUserToken()->isValidNow(200000));
    }

    #[TestDox('Identifies the visitor from the resolver when no token is stored.')]
    public function testIdentifiesFromResolverWithoutToken(): void
    {
        $session = $this->createSession(null, null, identity: $this->resolver('alice'));

        self::assertSame('alice', $session->getUserToken()->getSubject());
    }

    #[TestDox('Re-identifies the visitor when the resolved user changes on login.')]
    public function testReconcilesSubjectOnLogin(): void
    {
        $anonymous = Token::issue(appId: self::APP_ID, now: 1000)->withDuration(86400, 1000);

        $session = $this->createSession(null, $anonymous, identity: $this->resolver('alice'));

        self::assertSame('alice', $session->getUserToken()->getSubject());
    }

    #[TestDox('Anonymizes the visitor when the user logs out.')]
    public function testReconcilesSubjectOnLogout(): void
    {
        $identified = Token::issue(appId: self::APP_ID, subject: 'alice', now: 1000)->withDuration(86400, 1000);

        $session = $this->createSession(null, $identified, identity: $this->resolver(null));

        self::assertTrue($session->getUserToken()->isAnonymous());
    }

    #[TestDox('Keeps the token when the resolved user already matches.')]
    public function testKeepsTokenWhenResolverMatches(): void
    {
        $identified = Token::issue(appId: self::APP_ID, subject: 'alice', now: 1000)->withDuration(86400, 1000);

        $session = $this->createSession(null, $identified, identity: $this->resolver('alice'));

        self::assertSame($identified->toString(), $session->getUserToken()->toString());
    }

    #[TestDox('Signs an unsigned token, keeping the resolved user.')]
    public function testUpgradesUnsignedTokenToSigned(): void
    {
        [$apiKey] = EcKeyFactory::create();
        $unsigned = Token::issue(appId: self::APP_ID, subject: 'user-3', now: 1000)->withDuration(86400, 1000);

        $session = $this->createSession(null, $unsigned, $apiKey, identity: $this->resolver('user-3'));

        self::assertTrue($session->getUserToken()->isSigned());
        self::assertSame('user-3', $session->getUserToken()->getSubject());
    }

    #[TestDox('Issues an anonymous token when the token belongs to another application.')]
    public function testIssuesAnonymousForForeignAppToken(): void
    {
        $foreign = Token::issue(appId: '99999999-9999-4999-8999-999999999999', subject: 'user-x', now: 1000)
            ->withDuration(86400, 1000);

        $session = $this->createSession(null, $foreign);

        self::assertTrue($session->getUserToken()->isAnonymous());
        self::assertSame(self::APP_ID, $session->getUserToken()->getApplicationId());
    }

    #[TestDox('Discards a foreign application token even when it is expired, never carrying its subject over.')]
    public function testIssuesAnonymousForExpiredForeignAppToken(): void
    {
        $foreign = Token::issue(appId: '99999999-9999-4999-8999-999999999999', subject: 'user-x', now: 100)
            ->withDuration(86400, 100);

        $session = $this->createSession(null, $foreign, now: 200000);

        self::assertTrue($session->getUserToken()->isAnonymous());
        self::assertSame(self::APP_ID, $session->getUserToken()->getApplicationId());
    }

    #[TestDox('Reflects identification and anonymization in the token.')]
    public function testIdentifyAndAnonymize(): void
    {
        $session = $this->createSession(null);

        $session->identify('user-42');
        self::assertSame('user-42', $session->getUserToken()->getSubject());

        $session->anonymize();
        self::assertTrue($session->getUserToken()->isAnonymous());
    }

    #[TestDox('Rejects identifying with an empty user ID.')]
    public function testRejectsEmptyUserId(): void
    {
        $session = $this->createSession(null);

        $this->expectException(\InvalidArgumentException::class);

        $session->identify('');
    }

    #[TestDox('Re-signs a token that was signed with a different key, preserving subject and ID.')]
    public function testReSignsTokenFromDifferentKey(): void
    {
        [$sessionKey] = EcKeyFactory::create();
        [$otherKey] = EcKeyFactory::create('11111111-1111-4111-8111-111111111111');

        $foreign = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000)
            ->withDuration(86400, 1000)
            ->withTokenId('22222222-2222-4222-8222-222222222222')
            ->signedWith($otherKey);

        $token = $this->createSession(null, $foreign, $sessionKey)->getUserToken();

        self::assertTrue($token->isSigned());
        self::assertTrue($token->matchesKeyId($sessionKey));
        self::assertSame('user-1', $token->getSubject());
        self::assertSame('22222222-2222-4222-8222-222222222222', $token->getTokenId());
    }

    #[TestDox('Generates a fresh token ID when re-signing a token whose ID is not a valid UUID.')]
    public function testReSignsTokenWithInvalidTokenId(): void
    {
        [$sessionKey] = EcKeyFactory::create();
        [$otherKey] = EcKeyFactory::create('11111111-1111-4111-8111-111111111111');

        // A tampered cookie can carry a non-UUID "jti", which Token::parse() does not reject.
        $foreign = Token::of(
            ['typ' => 'JWT', 'alg' => 'none', 'appId' => self::APP_ID],
            [
                'iss' => 'croct.io',
                'aud' => 'croct.io',
                'iat' => 1000,
                'exp' => 1000 + 86400,
                'sub' => 'user-1',
                'jti' => 'not-a-uuid',
            ],
        )->signedWith($otherKey);

        $token = $this->createSession(null, $foreign, $sessionKey)->getUserToken();

        self::assertTrue($token->isSigned());
        self::assertTrue($token->matchesKeyId($sessionKey));
        self::assertSame('user-1', $token->getSubject());

        $tokenId = $token->getTokenId();

        self::assertNotNull($tokenId);
        self::assertNotSame('not-a-uuid', $tokenId);
        self::assertTrue(Uuid::isValid($tokenId));
    }

    #[TestDox('Treats an empty resolved user ID as anonymous.')]
    public function testTreatsEmptySubjectAsAnonymous(): void
    {
        [$sessionKey] = EcKeyFactory::create();

        $token = $this->createSession(null, null, $sessionKey, identity: $this->resolver(''))->getUserToken();

        self::assertTrue($token->isSigned());
        self::assertTrue($token->isAnonymous());
    }

    private function createSession(
        ?Uuid $clientId,
        ?Token $userToken = null,
        ?ApiKey $apiKey = null,
        int $now = 1000,
        ?IdentityResolver $identity = null,
    ): Session {
        return new Session(
            appId: self::APP_ID,
            apiKey: $apiKey ?? ApiKey::of(EcKeyFactory::IDENTIFIER),
            store: new InMemoryIdentityStore($clientId, $userToken),
            tokenDuration: 86400,
            signTokens: null,
            now: $now,
            identity: $identity,
        );
    }

    private function resolver(?string $userId): IdentityResolver
    {
        $identity = $this->createMock(IdentityResolver::class);
        $identity->method('getUserId')->willReturn($userId);

        return $identity;
    }
}
