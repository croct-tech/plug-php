<?php

declare(strict_types=1);

namespace Croct\Plug\Content;

/**
 * A content provider that never has any content.
 */
final class NullContentProvider implements ContentProvider
{
    /**
     * @return array<string, mixed>|null
     */
    public function getContent(string $id, ?string $language = null): ?array
    {
        return null;
    }
}
