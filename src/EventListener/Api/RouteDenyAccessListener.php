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

use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteDenyAccessListener
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function onPostDeserialize(RequestEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('previous_data');
        if (!$route instanceof Route) {
            return;
        }
        $this->checkSecurity($route);
    }

    public function checkSecurity(Route $route): void
    {
        if (!$this->security->isGranted(RouteVoter::ROUTE_READ, $route)) {
            throw new AccessDeniedException('Access denied.');
        }
    }
}
