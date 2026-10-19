<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentUsage;

/**
 * @phpstan-type RenderedOn array{iri: string, title: ?string, routePath: ?string}
 * @phpstan-type LayoutUse array{iri: string, reference: string}
 * @phpstan-type Location array{componentPosition: ?string, pageDataProperty: ?string, pageData: ?string, property: ?string, layout: ?LayoutUse, renderedOn: list<RenderedOn>}
 * @phpstan-type PresentedLocation array{componentPosition: ?string, pageDataProperty: ?string, pageData: ?string, property: ?string, layout: ?LayoutUse, renderedOn: list<RenderedOn>, renderedOnCount: int}
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class ComponentUsageLocations
{
    public const int MAX_LOCATIONS = 50;
    public const int MAX_RENDERED_ON = 20;

    /**
     * @param list<Location> $locations
     *
     * @return list<PresentedLocation>
     */
    public static function present(array $locations): array
    {
        $presented = [];
        foreach ($locations as $location) {
            $renderedOn = self::sortedRenderedOn($location['renderedOn']);
            $location['renderedOn'] = $renderedOn;
            $presented[] = [$location, \count($renderedOn)];
        }

        usort($presented, static fn (array $a, array $b): int => self::compareLocations($a[0], $b[0]));

        return array_map(static function (array $entry): array {
            [$location, $count] = $entry;
            $location['renderedOn'] = \array_slice($location['renderedOn'], 0, self::MAX_RENDERED_ON);
            $location['renderedOnCount'] = $count;

            return $location;
        }, \array_slice($presented, 0, self::MAX_LOCATIONS));
    }

    /**
     * @param list<RenderedOn> $renderedOn
     *
     * @return list<RenderedOn>
     */
    private static function sortedRenderedOn(array $renderedOn): array
    {
        $unique = [];
        foreach ($renderedOn as $item) {
            $unique[$item['iri']] ??= $item;
        }
        $unique = array_values($unique);
        usort($unique, static fn (array $a, array $b): int => self::compareRenderedOn($a, $b));

        return $unique;
    }

    /**
     * @param RenderedOn $a
     * @param RenderedOn $b
     */
    private static function compareRenderedOn(array $a, array $b): int
    {
        return self::compareNullsLast($a['routePath'], $b['routePath'], strcmp(...))
            ?: self::compareNullsLast($a['title'], $b['title'], self::compareTitles(...))
            ?: strcmp($a['iri'], $b['iri']);
    }

    /**
     * @param Location $a
     * @param Location $b
     */
    private static function compareLocations(array $a, array $b): int
    {
        return self::rank($a) <=> self::rank($b)
            ?: strcmp($a['layout']['reference'] ?? '', $b['layout']['reference'] ?? '')
            ?: strcmp($a['renderedOn'][0]['routePath'] ?? '', $b['renderedOn'][0]['routePath'] ?? '')
            ?: self::compareNullsLast($a['renderedOn'][0]['title'] ?? null, $b['renderedOn'][0]['title'] ?? null, self::compareTitles(...))
            ?: strcmp(self::identifier($a), self::identifier($b));
    }

    /**
     * @param Location $location
     */
    private static function rank(array $location): int
    {
        if (null !== $location['layout']) {
            return 0;
        }
        $first = $location['renderedOn'][0] ?? null;
        if (null === $first) {
            return 3;
        }

        return null === $first['routePath'] ? 2 : 1;
    }

    /**
     * @param Location $location
     */
    private static function identifier(array $location): string
    {
        return ($location['componentPosition'] ?? '') . ($location['pageData'] ?? '') . ($location['property'] ?? '');
    }

    private static function compareTitles(string $a, string $b): int
    {
        return strcasecmp($a, $b) ?: strcmp($a, $b);
    }

    /**
     * @param \Closure(string, string): int $compare
     */
    private static function compareNullsLast(?string $a, ?string $b, \Closure $compare): int
    {
        if (null === $a || null === $b) {
            return (null === $a) <=> (null === $b);
        }

        return $compare($a, $b);
    }
}
