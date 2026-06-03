<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Content\SlotMetadata;

/**
 * Result of fetching the content of a slot.
 */
final class FetchResponse
{
    private mixed $content;

    private ?SlotMetadata $metadata;

    public function __construct(mixed $content, ?SlotMetadata $metadata = null)
    {
        $this->content = $content;
        $this->metadata = $metadata;
    }

    /**
     * Gets the slot content.
     */
    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * Gets the content metadata.
     *
     * @return SlotMetadata|null The metadata, or null if none is available.
     */
    public function getMetadata(): ?SlotMetadata
    {
        return $this->metadata;
    }

    /**
     * Creates a response from the decoded API payload.
     */
    public static function fromResponse(mixed $data): self
    {
        $content = [];
        $metadata = null;

        if (\is_array($data)) {
            if (isset($data['content']) && \is_array($data['content'])) {
                $content = $data['content'];
            }

            if (isset($data['metadata']) && \is_array($data['metadata'])) {
                $metadata = SlotMetadata::fromArray($data['metadata']);
            }
        }

        return new self($content, $metadata);
    }
}
