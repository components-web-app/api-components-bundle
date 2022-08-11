<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PositionRemoveEventListener
{
    private ManagerRegistry $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (!$data instanceof AbstractComponent || !$request->isMethod(Request::METHOD_DELETE)) {
            return;
        }

        $positions = $data->getComponentPositions();
        $manager = $this->registry->getManagerForClass(ComponentPosition::class);
        if (!$manager) {
            return;
        }
        foreach ($positions as $position) {
            if (!$position->pageDataProperty) {
                $manager->remove($position);
            }
        }
    }
}
