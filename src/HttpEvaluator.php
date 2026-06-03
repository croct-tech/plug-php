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

    private ApiClient $client;

    private RequestContext $context;

    public function __construct(ApiClient $client, RequestContext $context)
    {
        $this->client = $client;
        $this->context = $context;
    }

    public function evaluate(string $query, ?EvaluationOptions $options = null): mixed
    {
        $options ??= EvaluationOptions::empty();
        $context = $this->context;

        $payload = ['query' => $query];

        $evaluationContext = $context->toEvaluationContext($options->getAttributes());

        if ($evaluationContext !== []) {
            $payload['context'] = $evaluationContext;
        }

        try {
            return $this->client->send(self::ENDPOINT, $payload, $context);
        } catch (ApiException $exception) {
            if ($options->hasFallback()) {
                return $options->getFallback();
            }

            throw new EvaluationException($exception->getMessage(), 0, $exception);
        }
    }
}
