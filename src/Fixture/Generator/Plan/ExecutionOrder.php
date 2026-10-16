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
final readonly class ExecutionOrder
{
    /** @var list<MethodPlan> */
    public array $methods;

    /** @var array<int, int> */
    private array $index;

    /**
     * @param list<MethodPlan> $methods
     */
    public function __construct(array $methods)
    {
        $order = $methods;
        $registered = [MethodKind::PageData->value => [], MethodKind::Page->value => []];
        foreach ($methods as $method) {
            if (isset($registered[$method->kind->value])) {
                $registered[$method->kind->value][] = $method;
            }
        }

        $evaluated = [];
        do {
            $ran = false;
            foreach ([MethodKind::PageData->value, MethodKind::Page->value] as $kind) {
                foreach ($registered[$kind] as $method) {
                    if ([] === $method->children || isset($evaluated[spl_object_id($method)])) {
                        continue;
                    }
                    $evaluated[spl_object_id($method)] = true;
                    $ran = true;
                    foreach ($method->children as $child) {
                        $order[] = $child;
                        $registered[$child->kind->value][] = $child;
                    }
                }
            }
        } while ($ran);

        $index = [];
        foreach ($order as $position => $method) {
            $index[spl_object_id($method)] = $position;
        }
        $this->methods = $order;
        $this->index = $index;
    }

    public function runsAfter(MethodPlan $method, MethodPlan $other): bool
    {
        return $this->index[spl_object_id($method)] > $this->index[spl_object_id($other)];
    }
}
