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

namespace Silverback\ApiComponentsBundle\Imagine;

use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FlysystemCacheResolver implements ResolverInterface
{
    public function isStored($path, $filter)
    {
        // TODO: Implement isStored() method.
    }

    public function resolve($path, $filter)
    {
        // TODO: Implement resolve() method.
    }

    public function store(BinaryInterface $binary, $path, $filter)
    {
        // TODO: Implement store() method.
    }

    public function remove(array $paths, array $filters)
    {
        // TODO: Implement remove() method.
    }
}
