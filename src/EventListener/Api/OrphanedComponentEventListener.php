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
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\PageDataInterface;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class OrphanedComponentEventListener
{
    private PageDataMetadataFactoryInterface $pageDataMetadataFactory;
    private ComponentUsageMetadataFactory $usageMetadataFactory;
    private ManagerRegistry $registry;

    public function __construct(PageDataMetadataFactoryInterface $pageDataMetadataFactory, ComponentUsageMetadataFactory $usageMetadataFactory, ManagerRegistry $registry)
    {
        $this->pageDataMetadataFactory = $pageDataMetadataFactory;
        $this->usageMetadataFactory = $usageMetadataFactory;
        $this->registry = $registry;
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        $resourceClass = $request->attributes->get('_api_resource_class');
        if (!$request->isMethod(Request::METHOD_DELETE)) {
            return;
        }

        if ($data instanceof ComponentPosition) {
            if ($data->component) {
                $this->removeOrphanedComponent($data->component, $resourceClass);
            }

            return;
        }
        if ($data instanceof PageDataInterface) {
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $pageDataMetadata = $this->pageDataMetadataFactory->create($resourceClass);
            foreach ($pageDataMetadata->getProperties() as $property) {
                $component = $propertyAccessor->getValue($data, $property->getProperty());
                if ($component instanceof ComponentInterface) {
                    $this->removeOrphanedComponent($component, $resourceClass);
                }
            }
        }
        if ($data instanceof RoutableInterface) {
            $route = $data->getRoute();
            if ($route) {
                $routeAssociations = 0;
                $route->getPage() && $routeAssociations++;
                $route->getPageData() && $routeAssociations++;
                $route->getRedirect() && $routeAssociations++;
                if ($routeAssociations <= 1) {
                    $manager = $this->registry->getManagerForClass(Route::class);
                    $manager?->remove($route);
                }
            }
        }
    }

    private function removeOrphanedComponent(ComponentInterface $component, string $resourceClass): void
    {
        $metadata = $this->usageMetadataFactory->create($component);
        if (1 === $metadata->getTotal()) {
            $manager = $this->registry->getManagerForClass($resourceClass);
            $manager?->remove($component);
        }
    }
}
