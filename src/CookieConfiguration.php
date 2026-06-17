<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Names, lifetimes, and attributes for the Croct session cookies.
 */
final class CookieConfiguration
{
    public const DEFAULT_CLIENT_ID_NAME = 'ct_client_id';

    public const DEFAULT_USER_TOKEN_NAME = 'ct_user_token';

    public const DEFAULT_CLIENT_ID_DURATION = 31536000;

    public const DEFAULT_USER_TOKEN_DURATION = 604800;

    private string $clientIdName;

    private string $userTokenName;

    private int $clientIdDuration;

    private int $userTokenDuration;

    private ?string $domain;

    private bool $secure;

    private string $sameSite;

    public function __construct(
        string $clientIdName = self::DEFAULT_CLIENT_ID_NAME,
        string $userTokenName = self::DEFAULT_USER_TOKEN_NAME,
        int $clientIdDuration = self::DEFAULT_CLIENT_ID_DURATION,
        int $userTokenDuration = self::DEFAULT_USER_TOKEN_DURATION,
        ?string $domain = null,
        bool $secure = true,
        string $sameSite = 'None',
    ) {
        $this->clientIdName = $clientIdName;
        $this->userTokenName = $userTokenName;
        $this->clientIdDuration = $clientIdDuration;
        $this->userTokenDuration = $userTokenDuration;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->sameSite = $sameSite;
    }

    /**
     * Gets the name of the client ID cookie.
     */
    public function getClientIdName(): string
    {
        return $this->clientIdName;
    }

    /**
     * Gets the name of the user token cookie.
     */
    public function getUserTokenName(): string
    {
        return $this->userTokenName;
    }

    /**
     * Gets the lifetime of the client ID cookie, in seconds.
     */
    public function getClientIdDuration(): int
    {
        return $this->clientIdDuration;
    }

    /**
     * Gets the lifetime of the user token cookie, in seconds.
     */
    public function getUserTokenDuration(): int
    {
        return $this->userTokenDuration;
    }

    /**
     * Gets the cookie domain.
     *
     * @return string|null The domain, or null to scope cookies to the current host.
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Checks whether the cookies are sent only over HTTPS.
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Gets the SameSite policy applied to the cookies.
     */
    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    /**
     * Builds the cookie settings for the browser-side SDK, so it reads and writes the same cookies.
     *
     * @return array{
     *     clientId: array<string, bool|int|string>,
     *     userToken: array<string, bool|int|string>,
     * }
     */
    public function toBrowserCookies(): array
    {
        $clientId = [
            'name' => $this->clientIdName,
            'maxAge' => $this->clientIdDuration,
            'path' => '/',
            'secure' => $this->secure,
            'sameSite' => \strtolower($this->sameSite),
        ];

        $userToken = [
            'name' => $this->userTokenName,
            'maxAge' => $this->userTokenDuration,
            'path' => '/',
            'secure' => $this->secure,
            'sameSite' => \strtolower($this->sameSite),
        ];

        if ($this->domain !== null) {
            $clientId['domain'] = $this->domain;
            $userToken['domain'] = $this->domain;
        }

        return [
            'clientId' => $clientId,
            'userToken' => $userToken,
        ];
    }
}
