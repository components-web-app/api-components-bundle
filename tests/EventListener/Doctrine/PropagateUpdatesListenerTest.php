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
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PropagateUpdatesListener;
use Silverback\ApiComponentsBundle\HttpCache\ResourceChangedPropagatorInterface;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentPositionRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
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

    private function listener(ComponentPositionRepository $positionRepository, EntityRepository $collectionRepository): PropagateUpdatesListener
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
            [$this->createStub(ResourceChangedPropagatorInterface::class)],
            $resourceClassResolver,
            $pageDataProvider,
            $positionRepository,
        );
    }

    /**
     * @param list<object>                $insertions
     * @param array<string, array<mixed>> $changeSet
     */
    private function entityManager(array $insertions, array $changeSet): EntityManagerInterface
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn($insertions);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([]);
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
