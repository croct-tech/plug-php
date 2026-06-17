<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Exception\EvaluationException;

/**
 * Evaluates CQL queries against the visitor's context.
 */
interface Evaluator
{
    /**
     * Evaluates a CQL query and returns its result.
     *
     * Returns the configured fallback if the evaluation fails, otherwise it throws.
     *
     * @param EvaluationOptions<mixed>|null $options
     *
     * @throws EvaluationException If the query is invalid or the request fails without a fallback.
     */
    public function evaluate(string $query, ?EvaluationOptions $options = null): mixed;
}
