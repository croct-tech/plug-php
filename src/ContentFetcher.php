<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Exception\ContentException;

/**
 * Fetches the personalized content of a slot.
 */
interface ContentFetcher
{
    /**
     * Fetches the content of a slot.
     *
     * Returns the configured fallback if the fetch fails, otherwise it throws.
     *
     * @template F = never
     *
     * @param string               $slotId  The slot ID, optionally versioned as `slot-id@version`
     *                                       (e.g. `home-banner@2`).
     * @param FetchOptions<F>|null $options
     *
     * @return FetchResponse<array<string, mixed>, F>
     *
     * @throws \InvalidArgumentException If the slot ID is malformed.
     * @throws ContentException If the request fails without a fallback.
     */
    public function fetch(string $slotId, ?FetchOptions $options = null): FetchResponse;
}
