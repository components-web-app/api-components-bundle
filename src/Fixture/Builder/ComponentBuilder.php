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

class ComponentBuilder
{
    /** @var array<string, GroupBuilder> */
    private array $groupBuilders = [];

    /** @var list<array{string, string|PageBuilder|PageDataBuilder}> */
    private array $routeLinks = [];

    /** @var list<IriLink> */
    private array $iriLinks = [];

    /** @var list<AbstractComponent> */
    private array $drafts = [];

    /**
     * @param (\Closure(AbstractComponent, AbstractComponent): void)|null $linkDraft
     */
    public function __construct(
        private readonly AbstractComponent $component,
        private readonly ?\Closure $linkDraft = null,
    ) {
    }

    public function uiComponent(string $suffix): self
    {
        $shortName = (new \ReflectionClass($this->component))->getShortName();
        $this->component->uiComponent = 'CwaComponent' . $shortName . 'Ui' . $suffix;

        return $this;
    }

    public function uiClassNames(string ...$classes): self
    {
        $this->component->uiClassNames = $classes;

        return $this;
    }

    public function group(string $name, array $allow = [], ?\Closure $configure = null, ?string $locationReference = null): GroupBuilder
    {
        if (!isset($this->groupBuilders[$name])) {
            $this->groupBuilders[$name] = new GroupBuilder($name, $allow, $locationReference);
        }
        if (null !== $configure) {
            $configure($this->groupBuilders[$name]);
        }

        return $this->groupBuilders[$name];
    }

    public function linkRoute(string $property, ?string $routeName = null, PageBuilder|PageDataBuilder|null $page = null): self
    {
        $this->routeLinks[] = RouteLink::to($property, $routeName, $page);

        return $this;
    }

    public function draft(AbstractComponent $draft): self
    {
        if (null === $this->linkDraft) {
            throw new \LogicException(\sprintf('%s is not publishable, so it cannot have a draft.', $this->component::class));
        }
        ($this->linkDraft)($this->component, $draft);
        $this->drafts[] = $draft;

        return $this;
    }

    /** @return list<array{string, string|PageBuilder|PageDataBuilder}> */
    public function getRouteLinks(): array
    {
        return $this->routeLinks;
    }

    /**
     * @param string|int|array<string|int>|null $key
     */
    public function linkIri(string $property, string|int|array|null $key = null, string|PageBuilder|null $page = null, ?string $layout = null, string|PageDataBuilder|null $pageData = null, string|PageBuilder|PageDataBuilder|null $route = null): self
    {
        $this->iriLinks[] = IriLink::to($property, $key, $page, $layout, $pageData, $route);

        return $this;
    }

    /** @return list<IriLink> */
    public function getIriLinks(): array
    {
        return $this->iriLinks;
    }

    /** @return list<AbstractComponent> */
    public function getDrafts(): array
    {
        return $this->drafts;
    }

    public function getComponent(): AbstractComponent
    {
        return $this->component;
    }

    /** @return array<string, GroupBuilder> */
    public function getGroupBuilders(): array
    {
        return $this->groupBuilders;
    }
}
