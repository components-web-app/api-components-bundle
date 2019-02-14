<?php

namespace Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber;

interface EntitySubscriberInterface
{
    /**
     * Returns a boolean as to whether the entity that is being processed is supported by this subscriber
     *
     * @param null $entity
     * @return bool
     */
    public function supportsEntity($entity = null): bool;

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return string[]
     */
    public function getSubscribedEvents(): array;
}
