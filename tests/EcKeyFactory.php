<?php

declare(strict_types=1);

namespace Croct\Plug\Tests;

use Croct\Plug\ApiKey;

/**
 * Generates ephemeral P-256 keys for exercising the ES256 signing path in tests.
 */
final class EcKeyFactory
{
    public const IDENTIFIER = '00000000-0000-4000-8000-000000000000';

    private function __construct()
    {
    }

    /**
     * Generates a P-256 key pair and returns the matching API key and public key (PEM).
     *
     * @return array{0: ApiKey, 1: string}
     *
     * @throws \RuntimeException If the key pair cannot be generated.
     */
    public static function create(string $identifier = self::IDENTIFIER): array
    {
        $pair = \openssl_pkey_new([
            'private_key_type' => \OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        if ($pair === false) {
            throw new \RuntimeException('Failed to generate an EC key pair.');
        }

        $result = '';
        \openssl_pkey_export($pair, $result);

        \assert(\is_string($result), 'openssl_pkey_export returns the PEM-encoded key as a string.');

        $pkcs8 = \preg_replace('/-----[^-]+-----|\s+/', '', $result);

        $details = \openssl_pkey_get_details($pair);

        if (!\is_string($pkcs8) || $details === false || !\is_string($details['key'] ?? null)) {
            throw new \RuntimeException('Failed to export the EC key pair.');
        }

        return [ApiKey::of($identifier, 'ES256;' . $pkcs8), $details['key']];
    }

    /**
     * Re-encodes a raw R||S ECDSA signature as DER, so OpenSSL can verify it.
     */
    public static function rawToDer(string $signature): string
    {
        $component = static function (string $value): string {
            $value = \ltrim($value, "\x00");

            if ($value === '' || (\ord($value[0]) & 0x80) !== 0) {
                $value = "\x00" . $value;
            }

            $length = \strlen($value);

            \assert($length < 128, 'A P-256 signature component fits short-form DER.');

            return "\x02" . \chr($length) . $value;
        };

        $body = $component(\substr($signature, 0, 32)) . $component(\substr($signature, 32));
        $bodyLength = \strlen($body);

        \assert($bodyLength < 128, 'The DER SEQUENCE body fits short-form DER.');

        return "\x30" . \chr($bodyLength) . $body;
    }
}
