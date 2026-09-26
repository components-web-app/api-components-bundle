<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ComponentPositionEventListener
{
    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (!$data instanceof ComponentPosition || !$request->isMethod(Request::METHOD_GET) || !$data->getPageDataProperty()) {
            return;
        }

        $event->getResponse()->setVary('path', false);
    }
}
