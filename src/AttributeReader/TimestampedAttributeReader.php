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

use Silverback\ApiComponentsBundle\Annotation\Timestamped;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class TimestampedAttributeReader extends AttributeReader
{
    public function getConfiguration(object|string $class): Timestamped
    {
        $timestamped = $this->getClassAttributeConfiguration($class, Timestamped::class);
        if (!$timestamped instanceof Timestamped) {
            throw new \LogicException(\sprintf('getClassAnnotationConfiguration should return the type %s', Timestamped::class));
        }

        return $timestamped;
    }
}
