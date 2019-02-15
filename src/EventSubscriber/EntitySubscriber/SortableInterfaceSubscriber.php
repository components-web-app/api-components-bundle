<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\AbstractDynamicPage;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Repository\ComponentLocationRepository;
use Silverback\ApiComponentBundle\Repository\ContentRepository;

class SortableInterfaceSubscriber implements EntitySubscriberInterface
{
    private $contentRepository;
    private $componentLocationRepository;

    public function __construct(
        ContentRepository $contentRepository,
        ComponentLocationRepository $componentLocationRepository
    )
    {
        $this->contentRepository = $contentRepository;
        $this->componentLocationRepository = $componentLocationRepository;
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
            if ($collection === null) {
                if ($entity instanceof AbstractDynamicPage) {
                    $collection = $this->contentRepository->findPageByType(get_class($entity));
                } elseif(
                    $entity instanceof ComponentLocation &&
                    ($dynamicPageClass = $entity->getDynamicPageClass())
                ) {
                    $collection = $this->componentLocationRepository->findByDynamicPage($dynamicPageClass);
                }
            }
            $entity->setSort($entity->calculateSort(true, $collection));
        }
    }
}
