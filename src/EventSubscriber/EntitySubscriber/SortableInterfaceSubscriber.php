<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\AbstractDynamicPage;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Repository\ContentRepository;

class SortableInterfaceSubscriber implements EntitySubscriberInterface
{
    private $contentRepository;

    public function __construct(
        ContentRepository $contentRepository
    )
    {
        $this->contentRepository = $contentRepository;
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
    public function prePersist(LifecycleEventArgs $eventArgs, SortableInterface $entity): void
    {
        if ($entity->getSort() === null) {
            $collection = $entity->getSortCollection();
            if ($collection === null && $entity instanceof AbstractDynamicPage) {
                $collection = $this->contentRepository->findPageByType(get_class($entity));
            }
            $entity->setSort($entity->calculateSort(true, $collection));
        }
    }
}
