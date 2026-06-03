<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Exception\ConfigurationException;

/**
 * Croct API key, optionally carrying a private key for signing user tokens.
 *
 * The serialized form combines a public identifier with an optional signing algorithm and
 * base64-encoded private key.
 */
final class ApiKey implements \Stringable
{
    private const PRIVATE_KEY_PATTERN = '/^[a-z0-9]+;[^;]+$/i';

    private const SUPPORTED_ALGORITHMS = ['ES256'];

    private string $identifier;

    private ?string $algorithm;

    private ?\SensitiveParameterValue $encodedKey;

    private ?\OpenSSLAsymmetricKey $loadedKey = null;

    private function __construct(
        string $identifier,
        ?string $algorithm,
        #[\SensitiveParameter]
        ?string $encodedKey,
    ) {
        $this->identifier = $identifier;
        $this->algorithm = $algorithm;
        $this->encodedKey = $encodedKey === null ? null : new \SensitiveParameterValue($encodedKey);
    }

    /**
     * Creates an API key from a serialized string, or returns the given instance unchanged.
     *
     * @throws ConfigurationException If the serialized key is malformed.
     */
    public static function from(
        #[\SensitiveParameter]
        string|self $apiKey,
    ): self {
        if ($apiKey instanceof self) {
            return $apiKey;
        }

        return self::parse($apiKey);
    }

    /**
     * Parses an API key from its serialized string form.
     *
     * @throws ConfigurationException If the serialized key is malformed.
     */
    public static function parse(#[\SensitiveParameter] string $apiKey): self
    {
        $parts = \explode(':', $apiKey);

        if (\count($parts) > 2) {
            throw new ConfigurationException('Invalid API key format.');
        }

        return self::of($parts[0], $parts[1] ?? null);
    }

    /**
     * Creates an API key from an identifier and an optional private key.
     *
     * @throws ConfigurationException If the identifier or private key is invalid.
     */
    public static function of(
        string $identifier,
        #[\SensitiveParameter]
        ?string $privateKey = null,
    ): self {
        if (!Uuid::isValid($identifier)) {
            throw new ConfigurationException('The API key identifier must be a UUID.');
        }

        if ($privateKey === null || $privateKey === '') {
            return new self($identifier, null, null);
        }

        if (\preg_match(self::PRIVATE_KEY_PATTERN, $privateKey) !== 1) {
            throw new ConfigurationException('The API key private key is malformed.');
        }

        $segments = \explode(';', $privateKey, 2);
        $algorithm = $segments[0];

        if (!\in_array($algorithm, self::SUPPORTED_ALGORITHMS, true)) {
            throw new ConfigurationException(\sprintf('Unsupported signing algorithm "%s".', $algorithm));
        }

        return new self($identifier, $algorithm, $segments[1] ?? '');
    }

    /**
     * Gets the public identifier.
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Gets the key ID, the hexadecimal SHA-256 of the identifier's raw bytes.
     */
    public function getIdentifierHash(): string
    {
        // The identifier is always a validated UUID, so hex2bin always succeeds.
        return \hash('sha256', (string) \hex2bin(\str_replace('-', '', $this->identifier)));
    }

    /**
     * Checks whether the key carries a private key for signing.
     */
    public function hasPrivateKey(): bool
    {
        return $this->encodedKey !== null;
    }

    /**
     * Gets the signing algorithm.
     *
     * @throws ConfigurationException If the key has no private key.
     */
    public function getSigningAlgorithm(): string
    {
        if ($this->algorithm === null) {
            throw new ConfigurationException('The API key does not have a private key.');
        }

        return $this->algorithm;
    }

    /**
     * Gets the serialized private key, including its algorithm.
     *
     * @throws ConfigurationException If the key has no private key.
     */
    public function getPrivateKey(): string
    {
        if ($this->algorithm === null) {
            throw new ConfigurationException('The API key does not have a private key.');
        }

        return $this->algorithm . ';' . $this->getEncodedKey();
    }

    /**
     * Signs the given data with ES256, returning the raw 64-byte signature.
     *
     * @throws ConfigurationException If the key has no usable private key.
     */
    public function sign(string $data): string
    {
        $result = '';

        // The private key is validated by loadPrivateKey, so signing always succeeds.
        \openssl_sign($data, $result, $this->loadPrivateKey(), \OPENSSL_ALGO_SHA256);

        /** @var string $signature */
        $signature = $result;

        return self::convertDerToRaw($signature);
    }

    /**
     * Exports the full serialized key, including the private key when present.
     *
     * @throws ConfigurationException If the private key cannot be read.
     */
    public function export(): string
    {
        return $this->identifier . ($this->hasPrivateKey() ? ':' . $this->getPrivateKey() : '');
    }

    /**
     * Gets a redacted representation that never reveals the private key.
     */
    public function __toString(): string
    {
        return '[redacted]';
    }

    /**
     * Loads and caches the OpenSSL handle for the private key.
     *
     * @throws ConfigurationException If the private key is missing or invalid.
     */
    private function loadPrivateKey(): \OpenSSLAsymmetricKey
    {
        if ($this->loadedKey !== null) {
            return $this->loadedKey;
        }

        $key = \openssl_pkey_get_private(
            "-----BEGIN PRIVATE KEY-----\n"
            . \chunk_split($this->getEncodedKey(), 64, "\n")
            . "-----END PRIVATE KEY-----\n",
        );

        if ($key === false) {
            throw new ConfigurationException('The API key contains an invalid private key.');
        }

        return $this->loadedKey = $key;
    }

    /**
     * Gets the base64-encoded private key material.
     *
     * @throws ConfigurationException If the key has no private key.
     */
    private function getEncodedKey(): string
    {
        $value = $this->encodedKey?->getValue();

        if (!\is_string($value)) {
            throw new ConfigurationException('The API key does not have a private key.');
        }

        return $value;
    }

    /**
     * Converts a DER-encoded ECDSA signature to the raw R||S concatenation required by JWS.
     *
     * Each component is left-padded to 32 bytes.
     */
    private static function convertDerToRaw(string $der): string
    {
        $offset = 4; // 0x30 SEQUENCE, sequence length, 0x02 INTEGER tag, R length.
        $rLength = \ord($der[3]);
        $r = \substr($der, $offset, $rLength);
        $offset += $rLength + 2; // Skip R, then the 0x02 INTEGER tag and S length.
        $s = \substr($der, $offset, \ord($der[$offset - 1]));

        return \str_pad(\ltrim($r, "\x00"), 32, "\x00", \STR_PAD_LEFT)
            . \str_pad(\ltrim($s, "\x00"), 32, "\x00", \STR_PAD_LEFT);
    }
}
