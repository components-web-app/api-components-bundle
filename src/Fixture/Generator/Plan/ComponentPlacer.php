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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\VariableNamer;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\EntityStateReader;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\PropertyValue;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteSnapshot;

/**
 * @internal
 */
final class ComponentPlacer
{
    private const array PAGE_DATA_STRUCTURE = ['route', 'page', 'parentPage', 'parentPageData'];

    /** @var array<int, ComponentPlan> */
    private array $plans = [];

    /** @var list<ComponentPlan> */
    private array $planList = [];

    /** @var array<int, list<PropertyValue>> */
    private array $states = [];

    /** @var list<array{ComponentPlan, PropertyValue, ComponentPlan}> */
    private array $relations = [];

    /** @var array<int, true> */
    private array $placedGroups = [];

    public function __construct(
        private readonly SiteSnapshot $site,
        private readonly EntityStateReader $stateReader,
        private readonly PropertyValueWriter $values,
        private readonly IriValueLinker $iris,
        private readonly GroupPlanner $groupPlanner,
        private readonly ImportCollector $imports,
        private readonly PhpValueExporter $exporter,
        private readonly NotReproducedReport $report,
        private readonly \DateTimeImmutable $now,
        private readonly bool $keepTimestamps,
    ) {
    }

    public function placeIn(MethodPlan $method): void
    {
        $owner = $method->entity;
        if ($owner instanceof Layout || $owner instanceof Page) {
            foreach (GroupPlanner::sort($owner->getComponentGroups()) as $group) {
                $groupPlan = $this->groupPlanner->plan($group, $owner);
                if (null !== $groupPlan) {
                    $method->groups[] = $groupPlan;
                    $this->placeGroup($groupPlan, $group, $method, [(string) $owner->reference, $groupPlan->name]);
                }
            }

            return;
        }
        if (!$owner instanceof AbstractPageData) {
            return;
        }

        $phrases = null === $owner->getTitle() ? [] : [$owner->getTitle()];
        foreach ($this->stateReader->read($owner, self::PAGE_DATA_STRUCTURE) as $value) {
            if (!$value->writable) {
                $this->values->reportUnwritable($owner, $value);
                continue;
            }
            if (PropertyValue::ROUTE === $value->kind && $value->value instanceof Route && $this->site->contains($value->value)) {
                $method->calls[] = $this->linkRoute($value->property, $value->value);
                continue;
            }
            if (PropertyValue::COMPONENT === $value->kind && $value->value instanceof AbstractComponent) {
                $held = $this->publishedInsteadOfDraft($value->value, \sprintf('The page data property %s::$%s', $this->stateReader->className($owner), $value->property), $owner);
                $component = $this->place($held, $method, [...$phrases, $value->property]);
                $method->entityAssignments[] = new Assignment($value->property, $component, $value->setter);
                continue;
            }
            if (PropertyValue::FIELD === $value->kind) {
                [$value, $calls] = $this->iris->link($this->stateReader->className($owner), $value);
                array_push($method->calls, ...$calls);
                if (null === $value) {
                    continue;
                }
            }
            $expression = $this->values->expression($owner, $value);
            if (null !== $expression) {
                $method->entityAssignments[] = new Assignment($value->property, $expression, $value->setter);
            }
        }
    }

    public function describeAll(): void
    {
        for ($index = 0; $index < \count($this->planList); ++$index) {
            $this->describe($this->planList[$index]);
        }
    }

    /**
     * @return list<ComponentPlan>
     */
    public function plans(): array
    {
        return $this->planList;
    }

    /**
     * @return list<array{ComponentPlan, PropertyValue, ComponentPlan}>
     */
    public function relations(): array
    {
        return $this->relations;
    }

    /**
     * @param list<string> $phrases
     */
    private function placeGroup(GroupPlan $groupPlan, ComponentGroup $group, MethodPlan $method, array $phrases): void
    {
        if (isset($this->placedGroups[spl_object_id($group)])) {
            return;
        }
        $this->placedGroups[spl_object_id($group)] = true;

        $positions = $group->componentPositions->toArray();
        usort($positions, static fn (ComponentPosition $a, ComponentPosition $b): int => $a->sortValue <=> $b->sortValue);
        foreach ($positions as $position) {
            if (null !== $position->pageDataProperty) {
                if (null !== $position->component) {
                    $this->report->add(\sprintf('The fallback component of the page data position "%s" in the component group "%s".', $position->pageDataProperty, $group->reference));
                }
                $class = null === $position->pageDataClass ? 'null' : $this->imports->add($position->pageDataClass) . '::class';
                $groupPlan->items[] = 'pageDataPosition(' . $class . ', ' . $this->export($position->pageDataProperty) . ')';
                continue;
            }
            if (null !== $position->component) {
                $held = $this->publishedInsteadOfDraft($position->component, 'A position', $position);
                $groupPlan->items[] = $this->place($held, $method, $phrases);
            }
        }
    }

    private function publishedInsteadOfDraft(AbstractComponent $component, string $holder, object $entity): AbstractComponent
    {
        $published = $this->stateReader->publishedOf($component);
        if (null === $published) {
            return $component;
        }
        $this->report->addForEach($holder . ' holds a draft of a published component, which is generated as the published component a write would store', $entity);

        return $published;
    }

    /**
     * @param list<string> $phrases
     */
    private function place(AbstractComponent $component, MethodPlan $method, array $phrases): ComponentPlan
    {
        $plan = $this->plans[spl_object_id($component)] ?? $this->newPlan($component, $method, $phrases);
        ++$plan->uses;
        $plan->scopes[spl_object_id($method)] = $method;

        return $plan;
    }

    /**
     * @param list<string> $phrases
     */
    private function newPlan(AbstractComponent $component, MethodPlan $method, array $phrases, ?ComponentPlan $published = null): ComponentPlan
    {
        $plan = new ComponentPlan($component, $this->imports->add($this->stateReader->className($component)), $method);
        $plan->published = $published;
        $plan->scopes[spl_object_id($method)] = $method;
        $this->plans[spl_object_id($component)] = $plan;
        $this->planList[] = $plan;

        $state = $this->stateReader->read($component);
        $this->states[spl_object_id($component)] = $state;
        $fields = [];
        foreach ($state as $value) {
            if (PropertyValue::FIELD === $value->kind) {
                $fields[$value->property] = $value->value;
            }
        }
        $identifying = VariableNamer::identifyingValue($fields);
        $plan->phrases = null === $identifying ? $phrases : [$identifying];

        foreach (GroupPlanner::sort($component->getComponentGroups()) as $group) {
            if (null !== $published) {
                if ($published->component->getComponentGroups()->contains($group)) {
                    continue;
                }
                $this->report->add(\sprintf('Component group "%s" is owned by a draft, which the fixture builder cannot give a group.', $group->reference));
                continue;
            }
            $groupPlan = $this->groupPlanner->plan($group, $component);
            if (null !== $groupPlan) {
                $plan->groups[] = $groupPlan;
                $this->placeGroup($groupPlan, $group, $method, [...$plan->phrases, $groupPlan->name]);
            }
        }

        return $plan;
    }

    private function describe(ComponentPlan $plan): void
    {
        $component = $plan->component;
        foreach ($this->states[spl_object_id($component)] as $value) {
            if (!$value->writable) {
                $this->values->reportUnwritable($component, $value);
                continue;
            }
            if (PropertyValue::COMPONENT === $value->kind && $value->value instanceof AbstractComponent) {
                $held = $this->publishedInsteadOfDraft($value->value, \sprintf('The component field %s::$%s', $this->stateReader->className($component), $value->property), $component);
                $related = $this->plans[spl_object_id($held)] ?? $this->newPlan($held, $plan->scope, [...$plan->phrases, $value->property]);
                $plan->related = $related->related = true;
                $this->relations[] = [$plan, $value, $related];
                continue;
            }
            if (PropertyValue::ROUTE === $value->kind && $value->value instanceof Route && $this->site->contains($value->value)) {
                $plan->links[] = $this->linkRoute($value->property, $value->value);
                continue;
            }
            if (PropertyValue::FIELD === $value->kind) {
                [$value, $calls] = $this->iris->link($this->stateReader->className($component), $value);
                array_push($plan->links, ...$calls);
                if (null === $value) {
                    continue;
                }
            }
            $expression = $this->values->expression($component, $value);
            if (null !== $expression) {
                $plan->assignments[] = new Assignment($value->property, $expression, $value->setter);
            }
        }

        $publishedAt = $this->stateReader->publishedAt($component);
        if (null !== $publishedAt) {
            if ($this->keepTimestamps || $publishedAt > $this->now) {
                $plan->publishAt = $this->export(\DateTimeImmutable::createFromInterface($publishedAt));
            } else {
                $plan->publishNow = true;
            }
        }

        $draft = null === $plan->published ? $this->stateReader->draftOf($component) : null;
        if (null !== $draft) {
            $plan->draft = $this->newPlan($draft, $plan->scope, $plan->phrases, $plan);
        }
    }

    private function linkRoute(string $property, Route $route): string
    {
        return \sprintf('linkRoute(%s, routeName: %s)', $this->export($property), $this->export($route->getName()));
    }

    private function export(mixed $value): string
    {
        return $this->exporter->export($value, $this->imports) ?? 'null';
    }
}
