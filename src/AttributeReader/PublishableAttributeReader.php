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

namespace Silverback\ApiComponentsBundle\AttributeReader;

use Silverback\ApiComponentsBundle\Annotation\Publishable;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableAttributeReader extends AttributeReader
{
    /**
     * @throws \ReflectionException
     */
    public function getConfiguration(object|string $class): Publishable
    {
        $publishable = $this->getClassAttributeConfiguration($class, Publishable::class);
        if (!$publishable instanceof Publishable) {
            throw new \LogicException(sprintf('getClassAnnotationConfiguration should return the type %s', Publishable::class));
        }

        return $publishable;
    }
}
