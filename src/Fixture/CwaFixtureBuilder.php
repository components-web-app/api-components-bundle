<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Builder\GroupBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\LayoutBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageDataBuilder;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;

class CwaFixtureBuilder
{
    private ?ObjectManager $manager = null;
    private ?AbstractPage $parentContext = null;

    /** @var array<string, LayoutBuilder> */
    private array $layoutBuilders = [];

    /**
     * Each entry: ['builder' => PageBuilder, 'layoutRef' => string, 'route' => ?string, 'routeName' => ?string, 'isTemplate' => bool]
     *
     * @var array<string, array>
     */
    private array $pageSpecs = [];

    /**
     * Each entry: ['builder' => PageDataBuilder, 'templateRef' => ?string, 'route' => ?string, 'routeName' => ?string]
     *
     * @var array<array>
     */
    private array $pageDataSpecs = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /** Maps spl_object_id(GroupBuilder) → ComponentGroup for use in phase 4 */
    private array $componentGroupMap = [];

    public function __construct(
        private readonly TimestampedDataPersister $timestampedPersister,
        private readonly RouteGeneratorInterface $routeGenerator,
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function withManager(ObjectManager $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    public function layout(string $ref, string $uiComponent): LayoutBuilder
    {
        if (!isset($this->layoutBuilders[$ref])) {
            $layout = new Layout();
            $layout->reference = $ref;
            $layout->uiComponent = $uiComponent;
            $this->layoutBuilders[$ref] = new LayoutBuilder($layout);
        }

        return $this->layoutBuilders[$ref];
    }

    public function page(
        string $ref,
        string $uiComponent,
        string $layout,
        ?string $route = null,
        ?string $routeName = null,
        bool $isTemplate = false,
        ?\Closure $configure = null,
    ): PageBuilder {
        if (!isset($this->pageSpecs[$ref])) {
            $page = new Page();
            $page->reference = $ref;
            $page->uiComponent = $uiComponent;
            $page->isTemplate = $isTemplate;
            if ($this->parentContext instanceof AbstractPageData) {
                $page->setParentPageData($this->parentContext);
            } elseif ($this->parentContext instanceof Page) {
                $page->setParentPage($this->parentContext);
            }
            $builder = new PageBuilder($page);
            if (null !== $configure) {
                $configure($builder);
            }
            $this->pageSpecs[$ref] = [
                'builder' => $builder,
                'layoutRef' => $layout,
                'route' => $route,
                'routeName' => $routeName,
                'isTemplate' => $isTemplate,
            ];
        }

        return $this->pageSpecs[$ref]['builder'];
    }

    public function pageData(
        AbstractPageData $pageData,
        ?string $template = null,
        ?string $route = null,
        ?string $routeName = null,
        ?\Closure $configure = null,
    ): PageDataBuilder {
        if ($this->parentContext instanceof Page) {
            $pageData->setParentPage($this->parentContext);
        } elseif ($this->parentContext instanceof AbstractPageData) {
            $pageData->setParentPageData($this->parentContext);
        }

        $builder = new PageDataBuilder($pageData);
        if (null !== $configure) {
            $configure($builder);
        }
        $this->pageDataSpecs[] = [
            'builder' => $builder,
            'templateRef' => $template,
            'route' => $route,
            'routeName' => $routeName,
        ];

        return $builder;
    }

    public function getRoute(string $routeName): Route
    {
        if (!isset($this->namedRoutes[$routeName])) {
            throw new \LogicException(sprintf('Named route "%s" not found. Did you call flush() before getRoute()?', $routeName));
        }

        return $this->namedRoutes[$routeName];
    }

    public function flush(): void
    {
        $this->phaseOne();
        $this->evaluateNested();
        $this->phaseTwo();
        $this->phaseThree();
        $this->phaseFour();
    }

    private function evaluateNested(): void
    {
        $existingPageDataCount = count($this->pageDataSpecs);
        $existingPageRefs = array_keys($this->pageSpecs);

        foreach ($this->pageDataSpecs as $spec) {
            $closure = $spec['builder']->getNestedClosure();
            if (null === $closure) {
                continue;
            }
            $this->parentContext = $spec['builder']->getPageData();
            $closure($this);
            $this->parentContext = null;
        }

        foreach ($this->pageSpecs as $spec) {
            $closure = $spec['builder']->getNestedClosure();
            if (null === $closure) {
                continue;
            }
            $this->parentContext = $spec['builder']->getPage();
            $closure($this);
            $this->parentContext = null;
        }

        $hasNew = false;

        $newPageDataSpecs = array_slice($this->pageDataSpecs, $existingPageDataCount);
        foreach ($newPageDataSpecs as $spec) {
            $this->timestampedPersister->persistTimestampedFields($spec['builder']->getPageData(), true);
            $this->manager->persist($spec['builder']->getPageData());
            $hasNew = true;
        }

        $newPageSpecs = array_diff_key($this->pageSpecs, array_flip($existingPageRefs));
        foreach ($newPageSpecs as $spec) {
            $page = $spec['builder']->getPage();
            $layoutBuilder = $this->layoutBuilders[$spec['layoutRef']] ?? null;
            if (null !== $layoutBuilder) {
                $page->layout = $layoutBuilder->getLayout();
            }
            $this->timestampedPersister->persistTimestampedFields($page, true);
            $this->manager->persist($page);
            $hasNew = true;
        }

        if ($hasNew) {
            $this->manager->flush();
        }
    }

    private function phaseOne(): void
    {
        foreach ($this->layoutBuilders as $layoutBuilder) {
            $layout = $layoutBuilder->getLayout();
            $this->timestampedPersister->persistTimestampedFields($layout, true);
            $this->manager->persist($layout);
        }

        foreach ($this->pageSpecs as $spec) {
            $page = $spec['builder']->getPage();
            $layoutBuilder = $this->layoutBuilders[$spec['layoutRef']] ?? null;
            if (null !== $layoutBuilder) {
                $page->layout = $layoutBuilder->getLayout();
            }
            $this->timestampedPersister->persistTimestampedFields($page, true);
            $this->manager->persist($page);
        }

        foreach ($this->pageDataSpecs as $spec) {
            $pageData = $spec['builder']->getPageData();
            if (null !== $spec['templateRef'] && isset($this->pageSpecs[$spec['templateRef']])) {
                $pageData->page = $this->pageSpecs[$spec['templateRef']]['builder']->getPage();
            }
            $this->timestampedPersister->persistTimestampedFields($pageData, true);
            $this->manager->persist($pageData);
        }

        $this->manager->flush();
    }

    private function phaseTwo(): void
    {
        $allGroupSpecs = [];

        foreach ($this->layoutBuilders as $layoutBuilder) {
            foreach ($layoutBuilder->getGroupBuilders() as $groupBuilder) {
                $allGroupSpecs[] = ['group' => $groupBuilder, 'owner' => $layoutBuilder->getLayout()];
            }
        }

        foreach ($this->pageSpecs as $spec) {
            foreach ($spec['builder']->getGroupBuilders() as $groupBuilder) {
                $allGroupSpecs[] = ['group' => $groupBuilder, 'owner' => $spec['builder']->getPage()];
            }
        }

        foreach ($allGroupSpecs as $item) {
            $groupBuilder = $item['group'];
            $owner = $item['owner'];

            $componentGroup = new ComponentGroup();
            $componentGroup->location = $groupBuilder->getName();

            if ($owner instanceof Layout) {
                $componentGroup->reference = sprintf('layout:%s/%s', $owner->reference, $groupBuilder->getName());
                $componentGroup->addLayout($owner);
            } else {
                $componentGroup->reference = sprintf('page:%s/%s', $owner->reference ?? $owner->getTitle(), $groupBuilder->getName());
                $componentGroup->addPage($owner);
            }

            foreach ($groupBuilder->getAllowedClasses() as $class) {
                $componentGroup->addAllowedComponent(
                    $this->iriConverter->getIriFromResource($class)
                );
            }

            $this->componentGroupMap[spl_object_id($groupBuilder)] = $componentGroup;
            $this->manager->persist($componentGroup);
        }

        $this->manager->flush();
    }

    private function phaseThree(): void
    {
        foreach ($this->pageSpecs as $spec) {
            $page = $spec['builder']->getPage();
            if (null !== $spec['route']) {
                $route = $this->createExplicitRoute($spec['route'], $spec['routeName']);
                $route->setPage($page);
                $this->timestampedPersister->persistTimestampedFields($route, true);
                $this->manager->persist($route);
                if (null !== $spec['routeName']) {
                    $this->namedRoutes[$spec['routeName']] = $route;
                }
            } elseif (!$spec['isTemplate']) {
                $route = $this->routeGenerator->create($page);
                if (null !== $spec['routeName'] && null !== $page->getRoute()) {
                    $this->namedRoutes[$spec['routeName']] = $page->getRoute();
                }
            }
        }

        foreach ($this->pageDataSpecs as $spec) {
            $pageData = $spec['builder']->getPageData();
            if (null !== $spec['route']) {
                $route = $this->createExplicitRoute($spec['route'], $spec['routeName']);
                $route->setPageData($pageData);
                $this->timestampedPersister->persistTimestampedFields($route, true);
                $this->manager->persist($route);
                if (null !== $spec['routeName']) {
                    $this->namedRoutes[$spec['routeName']] = $route;
                }
            } else {
                $route = $this->routeGenerator->create($pageData);
                if (null !== $spec['routeName'] && null !== $pageData->getRoute()) {
                    $this->namedRoutes[$spec['routeName']] = $pageData->getRoute();
                }
            }
        }

        $this->manager->flush();
    }

    private function phaseFour(): void
    {
        $hasPositions = false;

        foreach ($this->layoutBuilders as $layoutBuilder) {
            foreach ($layoutBuilder->getGroupBuilders() as $groupBuilder) {
                if ($this->createPositions($groupBuilder)) {
                    $hasPositions = true;
                }
            }
        }

        foreach ($this->pageSpecs as $spec) {
            foreach ($spec['builder']->getGroupBuilders() as $groupBuilder) {
                if ($this->createPositions($groupBuilder)) {
                    $hasPositions = true;
                }
            }
        }

        if ($hasPositions) {
            $this->manager->flush();
        }
    }

    private function createPositions(GroupBuilder $groupBuilder): bool
    {
        $componentGroup = $this->componentGroupMap[spl_object_id($groupBuilder)] ?? null;
        if (null === $componentGroup) {
            return false;
        }

        $hasAny = false;

        foreach ($groupBuilder->getComponents() as $item) {
            $component = $item['component'];
            $position = new ComponentPosition();
            $position->sortValue = $item['sort'];
            $position->component = $component;
            $componentGroup->addComponentPosition($position);
            $this->manager->persist($component);
            $this->manager->persist($position);
            $hasAny = true;
        }

        foreach ($groupBuilder->getPageDataPositions() as $item) {
            $position = new ComponentPosition();
            $position->sortValue = $item['sort'];
            $position->pageDataProperty = $item['property'];
            $componentGroup->addComponentPosition($position);
            $this->manager->persist($position);
            $hasAny = true;
        }

        return $hasAny;
    }

    private function createExplicitRoute(string $path, ?string $name): Route
    {
        $route = new Route();
        $route->setPath($path);
        $route->setName($name ?? $this->deriveRouteName($path));

        return $route;
    }

    private function deriveRouteName(string $path): string
    {
        $slug = trim(str_replace('/', '-', $path), '-');

        return $slug ?: 'root';
    }
}
