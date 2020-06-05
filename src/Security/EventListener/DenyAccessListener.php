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

use ApiPlatform\Core\Util\RequestAttributesExtractor;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

/**
 * This will NOT restrict access to components fetched as a collection. As recomended by API Platform best practices, that should
 * be implemented in a Doctrine extension by the application developer.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class DenyAccessListener
{
    private RouteRepository $routeRepository;
    private Security $security;

    public function __construct(RouteRepository $routeRepository, Security $security)
    {
        $this->routeRepository = $routeRepository;
        $this->security = $security;
    }

    public function onPreDeserialize(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$attributes = RequestAttributesExtractor::extractAttributes($request)) {
            return;
        }
        $resourceClass = $attributes['resource_class'];
        if (
            !is_a($resourceClass, AbstractComponent::class, true) ||
            !($component = $request->attributes->get('data')) instanceof AbstractComponent ||
            Request::METHOD_POST === $request->getMethod()
        ) {
            return;
        }

        $routes = $this->routeRepository->findByComponent($component);
        if (!\count($routes)) {
            return;
        }

        foreach ($routes as $route) {
            if ($this->security->isGranted($route)) {
                return;
            }
        }

        throw new AccessDeniedException('Access denied.');
    }
}
