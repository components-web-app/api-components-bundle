<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Orphan;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Test\Client;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Command\ScanOrphanedCommand;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedResourceReportRecord;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Factory\OrphanedResource\OrphanedResourcesChangedEmailFactory;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithParentTypedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\EventSubscriber\TemplatedEmailMessageEventSubscriber;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait OrphanedResourcesTrait
{
    use ApiRequestTrait;
    use ConsoleTrait;
    use ResourceTrait;

    public const array EVERY_KIND_ORPHANED_GROUPS = ['orphaned_group', 'owned_group'];
    public const array EVERY_KIND_ORPHANED_POSITIONS = ['empty_position', 'orphaned_group_position', 'orphaned_group_empty_position', 'owned_position'];
    public const array EVERY_KIND_ORPHANED_COMPONENTS = ['unused_component', 'orphaned_group_component', 'unused_published', 'owning_component', 'owned_component'];

    /**
     * @var array<string, object>
     */
    private static array $orphanFixtureResources = [];

    protected static function createOrphanedComponentGroup(): string
    {
        $group = new ComponentGroup();
        $group->reference = 'orphaned-group';
        $group->location = 'orphaned-group';
        self::persistTimestampedForOrphans($group);
        static::getEntityManager()->flush();
        $iri = static::iriOf($group);
        static::rebootKernel();

        return $iri;
    }

    protected static function createEmptyPositionOnPage(Page $page): string
    {
        $position = new ComponentPosition();
        $position->componentGroup = self::newPageComponentGroupForOrphans($page, 'empty-position-group');
        $position->sortValue = 0;
        self::persistTimestampedForOrphans($position);
        static::getEntityManager()->flush();
        $iri = static::iriOf($position);
        static::rebootKernel();

        return $iri;
    }

    protected static function placeOnPageForOrphans(Page $page, string $componentIri): void
    {
        $component = static::findByIri($componentIri);
        \assert($component instanceof AbstractComponent);
        $position = new ComponentPosition();
        $position->componentGroup = self::newPageComponentGroupForOrphans($page, 'held-resource-group');
        $position->component = $component;
        $position->sortValue = 0;
        self::persistTimestampedForOrphans($position);
        static::getEntityManager()->flush();
        static::rebootKernel();
    }

    protected static function placeInGroupForOrphans(string $groupIri, string $componentIri): void
    {
        $group = static::findByIri($groupIri);
        $component = static::getContainer()->get(IriConverterInterface::class)->getResourceFromIri($componentIri);
        \assert($group instanceof ComponentGroup && $component instanceof AbstractComponent);
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->component = $component;
        $position->sortValue = 0;
        self::persistTimestampedForOrphans($position);
        static::getEntityManager()->flush();
        static::rebootKernel();
    }

    protected static function removeFromDatabase(string $iri): void
    {
        $resource = static::findByIri($iri);
        self::assertNotNull($resource);
        $manager = static::getEntityManager();
        $manager->remove($resource);
        $manager->flush();
        static::rebootKernel();
    }

    /**
     * @return array<string, string>
     */
    protected static function createSiteWithEveryKindOfOrphanAndEveryKindOfUse(): array
    {
        $page = new Page();
        $page->reference = 'in-use-page';
        $page->isTemplate = false;
        self::persistTimestampedForOrphans($page);
        $route = new Route();
        $route->setPath('/in-use')->setName('/in-use')->setPage($page);
        self::persistTimestampedForOrphans($route);
        $pageGroup = self::orphanFixtureGroup('page_group');
        $page->addComponentGroup($pageGroup);

        $placedComponent = self::orphanFixtureComponent('placed_component');
        self::orphanFixturePosition('placed_position', $pageGroup, $placedComponent);
        $placedPublished = self::orphanFixturePublishable('placed_published');
        self::orphanFixturePosition('placed_published_position', $pageGroup, $placedPublished);
        self::orphanFixtureDraft('placed_published_draft', $placedPublished);
        self::orphanFixturePosition('empty_position', $pageGroup, null);

        $orphanedGroup = self::orphanFixtureGroup('orphaned_group');
        self::orphanFixturePosition('orphaned_group_position', $orphanedGroup, self::orphanFixtureComponent('orphaned_group_component'));
        self::orphanFixturePosition('orphaned_group_empty_position', $orphanedGroup, null);

        self::orphanFixtureComponent('unused_component');
        $unusedPublished = self::orphanFixturePublishable('unused_published');
        self::orphanFixtureDraft('unused_published_draft', $unusedPublished);

        $template = new Page();
        $template->reference = 'orphan-comparison-template';
        $template->isTemplate = true;
        self::persistTimestampedForOrphans($template);
        $pageData = new PageDataWithComponent();
        $pageData->page = $template;
        $pageData->component = self::orphanFixtureComponent('page_data_component');
        self::persistTimestampedForOrphans($pageData);
        $parentTypedPageData = new PageDataWithParentTypedComponent();
        $parentTypedPageData->page = $template;
        $parentTypedPageData->component = self::orphanFixtureComponent('parent_typed_component');
        self::persistTimestampedForOrphans($parentTypedPageData);

        $owningComponent = self::orphanFixtureComponent('owning_component');
        $ownedGroup = self::orphanFixtureGroup('owned_group');
        $owningComponent->addComponentGroup($ownedGroup);
        self::orphanFixturePosition('owned_position', $ownedGroup, self::orphanFixtureComponent('owned_component'));

        return self::flushOrphanFixture();
    }

    /**
     * @return array<string, string>
     */
    protected static function createOrphanedGroupWithNestedSharedPageDataAndDraftDescendants(): array
    {
        $page = new Page();
        $page->reference = 'chain-live-page';
        $page->isTemplate = false;
        self::persistTimestampedForOrphans($page);
        $route = new Route();
        $route->setPath('/chain-live')->setName('/chain-live')->setPage($page);
        self::persistTimestampedForOrphans($route);
        $liveGroup = self::orphanFixtureGroup('live_group');
        $page->addComponentGroup($liveGroup);

        $chainGroup = self::orphanFixtureGroup('chain_group');
        $chainComponent = self::orphanFixtureComponent('chain_component');
        self::orphanFixturePosition('chain_position', $chainGroup, $chainComponent);
        $chainOwnedGroup = self::orphanFixtureGroup('chain_owned_group');
        $chainComponent->addComponentGroup($chainOwnedGroup);
        self::orphanFixturePosition('chain_owned_position', $chainOwnedGroup, self::orphanFixtureComponent('chain_owned_component'));
        self::orphanFixturePosition('cycle_position', $chainOwnedGroup, $chainComponent);

        $sharedComponent = self::orphanFixtureComponent('shared_component');
        self::orphanFixturePosition('shared_position', $chainGroup, $sharedComponent);
        self::orphanFixturePosition('shared_live_position', $liveGroup, $sharedComponent);

        $sharedOwnedGroup = self::orphanFixtureGroup('shared_owned_group');
        $chainComponent->addComponentGroup($sharedOwnedGroup);
        $page->addComponentGroup($sharedOwnedGroup);
        self::orphanFixturePosition('shared_owned_position', $sharedOwnedGroup, self::orphanFixtureComponent('shared_owned_component'));

        $template = new Page();
        $template->reference = 'chain-template';
        $template->isTemplate = true;
        self::persistTimestampedForOrphans($template);
        $pageData = new PageDataWithComponent();
        $pageData->page = $template;
        $pageData->component = self::orphanFixtureComponent('page_data_component');
        self::persistTimestampedForOrphans($pageData);
        self::orphanFixturePosition('page_data_position', $chainGroup, $pageData->component);
        $parentTypedPageData = new PageDataWithParentTypedComponent();
        $parentTypedPageData->page = $template;
        $parentTypedPageData->component = self::orphanFixtureComponent('parent_typed_component');
        self::persistTimestampedForOrphans($parentTypedPageData);
        self::orphanFixturePosition('parent_typed_position', $chainGroup, $parentTypedPageData->component);

        $published = self::orphanFixturePublishable('chained_published');
        self::orphanFixturePosition('published_position', $chainGroup, $published);
        self::orphanFixtureDraft('chained_published_draft', $published);

        return self::flushOrphanFixture();
    }

    /**
     * @return array<string, string>
     */
    protected static function createRoutedPageWhoseGroupHoldsAComponentOwningAGroup(): array
    {
        $page = new Page();
        $page->reference = 'nested-owner-page';
        $page->isTemplate = false;
        self::persistTimestampedForOrphans($page);
        $route = new Route();
        $route->setPath('/nested-owner')->setName('/nested-owner')->setPage($page);
        self::persistTimestampedForOrphans($route);
        $outerGroup = self::orphanFixtureGroup('outer_group');
        $page->addComponentGroup($outerGroup);
        $outerComponent = self::orphanFixtureComponent('outer_component');
        self::orphanFixturePosition('outer_position', $outerGroup, $outerComponent);
        $innerGroup = self::orphanFixtureGroup('inner_group');
        $outerComponent->addComponentGroup($innerGroup);
        self::orphanFixturePosition('inner_position', $innerGroup, self::orphanFixtureComponent('inner_component'));

        return self::flushOrphanFixture();
    }

    protected static function storeOrphanedResourcesReport(): void
    {
        static::getContainer()->get(OrphanedResourceReportStore::class)->save(new OrphanedResourceReport(new \DateTimeImmutable()));
        static::rebootKernel();
    }

    protected static function storedOrphanedResourcesReport(): ?OrphanedResourceReport
    {
        static::getEntityManager()->clear();

        return static::getContainer()->get(OrphanedResourceReportStore::class)->fetch();
    }

    /**
     * @return array<string, list<string>>
     */
    protected static function storedOrphanedResourcesReportLists(): array
    {
        $report = static::storedOrphanedResourcesReport();
        self::assertNotNull($report, 'No orphaned resources report has been stored');

        return [
            'componentGroups' => $report->componentGroups,
            'componentPositions' => $report->componentPositions,
            'components' => $report->components,
        ];
    }

    /**
     * @param array<string, string> $iris
     */
    protected static function assertEveryKindOfOrphanReportStoredInTheDatabase(array $iris): void
    {
        $manager = static::getEntityManager();
        $table = $manager->getClassMetadata(OrphanedResourceReportRecord::class)->getTableName();
        $rows = $manager->getConnection()->fetchAllAssociative(\sprintf('SELECT component_groups, component_positions, components FROM %s', $table));
        self::assertCount(1, $rows, 'Expected one stored report row');
        $expected = [
            'component_groups' => self::EVERY_KIND_ORPHANED_GROUPS,
            'component_positions' => self::EVERY_KIND_ORPHANED_POSITIONS,
            'components' => self::EVERY_KIND_ORPHANED_COMPONENTS,
        ];
        foreach ($expected as $column => $names) {
            $stored = json_decode($rows[0][$column], true, 512, \JSON_THROW_ON_ERROR);
            $expectedIris = self::irisNamed($iris, $names);
            sort($stored);
            sort($expectedIris);
            self::assertSame($expectedIris, $stored, \sprintf('The stored %s differ', $column));
        }
    }

    /**
     * @param array<string, string> $iris
     * @param list<string>          $names
     *
     * @return list<string>
     */
    protected static function irisNamed(array $iris, array $names): array
    {
        return array_map(static fn (string $name): string => $iris[$name], $names);
    }

    /**
     * @param list<string> $iris
     */
    protected static function requestOrphanDeletion(Client $client, array $iris): ResponseInterface
    {
        return static::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', ['iris' => $iris]);
    }

    protected static function scanAndFetchOrphanReport(Client $client): void
    {
        static::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        static::requestJsonLd($client, 'GET', '/_/orphaned_resources');
    }

    protected static function assertComponentExistsInDatabase(string $iri, bool $exists = true): void
    {
        $manager = static::getEntityManager();
        $manager->clear();
        $found = null !== $manager->getRepository(AbstractComponent::class)->find(substr($iri, strrpos($iri, '/') + 1));
        self::assertSame($exists, $found, \sprintf('The component %s %s in the database', $iri, $found ? 'still exists' : 'does not exist'));
    }

    /**
     * @return array<string, int>
     */
    protected static function countStructuralResources(): array
    {
        return [
            'componentGroups' => static::countEntities(ComponentGroup::class),
            'componentPositions' => static::countEntities(ComponentPosition::class),
            'components' => static::countEntities(AbstractComponent::class),
        ];
    }

    /**
     * @return array{tester: CommandTester, notifications: list<TemplatedEmail>, logs: list<LogRecord>}
     */
    protected static function runScheduledOrphanScan(?string $option = null): array
    {
        static::rebootKernel();
        $tester = static::runCommand(ScanOrphanedCommand::NAME, null === $option ? [] : [$option => true]);
        $container = static::getContainer();
        $emails = iterator_to_array($container->get(TemplatedEmailMessageEventSubscriber::class)->getMessages(), false);
        $notifications = array_values(array_filter(
            $emails,
            static fn (TemplatedEmail $email): bool => str_starts_with((string) $email->getHeaders()->get('X-Message-ID')?->getBodyAsString(), OrphanedResourcesChangedEmailFactory::MESSAGE_ID_PREFIX . '-')
        ));
        $handler = $container->get('app.monolog.test_handler');
        \assert($handler instanceof TestHandler);
        $logs = array_values($handler->getRecords());
        static::rebootKernel();

        return ['tester' => $tester, 'notifications' => $notifications, 'logs' => $logs];
    }

    private static function persistTimestampedForOrphans(object $resource): void
    {
        static::getContainer()->get(TimestampedDataPersister::class)->persistTimestampedFields($resource, true);
        static::getEntityManager()->persist($resource);
    }

    private static function newPageComponentGroupForOrphans(Page $page, string $reference): ComponentGroup
    {
        $managedPage = static::getEntityManager()->find(Page::class, $page->getId());
        \assert($managedPage instanceof Page);
        $group = new ComponentGroup();
        $group->reference = $reference;
        $group->location = $reference;
        self::persistTimestampedForOrphans($group);
        $managedPage->addComponentGroup($group);

        return $group;
    }

    private static function orphanFixtureGroup(string $name): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $name;
        $group->location = $name;
        self::persistTimestampedForOrphans($group);

        return self::$orphanFixtureResources[$name] = $group;
    }

    private static function orphanFixtureComponent(string $name): DummyComponent
    {
        $component = new DummyComponent();
        static::getEntityManager()->persist($component);

        return self::$orphanFixtureResources[$name] = $component;
    }

    private static function orphanFixturePublishable(string $name): DummyPublishableComponent
    {
        $component = new DummyPublishableComponent();
        $component->reference = $name;
        $component->setPublishedAt(new \DateTime('-10 seconds'));
        static::getEntityManager()->persist($component);

        return self::$orphanFixtureResources[$name] = $component;
    }

    private static function orphanFixtureDraft(string $name, DummyPublishableComponent $published): void
    {
        $draft = new DummyPublishableComponent();
        $draft->reference = $name;
        $draft->setPublishedResource($published);
        static::getEntityManager()->persist($draft);
        self::$orphanFixtureResources[$name] = $draft;
    }

    private static function orphanFixturePosition(string $name, ComponentGroup $group, ?AbstractComponent $component): void
    {
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->component = $component;
        $position->sortValue = \count(self::$orphanFixtureResources);
        self::persistTimestampedForOrphans($position);
        self::$orphanFixtureResources[$name] = $position;
    }

    /**
     * @return array<string, string>
     */
    private static function flushOrphanFixture(): array
    {
        static::getEntityManager()->flush();
        $iris = array_map(static::iriOf(...), self::$orphanFixtureResources);
        self::$orphanFixtureResources = [];
        static::rebootKernel();

        return $iris;
    }
}
