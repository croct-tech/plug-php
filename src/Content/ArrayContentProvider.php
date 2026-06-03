<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

/**
 * A content provider backed by an in-memory map keyed by slot ID.
 */
class ArrayContentProvider implements ContentProvider
{
    /** @var array<string, array<string, mixed>> */
    private array $content;

    /**
     * @param array<string, array<string, mixed>> $content The content of each slot, keyed by ID.
     */
    public function __construct(array $content)
    {
        $this->content = $content;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContent(string $id): ?array
    {
        return $this->content[$id] ?? null;
    }
}
