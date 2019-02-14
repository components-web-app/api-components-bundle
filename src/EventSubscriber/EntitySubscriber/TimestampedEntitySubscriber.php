<?php

namespace Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Silverback\ApiComponentBundle\Entity\TimestampedEntityInterface;

class TimestampedEntitySubscriber implements EntitySubscriberInterface
{
    public function supportsEntity($entity = null): bool
    {
        return $entity instanceof TimestampedEntityInterface;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist
        ];
    }

    public function prePersist(LifecycleEventArgs $args, TimestampedEntityInterface $entity): void
    {
        $entityManager = $args->getEntityManager();
        if (!$entityManager->contains($entity)) {
            $entity->setCreated(new \DateTimeImmutable());
        }
        $entity->setModified(new \DateTime());
    }
}
