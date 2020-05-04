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

namespace Silverback\ApiComponentsBundle\AnnotationReader;

use Silverback\ApiComponentsBundle\Annotation\Timestamped;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class TimestampedAnnotationReader extends AnnotationReader
{
    /**
     * @param object|string $class
     */
    public function getConfiguration($class): Timestamped
    {
        return $this->getClassAnnotationConfiguration($class, Timestamped::class);
    }
}
