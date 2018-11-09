<?php

namespace Silverback\ApiComponentBundle\EventSubscriber\ApiPlatform;

use ApiPlatform\Core\EventListener\EventPriorities;
use Silverback\ApiComponentBundle\Entity\Content\Dynamic\AbstractDynamicPage;
use Silverback\ApiComponentBundle\Repository\ComponentLocationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DynamicPageSubscriber extends AbstractSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                ['setComponentLocations', EventPriorities::PRE_SERIALIZE]
            ]
        ];
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . ComponentLocationRepository::class
        ];
    }

    public function setComponentLocations(GetResponseForControllerResultEvent $event): void
    {
        $page = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();

        if (!$page instanceof AbstractDynamicPage || Request::METHOD_GET !== $method) {
            return;
        }

        /** @var ComponentLocationRepository $repository */
        $repository = $this->container->get(ComponentLocationRepository::class);
        $locations = $repository->findByDynamicPage($page);
        if (!empty($locations)) {
            $page->setComponentLocations($locations);
        }
    }
}
