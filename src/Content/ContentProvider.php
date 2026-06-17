<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

/**
 * Provides content for slots.
 */
interface ContentProvider
{
    /**
     * Gets the content of a slot.
     *
     * @param string      $id       The ID of the slot.
     * @param string|null $language The preferred language, or null for the default.
     *
     * @return array<string, mixed>|null The content of the slot, or null when none is available.
     */
    public function getSlotContent(string $id, ?string $language = null): ?array;
}
