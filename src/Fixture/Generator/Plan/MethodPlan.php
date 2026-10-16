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
final class MethodPlan
{
    /** @var list<string> */
    public array $arguments = [];

    /** @var list<string> */
    public array $calls = [];

    /** @var list<GroupPlan> */
    public array $groups = [];

    /** @var list<MethodPlan> */
    public array $children = [];

    /** @var list<ComponentPlan> */
    public array $components = [];

    /** @var list<Assignment> */
    public array $lateAssignments = [];

    /** @var list<array{ComponentPlan, ComponentPlan}> */
    public array $draftLinks = [];

    public ?string $entityClass = null;

    /** @var list<Assignment> */
    public array $entityAssignments = [];

    /** @var list<list<string>> */
    public array $redirects = [];

    public function __construct(
        public readonly MethodKind $kind,
        public readonly string $name,
        public readonly ?object $entity = null,
    ) {
    }
}
