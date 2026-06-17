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
    private int $processedComponentCount = 0;
    private int $processedPositionCount = 0;

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

    /** Returns only components added since the last call — safe to call on every flush(). */
    public function getNewComponents(): array
    {
        $new = \array_slice($this->components, $this->processedComponentCount);
        $this->processedComponentCount = \count($this->components);

        return $new;
    }

    /** Returns only pageData positions added since the last call — safe to call on every flush(). */
    public function getNewPageDataPositions(): array
    {
        $new = \array_slice($this->pageDataPositions, $this->processedPositionCount);
        $this->processedPositionCount = \count($this->pageDataPositions);

        return $new;
    }
}
