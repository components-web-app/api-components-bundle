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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Security\Voter\ComponentVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * This will NOT restrict access to components fetched as a collection. As recommended by API Platform best practices, that should
 * be implemented in a Doctrine extension by the application developer.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class DenyAccessListener
{
    public function __construct(
        private readonly Security $security,
        private readonly RouteRepository $routeRepository
    ) {
    }

    public function onPreDeserialize(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $resource = $request->attributes->get('data');

        if ($resource instanceof AbstractComponent) {
            if ($this->security->isGranted(ComponentVoter::READ_COMPONENT, $resource)) {
                return;
            }
            throw new AccessDeniedException('Component access denied.');
        }

        if ($resource instanceof AbstractPageData) {
            if (false !== $this->isPageDataAllowedByRoute($resource)) {
                return;
            }
            throw new AccessDeniedException('Page data access denied.');
        }
    }

    public function isPageDataAllowedByRoute(AbstractPageData $pageData): ?bool
    {
        $routes = $this->routeRepository->findByPageData($pageData);

        // abstain - no route to check
        if (!\count($routes)) {
            return null;
        }

        foreach ($routes as $route) {
            $isGranted = $this->security->isGranted(RouteVoter::READ_ROUTE, $route);
            if ($isGranted) {
                return true;
            }
        }

        return false;
    }
}
