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

namespace Silverback\ApiComponentBundle\Helper;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Annotation\MediaObject;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class MediaObjectHelper extends AbstractHelper
{
    public function __construct(Reader $reader, ManagerRegistry $registry)
    {
        $this->reader = $reader;
        $this->initRegistry($registry);
        $this->initReader($reader);
    }

    /**
     * @param object|string $class
     */
    public function getConfiguration($class): MediaObject
    {
        return $this->getAnnotationConfiguration($class, MediaObject::class);
    }
}
