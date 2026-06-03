<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Framework-neutral, lossless representation of a cookie to set on the response.
 *
 * It carries every field the common response targets need, so integrations can map it onto
 * their own response without losing information.
 *
 * Cookies are not HTTP-only by default, so client-side scripts can read them.
 */
final class Cookie
{
    private string $name;

    private string $value;

    private ?int $expiration;

    private string $path;

    private ?string $domain;

    private bool $secure;

    private bool $httpOnly;

    private ?string $sameSite;

    public function __construct(
        string $name,
        string $value,
        ?int $expiration = null,
        string $path = '/',
        ?string $domain = null,
        bool $secure = true,
        bool $httpOnly = false,
        ?string $sameSite = 'None',
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->expiration = $expiration;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = $sameSite;
    }

    /**
     * Gets the cookie name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the cookie value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Gets the expiration time as a Unix timestamp.
     *
     * @return int|null The expiration timestamp, or null for a session cookie.
     */
    public function getExpiration(): ?int
    {
        return $this->expiration;
    }

    /**
     * Gets the cookie path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Gets the cookie domain.
     *
     * @return string|null The domain, or null if not scoped to one.
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Checks whether the cookie is sent only over HTTPS.
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Checks whether the cookie is hidden from client-side scripts.
     */
    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    /**
     * Gets the SameSite policy.
     *
     * @return string|null The policy, or null if unset.
     */
    public function getSameSite(): ?string
    {
        return $this->sameSite;
    }

    /**
     * Renders the cookie as the value of a Set-Cookie response header.
     */
    public function toSetCookieHeader(?int $now = null): string
    {
        $parts = [\rawurlencode($this->name) . '=' . \rawurlencode($this->value)];

        if ($this->expiration !== null) {
            $parts[] = 'Expires=' . \gmdate('D, d M Y H:i:s', $this->expiration) . ' GMT';
            $parts[] = 'Max-Age=' . \max(0, $this->expiration - ($now ?? \time()));
        }

        $parts[] = 'Path=' . $this->path;

        if ($this->domain !== null) {
            $parts[] = 'Domain=' . $this->domain;
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if ($this->sameSite !== null) {
            $parts[] = 'SameSite=' . $this->sameSite;
        }

        return \implode('; ', $parts);
    }
}
