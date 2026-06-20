<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;

class PublicUrlLocalFilesystemAdapter extends LocalFilesystemAdapter implements PublicUrlGenerator
{
    public function __construct(string $location, private readonly string $publicUrlBase)
    {
        parent::__construct($location);
    }

    public function publicUrl(string $path, Config $config): string
    {
        return rtrim($this->publicUrlBase, '/') . '/' . ltrim($path, '/');
    }
}
