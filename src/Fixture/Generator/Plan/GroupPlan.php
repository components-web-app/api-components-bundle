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
final class GroupPlan
{
    /** @var list<ComponentPlan|string> */
    public array $items = [];

    /**
     * @param list<string> $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments,
    ) {
    }
}
