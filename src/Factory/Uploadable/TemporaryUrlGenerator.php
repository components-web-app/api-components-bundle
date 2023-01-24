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

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator as FlysystemTemporaryUrlGenerator;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;

class TemporaryUrlGenerator implements UploadableUrlGeneratorInterface
{
    public function generateUrl(object $object, string $fileProperty, Filesystem $filesystem, string $path): string
    {
        if (!$filesystem instanceof FlysystemTemporaryUrlGenerator) {
            throw new InvalidArgumentException(sprintf('The public URL generator requires a filesystem implementing %s', FlysystemTemporaryUrlGenerator::class));
        }
        return $filesystem->temporaryUrl($path, new \DateTime('+1 day'), new Config([]));
    }
}
