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

use Silverback\ApiComponentsBundle\Fixture\Generator\Site\PropertyValue;

/**
 * @internal
 */
final readonly class DeclarationOrderer
{
    /**
     * @param list<ComponentPlan>                                      $plans
     * @param list<array{ComponentPlan, PropertyValue, ComponentPlan}> $relations
     */
    public function __construct(
        private ExecutionOrder $order,
        private array $plans,
        private array $relations,
    ) {
    }

    public function declare(): void
    {
        foreach ($this->order->methods as $method) {
            $this->declareIn($method);
        }
    }

    /**
     * @param list<array{ComponentPlan, ComponentPlan}> $draftPairs
     */
    public function linkDrafts(array $draftPairs): void
    {
        foreach ($draftPairs as [$published, $draft]) {
            $later = $this->order->runsAfter($draft->scope, $published->scope) ? $draft->scope : $published->scope;
            $later->draftLinks[] = [$published, $draft];
        }
    }

    private function declareIn(MethodPlan $method): void
    {
        $state = [];
        $visit = function (ComponentPlan $plan) use (&$visit, &$state, $method): void {
            $id = spl_object_id($plan);
            if (isset($state[$id]) || $plan->scope !== $method) {
                return;
            }
            $state[$id] = false;
            foreach ($plan->groups as $group) {
                foreach ($group->items as $item) {
                    if ($item instanceof ComponentPlan) {
                        $visit($item);
                    }
                }
            }
            $relations = $this->relationsFrom($plan);
            foreach ($relations as [, , $related]) {
                $visit($related);
            }
            foreach ($relations as [$source, $value, $related]) {
                $this->assign($source, $value, $related, $state);
            }
            $state[$id] = true;
            if (!$plan->inline) {
                $method->components[] = $plan;
            }
        };

        foreach ($method->groups as $group) {
            foreach ($group->items as $item) {
                if ($item instanceof ComponentPlan) {
                    $visit($item);
                }
            }
        }
        foreach ($method->entityAssignments as $assignment) {
            if ($assignment->value instanceof ComponentPlan) {
                $visit($assignment->value);
            }
        }
        foreach ($this->plans as $plan) {
            if (!$plan->isUnplacedDraft()) {
                $visit($plan);
            }
        }
    }

    /**
     * @return list<array{ComponentPlan, PropertyValue, ComponentPlan}>
     */
    private function relationsFrom(ComponentPlan $plan): array
    {
        return array_values(array_filter(
            $this->relations,
            static fn (array $relation): bool => $relation[0] === $plan || $relation[0] === $plan->draft,
        ));
    }

    /**
     * @param array<int, bool> $state
     */
    private function assign(ComponentPlan $source, PropertyValue $value, ComponentPlan $related, array $state): void
    {
        $declaredBefore = $source->scope === $related->scope
            ? true === ($state[spl_object_id($related)] ?? null)
            : $this->order->runsAfter($source->scope, $related->scope);

        if ($declaredBefore) {
            $source->assignments[] = new Assignment($value->property, $related, $value->setter);

            return;
        }
        $related->scope->lateAssignments[] = new Assignment($value->property, $related, $value->setter, $source);
    }
}
