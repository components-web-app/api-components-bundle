<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Route;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class RouteReachabilityResolver
{
    /**
     * @var \WeakMap<AbstractPage, bool>
     */
    private \WeakMap $resolved;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly Security $security,
    ) {
        $this->resolved = new \WeakMap();
    }

    public function isReachable(AbstractPage $page): bool
    {
        if (isset($this->resolved[$page])) {
            return $this->resolved[$page];
        }

        $reachable = $this->walk($page);
        $this->resolved[$page] = $reachable;

        return $reachable;
    }

    private function walk(AbstractPage $page): bool
    {
        $queue = [$page];
        $visitedIds = [];

        while ($queue) {
            $current = array_shift($queue);

            $id = $current->getId()?->toString();
            if (null !== $id) {
                if (isset($visitedIds[$id])) {
                    continue;
                }
                $visitedIds[$id] = true;
            }

            $route = $current->getRoute();
            if (null !== $route && $this->security->isGranted(RouteVoter::READ_ROUTE, $route)) {
                return true;
            }

            foreach ($this->findDirectChildren($current) as $child) {
                $queue[] = $child;
            }
        }

        return false;
    }

    /**
     * @return AbstractPage[]
     */
    private function findDirectChildren(AbstractPage $parent): array
    {
        if (null === $parent->getId()) {
            return [];
        }

        $manager = $this->registry->getManagerForClass(Page::class);
        if (null === $manager) {
            return [];
        }

        $field = $parent instanceof Page ? 'parentPage' : 'parentPageData';

        return array_merge(
            $manager->getRepository(Page::class)->findBy([$field => $parent]),
            $manager->getRepository(AbstractPageData::class)->findBy([$field => $parent]),
        );
    }
}
