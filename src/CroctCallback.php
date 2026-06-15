<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * A script tag that runs a snippet once the client-side SDK is plugged.
 *
 * It registers the snippet through the `onCroctPlug` queue rendered by {@see CroctScript}, so the
 * code runs with the `croct` instance as soon as it is ready, regardless of how or when the loader
 * is fetched. The queue is defined here too, so the snippet is safe even when it appears before the
 * loader.
 */
final class CroctCallback implements \Stringable
{
    private string $body;

    private ?string $nonce;

    public function __construct(string $body, ?string $nonce = null)
    {
        $this->body = $body;
        $this->nonce = $nonce;
    }

    public function __toString(): string
    {
        $nonceAttribute = $this->nonce === null
            ? ''
            : \sprintf(' nonce="%s"', \htmlspecialchars($this->nonce, ENT_QUOTES));

        return \sprintf(
            '<script%s>(window.onCroctPlug=window.onCroctPlug||function(f){(onCroctPlug.q=onCroctPlug.q||[]).push(f)})'
            . '(function(croct){%s})</script>',
            $nonceAttribute,
            $this->body,
        );
    }
}
