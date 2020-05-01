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

use Silverback\ApiComponentsBundle\Annotation\Publishable;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableAnnotationReader extends AbstractAnnotationReader
{
    /**
     * @param object|string $class
     */
    public function getConfiguration($class): Publishable
    {
        return $this->getClassAnnotationConfiguration($class, Publishable::class);
    }
}
