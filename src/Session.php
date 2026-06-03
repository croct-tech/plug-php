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

    public function __construct(
        string $appId,
        ApiKey $apiKey,
        IdentityStore $store,
        int $tokenDuration = 86400,
        ?bool $signTokens = null,
        ?int $now = null,
    ) {
        $this->appId = $appId;
        $this->apiKey = $apiKey;
        $this->store = $store;
        $this->tokenDuration = $tokenDuration;
        $this->now = $now;
        $this->signTokens = $signTokens ?? $apiKey->hasPrivateKey();
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
        $token = $this->reissue($stored);

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
     * Reissues the user token when the stored one is absent or no longer usable.
     */
    private function reissue(?Token $token): Token
    {
        if ($token === null) {
            return $this->issueToken();
        }

        // The token belongs to another application: start fresh and anonymous, never carrying its
        // subject over, regardless of the token's expiration or signature state.
        $tokenAppId = $token->getApplicationId();

        if ($tokenAppId !== null && $tokenAppId !== $this->appId) {
            return $this->issueToken();
        }

        $subject = $token->getSubject();

        // Upgrade an unsigned token to a signed one when signing is enabled.
        if ($this->signTokens && !$token->isSigned()) {
            return $this->issueToken($subject);
        }

        // Refresh an expired (or not-yet-valid) token, preserving the subject.
        if (!$token->isValidNow($this->now)) {
            return $this->issueToken($subject);
        }

        // Signed with a different key: re-sign, preserving the subject and token ID.
        if ($token->isSigned() && !$token->matchesKeyId($this->apiKey)) {
            return $this->issueToken($subject, $token->getTokenId());
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
