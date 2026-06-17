<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * Resolves the locale of the visitor.
 */
interface LocaleResolver
{
    /**
     * Returns the detected locale, or null when none can be determined.
     */
    public function getLocale(): ?string;
}
