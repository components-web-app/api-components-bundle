<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Plan;

/**
 * @internal
 */
final readonly class Assignment
{
    public function __construct(
        public string $property,
        public string|ComponentPlan $value,
        public ?string $setter = null,
        public ?ComponentPlan $target = null,
    ) {
    }
}
