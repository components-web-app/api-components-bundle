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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Utility\PublicationDate;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class RouteLiveResolver
{
    /**
     * @var \WeakMap<Route, array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}>
     */
    private \WeakMap $resolved;

    public function __construct()
    {
        $this->resolved = new \WeakMap();
    }

    public function resolveEffectiveLiveAt(Route $route, ?AbstractPage $page = null): ?\DateTimeImmutable
    {
        if (null !== $page) {
            return $this->walk($route, $page);
        }

        $ownLiveAt = $route->getLiveAt();
        $memo = $this->resolved[$route] ?? null;
        if (null !== $memo && $memo[0] == $ownLiveAt) {
            return $memo[1];
        }

        $effective = $this->walk($route, null);
        $this->resolved[$route] = [$ownLiveAt, $effective];

        return $effective;
    }

    private function walk(Route $route, ?AbstractPage $page): ?\DateTimeImmutable
    {
        $latest = $route->getLiveAt();
        if (null === $latest) {
            return null;
        }

        $page ??= $route->getPage() ?? $route->getPageData();
        $visitedIds = [];

        while (null !== $page) {
            $id = $page->getId()?->toString();
            if (null !== $id) {
                if (isset($visitedIds[$id])) {
                    break;
                }
                $visitedIds[$id] = true;
            }

            $parent = $page->getParentPage() ?? $page->getParentPageData();
            if (null === $parent) {
                break;
            }

            $parentRoute = $parent->getRoute();
            if (null !== $parentRoute) {
                $parentLiveAt = $parentRoute->getLiveAt();
                if (null === $parentLiveAt) {
                    return null;
                }
                if ($parentLiveAt > $latest) {
                    $latest = $parentLiveAt;
                }
            }

            $page = $parent;
        }

        return $latest;
    }

    public function isLive(Route $route): bool
    {
        return PublicationDate::isActive($this->resolveEffectiveLiveAt($route));
    }
}
