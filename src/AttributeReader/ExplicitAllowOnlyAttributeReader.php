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

use Silverback\ApiComponentsBundle\Annotation\ExplicitAllowOnly;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ExplicitAllowOnlyAttributeReader extends AttributeReader
{
    /**
     * @throws \ReflectionException
     */
    public function getConfiguration(object|string $class): ExplicitAllowOnly
    {
        $config = $this->getClassAttributeConfiguration($class, ExplicitAllowOnly::class);
        if (!$config instanceof ExplicitAllowOnly) {
            throw new \LogicException(\sprintf('getClassAttributeConfiguration should return the type %s', ExplicitAllowOnly::class));
        }

        return $config;
    }
}
