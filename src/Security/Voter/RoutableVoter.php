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
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * If a routable object does not have a route, implement the security configuration. Usually admin only access.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class RoutableVoter extends AbstractRoutableVoter
{
    private ?string $securityStr;
    private ResourceAccessCheckerInterface $resourceAccessChecker;

    public function __construct(?string $securityStr, ResourceAccessCheckerInterface $resourceAccessChecker)
    {
        $this->securityStr = $securityStr;
        $this->resourceAccessChecker = $resourceAccessChecker;
    }

    /**
     * @param RoutableInterface $routable
     */
    protected function voteOnAttribute(string $attribute, $routable, TokenInterface $token): bool
    {
        if (!$this->securityStr) {
            return true;
        }

        if ($routable->getRoute()) {
            return true;
        }

        return $this->resourceAccessChecker->isGranted(\get_class($routable), $this->securityStr);
    }
}
