<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventSubscriber\ApiPlatform;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use Silverback\ApiComponentBundle\Entity\Component\Collection\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CollectionSubscriber extends AbstractSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                ['populateCollection', EventPriorities::PRE_SERIALIZE]
            ]
        ];
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . ContextAwareCollectionDataProviderInterface::class
        ];
    }

    public function populateCollection(GetResponseForControllerResultEvent $event): void
    {
        $collectionEntity = $event->getControllerResult();

        if (!$collectionEntity instanceof Collection) {
            return;
        }

        /** @var ContextAwareCollectionDataProviderInterface $dataProvider */
        $dataProvider = $this->container->get(ContextAwareCollectionDataProviderInterface::class);
        $dataProviderContext = [];
        $collection = $dataProvider->getCollection($collectionEntity->getResource(), Request::METHOD_GET, $dataProviderContext);
        $collectionEntity->setCollection($collection);
    }
}
