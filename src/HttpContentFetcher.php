<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Content\ContentProvider;
use Croct\Plug\Content\NullContentProvider;
use Croct\Plug\Exception\ApiException;
use Croct\Plug\Exception\ContentException;

/**
 * Content fetcher backed by the Croct API.
 */
final class HttpContentFetcher implements ContentFetcher
{
    private const ENDPOINT = 'external/web/content';

    private const STATIC_ENDPOINT = 'external/web/static-content';

    private ApiClient $client;

    private RequestContext $context;

    private ?IdentityStore $identity;

    private ContentProvider $contentProvider;

    public function __construct(
        ApiClient $client,
        RequestContext $context,
        ?IdentityStore $identity = null,
        ?ContentProvider $contentProvider = null,
    ) {
        $this->client = $client;
        $this->context = $context;
        $this->identity = $identity;
        $this->contentProvider = $contentProvider ?? new NullContentProvider();
    }

    /**
     * @template F = never
     *
     * @param FetchOptions<F>|null $options
     *
     * @return FetchResponse<array<string, mixed>, F>
     */
    public function fetch(string $slotId, ?FetchOptions $options = null): FetchResponse
    {
        $options ??= FetchOptions::defaults();
        $context = $this->context;
        $static = $options->isStatic();

        [$id, $version] = self::parseSlotId($slotId);

        $payload = ['slotId' => $id];

        if ($version !== null) {
            $payload['version'] = $version;
        }

        $locale = $options->getPreferredLocale() ?? $context->getPreferredLocale();

        if ($locale !== null) {
            $payload['preferredLocale'] = $locale;
        }

        if ($options->includesSchema()) {
            $payload['includeSchema'] = true;
        }

        // Static content is impersonal: it carries no visitor signals, preview, or page context.
        $headers = [];

        if (!$static) {
            $previewToken = $context->getPreviewToken();

            if ($previewToken !== null) {
                $payload['previewToken'] = $previewToken;
            }

            $evaluationContext = $context->toEvaluationContext($options->getAttributes());

            if ($evaluationContext !== []) {
                $payload['context'] = $evaluationContext;
            }

            $headers = [
                HttpHeader::CLIENT_ID->value => $this->identity?->getClientId()?->toString(),
                HttpHeader::TOKEN->value => $this->identity?->getUserToken()?->toString(),
                HttpHeader::CLIENT_IP->value => $context->getClientIp(),
                HttpHeader::CLIENT_AGENT->value => $context->getClientAgent(),
            ];
        }

        $endpoint = $static ? self::STATIC_ENDPOINT : self::ENDPOINT;

        try {
            return FetchResponse::fromResponse($this->client->send($endpoint, $payload, $headers));
        } catch (ApiException $exception) {
            if ($options->hasFallback()) {
                /** @var FetchResponse<array<string, mixed>, F> $response */
                $response = new FetchResponse($options->getFallback());

                return $response;
            }

            $content = $this->contentProvider->getContent($id, $locale);

            if ($content !== null) {
                /** @var FetchResponse<array<string, mixed>, F> $response */
                $response = new FetchResponse($content);

                return $response;
            }

            throw new ContentException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Splits a slot ID into its identifier and optional version.
     *
     * The version is encoded as a suffix on the slot ID, as in `home-banner@2`. It must be a
     * positive integer or the literal `latest`, which is the default and thus carries no version.
     *
     * @return array{string, string|null} The slot identifier and the version, or null for the latest.
     *
     * @throws \InvalidArgumentException If the slot ID is malformed.
     */
    private static function parseSlotId(string $slotId): array
    {
        $pattern = '/^(?<id>[a-z0-9]+(?:-[a-z0-9]+)*)(?:@(?<version>[1-9][0-9]*|latest))?$/';

        if (\preg_match($pattern, $slotId, $matches) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Malformed slot ID "%s".', $slotId));
        }

        $version = $matches['version'] ?? '';

        return [$matches['id'], $version === '' || $version === 'latest' ? null : $version];
    }
}
