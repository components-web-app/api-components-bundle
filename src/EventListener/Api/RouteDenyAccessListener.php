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

use ApiPlatform\Core\Api\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteDenyAccessListener
{
    private AccessMapInterface $accessMap;
    private IriConverterInterface $iriConverter;
    private Security $security;

    public function __construct(AccessMapInterface $accessMap, IriConverterInterface $iriConverter, Security $security)
    {
        $this->accessMap = $accessMap;
        $this->iriConverter = $iriConverter;
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

    private function checkSecurity(Route $route): void
    {
        $routeIri = $this->iriConverter->getIriFromResourceClass(Route::class);
        [$roles] = $this->accessMap->getPatterns(Request::create(sprintf('%s/%s', $routeIri, $route->getPath()), 'GET'));
        if ($roles) {
            foreach ($roles as $role) {
                if ($this->security->isGranted($role)) {
                    return;
                }
            }
            throw new AccessDeniedException('Access denied.');
        }
    }
}
