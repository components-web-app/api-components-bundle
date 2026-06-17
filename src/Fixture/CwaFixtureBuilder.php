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

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
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
     * Each entry: ['builder' => PageBuilder, 'layoutRef' => string, 'route' => ?string, 'routeName' => ?string, 'isTemplate' => bool, 'type' => 'page'].
     *
     * @var array<string, array>
     */
    private array $pageSpecs = [];

    /**
     * Each entry: ['builder' => PageDataBuilder, 'templateRef' => ?string, 'route' => ?string, 'routeName' => ?string, 'type' => 'pageData'].
     *
     * @var array<array>
     */
    private array $pageDataSpecs = [];

    /**
     * All page and pageData specs in registration order (parents before children).
     * Used in phaseThree() to ensure parent routes are created before child routes.
     *
     * @var array<array>
     */
    private array $orderedRouteSpecs = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /** Maps spl_object_id(GroupBuilder) → ComponentGroup for use in phase 4 */
    private array $componentGroupMap = [];

    /** Tracks object IDs already passed to persist() to avoid cycles in persistWithAssociations() */
    private array $persistedEntities = [];

    /** Phases 1–3 run exactly once; phase 4 runs on every flush() call to pick up new positions */
    private bool $initialFlushDone = false;

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
            $spec = [
                'builder' => $builder,
                'layoutRef' => $layout,
                'route' => $route,
                'routeName' => $routeName,
                'isTemplate' => $isTemplate,
                'type' => 'page',
            ];
            $this->pageSpecs[$ref] = $spec;
            $this->orderedRouteSpecs[] = $spec;
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
        $spec = [
            'builder' => $builder,
            'templateRef' => $template,
            'route' => $route,
            'routeName' => $routeName,
            'type' => 'pageData',
        ];
        $this->pageDataSpecs[] = $spec;
        $this->orderedRouteSpecs[] = $spec;

        return $builder;
    }

    /**
     * Explicitly persist an entity and walk its owning-side associations to persist related objects.
     * Use this for app-specific entities that the builder doesn't manage (e.g. HtmlContent set on a PageData).
     * Does not rely on Doctrine cascade — every related object is persisted explicitly.
     */
    public function persist(object $entity): static
    {
        $this->persistWithAssociations($entity);

        return $this;
    }

    public function getRoute(string $routeName): Route
    {
        if (!isset($this->namedRoutes[$routeName])) {
            throw new \LogicException(\sprintf('Named route "%s" not found. Did you call flush() before getRoute()?', $routeName));
        }

        return $this->namedRoutes[$routeName];
    }

    /**
     * Phases 1–3 run exactly once (on first call).
     * Phase 4 runs every call to pick up positions added after the first flush (e.g. nav links added after routes exist).
     */
    public function flush(): void
    {
        if (!$this->initialFlushDone) {
            $this->phaseOne();
            $this->evaluateNested();
            $this->phaseTwo();
            $this->phaseThree();
            $this->initialFlushDone = true;
        }

        $this->phaseFour();
    }

    private function evaluateNested(): void
    {
        $existingPageDataCount = \count($this->pageDataSpecs);
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

        $newPageDataSpecs = \array_slice($this->pageDataSpecs, $existingPageDataCount);
        foreach ($newPageDataSpecs as $spec) {
            $pageData = $spec['builder']->getPageData();
            $this->timestampedPersister->persistTimestampedFields($pageData, true);
            $this->persistWithAssociations($pageData);
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
            $this->persistWithAssociations($page);
            foreach ($spec['builder']->getGroupBuilders() as $groupBuilder) {
                $this->createAndLinkComponentGroup($groupBuilder, $page);
            }
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
            $this->persistWithAssociations($layout);
            foreach ($layoutBuilder->getGroupBuilders() as $groupBuilder) {
                $this->createAndLinkComponentGroup($groupBuilder, $layout);
            }
        }

        foreach ($this->pageSpecs as $spec) {
            $page = $spec['builder']->getPage();
            $layoutBuilder = $this->layoutBuilders[$spec['layoutRef']] ?? null;
            if (null !== $layoutBuilder) {
                $page->layout = $layoutBuilder->getLayout();
            }
            $this->timestampedPersister->persistTimestampedFields($page, true);
            $this->persistWithAssociations($page);
            foreach ($spec['builder']->getGroupBuilders() as $groupBuilder) {
                $this->createAndLinkComponentGroup($groupBuilder, $page);
            }
        }

        foreach ($this->pageDataSpecs as $spec) {
            $pageData = $spec['builder']->getPageData();
            if (null !== $spec['templateRef'] && isset($this->pageSpecs[$spec['templateRef']])) {
                $pageData->page = $this->pageSpecs[$spec['templateRef']]['builder']->getPage();
            }
            $this->timestampedPersister->persistTimestampedFields($pageData, true);
            $this->persistWithAssociations($pageData);
        }

        $this->manager->flush();
    }

    private function createAndLinkComponentGroup(GroupBuilder $groupBuilder, Layout|Page $owner): void
    {
        $ownerIri = $this->iriConverter->getIriFromResource($owner);

        $componentGroup = new ComponentGroup();
        $componentGroup->location = $ownerIri;
        $componentGroup->reference = $groupBuilder->getName() . '_' . $ownerIri;

        foreach ($groupBuilder->getAllowedClasses() as $class) {
            $componentGroup->addAllowedComponent(
                $this->iriConverter->getIriFromResource(
                    $class,
                    UrlGeneratorInterface::ABS_PATH,
                    (new GetCollection())->withClass($class)
                )
            );
        }

        $this->timestampedPersister->persistTimestampedFields($componentGroup, true);
        // Add to the owning side BEFORE the first flush so Doctrine writes the join table.
        $owner->getComponentGroups()->add($componentGroup);
        // Sync the inverse side for in-memory consistency (Doctrine populates it from DB on load).
        if ($owner instanceof Layout) {
            $componentGroup->layouts->add($owner);
        } else {
            $componentGroup->pages->add($owner);
        }
        $this->componentGroupMap[spl_object_id($groupBuilder)] = $componentGroup;
        $this->manager->persist($componentGroup);
    }

    private function phaseTwo(): void
    {
        // ComponentGroups are created and linked in phaseOne/evaluateNested
        // while both the owner and group are still new entities, so Doctrine
        // writes the ManyToMany join table correctly on the first flush.
    }

    private function phaseThree(): void
    {
        foreach ($this->orderedRouteSpecs as $spec) {
            if ('page' === $spec['type']) {
                $page = $spec['builder']->getPage();
                if (null !== $page->getRoute()) {
                    continue;
                }
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
                    $this->manager->persist($route);
                    if (null !== $spec['routeName'] && null !== $page->getRoute()) {
                        $this->namedRoutes[$spec['routeName']] = $page->getRoute();
                    }
                }
            } else {
                $pageData = $spec['builder']->getPageData();
                if (null !== $pageData->getRoute()) {
                    continue;
                }
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
                    $this->manager->persist($route);
                    if (null !== $spec['routeName'] && null !== $pageData->getRoute()) {
                        $this->namedRoutes[$spec['routeName']] = $pageData->getRoute();
                    }
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

        foreach ($groupBuilder->getNewComponents() as $item) {
            $component = $item['component'];
            $position = new ComponentPosition();
            $position->sortValue = $item['sort'];
            $position->component = $component;
            $position->componentGroup = $componentGroup;
            $this->timestampedPersister->persistTimestampedFields($position, true);
            $this->persistWithAssociations($component);
            $this->manager->persist($position);
            $hasAny = true;
        }

        foreach ($groupBuilder->getNewPageDataPositions() as $item) {
            $position = new ComponentPosition();
            $position->sortValue = $item['sort'];
            $position->pageDataProperty = $item['property'];
            $position->componentGroup = $componentGroup;
            $this->timestampedPersister->persistTimestampedFields($position, true);
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

    /**
     * Persists an entity and recursively persists all owning-side associated objects.
     * Does not rely on Doctrine cascade — every object is persisted explicitly.
     * Uses spl_object_id tracking to prevent cycles.
     */
    private function persistWithAssociations(object $entity): void
    {
        $oid = spl_object_id($entity);
        if (isset($this->persistedEntities[$oid])) {
            return;
        }
        $this->persistedEntities[$oid] = true;
        $this->manager->persist($entity);

        try {
            $metadata = $this->manager->getClassMetadata($entity::class);
            foreach ($metadata->getAssociationNames() as $assocName) {
                if ($metadata->isAssociationInverseSide($assocName)) {
                    continue;
                }
                $related = $this->readProperty($entity, $assocName);
                if (null === $related) {
                    continue;
                }
                if (is_iterable($related)) {
                    foreach ($related as $item) {
                        if (is_object($item)) {
                            $this->persistWithAssociations($item);
                        }
                    }
                } else {
                    $this->persistWithAssociations($related);
                }
            }
        } catch (\Exception) {
            // Entity class not in Doctrine metadata (e.g. during unit tests with stubs)
        }
    }

    private function readProperty(object $entity, string $property): mixed
    {
        $class = new \ReflectionClass($entity);
        do {
            if ($class->hasProperty($property)) {
                $prop = $class->getProperty($property);

                return $prop->isInitialized($entity) ? $prop->getValue($entity) : null;
            }
        } while ($class = $class->getParentClass());

        return null;
    }
}
