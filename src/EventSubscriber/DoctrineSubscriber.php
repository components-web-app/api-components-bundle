<?php

namespace Silverback\ApiComponentBundle\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber\EntitySubscriberInterface;

class DoctrineSubscriber implements EventSubscriber
{
    /** @var iterable|EntitySubscriberInterface[] */
    private $entitySubscribers;

    public function __construct(iterable $entitySubscribers)
    {
        $this->entitySubscribers = $entitySubscribers;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::preFlush,
            Events::onFlush,
            Events::preRemove
        ];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->runEntitySubscribers($args, Events::prePersist);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->runEntitySubscribers($args, Events::preUpdate);
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        $this->runEntitySubscribers($args, Events::preFlush);
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->runEntitySubscribers($args, Events::onFlush);
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $this->runEntitySubscribers($args, Events::preRemove);
    }

    private function runEntitySubscribers($args, string $event): void
    {
        $entity = ($args instanceof LifecycleEventArgs) ? $args->getEntity() : null;
        foreach ($this->entitySubscribers as $entitySubscriber) {
            if ($entitySubscriber->supportsEntity($entity) && \in_array($event, $entitySubscriber->getSubscribedEvents(), true)) {
                $entitySubscriber->$event($args, $entity);
            }
        }
    }
}
