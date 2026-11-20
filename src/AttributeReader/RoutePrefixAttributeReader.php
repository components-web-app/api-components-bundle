<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\AttributeReader;

use Silverback\ApiComponentsBundle\Annotation\RoutePrefix;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class RoutePrefixAttributeReader extends AttributeReader
{
    /**
     * @throws \ReflectionException
     */
    public function getConfiguration(object|string $class): RoutePrefix
    {
        $config = $this->getClassAttributeConfiguration($class, RoutePrefix::class);
        if (!$config instanceof RoutePrefix) {
            throw new \LogicException(\sprintf('getClassAttributeConfiguration should return the type %s', RoutePrefix::class));
        }

        return $config;
    }
}
