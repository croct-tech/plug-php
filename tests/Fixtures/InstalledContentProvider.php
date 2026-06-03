<?php

declare(strict_types=1);

namespace Croct\Plug\Tests\Fixtures;

use Croct\Plug\Content\ArrayContentProvider;

/**
 * Stands in for the CLI-generated content provider in discovery tests.
 */
final class InstalledContentProvider extends ArrayContentProvider
{
    public function __construct()
    {
        parent::__construct(['home-hero' => ['title' => 'Generated default']]);
    }
}
