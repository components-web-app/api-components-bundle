<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Factory\Uploadable;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Factory\Uploadable\PublicUrlGenerator;

class PublicUrlGeneratorTest extends TestCase
{
    public function test_generates_public_url_from_filesystem(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('publicUrl')
            ->with('/uploads/image.png', [])
            ->willReturn('https://cdn.example.com/uploads/image.png');

        $generator = new PublicUrlGenerator();

        $result = $generator->generateUrl(new \stdClass(), 'file', $filesystem, '/uploads/image.png');

        $this->assertSame('https://cdn.example.com/uploads/image.png', $result);
    }

    public function test_passes_config_to_filesystem(): void
    {
        $config = ['visibility' => 'public'];

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('publicUrl')
            ->with('/file.png', $config)
            ->willReturn('https://cdn.example.com/file.png');

        $generator = new PublicUrlGenerator($config);

        $result = $generator->generateUrl(new \stdClass(), 'file', $filesystem, '/file.png');

        $this->assertSame('https://cdn.example.com/file.png', $result);
    }
}
