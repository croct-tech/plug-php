<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Exception\MalformedTokenException;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;

/**
 * Identity store backed by HTTP cookies.
 *
 * Holds the incoming client ID and user token and exposes the resolved values as cookies to set on
 * the response. The factory methods read the incoming values from the available cookie source.
 */
final class CookieStorage implements IdentityStore
{
    private static ?self $instance = null;

    private ?Uuid $clientId;

    private ?Token $userToken;

    private CookieConfiguration $configuration;

    private ?int $now;

    public function __construct(
        ?Uuid $clientId = null,
        ?Token $userToken = null,
        ?CookieConfiguration $configuration = null,
        ?int $now = null,
    ) {
        $this->clientId = $clientId;
        $this->userToken = $userToken;
        $this->configuration = $configuration ?? new CookieConfiguration();
        $this->now = $now;
    }

    /**
     * Creates an instance from the cookies of the current request.
     */
    public static function fromGlobals(?CookieConfiguration $configuration = null, ?int $now = null): self
    {
        /** @var array<array-key, mixed> $cookies */
        $cookies = $_COOKIE;

        return self::fromArray($cookies, $configuration, $now);
    }

    /**
     * Returns the process-wide instance built from the current request's cookies.
     *
     * The instance is created once from the request cookies and reused, so the session can update
     * it and the cookies emitted afterwards reflect those changes without passing it around.
     *
     * In long-running runtimes (e.g. RoadRunner, Swoole), call reset() between requests to avoid
     * leaking session state across them.
     */
    public static function global(): self
    {
        return self::$instance ??= self::fromGlobals();
    }

    /**
     * Clears the process-wide instance returned by global().
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Creates an instance from the cookies of a server request.
     */
    public static function fromServerRequest(
        ServerRequest $request,
        ?CookieConfiguration $configuration = null,
        ?int $now = null,
    ): self {
        return self::fromArray($request->getCookieParams(), $configuration, $now);
    }

    /**
     * Creates an instance from a raw cookie map.
     *
     * @param array<array-key, mixed> $cookies The cookie name-value pairs.
     */
    public static function fromArray(
        array $cookies,
        ?CookieConfiguration $configuration = null,
        ?int $now = null,
    ): self {
        $configuration ??= new CookieConfiguration();

        return new self(
            self::readClientId($cookies, $configuration->getClientIdName()),
            self::readUserToken($cookies, $configuration->getUserTokenName()),
            $configuration,
            $now,
        );
    }

    public function getClientId(): ?Uuid
    {
        return $this->clientId;
    }

    public function getUserToken(): ?Token
    {
        return $this->userToken;
    }

    /**
     * Gets the cookie configuration.
     */
    public function getConfiguration(): CookieConfiguration
    {
        return $this->configuration;
    }

    public function saveClientId(Uuid $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function saveUserToken(Token $userToken): void
    {
        $this->userToken = $userToken;
    }

    /**
     * Returns the cookies to set on the response, reflecting the saved values.
     *
     * @return array{Cookie, Cookie}
     */
    public function getResponseCookies(): array
    {
        $now = $this->now ?? \time();

        return [
            new Cookie(
                name: $this->configuration->getClientIdName(),
                value: $this->clientId?->toString() ?? '',
                expiration: $now + $this->configuration->getClientIdDuration(),
                path: '/',
                domain: $this->configuration->getDomain(),
                secure: $this->configuration->isSecure(),
                httpOnly: false,
                sameSite: $this->configuration->getSameSite(),
            ),
            new Cookie(
                name: $this->configuration->getUserTokenName(),
                value: $this->userToken?->toString() ?? '',
                expiration: $now + $this->configuration->getUserTokenDuration(),
                path: '/',
                domain: $this->configuration->getDomain(),
                secure: $this->configuration->isSecure(),
                httpOnly: false,
                sameSite: $this->configuration->getSameSite(),
            ),
        ];
    }

    /**
     * Sends the response cookies to the browser.
     *
     * Intended for plain PHP scripts, and must be called before any output is sent.
     *
     * @param (callable(string, string, array<string, mixed>): bool)|null $emitter
     *     The function used to send each cookie. Defaults to PHP's setcookie().
     */
    public function emit(?callable $emitter = null): void
    {
        $emitter ??= \setcookie(...);

        foreach ($this->getResponseCookies() as $cookie) {
            $options = [
                'expires' => $cookie->getExpiration() ?? 0,
                'path' => $cookie->getPath(),
                'domain' => $cookie->getDomain() ?? '',
                'secure' => $cookie->isSecure(),
                'httponly' => $cookie->isHttpOnly(),
            ];

            if (\in_array($cookie->getSameSite(), ['None', 'Lax', 'Strict'], true)) {
                $options['samesite'] = $cookie->getSameSite();
            }

            $emitter($cookie->getName(), $cookie->getValue(), $options);
        }
    }

    /**
     * @param array<array-key, mixed> $cookies
     */
    private static function readClientId(array $cookies, string $name): ?Uuid
    {
        $value = self::readCookie($cookies, $name);

        return $value !== null && Uuid::isValid($value) ? Uuid::parse($value) : null;
    }

    /**
     * @param array<array-key, mixed> $cookies
     */
    private static function readUserToken(array $cookies, string $name): ?Token
    {
        $value = self::readCookie($cookies, $name);

        if ($value === null) {
            return null;
        }

        try {
            return Token::parse($value);
        } catch (MalformedTokenException) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $cookies
     */
    private static function readCookie(array $cookies, string $name): ?string
    {
        $value = $cookies[$name] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
