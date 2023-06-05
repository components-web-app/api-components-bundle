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

use ApiPlatform\Symfony\Security\ResourceAccessCheckerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Repository\Core\AbstractPageDataRepository;
use Silverback\ApiComponentsBundle\Security\EventListener\DenyAccessListener;
use Symfony\Bundle\SecurityBundle\Security;
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
    private Security $security;
    private AbstractPageDataRepository $pageDataRepository;
    private DenyAccessListener $denyAccessListener;

    public function __construct(?string $securityStr, ResourceAccessCheckerInterface $resourceAccessChecker, Security $security, AbstractPageDataRepository $pageDataRepository, DenyAccessListener $denyAccessListener)
    {
        $this->securityStr = $securityStr;
        $this->resourceAccessChecker = $resourceAccessChecker;
        $this->security = $security;
        $this->pageDataRepository = $pageDataRepository;
        $this->denyAccessListener = $denyAccessListener;
    }

    /**
     * @param RoutableInterface $routable
     */
    protected function voteOnAttribute(string $attribute, $routable, TokenInterface $token): bool
    {
        if (!$this->securityStr) {
            return true;
        }

        if ($route = $routable->getRoute()) {
            $isGranted = $this->security->isGranted(RouteVoter::READ_ROUTE, $route);
            if ($isGranted) {
                return true;
            }
        }

        if ($routable instanceof Page) {
            $pageData = $this->pageDataRepository->findBy([
                'page' => $routable,
            ]);
            foreach ($pageData as $pageDatum) {
                $isGranted = $this->denyAccessListener->isPageDataAllowedByRoute($pageDatum);
                if ($isGranted) {
                    return true;
                }
            }
        }

        return $this->resourceAccessChecker->isGranted($routable::class, $this->securityStr);
    }
}
