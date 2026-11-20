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

use Silverback\ApiComponentsBundle\AttributeReader\RoutePrefixAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class RoutePrefixResolver
{
    public function __construct(private RoutePrefixAttributeReader $routePrefixReader)
    {
    }

    public function requiredStart(RoutableInterface $routable): ?string
    {
        $ancestorPath = $this->nearestRoutedAncestorPath($routable);
        $prefix = $this->prefixOf($routable);
        if (null === $ancestorPath && null === $prefix) {
            return null;
        }

        return rtrim($ancestorPath ?? '', '/') . $prefix . '/';
    }

    public function prefixOf(RoutableInterface $routable): ?string
    {
        if (!$routable instanceof AbstractPageData || !$this->routePrefixReader->isConfigured($routable)) {
            return null;
        }

        return $this->routePrefixReader->getConfiguration($routable)->prefix;
    }

    private function nearestRoutedAncestorPath(RoutableInterface $routable): ?string
    {
        $visited = [$routable];
        $ancestor = $routable->getParentPage() ?? $routable->getParentPageData();
        while (null !== $ancestor && !\in_array($ancestor, $visited, true)) {
            $route = $ancestor->getRoute();
            if (null !== $route) {
                return $route->getPath();
            }
            $visited[] = $ancestor;
            $ancestor = $ancestor->getParentPage() ?? $ancestor->getParentPageData();
        }

        return null;
    }
}
