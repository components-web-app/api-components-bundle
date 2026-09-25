<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDetector;
use Silverback\ApiComponentsBundle\Metadata\ComponentUsageMetadata;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;

class OrphanedResourceDetectorTest extends TestCase
{
    private \SplObjectStorage $iris;

    /** @var array<class-string, list<object>> */
    private array $all = [];

    /** @var list<ComponentPosition> */
    private array $emptyPositions = [];

    /** @var array<int, int> */
    private array $usage = [];

    protected function setUp(): void
    {
        $this->iris = new \SplObjectStorage();
    }

    public function test_a_group_with_no_page_layout_or_component_owner_is_reported(): void
    {
        $orphan = $this->named(new ComponentGroup(), '/_/component_groups/orphan');
        $inPage = $this->named((new ComponentGroup())->addPage(new Page()), '/_/component_groups/page');
        $inLayout = $this->named((new ComponentGroup())->addLayout(new Layout()), '/_/component_groups/layout');
        $inComponent = $this->named((new ComponentGroup())->addComponent(new DummyComponent()), '/_/component_groups/component');
        $this->all[ComponentGroup::class] = [$orphan, $inPage, $inLayout, $inComponent];

        $report = $this->createDetector()->detect();

        self::assertSame(['/_/component_groups/orphan'], $report->componentGroups);
    }

    public function test_the_positions_reported_are_those_with_neither_a_component_nor_a_page_data_property(): void
    {
        $this->emptyPositions = [$this->named(new ComponentPosition(), '/_/component_positions/empty')];

        $report = $this->createDetector()->detect();

        self::assertSame(['/_/component_positions/empty'], $report->componentPositions);
    }

    public function test_a_component_used_nowhere_is_reported_and_a_used_one_is_not(): void
    {
        $unused = $this->named(new DummyComponent(), '/component/dummy_components/unused');
        $inPosition = $this->named(new DummyComponent(), '/component/dummy_components/position');
        $inPageData = $this->named(new DummyComponent(), '/component/dummy_components/page_data');
        $this->usage[spl_object_id($inPosition)] = 1;
        $this->usage[spl_object_id($inPageData)] = 1;
        $this->all[AbstractComponent::class] = [$unused, $inPosition, $inPageData];

        $report = $this->createDetector()->detect();

        self::assertSame(['/component/dummy_components/unused'], $report->components);
    }

    public function test_a_draft_is_never_reported(): void
    {
        $published = $this->named(new DummyPublishableComponent(), '/component/dummy_publishable_components/published');
        $draft = $this->named(new DummyPublishableComponent(), '/component/dummy_publishable_components/draft');
        $draft->setPublishedResource($published);
        $this->all[AbstractComponent::class] = [$draft, $published];

        $report = $this->createDetector()->detect();

        self::assertSame(['/component/dummy_publishable_components/published'], $report->components);
    }

    public function test_a_publishable_component_with_no_published_version_is_judged_by_its_usage(): void
    {
        $unused = $this->named(new DummyPublishableComponent(), '/component/dummy_publishable_components/unused');
        $used = $this->named(new DummyPublishableComponent(), '/component/dummy_publishable_components/used');
        $this->usage[spl_object_id($used)] = 1;
        $this->all[AbstractComponent::class] = [$unused, $used];

        $report = $this->createDetector()->detect();

        self::assertSame(['/component/dummy_publishable_components/unused'], $report->components);
    }

    public function test_iris_are_sorted_and_the_report_is_dated_now(): void
    {
        $this->all[ComponentGroup::class] = [
            $this->named(new ComponentGroup(), '/_/component_groups/b'),
            $this->named(new ComponentGroup(), '/_/component_groups/a'),
        ];
        $this->emptyPositions = [
            $this->named(new ComponentPosition(), '/_/component_positions/b'),
            $this->named(new ComponentPosition(), '/_/component_positions/a'),
        ];
        $this->all[AbstractComponent::class] = [
            $this->named(new DummyComponent(), '/component/dummy_components/b'),
            $this->named(new DummyComponent(), '/component/dummy_components/a'),
        ];

        $before = new \DateTimeImmutable();
        $report = $this->createDetector()->detect();

        self::assertSame(['/_/component_groups/a', '/_/component_groups/b'], $report->componentGroups);
        self::assertSame(['/_/component_positions/a', '/_/component_positions/b'], $report->componentPositions);
        self::assertSame(['/component/dummy_components/a', '/component/dummy_components/b'], $report->components);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $report->generatedAt->getTimestamp());
        self::assertLessThanOrEqual((new \DateTimeImmutable())->getTimestamp(), $report->generatedAt->getTimestamp());
    }

    public function test_an_empty_database_gives_an_empty_report(): void
    {
        $report = $this->createDetector()->detect();

        self::assertSame([], $report->componentGroups);
        self::assertSame([], $report->componentPositions);
        self::assertSame([], $report->components);
    }

    /**
     * @template T of object
     *
     * @param T $resource
     *
     * @return T
     */
    private function named(object $resource, string $iri): object
    {
        $this->iris[$resource] = $iri;

        return $resource;
    }

    private function createDetector(): OrphanedResourceDetector
    {
        $groupRepository = $this->createStub(ObjectRepository::class);
        $groupRepository->method('findAll')->willReturn($this->all[ComponentGroup::class] ?? []);

        $positionRepository = $this->createStub(ObjectRepository::class);
        $positionRepository->method('findBy')->willReturnCallback(
            fn (array $criteria) => ['component' => null, 'pageDataProperty' => null] === $criteria ? $this->emptyPositions : []
        );

        $componentRepository = $this->createStub(ObjectRepository::class);
        $componentRepository->method('findAll')->willReturn($this->all[AbstractComponent::class] ?? []);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturnCallback(
            static fn (object $entity, string $field) => 'publishedResource' === $field && $entity instanceof DummyPublishableComponent ? $entity->getPublishedResource() : null
        );
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getRepository')->willReturnCallback(static fn (string $class) => match ($class) {
            ComponentGroup::class => $groupRepository,
            ComponentPosition::class => $positionRepository,
            AbstractComponent::class => $componentRepository,
        });
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $usageFactory = $this->createStub(ComponentUsageMetadataFactory::class);
        $usageFactory->method('create')->willReturnCallback(
            fn (object $component) => new ComponentUsageMetadata($this->usage[spl_object_id($component)] ?? 0, 0)
        );

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(fn (object $resource) => $this->iris[$resource]);

        return new OrphanedResourceDetector($registry, $usageFactory, new PublishableAttributeReader($registry), $iriConverter);
    }
}
