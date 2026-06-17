<?php

declare(strict_types=1);

namespace Croct\Plug\Exception;

/**
 * Reports a failure while fetching the content of a slot.
 */
class ContentException extends \RuntimeException implements CroctException
{
}
