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

/**
 * @internal
 */
final class ComponentPlan
{
    public string $name = '';
    public bool $property = false;
    public bool $inline = false;
    public ?ComponentPlan $published = null;
    public ?ComponentPlan $draft = null;

    /** @var list<string> */
    public array $phrases = [];

    /** @var list<Assignment> */
    public array $assignments = [];

    public bool $publishNow = false;

    public ?string $publishAt = null;

    /** @var list<string> */
    public array $links = [];

    /** @var list<GroupPlan> */
    public array $groups = [];

    public int $uses = 0;

    /** @var array<int, MethodPlan> */
    public array $scopes = [];

    public bool $related = false;

    public function __construct(
        public readonly AbstractComponent $component,
        public readonly string $class,
        public MethodPlan $scope,
    ) {
    }

    public function canBeInline(): bool
    {
        return !$this->property
            && 1 === $this->uses
            && !$this->related
            && null === $this->published
            && null === $this->draft
            && [] === $this->assignments
            && [] === $this->links
            && [] === $this->groups
            && null === $this->publishAt;
    }

    public function isUnplacedDraft(): bool
    {
        return null !== $this->published && 0 === $this->uses;
    }
}
