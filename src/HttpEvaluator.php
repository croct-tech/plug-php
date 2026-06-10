<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Exception\ApiException;
use Croct\Plug\Exception\EvaluationException;

/**
 * Evaluator backed by the Croct API.
 */
final class HttpEvaluator implements Evaluator
{
    private const ENDPOINT = 'external/web/evaluate';

    private const MAX_QUERY_LENGTH = 500;

    private ApiClient $client;

    private RequestContext $context;

    private ?IdentityStore $identity;

    public function __construct(ApiClient $client, RequestContext $context, ?IdentityStore $identity = null)
    {
        $this->client = $client;
        $this->context = $context;
        $this->identity = $identity;
    }

    /**
     * @param EvaluationOptions<mixed>|null $options
     */
    public function evaluate(string $query, ?EvaluationOptions $options = null): mixed
    {
        // Reject oversized queries before reaching the API, and never mask the misuse with a fallback.
        $length = \mb_strlen($query, 'UTF-8');

        if ($length > self::MAX_QUERY_LENGTH) {
            throw new EvaluationException(
                \sprintf(
                    'The query must be at most %d characters long, but it is %d characters long.',
                    self::MAX_QUERY_LENGTH,
                    $length,
                ),
            );
        }

        $context = $this->context;

        $payload = ['query' => $query];

        $evaluationContext = $context->toEvaluationContext($options?->getAttributes() ?? []);

        if ($evaluationContext !== []) {
            $payload['context'] = $evaluationContext;
        }

        $headers = [
            HttpHeader::CLIENT_ID->value => $this->identity?->getClientId()?->toString(),
            HttpHeader::TOKEN->value => $this->identity?->getUserToken()?->toString(),
            HttpHeader::CLIENT_IP->value => $context->getClientIp(),
            HttpHeader::CLIENT_AGENT->value => $context->getClientAgent(),
        ];

        try {
            return $this->client->send(self::ENDPOINT, $payload, $headers);
        } catch (ApiException $exception) {
            if ($options !== null && $options->hasFallback()) {
                return $options->getFallback();
            }

            throw new EvaluationException($exception->getMessage(), 0, $exception);
        }
    }
}
