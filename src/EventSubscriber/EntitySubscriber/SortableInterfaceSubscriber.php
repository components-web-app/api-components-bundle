<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Silverback\ApiComponentBundle\Entity\SortableInterface;

class SortableInterfaceSubscriber implements EntitySubscriberInterface
{
    private $managerRegistry;

    public function __construct(
        ManagerRegistry $managerRegistry
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist
        ];
    }

    public function supportsEntity($entity = null): bool
    {
        return $entity instanceof SortableInterface;
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     * @param SortableInterface $entity
     */
    public function prePersist(/** @scrutinizer ignore-unused */ LifecycleEventArgs $eventArgs, SortableInterface $entity): void
    {
        if (
            $entity instanceof DynamicContent &&
            $entity->getSort() === null &&
            ($collection = $entity->getSortCollection()) === null
        ) {
            $resourceClass = \get_class($entity);
            $manager = $this->managerRegistry->getManagerForClass($resourceClass);
            if ($manager) {
                $repository = $manager->getRepository($resourceClass);
                $collection = new ArrayCollection($repository->findAll());
                if ($manager instanceof EntityManagerInterface) {
                    $scheduledInsertions = $manager->getUnitOfWork()->getScheduledEntityInsertions();
                    foreach ($scheduledInsertions as $scheduledInsertion) {
                        if ($scheduledInsertion !== $entity && is_a($scheduledInsertion, $resourceClass)) {
                            $collection->add($scheduledInsertion);
                        }
                    }
                }
            }
            $entity->setSort($entity->calculateSort(true, $collection));
        }
    }
}
