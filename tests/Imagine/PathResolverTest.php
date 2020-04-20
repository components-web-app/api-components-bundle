<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Imagine;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Imagine\PathResolver;

class PathResolverTest extends TestCase
{
    public function test_resolve_path_matching_root(): void
    {
        $pathResolver = new PathResolver(['/émé/']);
        $this->assertEquals('sub/file.jpg', $pathResolver->resolve('/émé/sub/file.jpg'));
    }

    public function test_resolve_path_without_root_match(): void
    {
        $pathResolver = new PathResolver();
        $this->assertEquals('/dir/sub/file.jpg', $pathResolver->resolve('/dir/sub/file.jpg'));
    }
}
