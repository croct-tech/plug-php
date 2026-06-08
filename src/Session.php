<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Manages the visitor's identity and tokens.
 */
final class Session implements IdentityStore
{
    private string $appId;

    private ApiKey $apiKey;

    private IdentityStore $store;

    private int $tokenDuration;

    private ?int $now;

    private bool $signTokens;

    private ?IdentityResolver $identity;

    public function __construct(
        string $appId,
        ApiKey $apiKey,
        IdentityStore $store,
        int $tokenDuration = 86400,
        ?bool $signTokens = null,
        ?int $now = null,
        ?IdentityResolver $identity = null,
    ) {
        $this->appId = $appId;
        $this->apiKey = $apiKey;
        $this->store = $store;
        $this->tokenDuration = $tokenDuration;
        $this->now = $now;
        $this->signTokens = $signTokens ?? $apiKey->hasPrivateKey();
        $this->identity = $identity;
    }

    /**
     * Gets the client ID, generating and storing one when none is set.
     */
    public function getClientId(): Uuid
    {
        $clientId = $this->store->getClientId();

        if ($clientId !== null) {
            return $clientId;
        }

        $clientId = Uuid::random();

        $this->saveClientId($clientId);

        return $clientId;
    }

    /**
     * Gets the user token, issuing and storing one when it is absent or no longer usable.
     */
    public function getUserToken(): Token
    {
        $stored = $this->store->getUserToken();
        $token = $this->resolveToken($stored);

        if ($stored === null || !$token->equals($stored)) {
            $this->saveUserToken($token);
        }

        return $token;
    }

    public function saveClientId(Uuid $clientId): void
    {
        $this->store->saveClientId($clientId);
    }

    public function saveUserToken(Token $userToken): void
    {
        $this->store->saveUserToken($userToken);
    }

    /**
     * Marks the visitor as a known user.
     *
     * @throws \InvalidArgumentException If the user ID is empty.
     */
    public function identify(string $userId): void
    {
        if ($userId === '') {
            throw new \InvalidArgumentException('The user ID must be non-empty.');
        }

        $this->saveUserToken($this->issueToken($userId));
    }

    /**
     * Resets the visitor to anonymous.
     */
    public function anonymize(): void
    {
        $this->saveUserToken($this->issueToken());
    }

    /**
     * Resolves the token to use, reissuing the stored one when it is absent or no longer usable.
     */
    private function resolveToken(?Token $token): Token
    {
        $userId = $this->identity?->getUserId();
        $hasResolver = $this->identity !== null;

        // Re-issue for the authenticated user when the token is missing, no longer usable, or its
        // subject no longer matches.
        if ($token === null
            || ($this->signTokens && !$token->isSigned())
            || !$token->isValidNow($this->now)
            || ($hasResolver && ($userId === null ? !$token->isAnonymous() : !$token->isSubject($userId)))
        ) {
            return $this->issueToken($userId);
        }

        // The token belongs to another application: start fresh.
        $tokenAppId = $token->getApplicationId();

        if ($tokenAppId !== null && $tokenAppId !== $this->appId) {
            return $this->issueToken($userId);
        }

        // Signed with a different key: re-sign, preserving the subject and token ID.
        if ($token->isSigned() && !$token->matchesKeyId($this->apiKey)) {
            return $this->issueToken($token->getSubject(), $token->getTokenId());
        }

        return $token;
    }

    /**
     * Issues a fresh token for the given subject, signing it when signing is enabled.
     */
    private function issueToken(?string $subject = null, ?string $tokenId = null): Token
    {
        if ($subject === '') {
            $subject = null;
        }

        $token = Token::issue($this->appId, $subject, $this->now)
            ->withDuration($this->tokenDuration, $this->now);

        if ($this->signTokens) {
            return $token->withTokenId($tokenId ?? Uuid::random()->toString())
                ->signedWith($this->apiKey);
        }

        return $token;
    }
}
