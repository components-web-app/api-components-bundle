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
final class ComponentScopeDecider
{
    /** @var array<int, MethodPlan> */
    private array $topLevel = [];

    /** @var array<int, true> */
    private array $mayBeSkipped = [];

    /**
     * @param list<MethodPlan> $methods
     */
    public function __construct(array $methods)
    {
        foreach ($methods as $method) {
            foreach (ScaffoldPlan::inFileOrder([$method]) as $descendant) {
                $this->topLevel[spl_object_id($descendant)] = $method;
            }
            $this->markMayBeSkipped($method, false);
        }
    }

    /**
     * @param list<ComponentPlan>                       $plans
     * @param list<array{ComponentPlan, ComponentPlan}> $pairs
     */
    public function decide(array $plans, array $pairs): void
    {
        do {
            $changed = false;
            foreach ($pairs as [$plan, $related]) {
                if ($plan->scope !== $related->scope && !($plan->property && $related->property)) {
                    $plan->property = $related->property = $changed = true;
                }
            }
            foreach ($plans as $plan) {
                if (!$plan->property && \count($plan->scopes) > 1) {
                    $plan->property = $changed = true;
                }
                if ($this->hoist($plan)) {
                    $changed = true;
                }
            }
        } while ($changed);

        foreach ($plans as $plan) {
            $plan->inline = $plan->canBeInline();
        }
    }

    private function markMayBeSkipped(MethodPlan $method, bool $underPageData): void
    {
        if ($underPageData) {
            $this->mayBeSkipped[spl_object_id($method)] = true;
        }
        foreach ($method->children as $child) {
            $this->markMayBeSkipped($child, $underPageData || MethodKind::PageData === $method->kind);
        }
    }

    private function hoist(ComponentPlan $plan): bool
    {
        $from = $plan->scope;
        $top = $this->topLevel[spl_object_id($from)];
        if (!$plan->property || $plan->isUnplacedDraft() || $top === $from) {
            return false;
        }
        if ([] !== $plan->groups && !isset($this->mayBeSkipped[spl_object_id($from)])) {
            return false;
        }
        $this->relocate($plan, $from, $top);

        return true;
    }

    private function relocate(ComponentPlan $plan, MethodPlan $from, MethodPlan $to): void
    {
        if ($plan->scope !== $from) {
            return;
        }
        $plan->scope = $to;
        $plan->scopes[spl_object_id($to)] = $to;
        if (null !== $plan->draft) {
            $plan->draft->scope = $to;
        }
        foreach ($plan->groups as $group) {
            foreach ($group->items as $item) {
                if ($item instanceof ComponentPlan && $item->scope === $from) {
                    if (1 === $item->uses) {
                        unset($item->scopes[spl_object_id($from)]);
                    }
                    $this->relocate($item, $from, $to);
                }
            }
        }
    }
}
