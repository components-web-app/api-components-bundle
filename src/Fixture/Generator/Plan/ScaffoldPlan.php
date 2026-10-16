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

use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;

/**
 * @internal
 */
final readonly class ScaffoldPlan
{
    /**
     * @param list<MethodPlan>    $methods
     * @param list<ComponentPlan> $properties
     * @param list<string>        $notReproduced
     */
    public function __construct(
        public array $methods,
        public array $properties,
        public ImportCollector $imports,
        public array $notReproduced = [],
    ) {
    }

    /**
     * @param list<MethodPlan> $methods
     *
     * @return list<MethodPlan>
     */
    public static function inFileOrder(array $methods): array
    {
        $ordered = [];
        foreach ($methods as $method) {
            $ordered[] = $method;
            array_push($ordered, ...self::inFileOrder($method->children));
        }

        return $ordered;
    }
}
