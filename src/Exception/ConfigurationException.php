<?php

declare(strict_types=1);

namespace Croct\Plug\Exception;

/**
 * Reports invalid configuration, such as a malformed API key or application ID.
 *
 * These are programming errors meant to be fixed at development time rather than caught at runtime.
 */
class ConfigurationException extends \InvalidArgumentException implements CroctException
{
}
