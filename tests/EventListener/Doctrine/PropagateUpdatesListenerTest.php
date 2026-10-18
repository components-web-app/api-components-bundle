<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Doctrine;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\EventListener\Console\FixtureLoadListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PropagateUpdatesListener;
use Silverback\ApiComponentsBundle\HttpCache\ResourceChangedPropagatorInterface;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentPositionRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Contracts\Service\ResetInterface;

class PropagateUpdatesListenerTest extends TestCase
{
    public function test_a_reset_after_a_flush_that_never_completed_leaves_nothing_for_the_next_flush(): void
    {
        $positionRepository = $this->createMock(ComponentPositionRepository::class);
        $positionRepository->expects(self::never())->method('findByPageDataProperties');
        $collectionRepository = $this->createMock(EntityRepository::class);
        $collectionRepository->expects(self::never())->method('findBy');
        $listener = $this->listener($positionRepository, $collectionRepository);

        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new PageData()], ['title' => ['old', 'new']])));

        self::assertInstanceOf(ResetInterface::class, $listener);
        $listener->reset();

        $listener->postFlush(new PostFlushEventArgs($this->entityManager([], [])));
    }

    public function test_without_a_reset_the_next_flush_propagates_what_the_unfinished_flush_collected(): void
    {
        $positionRepository = $this->createMock(ComponentPositionRepository::class);
        $positionRepository->expects(self::once())->method('findByPageDataProperties')->with(['title'])->willReturn([]);
        $collectionRepository = $this->createMock(EntityRepository::class);
        $collectionRepository->expects(self::once())->method('findBy')->willReturn([]);
        $listener = $this->listener($positionRepository, $collectionRepository);

        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new PageData()], ['title' => ['old', 'new']])));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager([], [])));
    }

    public function test_while_fixtures_load_a_flush_collects_and_propagates_nothing(): void
    {
        $positionRepository = $this->createMock(ComponentPositionRepository::class);
        $positionRepository->expects(self::never())->method('findByPageDataProperties');
        $collectionRepository = $this->createMock(EntityRepository::class);
        $collectionRepository->expects(self::never())->method('findBy');
        $propagator = $this->createMock(ResourceChangedPropagatorInterface::class);
        $propagator->expects(self::never())->method('add');
        $propagator->expects(self::never())->method('propagate');
        $fixtureLoad = new FixtureLoadListener();
        $fixtureLoad->onConsoleCommand(new ConsoleCommandEvent(new Command('doctrine:fixtures:load'), new ArrayInput([]), new NullOutput()));
        $listener = $this->listener($positionRepository, $collectionRepository, $propagator, $fixtureLoad);

        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new PageData()], ['title' => ['old', 'new']])));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager([], [])));
    }

    public function test_after_the_fixture_load_terminates_a_flush_propagates_again(): void
    {
        $positionRepository = $this->createStub(ComponentPositionRepository::class);
        $positionRepository->method('findByPageDataProperties')->willReturn([]);
        $collectionRepository = $this->createStub(EntityRepository::class);
        $collectionRepository->method('findBy')->willReturn([]);
        $propagator = $this->createMock(ResourceChangedPropagatorInterface::class);
        $propagator->expects(self::atLeastOnce())->method('add');
        $propagator->expects(self::once())->method('propagate');
        $fixtureLoad = new FixtureLoadListener();
        $command = new Command('doctrine:fixtures:load');
        $fixtureLoad->onConsoleCommand(new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput()));
        $fixtureLoad->onConsoleTerminate(new ConsoleTerminateEvent($command, new ArrayInput([]), new NullOutput(), Command::SUCCESS));
        $listener = $this->listener($positionRepository, $collectionRepository, $propagator, $fixtureLoad);

        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new PageData()], ['title' => ['old', 'new']])));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager([], [])));
    }

    /**
     * @return iterable<string, array{object, array<string, array<mixed>>, bool}>
     */
    public static function updatedEntities(): iterable
    {
        yield 'a publishable component whose publication date changed' => [new DummyPublishableComponent(), ['publishedAt' => [null, new \DateTime()]], true];
        yield 'a publishable component whose content changed' => [new DummyPublishableComponent(), ['reference' => ['old', 'new']], false];
        yield 'a component which is not publishable' => [new DummyComponent(), ['publishedAt' => [null, new \DateTime()]], false];
        yield 'a publishable resource which is not a component' => [new PropagateUpdatesPublishableResource(), ['publishedAt' => [null, new \DateTime()]], false];
    }

    #[DataProvider('updatedEntities')]
    public function test_only_a_change_to_the_publication_date_of_a_publishable_component_propagates_the_positions_holding_it(object $entity, array $changeSet, bool $propagatesPositions): void
    {
        $position = new ComponentPosition();
        $positionRepository = $this->createMock(ComponentPositionRepository::class);
        $positionRepository->expects($propagatesPositions ? self::once() : self::never())->method('findByComponent')->with($entity)->willReturn([$position]);
        $propagator = $this->createStub(ResourceChangedPropagatorInterface::class);
        $added = [];
        $propagator->method('add')->willReturnCallback(static function (object $item, ?string $type) use (&$added): void {
            $added[] = [$item, $type];
        });
        $listener = $this->listener($positionRepository, $this->createStub(EntityRepository::class), $propagator, publishableAttributeReader: new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));

        $listener->onFlush(new OnFlushEventArgs($this->entityManager([], $changeSet, [$entity])));

        self::assertSame($propagatesPositions, \in_array([$position, 'updated'], $added, true));
    }

    public function test_without_a_publishable_attribute_reader_a_publication_change_propagates_no_positions(): void
    {
        $positionRepository = $this->createMock(ComponentPositionRepository::class);
        $positionRepository->expects(self::never())->method('findByComponent');
        $listener = $this->listener($positionRepository, $this->createStub(EntityRepository::class));

        $listener->onFlush(new OnFlushEventArgs($this->entityManager([], ['publishedAt' => [null, new \DateTime()]], [new DummyPublishableComponent()])));
    }

    public function test_a_publishable_component_inserted_with_a_publication_date_propagates_no_positions_of_its_own(): void
    {
        $positionRepository = $this->createMock(ComponentPositionRepository::class);
        $positionRepository->expects(self::never())->method('findByComponent');
        $listener = $this->listener($positionRepository, $this->createStub(EntityRepository::class), publishableAttributeReader: new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));

        $listener->onFlush(new OnFlushEventArgs($this->entityManager([new DummyPublishableComponent()], ['publishedAt' => [null, new \DateTime()]])));
    }

    private function listener(ComponentPositionRepository $positionRepository, EntityRepository $collectionRepository, ?ResourceChangedPropagatorInterface $propagator = null, ?FixtureLoadListener $fixtureLoad = null, ?PublishableAttributeReader $publishableAttributeReader = null): PropagateUpdatesListener
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getRepository')->willReturn($collectionRepository);
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/page_data/page_datas/1');
        $resourceClassResolver = $this->createStub(ResourceClassResolverInterface::class);
        $resourceClassResolver->method('getResourceClass')->willReturn(PageData::class);
        $pageDataProvider = $this->createStub(PageDataProvider::class);
        $pageDataProvider->method('findPageDataComponentMetadata')->willReturn([]);

        return new PropagateUpdatesListener(
            $iriConverter,
            $registry,
            [$propagator ?? $this->createStub(ResourceChangedPropagatorInterface::class)],
            $resourceClassResolver,
            $pageDataProvider,
            $positionRepository,
            null,
            $fixtureLoad,
            $publishableAttributeReader,
        );
    }

    /**
     * @param list<object>                $insertions
     * @param array<string, array<mixed>> $changeSet
     * @param list<object>                $updates
     */
    private function entityManager(array $insertions, array $changeSet, array $updates = []): EntityManagerInterface
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn($insertions);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn($updates);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);
        $unitOfWork->method('getEntityChangeSet')->willReturn($changeSet);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getAssociationMappings')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);

        return $entityManager;
    }
}

#[Publishable]
class PropagateUpdatesPublishableResource
{
}
