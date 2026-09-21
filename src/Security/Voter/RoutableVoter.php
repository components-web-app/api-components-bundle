<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Security\Voter;

use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Helper\Route\RouteReachabilityResolver;
use Silverback\ApiComponentsBundle\Repository\Core\AbstractPageDataRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * A routable resource is readable when a route reaching it exists and is available now, otherwise
 * it falls back to the configured security expression. Usually admin only access.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class RoutableVoter extends AbstractRoutableVoter
{
    private ?string $securityStr;
    private ResourceAccessCheckerInterface $resourceAccessChecker;
    private AbstractPageDataRepository $pageDataRepository;
    private RouteReachabilityResolver $reachabilityResolver;

    public function __construct(?string $securityStr, ResourceAccessCheckerInterface $resourceAccessChecker, AbstractPageDataRepository $pageDataRepository, RouteReachabilityResolver $reachabilityResolver)
    {
        $this->securityStr = $securityStr;
        $this->resourceAccessChecker = $resourceAccessChecker;
        $this->pageDataRepository = $pageDataRepository;
        $this->reachabilityResolver = $reachabilityResolver;
    }

    /**
     * @param RoutableInterface $routable
     */
    protected function voteOnAttribute(string $attribute, mixed $routable, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$this->securityStr) {
            return true;
        }

        if ($routable instanceof AbstractPage && $this->reachabilityResolver->isReachable($routable)) {
            return true;
        }

        if ($routable instanceof Page) {
            $pageData = $this->pageDataRepository->findBy([
                'page' => $routable,
            ]);
            foreach ($pageData as $pageDatum) {
                if ($this->reachabilityResolver->isReachable($pageDatum)) {
                    return true;
                }
            }
        }

        return $this->resourceAccessChecker->isGranted($routable::class, $this->securityStr);
    }
}
