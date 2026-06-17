<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * How the browser fetches and runs the client-side SDK loader.
 */
enum LoadMode: string
{
    /**
     * Loads synchronously, blocking parsing until the SDK is ready.
     *
     * The `croct` global is available immediately after the loader tag, so inline scripts may call
     * it directly, at the cost of blocking rendering while the SDK downloads.
     */
    case SYNC = 'sync';

    /**
     * Loads without blocking parsing, running after the document is parsed.
     *
     * Keeps rendering responsive while still bootstrapping before `DOMContentLoaded`. Code that
     * depends on the SDK should run through the `onCroctPlug` callback.
     */
    case DEFER = 'defer';

    /**
     * Loads without blocking parsing, running as soon as it is fetched.
     *
     * Like {@see self::DEFER} but without ordering guarantees. Code that depends on the SDK should
     * run through the `onCroctPlug` callback.
     */
    case ASYNC = 'async';
}
