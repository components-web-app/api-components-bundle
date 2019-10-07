<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Security\EventListener;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\Content\Page\AbstractPage;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Silverback\ApiComponentBundle\Entity\RestrictedResourceInterface;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Denies access to content location or component resources if the content requires a security role not obtained by the user
 * or the component or location is not included in any contents which is accessible with the current user roles (or anonymous)
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class DenyAccessListener
{
    private $authorizedChecker;

    public function __construct(AuthorizedChecker $authorizedChecker)
    {
        $this->authorizedChecker = $authorizedChecker;
    }

    /**
     * @throws AccessDeniedException
     */
    public function onSecurity(ViewEvent $event): void
    {
        if ($this->authorizedChecker->isAuthorized()) {
            return;
        }
        $resource = $event->getRequest()->attributes->get('data');
        if ($resource) {
            $this->checkSecurity($resource);
        }
    }

    /**
     * @throws AccessDeniedException
     */
    private function checkSecurity($resource): void
    {
        if ($resource instanceof RestrictedResourceInterface) {
            $this->isResourceRestricted($resource);
        }elseif ($resource instanceof DynamicContent) {
            $this->isDynamicContentRestricted($resource);
        }elseif ($resource instanceof Route) {
            $this->isRouteRestricted($resource);
        }elseif ($resource instanceof  ComponentLocation) {
            $this->isComponentLocationRestricted($resource);
        }elseif ($resource instanceof AbstractComponent) {
            $this->isComponentRestricted($resource);
        }
    }

    private function isComponentRestricted(AbstractComponent $component): void
    {
        $locations = $component->getLocations();
        foreach ($locations as $componentLocation) {
            $this->isComponentLocationRestricted($componentLocation);
        }
    }

    private function isComponentLocationRestricted(ComponentLocation $componentLocation): void
    {
        if (($content = $componentLocation->getContent()) instanceof RestrictedResourceInterface) {
            $this->isResourceRestricted($content);
        }
    }

    private function isRouteRestricted(Route $route): void
    {
        if ($page = $route->getStaticPage()) {
            $this->isResourceRestricted($page);
        }
    }

    private function isDynamicContentRestricted(DynamicContent $dynamicContent): void
    {
        if ($page = $dynamicContent->getDynamicPage()) {
            $this->isResourceRestricted($page);
        }
    }

    private function isResourceRestricted(RestrictedResourceInterface $resource): void
    {
        if ($resource instanceof AbstractPage) {
            if ($parent = $resource->getParent()) {
                $this->isResourceRestricted($parent);
            }
            if ($parentRoute = $resource->getParentRoute()) {
                $this->isRouteRestricted($parentRoute);
            }
        }
        if (!($roles = $resource->getSecurityRoles())) {
            return;
        }
        $checker = $this->authorizedChecker->getAuthorizationChecker();
        foreach ($roles as $role) {
            if ($checker->isGranted($role)) {
                return;
            }
        }
        throw new AccessDeniedException('ACB: Access Denied.');
    }
}
