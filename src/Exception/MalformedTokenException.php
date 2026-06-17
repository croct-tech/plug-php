<?php

declare(strict_types=1);

namespace Croct\Plug\Exception;

/**
 * Reports that a token could not be parsed because it is malformed or corrupted.
 */
class MalformedTokenException extends \RuntimeException implements CroctException
{
}
