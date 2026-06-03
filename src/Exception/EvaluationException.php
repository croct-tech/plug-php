<?php

declare(strict_types=1);

namespace Croct\Plug\Exception;

/**
 * Reports a failure while evaluating a CQL query.
 */
class EvaluationException extends \RuntimeException implements CroctException
{
}
