<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Resolves the identifier of the currently authenticated user.
 */
interface IdentityResolver
{
    /**
     * Returns the identifier of the authenticated user, or null when the visitor is anonymous.
     */
    public function getUserId(): ?string;
}
