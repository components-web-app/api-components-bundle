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

use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

class PageBuilder
{
    /** @var array<string, GroupBuilder> */
    private array $groupBuilders = [];
    private ?\Closure $nestedClosure = null;

    public function __construct(private readonly Page $page)
    {
    }

    public function nested(\Closure $configure): void
    {
        $this->nestedClosure = $configure;
    }

    public function getNestedClosure(): ?\Closure
    {
        return $this->nestedClosure;
    }

    public function title(string $title): self
    {
        $this->page->setTitle($title);

        return $this;
    }

    public function metaDescription(string $metaDescription): self
    {
        $this->page->setMetaDescription($metaDescription);

        return $this;
    }

    public function uiClassNames(string ...$classes): self
    {
        $this->page->uiClassNames = $classes;

        return $this;
    }

    public function group(string $name, ?\Closure $configure = null, ?string $locationReference = null): GroupBuilder
    {
        if (!isset($this->groupBuilders[$name])) {
            $this->groupBuilders[$name] = new GroupBuilder($name, [], $locationReference);
        }
        if (null !== $configure) {
            $configure($this->groupBuilders[$name]);
        }

        return $this->groupBuilders[$name];
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    public function getRoute(): ?Route
    {
        return $this->page->getRoute();
    }

    /** @return array<string, GroupBuilder> */
    public function getGroupBuilders(): array
    {
        return $this->groupBuilders;
    }
}
