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

use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteVoter extends Voter
{
    public const READ_ROUTE = 'read_route';
    private ?array $config;
    private ResourceAccessCheckerInterface $resourceAccessChecker;

    public function __construct(?array $config, ResourceAccessCheckerInterface $resourceAccessChecker)
    {
        $this->config = $config;
        $this->resourceAccessChecker = $resourceAccessChecker;
    }

    protected function supports($attribute, $subject): bool
    {
        return self::READ_ROUTE === $attribute && $subject instanceof Route && $this->config;
    }

    /**
     * @param Route $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        foreach ($this->config as $routeConfig) {
            $routeRegex = str_replace('\*', '(.*)', preg_quote($routeConfig['route'], '#'));
            if (!$this->resourceAccessChecker->isGranted($subject::class, $routeConfig['security']) && preg_match(\sprintf('#%s#', $routeRegex), $subject->getPath())) {
                return false;
            }
        }

        return true;
    }
}
