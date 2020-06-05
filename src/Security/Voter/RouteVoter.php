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

use ApiPlatform\Core\Security\ResourceAccessCheckerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteVoter extends Voter
{
    private ?array $config;
    private ResourceAccessCheckerInterface $resourceAccessChecker;

    public function __construct(?array $config, ResourceAccessCheckerInterface $resourceAccessChecker)
    {
        $this->config = $config;
        $this->resourceAccessChecker = $resourceAccessChecker;
    }

    protected function supports($subject, $notRequired): bool
    {
        return $subject instanceof Route && $this->config;
    }

    /**
     * @param Route $route
     */
    protected function voteOnAttribute($route, $notRequired, TokenInterface $token): bool
    {
        foreach ($this->config as $index => $routeConfig) {
            $routeRegex = str_replace('\*', '(.*)', preg_quote($routeConfig['route'], '#'));
            if (!$this->resourceAccessChecker->isGranted(\get_class($route), $routeConfig['security']) && preg_match(sprintf('#%s#', $routeRegex), $route->getPath())) {
                return false;
            }
        }

        return true;
    }
}
