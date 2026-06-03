<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

/**
 * Where the content served for a slot came from.
 */
enum ContentSource: string
{
    /**
     * Indicates that the content came from the slot's own default content.
     */
    case SLOT = 'slot';

    /**
     * Indicates that the content was served by a targeted experience.
     */
    case EXPERIENCE = 'experience';

    /**
     * Indicates that the content was served by a running experiment.
     */
    case EXPERIMENT = 'experiment';
}
