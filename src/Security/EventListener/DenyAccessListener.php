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

namespace Silverback\ApiComponentsBundle\Security\EventListener;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class DenyAccessListener
{
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private RouteRepository $routeRepository;

    public function __construct(ResourceMetadataFactoryInterface $resourceMetadataFactory, RouteRepository $routeRepository)
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->routeRepository = $routeRepository;
    }

    public function onPreDeserialize(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$attributes = RequestAttributesExtractor::extractAttributes($request)) {
            return;
        }
        if (!is_a($resourceClass = $attributes['resource_class'], AbstractComponent::class, true)) {
            return;
        }
        dump($attributes);
        $this->routeRepository->findByComponent($resourceClass);
    }
}
