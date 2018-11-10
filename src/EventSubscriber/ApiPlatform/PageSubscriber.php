<?php

namespace Silverback\ApiComponentBundle\EventSubscriber\ApiPlatform;

use ApiPlatform\Core\EventListener\EventPriorities;
use Silverback\ApiComponentBundle\Entity\Content\Page\Page;
use Silverback\ApiComponentBundle\Repository\LayoutRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PageSubscriber extends AbstractSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                ['provideDefaultLayout', EventPriorities::PRE_SERIALIZE]
            ]
        ];
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . LayoutRepository::class
        ];
    }

    public function provideDefaultLayout(GetResponseForControllerResultEvent $event): void
    {
        $page = $event->getControllerResult();

        if (!$page instanceof Page || $page->getLayout()) {
            return;
        }

        /** @var LayoutRepository $repository */
        $repository = $this->container->get(LayoutRepository::class);
        $page->setLayout($repository->findOneBy(['default' => true]));
    }
}
