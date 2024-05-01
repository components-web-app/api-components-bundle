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
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionEventListener
{
    use ClassMetadataTrait;

    private readonly PublishableAttributeReader $publishableAttributeReader;

    public function __construct(ManagerRegistry $registry, PublishableStatusChecker $publishableStatusChecker)
    {
        $this->initRegistry($registry);
        $this->publishableAttributeReader = $publishableStatusChecker->getAttributeReader();
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $this->removeEmptyPositions($event);
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $this->addVaryHeader($event);
    }

    private function addVaryHeader(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        $method = $request->getMethod();
        if (!$data instanceof ComponentPosition || Request::METHOD_GET !== $method) {
            return;
        }
        if ($data->getPageDataProperty()) {
            $response = $event->getResponse();
            $response->setVary('path', false);
        }
    }

    private function removeEmptyPositions(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        // if we are deleting a component - check the positions for deletion as well
        if (!$data instanceof AbstractComponent || !$request->isMethod(Request::METHOD_DELETE)) {
            return;
        }

        $positions = $data->getComponentPositions();
        $manager = $this->registry->getManagerForClass(ComponentPosition::class);
        if (!$manager) {
            return;
        }

        // if there is a draft resource, that should now be the one assigned to the component position resources we should not delete the positions
        $className = $request->attributes->get('_api_resource_class');
        $configuration = $this->publishableAttributeReader->getConfiguration($className);
        $classMetadata = $this->getClassMetadata($className);
        $draftResource = $classMetadata->getFieldValue($data, $configuration->reverseAssociationName) ?? $data;
        if ($draftResource) {
            foreach ($positions as $position) {
                $position->component = $draftResource;
            }
            return;
        }


        foreach ($positions as $position) {
            if (!$position->pageDataProperty) {
                $manager->remove($position);
            }
        }
    }
}
