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
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
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
use Silverback\ApiComponentsBundle\Fixture\Builder\IriLink;
use Silverback\ApiComponentsBundle\Fixture\Builder\LayoutBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageDataBuilder;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Utility\RouteName;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\InvalidTypeException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Service\ResetInterface;

class CwaFixtureBuilder implements ResetInterface
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

    /** @var array<int, array{path: string, to: string|PageBuilder|PageDataBuilder, name: ?string, route: ?Route}> */
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

    /** @var array<int, int> keyed by spl_object_id of the ComponentBuilder or PageDataBuilder */
    private array $appliedRouteLinks = [];

    /** @var array<int, int> */
    private array $appliedIriLinks = [];

    private bool $hasPendingLinks = false;

    private bool $flushing = false;

    /** @var array<int, object> */
    private array $pendingPersists = [];

    private readonly PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        private readonly TimestampedDataPersister $timestampedPersister,
        private readonly RouteGeneratorInterface $routeGenerator,
        private readonly IriConverterInterface $iriConverter,
        private readonly ?UploadableFileManager $uploadableFileManager = null,
        private readonly ?UploadableAttributeReaderInterface $uploadableAttributeReader = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ConsoleOutputListener $consoleOutput = null,
        private readonly ?PublishableAttributeReader $publishableAttributeReader = null,
    ) {
        $this->summary = new CwaFixtureSummary();
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function withManager(ObjectManager $manager): static
    {
        $this->reset();
        $this->manager = $manager;

        return $this;
    }

    public function reset(): void
    {
        $this->manager = null;
        $this->parentContext = null;
        $this->summary = new CwaFixtureSummary();
        $this->layoutBuilders = [];
        $this->pageSpecs = [];
        $this->pageDataSpecs = [];
        $this->orderedRouteSpecs = [];
        $this->namedRoutes = [];
        $this->createdRouteNames = [];
        $this->afterRoutesCallbacks = [];
        $this->appliedRouteLinks = [];
        $this->appliedIriLinks = [];
        $this->redirectSpecs = [];
        $this->componentBuilders = [];
        $this->componentGroupMap = [];
        $this->namedComponentGroups = [];
        $this->persistedEntities = [];
        $this->evaluatedNestedIds = [];
        $this->firedCallbackIds = [];
        $this->decisions = [];
        $this->resolved = [];
        $this->existing = [];
        $this->skipped = [];
        $this->skippedGroupBuilders = [];
        $this->routedSpecs = [];
        $this->hasPendingLinks = false;
        $this->flushing = false;
        $this->pendingPersists = [];
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
            $this->componentBuilders[$oid] = new ComponentBuilder($component, $this->linkDraft(...));
        }

        return $this->componentBuilders[$oid];
    }

    /**
     * @template T of object
     *
     * @param T $resource
     *
     * @return T
     */
    public function publish(object $resource, ?\DateTimeInterface $at = null): object
    {
        $fieldName = $this->publishableConfiguration($resource, 'be published')->fieldName;
        $type = $this->manager?->getClassMetadata($resource::class)->getTypeOfField($fieldName);
        $at ??= new \DateTimeImmutable();
        $date = str_contains((string) $type, '_immutable') ? \DateTimeImmutable::createFromInterface($at) : \DateTime::createFromInterface($at);
        $this->propertyAccessor->setValue($resource, $fieldName, $date);

        return $resource;
    }

    private function linkDraft(AbstractComponent $published, AbstractComponent $draft): void
    {
        $this->propertyAccessor->setValue($draft, $this->publishableConfiguration($published, 'have a draft')->associationName, $published);
    }

    private function publishableConfiguration(object $resource, string $action): Publishable
    {
        if (null === $this->publishableAttributeReader || !$this->publishableAttributeReader->isConfigured($resource)) {
            throw new \LogicException(\sprintf('%s is not publishable, so it cannot %s.', $resource::class, $action));
        }

        return $this->publishableAttributeReader->getConfiguration($resource);
    }

    public function afterRoutes(\Closure $callback): static
    {
        $this->afterRoutesCallbacks[] = $callback;

        return $this;
    }

    public function redirect(string $path, string|PageBuilder|PageDataBuilder $to, ?string $name = null): static
    {
        $this->redirectSpecs[] = ['path' => $path, 'to' => $to, 'name' => $name, 'route' => null];

        return $this;
    }

    public function getRoute(string $routeName): Route
    {
        return $this->findNamedRoute($routeName) ?? throw new \LogicException(\sprintf('Named route "%s" not found. Did you call flush() before getRoute()?', $routeName));
    }

    private function findNamedRoute(string $routeName): ?Route
    {
        if (!isset($this->namedRoutes[$routeName]) && ($existing = $this->manager?->getRepository(Route::class)->findOneBy(['name' => $routeName])) instanceof Route) {
            $this->existing[spl_object_id($existing)] = true;
            $this->namedRoutes[$routeName] = $existing;
        }

        return $this->namedRoutes[$routeName] ?? null;
    }

    private function routeNamedBy(string $routeName, string $referrer): Route
    {
        return $this->findNamedRoute($routeName) ?? throw new \LogicException(\sprintf('%s the route name "%s", but no route in this load or the database has that name. Routes in this load: %s.', $referrer, $routeName, [] === $this->namedRoutes ? 'none' : implode(', ', array_keys($this->namedRoutes))));
    }

    private function routeTo(string|PageBuilder|PageDataBuilder $target, string $referrer): Route
    {
        if (\is_string($target)) {
            return $this->routeNamedBy($target, $referrer);
        }

        return $target->getRoute() ?? $this->routeAtDeclaredPath($target) ?? throw new \LogicException(\sprintf('%s %s, which has no route.', $referrer, $target instanceof PageBuilder ? \sprintf('the page "%s"', $target->getPage()->reference) : \sprintf('the page data %s %s', $target->getPageData()::class, null === $target->getPageData()->getTitle() ? '(untitled)' : \sprintf('"%s"', $target->getPageData()->getTitle()))));
    }

    private function routeAtDeclaredPath(PageBuilder|PageDataBuilder $target): ?Route
    {
        foreach ([...$this->pageSpecs, ...$this->pageDataSpecs] as $spec) {
            if ($spec['builder'] === $target && null !== $spec['route']) {
                return $this->findOne(Route::class, ['path' => $spec['route']]);
            }
        }

        return null;
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
        $entityCountBefore = \count($this->persistedEntities);
        $processedPageDataCount = \count($this->pageDataSpecs);
        $processedPageRefs = array_flip(array_keys($this->pageSpecs));
        $hasNew = false;

        while ($this->runNestedClosures()) {
            foreach (array_diff_key($this->pageSpecs, $processedPageRefs) as $ref => $spec) {
                $processedPageRefs[$ref] = true;
                $this->processPage($ref, $spec);
                $hasNew = true;
            }

            foreach (\array_slice($this->pageDataSpecs, $processedPageDataCount) as $spec) {
                ++$processedPageDataCount;
                $this->processPageData($spec);
                $hasNew = true;
            }
        }

        if ($this->processComponentBuilders()) {
            $hasNew = true;
        }

        $hasNew = $hasNew || \count($this->persistedEntities) > $entityCountBefore || $this->hasPendingLinks;
        $this->hasPendingLinks = false;

        if ($hasNew) {
            $this->manager->flush();
        }
    }

    private function runNestedClosures(): bool
    {
        $ran = false;

        foreach ($this->pageDataSpecs as $spec) {
            $closures = $spec['builder']->getNestedClosures();
            if ([] === $closures) {
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
            $this->runNested($closures, $this->resolve($spec['builder']->getPageData()));
            $ran = true;
            $addedRefs = array_values(array_diff(array_keys($this->pageSpecs), $beforePageRefs));
            $spec['builder']->setChildPageRefs($addedRefs);
        }

        foreach ($this->pageSpecs as $spec) {
            $closures = $spec['builder']->getNestedClosures();
            if ([] === $closures) {
                continue;
            }
            $oid = spl_object_id($spec['builder']);
            if (isset($this->evaluatedNestedIds[$oid])) {
                continue;
            }
            $this->evaluatedNestedIds[$oid] = true;
            $this->runNested($closures, $this->resolve($spec['builder']->getPage()));
            $ran = true;
        }

        return $ran;
    }

    /**
     * @param list<\Closure> $closures
     */
    private function runNested(array $closures, object $parent): void
    {
        $this->parentContext = $parent;
        try {
            foreach ($closures as $closure) {
                $closure($this);
            }
        } finally {
            $this->parentContext = null;
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

        if (!$this->hasNestedClosures()) {
            $this->processComponentBuilders();
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

    private function hasNestedClosures(): bool
    {
        foreach ([...$this->pageSpecs, ...$this->pageDataSpecs] as $spec) {
            if ([] !== $spec['builder']->getNestedClosures()) {
                return true;
            }
        }

        return false;
    }

    private function processComponentBuilders(): bool
    {
        $groupCountBefore = \count($this->componentGroupMap);

        foreach ($this->componentBuilders as $componentBuilder) {
            if ([] === $componentBuilder->getGroupBuilders()) {
                continue;
            }
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

        return \count($this->componentGroupMap) > $groupCountBefore;
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

        if (($builder->isWithoutRoute() || null === $pageData->getTitle()) && null === $spec['route']) {
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
        $existing = null === $path ? null : $this->findOne(Route::class, ['path' => $path])?->getPageData();
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
            $positions = \count($groupBuilder->getComponents()) + \count($groupBuilder->getPageDataPositions());
            if ($positions > 0) {
                $this->summary->record(self::SKIPPED, CwaFixtureSummary::POSITION, 'group kept', $positions);
                $this->logger?->notice(\sprintf('The group `%s` of %s already exists, so its %d scaffold positions were not created.', $groupBuilder->getName(), $this->describeOwner($owner), $positions));
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
            if (null === $spec['route'] && ($builder->isWithoutRoute() || null === $entity->getTitle())) {
                $this->routedSpecs[$builderId] = true;
                continue;
            }
            if (null !== $spec['route']) {
                $name = $spec['routeName'] ?? RouteName::fromPath($spec['route']);
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
                $this->namedRoutes[$route->getName()] = $route;
                if (null !== $spec['routeName']) {
                    $this->namedRoutes[$spec['routeName']] = $route;
                }
                $this->note(self::CREATED, CwaFixtureSummary::ROUTE, $spec['route']);
                $hadNew = true;
            } elseif ('pageData' === $spec['type'] || !$spec['isTemplate']) {
                $route = $this->routeGenerator->create($entity);
                $this->manager->persist($route);
                $generated = $entity->getRoute();
                if (null !== $generated) {
                    $this->createdRouteNames[$generated->getName()] = $generated->getPath();
                    $this->namedRoutes[$generated->getName()] = $generated;
                    if (null !== $spec['routeName']) {
                        $this->namedRoutes[$spec['routeName']] = $generated;
                    }
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
            if (null !== $spec['name'] && isset($this->createdRouteNames[$spec['name']])) {
                throw new \LogicException(\sprintf('The redirect "%s" cannot be named "%s", because the route "%s" already has that name.', $spec['path'], $spec['name'], $this->createdRouteNames[$spec['name']]));
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
                $named = $this->findOne(Route::class, ['name' => $spec['name']]);
                if ($named instanceof Route) {
                    $this->existing[spl_object_id($named)] = true;
                    $this->namedRoutes[$spec['name']] = $named;
                    $this->redirectSpecs[$index]['route'] = $named;
                    $this->note(self::SKIPPED, CwaFixtureSummary::ROUTE, $spec['path'], 'name in use');
                    continue;
                }
            }
            $route = $this->createExplicitRoute($spec['path'], $spec['name'] ?? $this->uniqueRouteName(RouteName::fromPath($spec['path'])));
            $route->setRedirect($this->routeTo($spec['to'], \sprintf('The redirect "%s" leads to', $spec['path'])));
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
            $route = $entity?->getRoute() ?? (null !== $spec['route'] ? $this->findOne(Route::class, ['path' => $spec['route']]) : null);
            if (!$route instanceof Route) {
                return;
            }
            $this->existing[spl_object_id($route)] = true;
            foreach (array_unique([$route->getName(), RouteName::fromPath($spec['route'] ?? $route->getPath())]) as $name) {
                $this->namedRoutes[$name] ??= $route;
            }

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
        $this->resolveRouteLinks();
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

        $this->resolveRouteLinks();

        if ($hasChanges) {
            $this->manager->flush();
            $this->hasPendingLinks = false;
        }
    }

    private function resolveRouteLinks(): void
    {
        $this->syncSkipped();

        foreach ($this->componentBuilders as $oid => $componentBuilder) {
            if (isset($this->skipped[$oid])) {
                continue;
            }
            $this->applyRouteLinks($componentBuilder, $componentBuilder->getComponent(), $componentBuilder->getRouteLinks());
            $this->applyIriLinks($componentBuilder, $componentBuilder->getComponent(), $componentBuilder->getIriLinks());
        }

        foreach ($this->pageDataSpecs as $spec) {
            if (self::CREATED !== ($this->decisions[spl_object_id($spec['builder'])] ?? null)) {
                continue;
            }
            $this->applyRouteLinks($spec['builder'], $spec['builder']->getPageData(), $spec['builder']->getRouteLinks());
            $this->applyIriLinks($spec['builder'], $spec['builder']->getPageData(), $spec['builder']->getIriLinks());
        }
    }

    /**
     * @param list<array{string, string|PageBuilder|PageDataBuilder}> $links
     */
    private function applyRouteLinks(ComponentBuilder|PageDataBuilder $builder, object $target, array $links): void
    {
        $oid = spl_object_id($builder);
        $pending = \array_slice($links, $this->appliedRouteLinks[$oid] ?? 0);
        $this->appliedRouteLinks[$oid] = \count($links);
        foreach ($pending as [$property, $routeTarget]) {
            $route = $this->routeTo($routeTarget, \sprintf('%s::$%s links to', $target::class, $property));
            try {
                $this->propertyAccessor->setValue($target, $property, $route);
            } catch (NoSuchPropertyException|InvalidTypeException $exception) {
                throw new \LogicException(\sprintf('linkRoute(): %s has no writable property "%s" that can hold a Route.', $target::class, $property), 0, $exception);
            }
            if (isset($this->persistedEntities[spl_object_id($target)])) {
                $this->hasPendingLinks = true;
            }
        }
    }

    /**
     * @param list<IriLink> $links
     */
    private function applyIriLinks(ComponentBuilder|PageDataBuilder $builder, object $target, array $links): void
    {
        $oid = spl_object_id($builder);
        $pending = \array_slice($links, $this->appliedIriLinks[$oid] ?? 0);
        $this->appliedIriLinks[$oid] = \count($links);
        foreach ($pending as $link) {
            $iri = (string) $this->iriConverter->getIriFromResource($this->iriLinkTarget($link, \sprintf('%s::$%s links to', $target::class, $link->describe())));
            try {
                $this->propertyAccessor->setValue($target, $link->property, [] === $link->key ? $iri : $this->withIriAtKey($target, $link, $iri));
            } catch (AccessException|InvalidTypeException $exception) {
                throw new \LogicException(\sprintf('linkIri(): %s has no writable property "%s" that can hold an IRI.', $target::class, $link->property), 0, $exception);
            }
            if (isset($this->persistedEntities[spl_object_id($target)])) {
                $this->hasPendingLinks = true;
            }
        }
    }

    private function iriLinkTarget(IriLink $link, string $referrer): object
    {
        if (IriLink::ROUTE === $link->target) {
            return $this->routeTo($link->name, $referrer);
        }
        if ($link->name instanceof PageBuilder) {
            return $this->resolve($link->name->getPage());
        }
        if ($link->name instanceof PageDataBuilder) {
            return $this->resolve($link->name->getPageData());
        }

        return match ($link->target) {
            IriLink::PAGE => (isset($this->pageSpecs[$link->name]) ? $this->resolve($this->pageSpecs[$link->name]['builder']->getPage()) : $this->findOne(Page::class, ['reference' => $link->name]))
                ?? throw new \LogicException(\sprintf('%s the page "%s", but no page in this load or the database has that reference.', $referrer, $link->name)),
            IriLink::LAYOUT => (isset($this->layoutBuilders[$link->name]) ? $this->resolve($this->layoutBuilders[$link->name]->getLayout()) : $this->findOne(Layout::class, ['reference' => $link->name]))
                ?? throw new \LogicException(\sprintf('%s the layout "%s", but no layout in this load or the database has that reference.', $referrer, $link->name)),
            default => $this->pageDataAt($this->routeNamedBy($link->name, $referrer))
                ?? throw new \LogicException(\sprintf('%s the page data at the route "%s", but that route has no page data.', $referrer, $link->name)),
        };
    }

    private function pageDataAt(Route $route): ?AbstractPageData
    {
        if (null !== $route->getPageData()) {
            return $route->getPageData();
        }
        foreach ($this->pageDataSpecs as $spec) {
            $pageData = $this->resolve($spec['builder']->getPageData());
            if ($pageData instanceof AbstractPageData && $pageData->getRoute() === $route) {
                return $pageData;
            }
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    private function withIriAtKey(object $target, IriLink $link, string $iri): array
    {
        $value = $this->propertyAccessor->getValue($target, $link->property) ?? [];
        $path = $target::class . '::$' . $link->property;
        if (!\is_array($value)) {
            throw new \LogicException(\sprintf('linkIri(): %s is not an array, so it has no key %s.', $path, json_encode($link->key[0])));
        }
        $cursor = &$value;
        $last = \count($link->key) - 1;
        foreach ($link->key as $index => $segment) {
            if ($index === $last) {
                $cursor[$segment] = $iri;
                break;
            }
            $cursor[$segment] ??= [];
            $path .= '[' . json_encode($segment) . ']';
            if (!\is_array($cursor[$segment])) {
                throw new \LogicException(\sprintf('linkIri(): %s is not an array, so it has no key %s.', $path, json_encode($link->key[$index + 1])));
            }
            $cursor = &$cursor[$segment];
        }

        return $value;
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

        foreach ($this->componentBuilders as $componentBuilder) {
            if ([] === $componentBuilder->getGroupBuilders() && $this->persistLoose($componentBuilder->getComponent())) {
                $hasPositions = true;
            }
        }

        foreach ($this->componentBuilders as $componentBuilder) {
            foreach ($componentBuilder->getDrafts() as $draft) {
                if ($this->persistLoose($draft)) {
                    $hasPositions = true;
                }
            }
        }

        if ($hasPositions || $this->hasPendingLinks) {
            $this->manager->flush();
        }
        $this->hasPendingLinks = false;
    }

    private function persistLoose(AbstractComponent $component): bool
    {
        $oid = spl_object_id($component);
        if (isset($this->skipped[$oid]) || $this->isSettled($component)) {
            return false;
        }
        if ($this->reachesSkipped($component)) {
            $this->markSkipped($component);
            $this->note(self::SKIPPED, CwaFixtureSummary::COMPONENT, $component::class, 'linked to skipped content');

            return false;
        }
        if ($this->timestampedPersister->isConfigured($component)) {
            $this->timestampedPersister->persistTimestampedFields($component, true);
        }
        $this->persistWithAssociations($component);

        return true;
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
        $route->setName($name ?? RouteName::fromPath($path));

        return $route;
    }

    private function uniqueRouteName(string $name): string
    {
        $candidate = $name;
        for ($suffix = 1; isset($this->createdRouteNames[$candidate]) || null !== $this->findOne(Route::class, ['name' => $candidate]); ++$suffix) {
            $candidate = RouteName::withSuffix($name, $suffix);
        }

        return $candidate;
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

    private function describeOwner(Layout|Page|AbstractComponent $owner): string
    {
        return match (true) {
            $owner instanceof Layout => \sprintf('the layout `%s`', $owner->reference),
            $owner instanceof Page => \sprintf('the page `%s`', $owner->reference),
            default => \sprintf('a %s component', $owner::class),
        };
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
