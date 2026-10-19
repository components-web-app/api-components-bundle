<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Metadata\Factory;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\ComponentUsage\ComponentUsageLocator;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\PageDataComponentMetadata;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentPositionRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

final class ComponentUsageMetadataFactoryTest extends TestCase
{
    use PublishableDatabaseTrait;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    public function test_a_component_that_is_not_publishable_counts_its_own_positions(): void
    {
        $component = new DummyComponent();
        $this->entityManager->persist($component);
        $this->place($component, 2);

        $usage = $this->factory()->create($component);

        self::assertSame(2, $usage->getPositionCount());
        self::assertSame(0, $usage->getPageDataCount());
        self::assertSame(2, $usage->getTotal());
    }

    public function test_a_draft_that_is_not_active_counts_the_positions_of_its_published_component(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $this->place($published, 1);

        self::assertSame(1, $this->factory()->create($draft)->getPositionCount());
        self::assertSame(1, $this->factory()->create($published)->getPositionCount());
    }

    public function test_a_component_whose_publication_date_has_passed_counts_its_own_positions_even_while_it_still_holds_a_published_component(): void
    {
        [$published, $due] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('-1 day'));
        $this->place($published, 1);

        self::assertSame(0, $this->factory()->create($due)->getPositionCount());
    }

    public function test_a_component_whose_publication_date_is_in_the_future_counts_the_positions_of_its_published_component(): void
    {
        [$published, $scheduled] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), new \DateTime('+1 day'));
        $this->place($published, 1);

        self::assertSame(1, $this->factory()->create($scheduled)->getPositionCount());
    }

    public function test_each_page_data_property_holding_the_component_is_counted(): void
    {
        $component = new DummyComponent();
        $holding = new PageDataWithComponent();
        $holding->component = $component;
        $other = new PageDataWithComponent();
        $other->component = new DummyComponent();
        $pageDataProvider = $this->createStub(PageDataProvider::class);
        $pageDataProvider->method('findPageDataComponentMetadata')->willReturn([
            new PageDataComponentMetadata([$holding, $other], new ArrayCollection(['component', 'publishableComponent'])),
            new PageDataComponentMetadata([$holding], new ArrayCollection(['component'])),
        ]);
        $this->entityManager->persist($component);
        $this->entityManager->flush();

        $usage = $this->factory($pageDataProvider)->create($component);

        self::assertSame(0, $usage->getPositionCount());
        self::assertSame(2, $usage->getPageDataCount());
    }

    public function test_the_locations_are_left_out_unless_asked_for(): void
    {
        $component = new DummyComponent();
        $this->entityManager->persist($component);
        $this->place($component, 1);

        self::assertNull($this->factory()->create($component)->getLocations());
    }

    public function test_the_locations_list_one_entry_per_counted_position_and_page_data_property(): void
    {
        $component = new DummyComponent();
        $page = new Page();
        $page->reference = 'template';
        $page->isTemplate = true;
        $page->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $holding = new PageDataWithComponent();
        $holding->page = $page;
        $holding->setTitle('Holding');
        $holding->component = $component;
        $holding->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($component);
        $this->entityManager->persist($page);
        $this->entityManager->persist($holding);
        $this->place($component, 2);
        $pageDataProvider = $this->createStub(PageDataProvider::class);
        $pageDataProvider->method('findPageDataComponentMetadata')->willReturn([
            new PageDataComponentMetadata([$holding], new ArrayCollection(['component'])),
        ]);

        $usage = $this->factory($pageDataProvider)->create($component, true);

        $locations = $usage->getLocations() ?? [];
        self::assertCount($usage->getTotal(), $locations);
        self::assertCount(2, array_filter(array_column($locations, 'componentPosition')));
        self::assertSame(['component'], array_values(array_filter(array_column($locations, 'property'))));
    }

    private function place(AbstractComponent $component, int $times): void
    {
        $group = new ComponentGroup();
        $group->reference = 'group';
        $group->location = 'group';
        $group->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($group);
        for ($i = 0; $i < $times; ++$i) {
            $position = new ComponentPosition();
            $position->componentGroup = $group;
            $position->component = $component;
            $position->sortValue = $i;
            $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
            $this->entityManager->persist($position);
        }
        $this->entityManager->flush();
    }

    private function factory(?PageDataProvider $pageDataProvider = null): ComponentUsageMetadataFactory
    {
        if (null === $pageDataProvider) {
            $pageDataProvider = $this->createStub(PageDataProvider::class);
            $pageDataProvider->method('findPageDataComponentMetadata')->willReturn([]);
        }

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (object $resource): string => \sprintf('/%s/%s', $resource::class, spl_object_id($resource)));

        return new ComponentUsageMetadataFactory(
            new ComponentPositionRepository($this->registry),
            $pageDataProvider,
            $this->publishableStatusChecker,
            $this->registry,
            new ComponentUsageLocator($this->registry, new PublishableAttributeReader($this->registry), $iriConverter),
        );
    }
}
