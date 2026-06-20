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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Builder\ComponentBuilder;
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

    /** @var array<int, ComponentBuilder> keyed by spl_object_id of the AbstractComponent */
    private array $componentBuilders = [];

    /** Maps spl_object_id(GroupBuilder) → ComponentGroup for use in phase 4 */
    private array $componentGroupMap = [];

    /** ComponentGroups keyed by their full reference, for deduplication when locationReference is set */
    private array $namedComponentGroups = [];

    /** Tracks object IDs already passed to persist() to avoid cycles in persistWithAssociations() */
    private array $persistedEntities = [];

    /** @var array<int, true> keyed by spl_object_id(PageDataBuilder|PageBuilder) — prevents re-evaluating nested closures */
    private array $evaluatedNestedIds = [];

    /** @var array<int, true> keyed by spl_object_id(PageDataBuilder) — prevents re-firing onRoutesCreated callbacks */
    private array $firedCallbackIds = [];

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

    public function component(AbstractComponent $component): ComponentBuilder
    {
        $oid = spl_object_id($component);
        if (!isset($this->componentBuilders[$oid])) {
            $this->componentBuilders[$oid] = new ComponentBuilder($component);
        }

        return $this->componentBuilders[$oid];
    }

    public function getRoute(string $routeName): Route
    {
        if (!isset($this->namedRoutes[$routeName])) {
            throw new \LogicException(\sprintf('Named route "%s" not found. Did you call flush() before getRoute()?', $routeName));
        }

        return $this->namedRoutes[$routeName];
    }

    /**
     * All phases run on every call; each phase is idempotent and skips already-processed work.
     * Phase 4 always picks up positions added since the previous call (e.g. nav links added after routes exist).
     */
    public function flush(): void
    {
        $this->phaseOne();
        $this->evaluateNested();
        $this->phaseThree();
        $this->phaseThreePointFive();
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
            $oid = spl_object_id($spec['builder']);
            if (isset($this->evaluatedNestedIds[$oid])) {
                continue;
            }
            $this->evaluatedNestedIds[$oid] = true;
            $beforePageRefs = array_keys($this->pageSpecs);
            $this->parentContext = $spec['builder']->getPageData();
            $closure($this);
            $this->parentContext = null;
            $addedRefs = array_values(array_diff(array_keys($this->pageSpecs), $beforePageRefs));
            $spec['builder']->setChildPageRefs($addedRefs);
        }

        foreach ($this->pageSpecs as $spec) {
            $closure = $spec['builder']->getNestedClosure();
            if (null === $closure) {
                continue;
            }
            $oid = spl_object_id($spec['builder']);
            if (isset($this->evaluatedNestedIds[$oid])) {
                continue;
            }
            $this->evaluatedNestedIds[$oid] = true;
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
        $entityCountBefore = \count($this->persistedEntities);
        $groupCountBefore = \count($this->componentGroupMap) + \count($this->namedComponentGroups);

        foreach ($this->layoutBuilders as $layoutBuilder) {
            $layout = $layoutBuilder->getLayout();
            if (!isset($this->persistedEntities[spl_object_id($layout)])) {
                $this->timestampedPersister->persistTimestampedFields($layout, true);
            }
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
            if (!isset($this->persistedEntities[spl_object_id($page)])) {
                $this->timestampedPersister->persistTimestampedFields($page, true);
            }
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
            if (!isset($this->persistedEntities[spl_object_id($pageData)])) {
                $this->timestampedPersister->persistTimestampedFields($pageData, true);
            }
            $this->persistWithAssociations($pageData);
        }

        foreach ($this->componentBuilders as $componentBuilder) {
            $component = $componentBuilder->getComponent();
            if (!isset($this->persistedEntities[spl_object_id($component)])) {
                if ($this->timestampedPersister->isConfigured($component)) {
                    $this->timestampedPersister->persistTimestampedFields($component, true);
                }
            }
            $this->persistWithAssociations($component);
            foreach ($componentBuilder->getGroupBuilders() as $groupBuilder) {
                $this->createAndLinkComponentGroup($groupBuilder, $component);
            }
        }

        $hadNew = \count($this->persistedEntities) > $entityCountBefore
            || \count($this->componentGroupMap) + \count($this->namedComponentGroups) > $groupCountBefore;

        if ($hadNew) {
            $this->manager->flush();
        }
    }

    private function createAndLinkComponentGroup(GroupBuilder $groupBuilder, Layout|Page|AbstractComponent $owner): void
    {
        if (isset($this->componentGroupMap[spl_object_id($groupBuilder)])) {
            return;
        }

        $ownerIri = $this->iriConverter->getIriFromResource($owner);
        $locationRef = $groupBuilder->getLocationReference() ?? $ownerIri;
        $fullRef = $groupBuilder->getName() . '_' . $locationRef;

        if (null !== $groupBuilder->getLocationReference() && isset($this->namedComponentGroups[$fullRef])) {
            $componentGroup = $this->namedComponentGroups[$fullRef];
        } else {
            $componentGroup = new ComponentGroup();
            $componentGroup->location = $ownerIri;
            $componentGroup->reference = $fullRef;

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
            $this->manager->persist($componentGroup);

            if (null !== $groupBuilder->getLocationReference()) {
                $this->namedComponentGroups[$fullRef] = $componentGroup;
            }
        }

        // Add to the owning side BEFORE the first flush so Doctrine writes the join table.
        // Sync the inverse side for in-memory consistency (Doctrine populates it from DB on load).
        if ($owner instanceof Layout) {
            $owner->getComponentGroups()->add($componentGroup);
            $componentGroup->layouts->add($owner);
        } elseif ($owner instanceof Page) {
            $owner->getComponentGroups()->add($componentGroup);
            $componentGroup->pages->add($owner);
        } else {
            $owner->addComponentGroup($componentGroup);
            $componentGroup->components->add($owner);
        }

        $this->componentGroupMap[spl_object_id($groupBuilder)] = $componentGroup;
    }

    private function phaseThree(): void
    {
        $hadNew = false;

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
                    $hadNew = true;
                } elseif (!$spec['isTemplate']) {
                    $route = $this->routeGenerator->create($page);
                    $this->manager->persist($route);
                    if (null !== $spec['routeName'] && null !== $page->getRoute()) {
                        $this->namedRoutes[$spec['routeName']] = $page->getRoute();
                    }
                    $hadNew = true;
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
                    $hadNew = true;
                } else {
                    $route = $this->routeGenerator->create($pageData);
                    $this->manager->persist($route);
                    if (null !== $spec['routeName'] && null !== $pageData->getRoute()) {
                        $this->namedRoutes[$spec['routeName']] = $pageData->getRoute();
                    }
                    $hadNew = true;
                }
            }
        }

        if ($hadNew) {
            $this->manager->flush();
        }
    }

    private function phaseThreePointFive(): void
    {
        $hasChanges = false;

        foreach ($this->pageDataSpecs as $spec) {
            $cb = $spec['builder']->getOnRoutesCreated();
            if (null === $cb) {
                continue;
            }
            $oid = spl_object_id($spec['builder']);
            if (isset($this->firedCallbackIds[$oid])) {
                continue;
            }
            $this->firedCallbackIds[$oid] = true;
            $childBuilders = array_values(array_filter(array_map(
                fn ($ref) => $this->pageSpecs[$ref]['builder'] ?? null,
                $spec['builder']->getChildPageRefs(),
            )));
            $cb($childBuilders);
            $hasChanges = true;
        }

        if ($hasChanges) {
            $this->manager->flush();
        }
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

        foreach ($this->componentBuilders as $componentBuilder) {
            foreach ($componentBuilder->getGroupBuilders() as $groupBuilder) {
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
            if (!isset($this->persistedEntities[spl_object_id($component)])
                && $this->timestampedPersister->isConfigured($component)) {
                $this->timestampedPersister->persistTimestampedFields($component, true);
            }
            $this->timestampedPersister->persistTimestampedFields($position, true);
            $this->persistWithAssociations($component);
            $this->manager->persist($position);
            $hasAny = true;
        }

        foreach ($groupBuilder->getNewPageDataPositions() as $item) {
            $position = new ComponentPosition();
            $position->sortValue = $item['sort'];
            $position->pageDataProperty = $item['property'];
            $position->pageDataClass = $item['class'];
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
                        if (\is_object($item)) {
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
