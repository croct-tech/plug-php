<?php

declare(strict_types=1);

namespace Croct\Plug\Exception;

/**
 * Implemented by every exception the SDK throws, so they can all be caught together.
 */
interface CroctException extends \Throwable
{
}
