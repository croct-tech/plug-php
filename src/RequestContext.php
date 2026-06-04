<?php

declare(strict_types=1);

namespace Croct\Plug;

use Psr\Http\Message\ServerRequestInterface as ServerRequest;

/**
 * Snapshot of the request signals sent to the API.
 *
 * It carries request-derived context such as the URL, referrer, IP, user agent, and locale. The
 * visitor identity is resolved separately, so this snapshot stays independent of any session.
 */
final class RequestContext
{
    private const PREVIEW_QUERY_PARAMETER = 'croct-preview';

    private const PREVIEW_EXIT = 'exit';

    private ?string $previewToken;

    private ?string $url;

    private ?string $referrer;

    private ?string $clientAgent;

    private ?string $clientIp;

    private ?string $preferredLocale;

    public function __construct(
        ?string $previewToken = null,
        ?string $url = null,
        ?string $referrer = null,
        ?string $clientAgent = null,
        ?string $clientIp = null,
        ?string $preferredLocale = null,
    ) {
        $this->previewToken = $previewToken;
        $this->url = $url;
        $this->referrer = $referrer;
        $this->clientAgent = $clientAgent;
        $this->clientIp = $clientIp;
        $this->preferredLocale = $preferredLocale;
    }

    /**
     * Creates a context from the PHP request superglobals.
     */
    public static function fromGlobals(): self
    {
        /** @var array<array-key, mixed> $server */
        $server = $_SERVER;

        $https = self::getOptionalString($server['HTTPS'] ?? null);
        $port = self::getOptionalString($server['SERVER_PORT'] ?? null);
        $secure = ($https !== null && \strtolower($https) !== 'off') || $port === '443';

        $host = self::getOptionalString($server['HTTP_HOST'] ?? null);
        $uri = self::getOptionalString($server['REQUEST_URI'] ?? null);
        $url = $host !== null ? ($secure ? 'https' : 'http') . '://' . $host . ($uri ?? '') : null;

        $forwardedFor = self::getOptionalString($server['HTTP_X_FORWARDED_FOR'] ?? null)
            ?? self::getOptionalString($server['REMOTE_ADDR'] ?? null);

        /** @var array<array-key, mixed> $query */
        $query = $_GET;

        return new self(
            previewToken: self::resolvePreviewToken(
                self::getOptionalString($query[self::PREVIEW_QUERY_PARAMETER] ?? null),
            ),
            url: $url,
            referrer: self::getOptionalString($server['HTTP_REFERER'] ?? null),
            clientAgent: self::getOptionalString($server['HTTP_USER_AGENT'] ?? null),
            clientIp: $forwardedFor !== null ? \trim(\explode(',', $forwardedFor)[0]) : null,
        );
    }

    /**
     * Creates a context from a PSR-7 server request.
     */
    public static function fromServerRequest(ServerRequest $request): self
    {
        /** @var array<array-key, mixed> $server */
        $server = $request->getServerParams();

        $forwardedFor = self::getOptionalHeader($request, 'X-Forwarded-For')
            ?? self::getOptionalString($server['REMOTE_ADDR'] ?? null);

        $url = (string) $request->getUri();

        $query = $request->getQueryParams();

        return new self(
            previewToken: self::resolvePreviewToken(
                self::getOptionalString($query[self::PREVIEW_QUERY_PARAMETER] ?? null),
            ),
            url: $url !== '' ? $url : null,
            referrer: self::getOptionalHeader($request, 'Referer'),
            clientAgent: self::getOptionalHeader($request, 'User-Agent'),
            clientIp: $forwardedFor !== null ? \trim(\explode(',', $forwardedFor)[0]) : null,
        );
    }

    /**
     * Gets the preview token.
     *
     * @return string|null The preview token, or null if not previewing.
     */
    public function getPreviewToken(): ?string
    {
        return $this->previewToken;
    }

    /**
     * Gets the request URL.
     *
     * @return string|null The URL, or null if unknown.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Gets the referrer URL.
     *
     * @return string|null The referrer, or null if absent.
     */
    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    /**
     * Gets the client user agent.
     *
     * @return string|null The user agent, or null if absent.
     */
    public function getClientAgent(): ?string
    {
        return $this->clientAgent;
    }

    /**
     * Gets the client IP address.
     *
     * @return string|null The IP address, or null if unknown.
     */
    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    /**
     * Gets the preferred locale.
     *
     * @return string|null The locale, or null if unspecified.
     */
    public function getPreferredLocale(): ?string
    {
        return $this->preferredLocale;
    }

    /**
     * Builds the evaluation context, with the page and custom attributes, sent to the API.
     *
     * @param array<string, mixed> $attributes The custom attributes to include.
     *
     * @return array<string, mixed> The assembled evaluation context.
     */
    public function toEvaluationContext(array $attributes = []): array
    {
        $context = [];

        if ($this->url !== null) {
            $page = ['url' => $this->url];

            if ($this->referrer !== null) {
                $page['referrer'] = $this->referrer;
            }

            $context['page'] = $page;
        }

        if ($attributes !== []) {
            $context['attributes'] = $attributes;
        }

        return $context;
    }

    /**
     * Resolves the preview token from the request, treating the preview-exit sentinel as no preview.
     */
    private static function resolvePreviewToken(?string $token): ?string
    {
        return $token === null || $token === self::PREVIEW_EXIT ? null : $token;
    }

    /**
     * Gets a request header value, or null when it is empty.
     */
    private static function getOptionalHeader(ServerRequest $request, string $name): ?string
    {
        $value = $request->getHeaderLine($name);

        return $value !== '' ? $value : null;
    }

    /**
     * Coerces a value to a non-empty string, or null otherwise.
     */
    private static function getOptionalString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
