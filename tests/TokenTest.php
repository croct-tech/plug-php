<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\Exception\MalformedTokenException;
use Croct\Plug\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(Token::class)]
#[TestDox('A visitor token')]
final class TokenTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    #[TestDox('Can be issued anonymous and unsigned by default.')]
    public function testIssuesAnonymousUnsignedToken(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: null, now: 1000);

        self::assertSame('none', $token->getAlgorithm());
        self::assertSame(self::APP_ID, $token->getApplicationId());
        self::assertSame(1000, $token->getIssueTime());
        self::assertTrue($token->isAnonymous());
        self::assertFalse($token->isSigned());
        self::assertStringEndsWith('.', $token->toString());
    }

    #[TestDox('Carries the subject when issued for a user.')]
    public function testIssuesTokenWithSubject(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'user-42', now: 1000);

        self::assertFalse($token->isAnonymous());
        self::assertSame('user-42', $token->getSubject());
        self::assertTrue($token->isSubject('user-42'));
    }

    #[TestDox('Cannot be issued with an empty subject.')]
    public function testRejectsEmptySubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Token::issue(self::APP_ID, '');
    }

    #[TestDox('Round-trips through its serialized form.')]
    public function testRoundTripsThroughSerialization(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000)->withDuration(86400, 1000);
        $parsed = Token::parse($token->toString());

        self::assertSame($token->toString(), $parsed->toString());
        self::assertSame('user-1', $parsed->getSubject());
        self::assertSame(1000, $parsed->getIssueTime());
        self::assertSame(87400, $parsed->getExpirationTime());
    }

    /**
     * @return array<string, array{now: int, expected: bool}>
     */
    public static function getTestsForValidity(): array
    {
        return [
            'before the issue time' => [
                'now' => 999,
                'expected' => false,
            ],
            'at the issue time' => [
                'now' => 1000,
                'expected' => true,
            ],
            'at the expiration time' => [
                'now' => 1100,
                'expected' => true,
            ],
            'after the expiration time' => [
                'now' => 1101,
                'expected' => false,
            ],
        ];
    }

    #[DataProvider('getTestsForValidity')]
    #[TestDox('Can only be valid between its issue and expiration times.')]
    public function testReportsValidity(int $now, bool $expected): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: null, now: 1000)->withDuration(100, 1000);

        self::assertSame($expected, $token->isValidNow($now));
    }

    #[TestDox('Compares issue times to tell which token is newer.')]
    public function testComparesIssueTimes(): void
    {
        $older = Token::issue(appId: self::APP_ID, subject: null, now: 1000);
        $newer = Token::issue(appId: self::APP_ID, subject: null, now: 2000);

        self::assertTrue($newer->isNewerThan($older));
        self::assertFalse($older->isNewerThan($newer));
    }

    #[TestDox('Compares equal by its headers, payload, and signature.')]
    public function testEquals(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000);

        self::assertTrue($token->equals(Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000)));
        self::assertFalse($token->equals(Token::issue(appId: self::APP_ID, subject: 'user-2', now: 1000)));
    }

    #[TestDox('Requires a valid UUID as the token ID.')]
    public function testRejectsNonUuidTokenId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Token::issue(self::APP_ID)->withTokenId('not-a-uuid');
    }

    #[TestDox('Produces a signature that verifies against the API key.')]
    public function testProducesVerifiableSignatureWhenSigned(): void
    {
        [$apiKey, $publicKey] = EcKeyFactory::create();

        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000)->signedWith($apiKey);

        self::assertSame('ES256', $token->getAlgorithm());
        self::assertSame($apiKey->getIdentifierHash(), $token->getKeyId());
        self::assertTrue($token->isSigned());
        self::assertTrue($token->matchesKeyId($apiKey));

        $parts = \explode('.', $token->toString());

        self::assertCount(3, $parts);

        $signature = \base64_decode(\strtr($parts[2], '-_', '+/'), true);

        self::assertNotFalse($signature);
        self::assertSame(64, \strlen($signature));
        self::assertSame(
            1,
            \openssl_verify(
                $parts[0] . '.' . $parts[1],
                EcKeyFactory::rawToDer($signature),
                $publicKey,
                \OPENSSL_ALGO_SHA256,
            ),
        );
    }

    /**
     * @return array<string, array{token: string}>
     */
    public static function getTestsForMalformedTokens(): array
    {
        return [
            'empty string' => [
                'token' => '',
            ],
            'single segment' => [
                'token' => 'not-a-token',
            ],
            'corrupted segments' => [
                'token' => '@@@.@@@',
            ],
        ];
    }

    #[DataProvider('getTestsForMalformedTokens')]
    #[TestDox('Cannot be parsed when malformed.')]
    public function testRejectsMalformedToken(string $token): void
    {
        $this->expectException(MalformedTokenException::class);

        Token::parse($token);
    }

    #[TestDox('Cannot be parsed when an otherwise valid token carries a fourth segment.')]
    public function testRejectsExtraSegments(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: null, now: 1000)->toString() . '.extra';

        $this->expectException(MalformedTokenException::class);

        Token::parse($token);
    }

    #[TestDox('Cannot be issued with a negative timestamp.')]
    public function testRejectsNegativeTimestamp(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Token::issue(appId: self::APP_ID, subject: null, now: -1);
    }

    /**
     * @return array<string, array{headers: array<string, mixed>, payload: array<string, mixed>}>
     */
    public static function getTestsForInvalidClaims(): array
    {
        return [
            'missing header' => [
                'headers' => ['typ' => 'JWT'],
                'payload' => ['iss' => 'croct.io', 'aud' => 'croct.io', 'iat' => 1],
            ],
            'missing issuer' => [
                'headers' => ['typ' => 'JWT', 'alg' => 'none'],
                'payload' => ['aud' => 'croct.io', 'iat' => 1],
            ],
            'missing audience' => [
                'headers' => ['typ' => 'JWT', 'alg' => 'none'],
                'payload' => ['iss' => 'croct.io', 'iat' => 1],
            ],
            'missing issue time' => [
                'headers' => ['typ' => 'JWT', 'alg' => 'none'],
                'payload' => ['iss' => 'croct.io', 'aud' => 'croct.io'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $payload
     */
    #[DataProvider('getTestsForInvalidClaims')]
    #[TestDox('Cannot be created with missing or invalid claims.')]
    public function testRejectsInvalidClaims(array $headers, array $payload): void
    {
        $this->expectException(MalformedTokenException::class);

        Token::of($headers, $payload);
    }

    #[TestDox('Cannot be parsed when a segment is not valid JSON.')]
    public function testRejectsNonJsonSegment(): void
    {
        $this->expectException(MalformedTokenException::class);

        Token::parse(self::base64Url('not json') . '.' . self::base64Url('{}'));
    }

    #[TestDox('Cannot be parsed when a segment is not a JSON object.')]
    public function testRejectsNonObjectSegment(): void
    {
        $this->expectException(MalformedTokenException::class);

        Token::parse(self::base64Url('123') . '.' . self::base64Url('{}'));
    }

    #[TestDox('Carries a token ID when one is set.')]
    public function testSetsTokenId(): void
    {
        $tokenId = '22222222-2222-4222-8222-222222222222';

        self::assertSame($tokenId, Token::issue(self::APP_ID)->withTokenId($tokenId)->getTokenId());
        self::assertNull(Token::issue(self::APP_ID)->getTokenId());
    }

    #[TestDox('Casts to its serialized form.')]
    public function testCastsToString(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000);

        self::assertSame($token->toString(), (string) $token);
    }

    #[TestDox('Cannot be serialized when a claim is not encodable.')]
    public function testRejectsUnencodableClaims(): void
    {
        $token = Token::of(
            ['typ' => 'JWT', 'alg' => 'none', 'appId' => "\xB1\x31"],
            ['iss' => 'croct.io', 'aud' => 'croct.io', 'iat' => 1000],
        );

        $this->expectException(\LogicException::class);

        $token->toString();
    }

    private static function base64Url(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }
}
