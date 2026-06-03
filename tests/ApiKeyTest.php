<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;
use Croct\Plug\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiKey::class)]
#[TestDox('An API key')]
final class ApiKeyTest extends TestCase
{
    private const IDENTIFIER = '00000000-0000-4000-8000-000000000000';

    #[TestDox('Can be created from an identifier alone.')]
    public function testParsesIdentifierOnlyKey(): void
    {
        $apiKey = ApiKey::parse(self::IDENTIFIER);

        self::assertSame(self::IDENTIFIER, $apiKey->getIdentifier());
        self::assertFalse($apiKey->hasPrivateKey());
    }

    #[TestDox('Can carry a private key for signing.')]
    public function testParsesKeyWithPrivateKey(): void
    {
        [$apiKey] = EcKeyFactory::create();

        self::assertTrue($apiKey->hasPrivateKey());
        self::assertSame('ES256', $apiKey->getSigningAlgorithm());
        self::assertStringStartsWith('ES256;', $apiKey->getPrivateKey());
    }

    /**
     * @return array<string, array{identifier: string, privateKey: string|null}>
     */
    public static function getTestsForInvalidKeys(): array
    {
        return [
            'non-UUID identifier' => [
                'identifier' => 'not-a-uuid',
                'privateKey' => null,
            ],
            'too many segments' => [
                'identifier' => 'a:b:c',
                'privateKey' => null,
            ],
            'malformed private key' => [
                'identifier' => self::IDENTIFIER,
                'privateKey' => 'no-separator',
            ],
            'unsupported algorithm' => [
                'identifier' => self::IDENTIFIER,
                'privateKey' => 'RS256;key',
            ],
        ];
    }

    #[DataProvider('getTestsForInvalidKeys')]
    #[TestDox('Cannot be created from a malformed value.')]
    public function testRejectsInvalidKeys(string $identifier, ?string $privateKey): void
    {
        $this->expectException(ConfigurationException::class);

        $privateKey === null ? ApiKey::parse($identifier) : ApiKey::of($identifier, $privateKey);
    }

    #[TestDox('Derives the key ID from the SHA-256 of the identifier bytes.')]
    public function testComputesIdentifierHash(): void
    {
        $bytes = \hex2bin(\str_replace('-', '', self::IDENTIFIER));

        self::assertNotFalse($bytes);
        self::assertSame(\hash('sha256', $bytes), ApiKey::of(self::IDENTIFIER)->getIdentifierHash());
    }

    #[TestDox('Signs data with a verifiable ES256 signature.')]
    public function testSignsWithVerifiableSignature(): void
    {
        [$apiKey, $publicKey] = EcKeyFactory::create();

        $signature = $apiKey->sign('header.payload');

        self::assertSame(64, \strlen($signature));
        self::assertSame(
            1,
            \openssl_verify('header.payload', EcKeyFactory::rawToDer($signature), $publicKey, \OPENSSL_ALGO_SHA256),
        );
    }

    #[TestDox('Can be cast to a redacted string.')]
    public function testRedactsToString(): void
    {
        [$apiKey] = EcKeyFactory::create();

        self::assertSame('[redacted]', (string) $apiKey);
    }

    #[TestDox('Round-trips through its exported form.')]
    public function testRoundTripsThroughExport(): void
    {
        [$apiKey] = EcKeyFactory::create();

        self::assertSame($apiKey->export(), ApiKey::parse($apiKey->export())->export());
    }

    #[TestDox('Exports an identifier-only key without a private key.')]
    public function testExportsIdentifierOnlyKey(): void
    {
        self::assertSame(self::IDENTIFIER, ApiKey::of(self::IDENTIFIER)->export());
    }

    #[TestDox('Returns the same instance when created from one.')]
    public function testReusesExistingInstance(): void
    {
        $apiKey = ApiKey::of(self::IDENTIFIER);

        self::assertSame($apiKey, ApiKey::from($apiKey));
    }

    #[TestDox('Parses an instance from its serialized string form.')]
    public function testParsesFromSerializedString(): void
    {
        self::assertSame(self::IDENTIFIER, ApiKey::from(self::IDENTIFIER)->getIdentifier());
    }

    #[TestDox('Rejects reading the signing algorithm without a private key.')]
    public function testRejectsAlgorithmWithoutPrivateKey(): void
    {
        $this->expectException(ConfigurationException::class);

        ApiKey::of(self::IDENTIFIER)->getSigningAlgorithm();
    }

    #[TestDox('Rejects reading the private key without one.')]
    public function testRejectsPrivateKeyWithoutPrivateKey(): void
    {
        $this->expectException(ConfigurationException::class);

        ApiKey::of(self::IDENTIFIER)->getPrivateKey();
    }

    #[TestDox('Rejects signing without a private key.')]
    public function testRejectsSigningWithoutPrivateKey(): void
    {
        $this->expectException(ConfigurationException::class);

        ApiKey::of(self::IDENTIFIER)->sign('header.payload');
    }

    #[TestDox('Rejects signing with an invalid private key.')]
    public function testRejectsSigningWithInvalidPrivateKey(): void
    {
        $this->expectException(ConfigurationException::class);

        ApiKey::of(self::IDENTIFIER, 'ES256;not-a-real-key')->sign('header.payload');
    }

    #[TestDox('Loads the private key once across multiple signatures.')]
    public function testCachesLoadedPrivateKey(): void
    {
        [$apiKey, $publicKey] = EcKeyFactory::create();

        $first = $apiKey->sign('header.payload');
        $second = $apiKey->sign('header.payload');

        foreach ([$first, $second] as $signature) {
            self::assertSame(
                1,
                \openssl_verify(
                    'header.payload',
                    EcKeyFactory::rawToDer($signature),
                    $publicKey,
                    \OPENSSL_ALGO_SHA256,
                ),
            );
        }
    }
}
