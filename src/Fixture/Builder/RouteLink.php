<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Builder;

final class RouteLink
{
    /**
     * @return array{string, string|PageBuilder|PageDataBuilder}
     */
    public static function to(string $property, ?string $routeName, PageBuilder|PageDataBuilder|null $page): array
    {
        $given = (int) (null !== $routeName) + (int) (null !== $page);
        if (1 !== $given) {
            throw new \InvalidArgumentException(\sprintf('linkRoute() for the property "%s" needs exactly one of routeName or page; %d given.', $property, $given));
        }

        return [$property, $page ?? (string) $routeName];
    }
}
