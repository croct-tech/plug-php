<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * A script tag that runs a snippet once the client-side SDK is plugged.
 *
 * It registers the snippet through the `onCroctPlug` queue, defining the queue first (the same way
 * {@see CroctScript} does) so the snippet stays safe even when it renders before the loader.
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
            '<script%s>(%s)(croct=>{%s})</script>',
            $nonceAttribute,
            CroctScript::QUEUE,
            // Trim the indentation a template block adds around the snippet.
            \trim($this->body),
        );
    }
}
