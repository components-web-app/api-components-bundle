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

namespace Silverback\ApiComponentsBundle\Security\Voter;

use ApiPlatform\Core\Api\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteVoter extends Voter
{
    public const ROUTE_READ = 'route_read';

    private AccessMapInterface $accessMap;
    private IriConverterInterface $iriConverter;
    private Security $security;

    public function __construct(AccessMapInterface $accessMap, IriConverterInterface $iriConverter, Security $security)
    {
        $this->accessMap = $accessMap;
        $this->iriConverter = $iriConverter;
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return self::ROUTE_READ === $attribute && $subject instanceof Route;
    }

    protected function voteOnAttribute(string $attribute, $route, TokenInterface $token): bool
    {
        $routeIri = $this->iriConverter->getIriFromResourceClass(Route::class);
        [$roles] = $this->accessMap->getPatterns(Request::create(sprintf('%s/%s', $routeIri, $route->getPath()), 'GET'));
        if ($roles) {
            foreach ($roles as $role) {
                if ($this->security->isGranted($role)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
