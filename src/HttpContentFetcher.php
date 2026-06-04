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

    public function fetch(string $slotId, ?FetchOptions $options = null): FetchResponse
    {
        $options ??= FetchOptions::empty();
        $context = $this->context;
        $static = $options->isStatic();

        $payload = ['slotId' => $slotId];

        $version = $options->getVersion();

        if ($version !== null) {
            $payload['version'] = (string) $version;
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
                return new FetchResponse($options->getFallback());
            }

            $content = $this->contentProvider->getContent($slotId);

            if ($content !== null) {
                return new FetchResponse($content);
            }

            throw new ContentException($exception->getMessage(), 0, $exception);
        }
    }
}
