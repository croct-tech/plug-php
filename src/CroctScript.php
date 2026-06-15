<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * The tags that load the client-side SDK and bootstrap it with the plug options.
 *
 * It renders three tags. The first is a small `onCroctPlug` queue so page code can register
 * callbacks that run once the SDK is plugged. The second is the loader that fetches the SDK.
 * The third is a bootstrap that plugs the SDK and flushes the queue.
 *
 * By default the SDK loads without blocking rendering. The synchronous mode instead makes the
 * `croct` global available right away.
 */
final class CroctScript implements \Stringable
{
    /**
     * The default URL of the client-side SDK loader.
     */
    public const DEFAULT_SCRIPT_URL = 'https://cdn.croct.io/js/v1/lib/plug.js';

    private string $scriptSrc;

    /** @var array<string, mixed> */
    private array $options;

    private ?string $nonce;

    private LoadMode $mode;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $scriptUrl,
        array $options,
        ?string $nonce = null,
        LoadMode $mode = LoadMode::DEFER,
    ) {
        $this->scriptSrc = $scriptUrl;
        $this->options = $options;
        $this->nonce = $nonce;
        $this->mode = $mode;
    }

    public function __toString(): string
    {
        $nonceAttribute = $this->nonce === null
            ? ''
            : \sprintf(' nonce="%s"', \htmlspecialchars($this->nonce, ENT_QUOTES));

        $modeAttribute = match ($this->mode) {
            LoadMode::SYNC => '',
            LoadMode::DEFER => ' defer',
            LoadMode::ASYNC => ' async',
        };

        $options = \json_encode(
            $this->options,
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_AMP,
        );

        return \sprintf(
            // The queue lets page code register `onCroctPlug` callbacks before the SDK is ready.
            '<script%s>window.onCroctPlug=window.onCroctPlug||function(f){(onCroctPlug.q=onCroctPlug.q||[]).push(f)}'
            . '</script>'
            . '<script src="%s"%s%s></script>'
            // Plug the SDK, then drain the queue. Run now when the loader has already executed (sync,
            // or async resolved before this tag), otherwise on its load event (defer/async).
            . '<script%s>(function(s){function b(){croct.plug(%s);var q=onCroctPlug.q||[];'
            . 'window.onCroctPlug=function(f){f(croct)};for(var i=0;i<q.length;i++)q[i](croct)}'
            . 'window.croct?b():s.onload=b})(document.currentScript.previousElementSibling)</script>',
            $nonceAttribute,
            \htmlspecialchars($this->scriptSrc, ENT_QUOTES),
            $modeAttribute,
            $nonceAttribute,
            $nonceAttribute,
            $options,
        );
    }
}
