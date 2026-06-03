<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Persists the visitor's identity across requests.
 */
interface IdentityStore
{
    /**
     * Gets the client ID.
     *
     * @return Uuid|null The stored client ID, or null if none is set.
     */
    public function getClientId(): ?Uuid;

    /**
     * Gets the user token.
     *
     * @return Token|null The stored user token, or null if none is set.
     */
    public function getUserToken(): ?Token;

    /**
     * Saves the resolved client ID.
     */
    public function saveClientId(Uuid $clientId): void;

    /**
     * Saves the resolved user token.
     */
    public function saveUserToken(Token $userToken): void;
}
