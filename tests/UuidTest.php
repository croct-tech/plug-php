<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(Uuid::class)]
#[TestDox('A UUID')]
final class UuidTest extends TestCase
{
    #[TestDox('Generates a random version 4 UUID.')]
    public function testGeneratesRandomUuid(): void
    {
        $uuid = Uuid::random();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid->toString(),
        );
        self::assertNotSame(Uuid::random()->toString(), $uuid->toString());
    }

    /**
     * @return array<string, array{value: string, valid: bool}>
     */
    public static function getTestsForValidation(): array
    {
        return [
            'canonical' => [
                'value' => '11111111-2222-4333-8444-555555555555',
                'valid' => true,
            ],
            'uppercase' => [
                'value' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
                'valid' => true,
            ],
            'compact (no hyphens)' => [
                'value' => '11111111222243338444555555555555',
                'valid' => false,
            ],
            'too short' => [
                'value' => '11111111-2222-4333-8444',
                'valid' => false,
            ],
            'non-hexadecimal' => [
                'value' => 'gggggggg-2222-4333-8444-555555555555',
                'valid' => false,
            ],
            'empty' => [
                'value' => '',
                'valid' => false,
            ],
        ];
    }

    #[DataProvider('getTestsForValidation')]
    #[TestDox('Validates the canonical UUID format, case-insensitively.')]
    public function testValidatesFormat(string $value, bool $valid): void
    {
        self::assertSame($valid, Uuid::isValid($value));
    }

    #[TestDox('Parses a UUID, normalizing it to lowercase.')]
    public function testParsesAndNormalizes(): void
    {
        $uuid = Uuid::parse('AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE');

        self::assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $uuid->toString());
        self::assertSame($uuid->toString(), (string) $uuid);
    }

    #[TestDox('Compares UUIDs by their canonical value.')]
    public function testEquals(): void
    {
        $uuid = Uuid::parse('AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE');

        self::assertTrue($uuid->equals(Uuid::parse('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee')));
        self::assertFalse($uuid->equals(Uuid::parse('11111111-2222-4333-8444-555555555555')));
    }

    #[TestDox('Rejects parsing an invalid value.')]
    public function testRejectsInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Uuid::parse('not-a-uuid');
    }
}
