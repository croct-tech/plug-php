<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Identity store that keeps the client ID and user token in memory.
 *
 * Useful for tests, one-off requests, or building a custom persistence mechanism by reading the
 * values back after use.
 */
final class InMemoryIdentityStore implements IdentityStore
{
    private ?Uuid $clientId;

    private ?Token $userToken;

    public function __construct(?Uuid $clientId = null, ?Token $userToken = null)
    {
        $this->clientId = $clientId;
        $this->userToken = $userToken;
    }

    public function getClientId(): ?Uuid
    {
        return $this->clientId;
    }

    public function getUserToken(): ?Token
    {
        return $this->userToken;
    }

    public function saveClientId(Uuid $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function saveUserToken(Token $userToken): void
    {
        $this->userToken = $userToken;
    }
}
