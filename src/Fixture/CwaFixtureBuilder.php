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
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\EventListener\Console\ConsoleOutputListener;
use Silverback\ApiComponentsBundle\Fixture\Builder\ComponentBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\GroupBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\LayoutBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageDataBuilder;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;

class CwaFixtureBuilder
{
    private const string CREATED = CwaFixtureSummary::CREATED;
    private const string KEPT = CwaFixtureSummary::KEPT;
    private const string SKIPPED = CwaFixtureSummary::SKIPPED;

    private ?ObjectManager $manager = null;
    private ?AbstractPage $parentContext = null;
    private CwaFixtureSummary $summary;

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

    /** @var array<array> */
    private array $orderedRouteSpecs = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /** @var array<string, string> path keyed by name, for routes created by this builder */
    private array $createdRouteNames = [];

    /** @var array<int, \Closure> */
    private array $afterRoutesCallbacks = [];

    /** @var array<int, array{path: string, to: string, name: ?string, route: ?Route}> */
    private array $redirectSpecs = [];

    /** @var array<int, ComponentBuilder> keyed by spl_object_id of the AbstractComponent */
    private array $componentBuilders = [];

    /** @var array<int, ComponentGroup> keyed by spl_object_id of the GroupBuilder */
    private array $componentGroupMap = [];

    /** @var array<string, ComponentGroup> keyed by full reference, for groups with a location reference */
    private array $namedComponentGroups = [];

    /** @var array<int, true> */
    private array $persistedEntities = [];

    /** @var array<int, true> */
    private array $evaluatedNestedIds = [];

    /** @var array<int, true> */
    private array $firedCallbackIds = [];

    /** @var array<int, string> keyed by spl_object_id of the LayoutBuilder, PageBuilder or PageDataBuilder */
    private array $decisions = [];

    /** @var array<int, object> keyed by spl_object_id of the scaffold entity */
    private array $resolved = [];

    /** @var array<int, true> keyed by spl_object_id of entities that were already in the database */
    private array $existing = [];

    /** @var array<int, true> keyed by spl_object_id of scaffold objects that must never be persisted */
    private array $skipped = [];

    /** @var array<int, GroupBuilder> */
    private array $skippedGroupBuilders = [];

    /** @var array<int, true> keyed by spl_object_id of the route spec's builder */
    private array $routedSpecs = [];

    private bool $hasPendingLinks = false;

    private bool $flushing = false;

    /** @var array<int, object> */
    private array $pendingPersists = [];

    public function __construct(
        private readonly TimestampedDataPersister $timestampedPersister,
        private readonly RouteGeneratorInterface $routeGenerator,
        private readonly IriConverterInterface $iriConverter,
        private readonly ?UploadableFileManager $uploadableFileManager = null,
        private readonly ?UploadableAttributeReaderInterface $uploadableAttributeReader = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ConsoleOutputListener $consoleOutput = null,
    ) {
        $this->summary = new CwaFixtureSummary();
    }

    public function withManager(ObjectManager $manager): static
    {
        $this->manager = $manager;
        $this->summary = new CwaFixtureSummary();

        return $this;
    }

    public function getSummary(): CwaFixtureSummary
    {
        return $this->summary;
    }

    public function reportSummary(): void
    {
        $message = (string) $this->summary;
        $this->logger?->info($message);
        $this->consoleOutput?->getOutput()?->writeln(\sprintf('  <comment>></comment> <info>%s</info>', $message));
    }

    public function layout(string $ref, string $uiComponent, ?array $uiClassNames = null): LayoutBuilder
    {
        if (!isset($this->layoutBuilders[$ref])) {
            $layout = new Layout();
            $layout->reference = $ref;
            $layout->uiComponent = '' !== $uiComponent ? 'CwaLayout' . $uiComponent : null;
            $layout->uiClassNames = $uiClassNames;
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
        ?array $uiClassNames = null,
    ): PageBuilder {
        if (!isset($this->pageSpecs[$ref])) {
            $page = new Page();
            $page->reference = $ref;
            $page->uiComponent = '' !== $uiComponent ? 'CwaPage' . $uiComponent : null;
            $page->uiClassNames = $uiClassNames;
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

    public function persist(object $entity): static
    {
        if (!$this->flushing) {
            $this->pendingPersists[spl_object_id($entity)] = $entity;

            return $this;
        }
        if ($this->reachesSkipped($entity)) {
            $this->markSkipped($entity);
            $this->note(self::SKIPPED, $entity instanceof AbstractComponent ? CwaFixtureSummary::COMPONENT : CwaFixtureSummary::ENTITY, $entity::class, 'linked to skipped content');

            return $this;
        }
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

    public function afterRoutes(\Closure $callback): static
    {
        $this->afterRoutesCallbacks[] = $callback;

        return $this;
    }

    public function redirect(string $path, string $to, ?string $name = null): static
    {
        $this->redirectSpecs[] = ['path' => $path, 'to' => $to, 'name' => $name, 'route' => null];

        return $this;
    }

    public function getRoute(string $routeName): Route
    {
        if (!isset($this->namedRoutes[$routeName])) {
            throw new \LogicException(\sprintf('Named route "%s" not found. Did you call flush() before getRoute()?', $routeName));
        }

        return $this->namedRoutes[$routeName];
    }

    public function flush(): void
    {
        $this->flushing = true;
        try {
            $this->phaseOne();
            $this->evaluateNested();
            $this->phaseThree();
            $this->phaseThreePointFive();
            $this->phaseFour();
        } finally {
            $this->flushing = false;
        }
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
            if (self::SKIPPED === ($this->decisions[$oid] ?? null)) {
                continue;
            }
            $beforePageRefs = array_keys($this->pageSpecs);
            $this->parentContext = $this->resolve($spec['builder']->getPageData());
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
            $this->parentContext = $this->resolve($spec['builder']->getPage());
            $closure($this);
            $this->parentContext = null;
        }

        $hasNew = false;

        foreach (array_diff_key($this->pageSpecs, array_flip($existingPageRefs)) as $ref => $spec) {
            $this->processPage($ref, $spec);
            $hasNew = true;
        }

        foreach (\array_slice($this->pageDataSpecs, $existingPageDataCount) as $spec) {
            $this->processPageData($spec);
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

        foreach ($this->layoutBuilders as $ref => $layoutBuilder) {
            $layout = $layoutBuilder->getLayout();
            if (!isset($this->decisions[spl_object_id($layoutBuilder)])) {
                $this->decide($layoutBuilder, $layout, $this->findOne(Layout::class, ['reference' => $ref]), CwaFixtureSummary::LAYOUT, (string) $ref);
            }
            if (self::CREATED === $this->decisions[spl_object_id($layoutBuilder)]) {
                if (!isset($this->persistedEntities[spl_object_id($layout)])) {
                    $this->timestampedPersister->persistTimestampedFields($layout, true);
                }
                $this->persistWithAssociations($layout);
            }
            foreach ($layoutBuilder->getGroupBuilders() as $groupBuilder) {
                $this->createAndLinkComponentGroup($groupBuilder, $this->resolve($layout));
            }
        }

        foreach ($this->pageSpecs as $ref => $spec) {
            $this->processPage($ref, $spec);
        }

        foreach ($this->pageDataSpecs as $spec) {
            $this->processPageData($spec);
        }

        foreach ($this->componentBuilders as $componentBuilder) {
            $component = $componentBuilder->getComponent();
            if ($this->reachesSkipped($component)) {
                $this->markSkipped($component);
                continue;
            }
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

        foreach ($this->pendingPersists as $oid => $entity) {
            unset($this->pendingPersists[$oid]);
            $this->persist($entity);
        }

        $hadNew = \count($this->persistedEntities) > $entityCountBefore
            || \count($this->componentGroupMap) + \count($this->namedComponentGroups) > $groupCountBefore
            || $this->hasPendingLinks;
        $this->hasPendingLinks = false;

        if ($hadNew) {
            $this->manager->flush();
        }
    }

    private function processPage(string $ref, array $spec): void
    {
        $builder = $spec['builder'];
        $page = $builder->getPage();
        if (!isset($this->decisions[spl_object_id($builder)])) {
            $existing = $this->findOne(Page::class, ['reference' => $ref]);
            $this->decide($builder, $page, $existing, CwaFixtureSummary::PAGE, $ref);
            if ($existing instanceof Page) {
                $builder->setExistingPage($existing);
            }
        }
        if (self::CREATED === $this->decisions[spl_object_id($builder)]) {
            $layoutBuilder = $this->layoutBuilders[$spec['layoutRef']] ?? null;
            if (null !== $layoutBuilder) {
                $page->layout = $this->resolve($layoutBuilder->getLayout());
            }
            if (!isset($this->persistedEntities[spl_object_id($page)])) {
                $this->timestampedPersister->persistTimestampedFields($page, true);
            }
            $this->persistWithAssociations($page);
        }
        foreach ($builder->getGroupBuilders() as $groupBuilder) {
            $this->createAndLinkComponentGroup($groupBuilder, $this->resolve($page));
        }
    }

    private function processPageData(array $spec): void
    {
        $builder = $spec['builder'];
        $pageData = $builder->getPageData();
        if (!isset($this->decisions[spl_object_id($builder)])) {
            $this->decidePageData($spec);
        }
        if (self::CREATED !== $this->decisions[spl_object_id($builder)]) {
            return;
        }
        if (null !== $spec['templateRef'] && isset($this->pageSpecs[$spec['templateRef']])) {
            $pageData->page = $this->resolve($this->pageSpecs[$spec['templateRef']]['builder']->getPage());
        }
        if (!isset($this->persistedEntities[spl_object_id($pageData)])) {
            $this->timestampedPersister->persistTimestampedFields($pageData, true);
        }
        $this->persistWithAssociations($pageData);
    }

    private function decidePageData(array $spec): void
    {
        $builder = $spec['builder'];
        $pageData = $builder->getPageData();
        $label = $pageData->getTitle() ?? $pageData::class;

        if ($this->reachesSkipped($pageData)) {
            $this->decisions[spl_object_id($builder)] = self::SKIPPED;
            $this->skipGraph($pageData);
            $this->note(self::SKIPPED, CwaFixtureSummary::PAGE_DATA, $label, 'parent skipped');

            return;
        }

        if ($builder->isWithoutRoute() && null === $spec['route']) {
            $template = null !== $spec['templateRef'] ? ($this->pageSpecs[$spec['templateRef']]['builder'] ?? null) : null;
            $parent = $pageData->getParentPage() ?? $pageData->getParentPageData();
            if ((null !== $template && self::CREATED === ($this->decisions[spl_object_id($template)] ?? null))
                || (null !== $parent && $this->isCreatedInThisRun($parent))) {
                $this->decide($builder, $pageData, null, CwaFixtureSummary::PAGE_DATA, $label);

                return;
            }
            $this->decisions[spl_object_id($builder)] = self::SKIPPED;
            $this->skipGraph($pageData);
            $this->note(self::SKIPPED, CwaFixtureSummary::PAGE_DATA, $label, 'unidentifiable');

            return;
        }

        $parent = $pageData->getParentPage() ?? $pageData->getParentPageData();
        if (null === $spec['route'] && null !== $parent && (null === $parent->getRoute() || $this->isCreatedInThisRun($parent))) {
            $this->decide($builder, $pageData, null, CwaFixtureSummary::PAGE_DATA, $label);

            return;
        }

        $path = $spec['route'] ?? $this->routeGenerator->generatePath($pageData);
        $existing = $this->findOne(Route::class, ['path' => $path])?->getPageData();
        if (!$existing instanceof $pageData) {
            $existing = null;
        }
        $this->decide($builder, $pageData, $existing, CwaFixtureSummary::PAGE_DATA, $label);
        if (null !== $existing) {
            $builder->setExistingPageData($existing);
        }
    }

    private function isCreatedInThisRun(object $entity): bool
    {
        return isset($this->persistedEntities[spl_object_id($entity)]) && !isset($this->existing[spl_object_id($entity)]);
    }

    private function decide(object $builder, object $scaffold, ?object $existing, string $type, string $label): void
    {
        if (null === $existing) {
            $this->decisions[spl_object_id($builder)] = self::CREATED;
            $this->note(self::CREATED, $type, $label);

            return;
        }
        $this->decisions[spl_object_id($builder)] = self::KEPT;
        $this->resolved[spl_object_id($scaffold)] = $existing;
        $this->existing[spl_object_id($existing)] = true;
        $this->skipGraph($scaffold);
        $this->note(self::KEPT, $type, $label);
    }

    private function createAndLinkComponentGroup(GroupBuilder $groupBuilder, Layout|Page|AbstractComponent $owner): void
    {
        $groupBuilderId = spl_object_id($groupBuilder);
        if (isset($this->componentGroupMap[$groupBuilderId]) || isset($this->skippedGroupBuilders[$groupBuilderId])) {
            return;
        }

        $ownerIri = $this->iriConverter->getIriFromResource($owner);
        $locationRef = $groupBuilder->getLocationReference() ?? $ownerIri;
        $fullRef = $groupBuilder->getName() . '_' . $locationRef;

        $componentGroup = null !== $groupBuilder->getLocationReference() ? ($this->namedComponentGroups[$fullRef] ?? null) : null;
        if (null === $componentGroup) {
            $existing = $this->findOne(ComponentGroup::class, ['reference' => $fullRef]);
            if ($existing instanceof ComponentGroup) {
                $componentGroup = $existing;
                $this->existing[spl_object_id($existing)] = true;
                if (null !== $groupBuilder->getLocationReference()) {
                    $this->namedComponentGroups[$fullRef] = $existing;
                }
                $this->note(self::KEPT, CwaFixtureSummary::GROUP, $fullRef);
            }
        }

        if (null !== $componentGroup && isset($this->existing[spl_object_id($componentGroup)])) {
            if ($this->linkGroup($componentGroup, $owner)) {
                $this->hasPendingLinks = true;
            }
            $this->skippedGroupBuilders[$groupBuilderId] = $groupBuilder;
            $this->syncSkipped();
            if ([] !== $groupBuilder->getComponents() || [] !== $groupBuilder->getPageDataPositions()) {
                $this->logger?->notice(\sprintf('The group `%s` already exists, so its %d scaffold positions were not created.', $fullRef, \count($groupBuilder->getComponents()) + \count($groupBuilder->getPageDataPositions())));
            }

            return;
        }

        if (null === $componentGroup) {
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
            $this->note(self::CREATED, CwaFixtureSummary::GROUP, $fullRef);

            if (null !== $groupBuilder->getLocationReference()) {
                $this->namedComponentGroups[$fullRef] = $componentGroup;
            }
        }

        $this->linkGroup($componentGroup, $owner);
        $this->componentGroupMap[$groupBuilderId] = $componentGroup;
    }

    private function linkGroup(ComponentGroup $componentGroup, Layout|Page|AbstractComponent $owner): bool
    {
        if ($owner->getComponentGroups()->contains($componentGroup)) {
            return false;
        }
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

        return true;
    }

    private function phaseThree(): void
    {
        $hadNew = false;

        foreach ($this->orderedRouteSpecs as $spec) {
            $builder = $spec['builder'];
            $builderId = spl_object_id($builder);
            if (isset($this->routedSpecs[$builderId])) {
                continue;
            }
            $decision = $this->decisions[$builderId] ?? self::CREATED;
            $entity = 'page' === $spec['type'] ? $builder->getPage() : $builder->getPageData();
            if (self::SKIPPED === $decision) {
                $this->routedSpecs[$builderId] = true;
                continue;
            }
            if (self::KEPT === $decision) {
                $this->routedSpecs[$builderId] = true;
                $this->registerNamedRoute($spec, $this->resolve($entity));
                continue;
            }
            if (null !== $entity->getRoute()) {
                continue;
            }
            if (null === $spec['route'] && $builder->isWithoutRoute()) {
                $this->routedSpecs[$builderId] = true;
                continue;
            }
            if (null !== $spec['route']) {
                $name = $spec['routeName'] ?? $this->deriveRouteName($spec['route']);
                if (null !== $this->findOne(Route::class, ['path' => $spec['route']]) || null !== $this->findOne(Route::class, ['name' => $name])) {
                    $this->routedSpecs[$builderId] = true;
                    $this->note(self::SKIPPED, CwaFixtureSummary::ROUTE, $spec['route'], 'path in use');
                    $this->registerNamedRoute($spec, null);
                    continue;
                }
                $route = $this->createExplicitRoute($spec['route'], $spec['routeName']);
                if ($entity instanceof Page) {
                    $route->setPage($entity);
                } else {
                    $route->setPageData($entity);
                }
                $this->timestampedPersister->persistTimestampedFields($route, true);
                $this->manager->persist($route);
                $this->createdRouteNames[$route->getName()] = $route->getPath();
                if (null !== $spec['routeName']) {
                    $this->namedRoutes[$spec['routeName']] = $route;
                }
                $this->note(self::CREATED, CwaFixtureSummary::ROUTE, $spec['route']);
                $hadNew = true;
            } elseif ('pageData' === $spec['type'] || !$spec['isTemplate']) {
                $route = $this->routeGenerator->create($entity);
                $this->manager->persist($route);
                if (null !== $entity->getRoute()) {
                    $this->createdRouteNames[$entity->getRoute()->getName()] = $entity->getRoute()->getPath();
                }
                if (null !== $spec['routeName'] && null !== $entity->getRoute()) {
                    $this->namedRoutes[$spec['routeName']] = $entity->getRoute();
                }
                $this->note(self::CREATED, CwaFixtureSummary::ROUTE, (string) $route->getPath());
                $hadNew = true;
            }
            $this->applyLiveAt($builder, $entity);
        }

        foreach ($this->redirectSpecs as $index => $spec) {
            if (null !== $spec['route']) {
                continue;
            }
            $existing = $this->findOne(Route::class, ['path' => $spec['path']]);
            if ($existing instanceof Route) {
                $this->existing[spl_object_id($existing)] = true;
                $this->namedRoutes[$spec['name'] ?? $existing->getName()] = $existing;
                $this->redirectSpecs[$index]['route'] = $existing;
                $this->note(self::KEPT, CwaFixtureSummary::ROUTE, $spec['path']);
                continue;
            }
            if (null !== $spec['name']) {
                if (isset($this->createdRouteNames[$spec['name']])) {
                    throw new \LogicException(\sprintf('The redirect "%s" cannot be named "%s", because the route "%s" already has that name.', $spec['path'], $spec['name'], $this->createdRouteNames[$spec['name']]));
                }
                $named = $this->findOne(Route::class, ['name' => $spec['name']]);
                if ($named instanceof Route) {
                    $this->existing[spl_object_id($named)] = true;
                    $this->namedRoutes[$spec['name']] = $named;
                    $this->redirectSpecs[$index]['route'] = $named;
                    $this->note(self::SKIPPED, CwaFixtureSummary::ROUTE, $spec['path'], 'name in use');
                    continue;
                }
            }
            $route = $this->createExplicitRoute($spec['path'], $spec['name'] ?? $this->uniqueRouteName($this->deriveRouteName($spec['path'])));
            $route->setRedirect($this->getRoute($spec['to']));
            $this->timestampedPersister->persistTimestampedFields($route, true);
            $this->manager->persist($route);
            $this->createdRouteNames[$route->getName()] = $route->getPath();
            $this->namedRoutes[$route->getName()] = $route;
            $this->redirectSpecs[$index]['route'] = $route;
            $this->note(self::CREATED, CwaFixtureSummary::ROUTE, $spec['path']);
            $hadNew = true;
        }

        if ($hadNew) {
            $this->manager->flush();
        }
    }

    private function registerNamedRoute(array $spec, ?AbstractPage $entity): void
    {
        if (null === $spec['routeName']) {
            return;
        }
        $route = null !== $spec['route'] ? $this->findOne(Route::class, ['path' => $spec['route']]) : null;
        $route ??= $entity?->getRoute() ?? $this->findOne(Route::class, ['name' => $spec['routeName']]);
        if (!$route instanceof Route) {
            $this->logger?->notice(\sprintf('The named route `%s` was not found, so getRoute() cannot return it.', $spec['routeName']));

            return;
        }
        $this->existing[spl_object_id($route)] = true;
        $this->namedRoutes[$spec['routeName']] = $route;
        $this->logger?->debug(\sprintf('Registered the existing route `%s` as `%s`.', $route->getPath(), $spec['routeName']));
    }

    private function applyLiveAt(PageBuilder|PageDataBuilder $builder, AbstractPage $page): void
    {
        if ($builder->hasLiveAt()) {
            $page->getRoute()?->setLiveAt($builder->getLiveAt());
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
            if (self::CREATED !== ($this->decisions[$oid] ?? self::CREATED)) {
                $this->logger?->debug(\sprintf('onRoutesCreated was not called for the page data `%s`, which was not created.', $spec['builder']->getPageData()->getTitle()));
                continue;
            }
            $childBuilders = array_values(array_filter(array_map(
                fn ($ref) => $this->pageSpecs[$ref]['builder'] ?? null,
                $spec['builder']->getChildPageRefs(),
            )));
            $cb($childBuilders);
            $hasChanges = true;
        }

        foreach ($this->afterRoutesCallbacks as $index => $callback) {
            unset($this->afterRoutesCallbacks[$index]);
            $callback($this);
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
        if (isset($this->skippedGroupBuilders[spl_object_id($groupBuilder)])) {
            $this->syncSkipped();
            $groupBuilder->getNewComponents();
            $groupBuilder->getNewPageDataPositions();

            return false;
        }

        $componentGroup = $this->componentGroupMap[spl_object_id($groupBuilder)] ?? null;
        if (null === $componentGroup) {
            return false;
        }

        $hasAny = false;

        foreach ($groupBuilder->getNewComponents() as $item) {
            $component = $item['component'];
            if ($this->reachesSkipped($component)) {
                $this->markSkipped($component);
                $this->note(self::SKIPPED, CwaFixtureSummary::COMPONENT, $component::class, 'linked to skipped content');
                continue;
            }
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

    private function uniqueRouteName(string $name): string
    {
        $candidate = $name;
        for ($suffix = 1; isset($this->createdRouteNames[$candidate]) || null !== $this->findOne(Route::class, ['name' => $candidate]); ++$suffix) {
            $candidate = $name . '-' . $suffix;
        }

        return $candidate;
    }

    private function deriveRouteName(string $path): string
    {
        $slug = trim(str_replace('/', '-', $path), '-');

        return $slug ?: 'root';
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function resolve(object $entity): object
    {
        return $this->resolved[spl_object_id($entity)] ?? $entity;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function findOne(string $class, array $criteria): ?object
    {
        return $this->manager->getRepository($class)->findOneBy($criteria);
    }

    private function note(string $outcome, string $type, string $label, string $reason = ''): void
    {
        $this->summary->record($outcome, $type, $reason);
        $message = \sprintf('%s %s `%s`%s', $outcome, $type, $label, '' === $reason ? '' : ' (' . $reason . ')');
        if (self::CREATED === $outcome) {
            $this->logger?->debug($message);

            return;
        }
        $this->logger?->notice($message);
    }

    private function isSettled(object $entity): bool
    {
        $oid = spl_object_id($entity);

        return isset($this->persistedEntities[$oid]) || isset($this->existing[$oid]) || $this->manager->contains($entity);
    }

    private function reachesSkipped(object $entity): bool
    {
        $this->syncSkipped();
        $visited = [];

        return $this->walkForSkipped($entity, $visited);
    }

    /**
     * @param array<int, true> $visited
     */
    private function walkForSkipped(object $entity, array &$visited): bool
    {
        $oid = spl_object_id($entity);
        if (isset($this->skipped[$oid])) {
            return true;
        }
        if (isset($visited[$oid]) || $this->isSettled($entity)) {
            return false;
        }
        $visited[$oid] = true;
        foreach ($this->owningRelations($entity) as $related) {
            if ($this->walkForSkipped($related, $visited)) {
                return true;
            }
        }

        return false;
    }

    private function skipGraph(object $entity): void
    {
        $oid = spl_object_id($entity);
        if (isset($this->skipped[$oid])) {
            return;
        }
        $this->markSkipped($entity);
        foreach ($this->owningRelations($entity) as $related) {
            if (!$this->isSettled($related)) {
                $this->skipGraph($related);
            }
        }
    }

    private function markSkipped(object $entity): void
    {
        $this->skipped[spl_object_id($entity)] = true;
        foreach (($this->componentBuilders[spl_object_id($entity)] ?? null)?->getGroupBuilders() ?? [] as $groupBuilder) {
            $this->skippedGroupBuilders[spl_object_id($groupBuilder)] = $groupBuilder;
        }
        $this->syncSkipped();
    }

    private function syncSkipped(): void
    {
        do {
            $changed = false;
            foreach ($this->skippedGroupBuilders as $groupBuilder) {
                foreach ($groupBuilder->getComponents() as $item) {
                    $oid = spl_object_id($item['component']);
                    if (isset($this->skipped[$oid])) {
                        continue;
                    }
                    $this->skipped[$oid] = true;
                    foreach (($this->componentBuilders[$oid] ?? null)?->getGroupBuilders() ?? [] as $owned) {
                        $this->skippedGroupBuilders[spl_object_id($owned)] = $owned;
                    }
                    $changed = true;
                }
            }
        } while ($changed);
    }

    private function persistWithAssociations(object $entity): void
    {
        $oid = spl_object_id($entity);
        if (isset($this->persistedEntities[$oid]) || isset($this->existing[$oid])) {
            return;
        }
        $wasManaged = $this->manager->contains($entity);
        $this->persistedEntities[$oid] = true;
        $this->manager->persist($entity);
        $this->persistUploadedFile($entity);
        if ($entity instanceof AbstractComponent && !$wasManaged) {
            $this->note(self::CREATED, CwaFixtureSummary::COMPONENT, $entity::class);
        }

        foreach ($this->owningRelations($entity) as $related) {
            $this->persistWithAssociations($related);
        }
    }

    /**
     * @return list<object>
     */
    private function owningRelations(object $entity): array
    {
        $related = [];
        try {
            $metadata = $this->manager->getClassMetadata($entity::class);
            foreach ($metadata->getAssociationNames() as $assocName) {
                if ($metadata->isAssociationInverseSide($assocName)) {
                    continue;
                }
                $value = $this->readProperty($entity, $assocName);
                if (null === $value) {
                    continue;
                }
                if (is_iterable($value)) {
                    foreach ($value as $item) {
                        if (\is_object($item)) {
                            $related[] = $item;
                        }
                    }
                } elseif (\is_object($value)) {
                    $related[] = $value;
                }
            }
        } catch (\Exception) {
        }

        return $related;
    }

    private function persistUploadedFile(object $entity): void
    {
        if (null === $this->uploadableFileManager
            || null === $this->uploadableAttributeReader
            || !$this->uploadableAttributeReader->isConfigured($entity)) {
            return;
        }

        $this->uploadableFileManager->persistFiles($entity);
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
