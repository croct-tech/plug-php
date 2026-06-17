<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Exception\ConfigurationException;
use Croct\Plug\Exception\MalformedTokenException;

/**
 * Immutable JSON Web Token identifying a visitor.
 *
 * A token is issued unsigned and may later be signed with an API key. Identity is asserted, not
 * authenticated, unless the token is signed.
 */
final class Token implements \Stringable
{
    /** @var array<string, mixed> */
    private array $headers;

    /** @var array<string, mixed> */
    private array $payload;

    private string $signature;

    /**
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $payload
     */
    private function __construct(array $headers, array $payload, string $signature)
    {
        $this->headers = $headers;
        $this->payload = $payload;
        $this->signature = $signature;
    }

    /**
     * Issues a new unsigned token for the given application and optional subject.
     *
     * @throws \InvalidArgumentException If the timestamp is negative or the subject is empty.
     */
    public static function issue(string $appId, ?string $subject = null, ?int $now = null): self
    {
        $now ??= \time();

        if ($now < 0) {
            throw new \InvalidArgumentException('The timestamp must be non-negative.');
        }

        if ($subject === '') {
            throw new \InvalidArgumentException('The subject must be non-empty.');
        }

        $payload = [
            'iss' => 'croct.io',
            'aud' => 'croct.io',
            'iat' => $now,
        ];

        if ($subject !== null) {
            $payload['sub'] = $subject;
        }

        return new self(
            [
                'typ' => 'JWT',
                'alg' => 'none',
                'appId' => $appId,
            ],
            $payload,
            '',
        );
    }

    /**
     * Parses a token from its serialized form.
     *
     * @throws MalformedTokenException If the token is malformed or corrupted.
     */
    public static function parse(string $token): self
    {
        if ($token === '') {
            throw new MalformedTokenException('The token cannot be empty.');
        }

        $parts = \explode('.', $token);
        $count = \count($parts);

        if ($count < 2 || $count > 3) {
            throw new MalformedTokenException('The token is malformed.');
        }

        return self::of(self::decodeSegment($parts[0]), self::decodeSegment($parts[1]), $parts[2] ?? '');
    }

    /**
     * Creates a token from its decoded parts, validating the required claims.
     *
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $payload
     *
     * @throws MalformedTokenException If a required header or claim is missing or invalid.
     */
    public static function of(array $headers, array $payload, string $signature = ''): self
    {
        foreach (['typ', 'alg'] as $header) {
            if (!isset($headers[$header]) || !\is_string($headers[$header])) {
                throw new MalformedTokenException(\sprintf('The token header "%s" is missing or invalid.', $header));
            }
        }

        if (!isset($payload['iss']) || !\is_string($payload['iss'])) {
            throw new MalformedTokenException('The token claim "iss" is missing or invalid.');
        }

        if (!isset($payload['aud']) || !(\is_string($payload['aud']) || \is_array($payload['aud']))) {
            throw new MalformedTokenException('The token claim "aud" is missing or invalid.');
        }

        if (!isset($payload['iat']) || !\is_int($payload['iat'])) {
            throw new MalformedTokenException('The token claim "iat" is missing or invalid.');
        }

        return new self($headers, $payload, $signature);
    }

    /**
     * Returns a signed copy of this token using the given API key.
     *
     * @throws ConfigurationException If the API key cannot sign the token.
     */
    public function signedWith(ApiKey $apiKey): self
    {
        $headers = $this->headers;
        $headers['kid'] = $apiKey->getIdentifierHash();
        $headers['alg'] = $apiKey->getSigningAlgorithm();

        $input = self::base64UrlEncode(self::encodeJson($headers))
            . '.'
            . self::base64UrlEncode(self::encodeJson($this->payload));

        return new self($headers, $this->payload, self::base64UrlEncode($apiKey->sign($input)));
    }

    /**
     * Checks whether the token is cryptographically signed.
     */
    public function isSigned(): bool
    {
        return $this->getAlgorithm() !== 'none' && $this->signature !== '';
    }

    /**
     * Checks whether the token has no subject.
     */
    public function isAnonymous(): bool
    {
        return $this->getSubject() === null;
    }

    /**
     * Checks whether the token's subject matches the given user.
     */
    public function isSubject(string $subject): bool
    {
        return $this->getSubject() === $subject;
    }

    /**
     * Checks whether the token is valid at the given time, defaulting to the current time.
     */
    public function isValidNow(?int $now = null): bool
    {
        $now ??= \time();
        $expiration = $this->getExpirationTime();

        return ($expiration === null || $expiration >= $now) && $this->getIssueTime() <= $now;
    }

    /**
     * Checks whether this token was issued more recently than the given one.
     */
    public function isNewerThan(self $token): bool
    {
        return $this->getIssueTime() > $token->getIssueTime();
    }

    /**
     * Checks whether this token is equal to the given one.
     */
    public function equals(self $token): bool
    {
        return $this->headers === $token->headers
            && $this->payload === $token->payload
            && $this->signature === $token->signature;
    }

    /**
     * Checks whether the token was signed with the given API key.
     */
    public function matchesKeyId(ApiKey $apiKey): bool
    {
        return $this->getKeyId() === $apiKey->getIdentifierHash();
    }

    /**
     * Returns a copy with the given token ID.
     *
     * @throws \InvalidArgumentException If the token ID is not a valid UUID.
     */
    public function withTokenId(string $tokenId): self
    {
        $payload = $this->payload;
        $payload['jti'] = Uuid::parse($tokenId)->toString();

        return new self($this->headers, $payload, $this->signature);
    }

    /**
     * Returns a copy valid for the given duration, starting at the given time.
     */
    public function withDuration(int $duration, ?int $now = null): self
    {
        $now ??= \time();

        $payload = $this->payload;
        $payload['iat'] = $now;
        $payload['exp'] = $now + $duration;

        return new self($this->headers, $payload, $this->signature);
    }

    /**
     * Gets the application ID.
     *
     * @return string|null The application ID, or null if absent.
     */
    public function getApplicationId(): ?string
    {
        $appId = $this->headers['appId'] ?? null;

        return \is_string($appId) ? $appId : null;
    }

    /**
     * Gets the signing algorithm.
     *
     * @return string The algorithm name, or "none" when the token is unsigned.
     */
    public function getAlgorithm(): string
    {
        $algorithm = $this->headers['alg'] ?? null;

        return \is_string($algorithm) ? $algorithm : 'none';
    }

    /**
     * Gets the signing key ID.
     *
     * @return string|null The key ID, or null when the token is unsigned.
     */
    public function getKeyId(): ?string
    {
        $keyId = $this->headers['kid'] ?? null;

        return \is_string($keyId) ? $keyId : null;
    }

    /**
     * Gets the subject.
     *
     * @return string|null The user the token identifies, or null when anonymous.
     */
    public function getSubject(): ?string
    {
        $subject = $this->payload['sub'] ?? null;

        return \is_string($subject) ? $subject : null;
    }

    /**
     * Gets the token ID.
     *
     * @return string|null The unique token ID, or null if not set.
     */
    public function getTokenId(): ?string
    {
        $tokenId = $this->payload['jti'] ?? null;

        return \is_string($tokenId) ? $tokenId : null;
    }

    /**
     * Gets the issue time as a Unix timestamp.
     */
    public function getIssueTime(): int
    {
        $issueTime = $this->payload['iat'] ?? 0;

        return \is_int($issueTime) ? $issueTime : 0;
    }

    /**
     * Gets the expiration time as a Unix timestamp.
     *
     * @return int|null The expiration timestamp, or null if the token never expires.
     */
    public function getExpirationTime(): ?int
    {
        $expiration = $this->payload['exp'] ?? null;

        return \is_int($expiration) ? $expiration : null;
    }

    /**
     * Gets the serialized token string.
     */
    public function toString(): string
    {
        return self::base64UrlEncode(self::encodeJson($this->headers))
            . '.'
            . self::base64UrlEncode(self::encodeJson($this->payload))
            . '.'
            . $this->signature;
    }

    /**
     * Gets the serialized token string.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Decodes a base64url-encoded token segment into an associative array.
     *
     * @return array<string, mixed> The decoded segment.
     *
     * @throws MalformedTokenException If the segment cannot be decoded.
     */
    private static function decodeSegment(string $segment): array
    {
        $decoded = \base64_decode(\strtr($segment, '-_', '+/'), true);

        if ($decoded === false) {
            throw new MalformedTokenException('The token is corrupted.');
        }

        try {
            $data = \json_decode($decoded, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new MalformedTokenException('The token is corrupted.', 0, $exception);
        }

        if (!\is_array($data)) {
            throw new MalformedTokenException('The token is corrupted.');
        }

        $result = [];

        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * Encodes the given data as a compact JSON string.
     *
     * @param array<string, mixed> $data The data to encode.
     */
    private static function encodeJson(array $data): string
    {
        $json = \json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \LogicException('Failed to encode the token: ' . \json_last_error_msg());
        }

        return $json;
    }

    /**
     * Encodes the given binary data using base64url, without padding.
     */
    private static function base64UrlEncode(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }
}
