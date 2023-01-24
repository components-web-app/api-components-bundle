<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Factory\Uploadable;

use League\Flysystem\Filesystem;

class PublicUrlGenerator implements UploadableUrlGeneratorInterface
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function generateUrl(object $object, string $fileProperty, Filesystem $filesystem, string $path): string
    {
        return $filesystem->publicUrl($path, $this->config);
    }
}
