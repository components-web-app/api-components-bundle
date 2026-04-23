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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\PageDataInterface;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Helper\OrphanedResourceHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
readonly class DeletedResourceEventListener {
    public function __construct(
        private OrphanedResourceHelper $orphanedResourceHelper) {
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        $resourceClass = $request->attributes->get('_api_resource_class');
        // only listen for deleted
        if (!$request->isMethod(Request::METHOD_DELETE)) {
            return;
        }

        if ($data instanceof ComponentPosition) {
            $this->orphanedResourceHelper->handleRemovedComponentPosition($data);
            return;
        }

        if ($data instanceof Page || $data instanceof AbstractComponent || $data instanceof Layout) {
            $this->orphanedResourceHelper->handleRemovedRootResource($data);
        }

        if ($data instanceof ComponentGroup) {
            $this->orphanedResourceHelper->handleRemovedComponentGroup($data);
        }

        if ($data instanceof PageDataInterface) {
            $this->orphanedResourceHelper->handleRemovedPageData($data, $resourceClass);
        }

        if ($data instanceof RoutableInterface) {
            $this->orphanedResourceHelper->handleRemovedRoutable($data);
        }
    }
}
