<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Builder;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;

class GroupBuilder
{
    private array $components = [];
    private array $pageDataPositions = [];
    private int $nextSort = 10;

    public function __construct(
        private readonly string $name,
        private readonly array $allowedClasses = [],
    ) {
    }

    public function add(AbstractComponent $component, ?int $sort = null): self
    {
        $this->components[] = ['component' => $component, 'sort' => $sort ?? $this->nextSort];
        $this->nextSort += 10;

        return $this;
    }

    public function pageDataPosition(string $propertyName, ?int $sort = null): self
    {
        $this->pageDataPositions[] = ['property' => $propertyName, 'sort' => $sort ?? $this->nextSort];
        $this->nextSort += 10;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAllowedClasses(): array
    {
        return $this->allowedClasses;
    }

    public function getComponents(): array
    {
        return $this->components;
    }

    public function getPageDataPositions(): array
    {
        return $this->pageDataPositions;
    }
}
