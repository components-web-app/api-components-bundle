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

namespace Silverback\ApiComponentBundle\AnnotationReader;

use Silverback\ApiComponentBundle\Annotation\Timestamped;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class TimestampedAnnotationReader extends AbstractAnnotationReader
{
    /**
     * @param object|string $class
     */
    public function getConfiguration($class): Timestamped
    {
        return $this->getClassAnnotationConfiguration($class, Timestamped::class);
    }
}
