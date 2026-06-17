<?php

declare(strict_types=1);

namespace Croct\Plug;

use Croct\Plug\Content\SlotMetadata;

/**
 * Result of fetching the content of a slot.
 *
 * @template-covariant TContent The content type returned on success.
 * @template-covariant TFallback The fallback type returned when the fetch fails.
 * @template-covariant TSchema of bool The schema flag: `true` when the schema was requested.
 */
final class FetchResponse
{
    /** @var TContent|TFallback */
    private mixed $content;

    /** @var SlotMetadata<TSchema>|null */
    private ?SlotMetadata $metadata;

    /**
     * @param TContent|TFallback         $content
     * @param SlotMetadata<TSchema>|null $metadata
     */
    public function __construct(mixed $content, ?SlotMetadata $metadata = null)
    {
        $this->content = $content;
        $this->metadata = $metadata;
    }

    /**
     * Gets the slot content, or the fallback when the fetch failed.
     *
     * @return TContent|TFallback
     */
    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * Gets the content metadata.
     *
     * @return SlotMetadata<TSchema>|null The metadata, or null if none is available.
     */
    public function getMetadata(): ?SlotMetadata
    {
        return $this->metadata;
    }

    /**
     * Creates a response from the decoded API payload.
     *
     * @return self<array<string, mixed>, never, never>
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

        /** @var self<array<string, mixed>, never, never> $response */
        $response = new self($content, $metadata);

        return $response;
    }
}
