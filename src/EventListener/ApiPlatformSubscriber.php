<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventListener;

use ApiPlatform\Core\EventListener\EventPriorities;
use Silverback\ApiComponentBundle\Entity\Core\Route;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ApiPlatformSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['callComponentPopulators', EventPriorities::PRE_SERIALIZE],
        ];
    }

    public function callComponentPopulators(ViewEvent $event): void
    {
        $route = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();
        if (!$route instanceof Route || Request::METHOD_GET !== $method) {
            return;
        }
        // here we will call a service which will be aware of all the component populators
        // and call all of them that support the page data on the route
        //
    }
}
