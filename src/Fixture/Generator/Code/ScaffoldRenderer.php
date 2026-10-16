<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Code;

use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\Assignment;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ComponentPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\GroupPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodKind;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ScaffoldPlan;

/**
 * @internal
 */
final class ScaffoldRenderer
{
    private CodeWriter $writer;
    private bool $firstBlock = true;

    public function render(ScaffoldPlan $plan, string $namespace, string $className): string
    {
        $this->writer = new CodeWriter();
        $this->writer->line('<?php')->blank()->line(\sprintf('namespace %s;', $namespace))->blank();
        foreach ($plan->imports->lines() as $line) {
            $this->writer->line($line);
        }
        $this->writer->blank()->line(\sprintf('class %s extends AbstractCwaScaffold', $className))->line('{')->indent();

        foreach ($plan->properties as $property) {
            $this->writer->line(\sprintf('private %s $%s;', $property->class, $property->name));
        }
        if ([] !== $plan->properties) {
            $this->writer->blank();
        }

        $this->writer->line('public function build(CwaFixtureBuilder $cwa): void')->line('{')->indent();
        foreach ($plan->methods as $method) {
            $this->writer->line(\sprintf('$this->%s($cwa);', $method->name));
        }
        $this->writer->outdent()->line('}');

        foreach (ScaffoldPlan::inFileOrder($plan->methods) as $method) {
            $this->writer->blank()->line(\sprintf('private function %s(CwaFixtureBuilder $cwa): void', $method->name))->line('{')->indent();
            $this->firstBlock = true;
            $this->method($method);
            $this->writer->outdent()->line('}');
        }

        return (string) $this->writer->outdent()->line('}');
    }

    private function method(MethodPlan $method): void
    {
        foreach ($method->redirects as $arguments) {
            $this->writer->line($this->writer->call('$cwa->redirect', $arguments) . ';');
        }
        foreach ($method->components as $component) {
            $this->component($component);
        }
        if ([] !== $method->lateAssignments || [] !== $method->draftLinks) {
            $this->block();
            foreach ($method->lateAssignments as $assignment) {
                if (null !== $assignment->target) {
                    $this->assignment(self::expression($assignment->target), $assignment);
                }
            }
            foreach ($method->draftLinks as [$published, $draft]) {
                $this->writer->line(\sprintf('$cwa->component(%s)->draft(%s);', self::expression($published), self::expression($draft)));
            }
        }

        match ($method->kind) {
            MethodKind::Layout => $this->owner($method, 'layout', '$layout'),
            MethodKind::Page => $this->owner($method, 'page', '$page'),
            MethodKind::PageData => $this->pageData($method),
            MethodKind::Redirects => null,
        };
    }

    public static function expression(ComponentPlan $component): string
    {
        if ($component->inline) {
            $new = 'new ' . $component->class . '()';

            return $component->publishNow ? '$cwa->publish(' . $new . ')' : $new;
        }

        return ($component->property ? '$this->' : '$') . $component->name;
    }

    private function component(ComponentPlan $component): void
    {
        $this->block();
        $variable = self::expression($component);
        $this->writer->line(\sprintf('%s = new %s();', $variable, $component->class));
        $this->state($component, $variable);
        foreach ($component->groups as $group) {
            $this->writer->chain(\sprintf('$cwa->component(%s)', $variable), $this->groupCalls($group));
        }

        $draft = $component->draft;
        if (null !== $draft) {
            $this->block();
            $draftVariable = self::expression($draft);
            $this->writer->line(\sprintf('%s = new %s();', $draftVariable, $draft->class));
            $this->state($draft, $draftVariable);
            $this->writer->line(\sprintf('$cwa->component(%s)->draft(%s);', $variable, $draftVariable));
        }
    }

    private function state(ComponentPlan $component, string $variable): void
    {
        foreach ($component->assignments as $assignment) {
            $this->assignment($variable, $assignment);
        }
        if ($component->publishNow) {
            $this->writer->line($this->writer->call('$cwa->publish', [$variable]) . ';');
        } elseif (null !== $component->publishAt) {
            $this->writer->line($this->writer->call('$cwa->publish', [$variable, 'at: ' . $component->publishAt]) . ';');
        }
        if (1 === \count($component->links)) {
            $this->writer->line(\sprintf('$cwa->component(%s)->%s;', $variable, $component->links[0]));
        } elseif ([] !== $component->links) {
            $this->writer->chain(\sprintf('$cwa->component(%s)', $variable), $component->links);
        }
    }

    private function assignment(string $variable, Assignment $assignment): void
    {
        $value = $assignment->value instanceof ComponentPlan ? self::expression($assignment->value) : $assignment->value;
        $this->writer->line(null === $assignment->setter
            ? \sprintf('%s->%s = %s;', $variable, $assignment->property, $value)
            : \sprintf('%s->%s(%s);', $variable, $assignment->setter, $value));
    }

    private function owner(MethodPlan $method, string $builderMethod, string $variable): void
    {
        $this->block();
        $head = $this->writer->call('$cwa->' . $builderMethod, $method->arguments);
        $nested = $this->nested($method);

        if ([] === $method->groups || (1 === \count($method->groups) && [] === $nested)) {
            $this->writer->chain($head, [...$method->calls, ...$nested, ...array_merge(...array_map($this->groupCalls(...), $method->groups))]);

            return;
        }

        $assignment = $variable . ' = ';
        $this->writer->chain($assignment . $this->writer->call('$cwa->' . $builderMethod, $method->arguments, \strlen($assignment)), $method->calls);
        foreach ($method->groups as $group) {
            $this->block();
            $calls = $this->groupCalls($group);
            $this->writer->chain($variable . '->' . array_shift($calls), $calls);
        }
        if ([] !== $nested) {
            $this->block();
            foreach ($nested as $call) {
                $this->writer->line($variable . '->' . $call . ';');
            }
        }
    }

    private function pageData(MethodPlan $method): void
    {
        $this->block();
        $this->writer->line(\sprintf('$pageData = new %s();', $method->entityClass));
        foreach ($method->entityAssignments as $assignment) {
            $this->assignment('$pageData', $assignment);
        }

        $this->block();
        $this->writer->chain($this->writer->call('$cwa->pageData', $method->arguments), [...$method->calls, ...$this->nested($method)]);
    }

    /**
     * @return list<string>
     */
    private function groupCalls(GroupPlan $group): array
    {
        $calls = [$this->writer->chainedCall('group', $group->arguments)];
        foreach ($group->items as $item) {
            $calls[] = $item instanceof ComponentPlan ? 'add(' . self::expression($item) . ')' : $item;
        }

        return $calls;
    }

    /**
     * @return list<string>
     */
    private function nested(MethodPlan $method): array
    {
        return array_map(static fn (MethodPlan $child): string => \sprintf('nested($this->%s(...))', $child->name), $method->children);
    }

    private function block(): void
    {
        if (!$this->firstBlock) {
            $this->writer->blank();
        }
        $this->firstBlock = false;
    }
}
