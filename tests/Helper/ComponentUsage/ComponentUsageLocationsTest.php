<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentUsage;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\ComponentUsage\ComponentUsageLocations;

class ComponentUsageLocationsTest extends TestCase
{
    public function test_rendered_on_is_sorted_by_route_path_then_unrouted_by_title_case_insensitively_with_untitled_last(): void
    {
        $presented = ComponentUsageLocations::present([self::position('/p', null, [
            self::page('/untitled', null, null),
            self::page('/beta', 'beta', null),
            self::page('/b', 'Zed', '/b/path'),
            self::page('/upper', 'Alpha', null),
            self::page('/a', 'Zed', '/a/path'),
            self::page('/lower', 'alpha', null),
            self::page('/charlie', 'Charlie', null),
        ])]);

        self::assertSame(['/a', '/b', '/upper', '/lower', '/beta', '/charlie', '/untitled'], array_column($presented[0]['renderedOn'], 'iri'));
        self::assertSame(7, $presented[0]['renderedOnCount']);
    }

    public function test_rendered_on_with_the_same_path_and_title_is_ordered_by_iri(): void
    {
        $presented = ComponentUsageLocations::present([self::position('/p', null, [
            self::page('/z', 'Same', '/same'),
            self::page('/y', 'Same', '/same'),
        ])]);

        self::assertSame(['/y', '/z'], array_column($presented[0]['renderedOn'], 'iri'));
    }

    public function test_a_page_reached_twice_is_rendered_on_once_and_counted_once(): void
    {
        $presented = ComponentUsageLocations::present([self::position('/p', null, [
            self::page('/one', 'One', '/one'),
            self::page('/one', 'One', '/one'),
        ])]);

        self::assertSame([self::page('/one', 'One', '/one')], $presented[0]['renderedOn']);
        self::assertSame(1, $presented[0]['renderedOnCount']);
    }

    public function test_rendered_on_is_capped_after_sorting_and_its_count_stays_exact(): void
    {
        $pages = [];
        for ($index = ComponentUsageLocations::MAX_RENDERED_ON; $index >= 0; --$index) {
            $pages[] = self::page(\sprintf('/%02d', $index), null, \sprintf('/%02d', $index));
        }

        $presented = ComponentUsageLocations::present([self::position('/p', null, $pages)]);

        self::assertCount(20, $presented[0]['renderedOn']);
        self::assertSame('/00', $presented[0]['renderedOn'][0]['iri']);
        self::assertSame('/19', $presented[0]['renderedOn'][19]['iri']);
        self::assertSame(21, $presented[0]['renderedOnCount']);
    }

    public function test_locations_are_ordered_layouts_by_reference_then_routed_by_path_then_unrouted_by_title_then_unrendered(): void
    {
        $presented = ComponentUsageLocations::present([
            self::position('/unrendered', null, []),
            self::position('/unrouted-b', null, [self::page('/page-b', 'beta', null)]),
            self::position('/routed-z', null, [self::page('/page-z', 'A', '/z')]),
            self::position('/layout-b', ['iri' => '/l2', 'reference' => 'b'], [self::page('/page-a', 'A', '/a')]),
            self::position('/unrouted-a', null, [self::page('/page-a2', 'Alpha', null)]),
            self::position('/routed-a', null, [self::page('/page-m', 'Z', '/m'), self::page('/page-a3', 'Z', '/a')]),
            self::position('/layout-a', ['iri' => '/l1', 'reference' => 'a'], []),
        ]);

        self::assertSame(['/layout-a', '/layout-b', '/routed-a', '/routed-z', '/unrouted-a', '/unrouted-b', '/unrendered'], array_column($presented, 'componentPosition'));
    }

    public function test_locations_tied_on_their_first_page_are_ordered_by_position_then_page_data_and_property(): void
    {
        $page = self::page('/one', 'One', '/one');
        $presented = ComponentUsageLocations::present([
            self::pageDataUse('/pd', 'second', [$page]),
            self::position('/b', null, [$page]),
            self::pageDataUse('/pd', 'first', [$page]),
            self::position('/a', null, [$page]),
        ]);

        self::assertSame(['/a', '/b', null, null], array_column($presented, 'componentPosition'));
        self::assertSame(['first', 'second'], array_values(array_filter(array_column($presented, 'property'))));
    }

    public function test_untitled_unrouted_locations_follow_titled_ones(): void
    {
        $presented = ComponentUsageLocations::present([
            self::position('/untitled', null, [self::page('/u', null, null)]),
            self::position('/titled', null, [self::page('/t', 'Zed', null)]),
        ]);

        self::assertSame(['/titled', '/untitled'], array_column($presented, 'componentPosition'));
    }

    public function test_locations_are_capped_at_50_after_sorting(): void
    {
        $locations = [];
        for ($index = ComponentUsageLocations::MAX_LOCATIONS; $index >= 0; --$index) {
            $locations[] = self::position(\sprintf('/%02d', $index), null, [self::page('/page', 'Page', \sprintf('/%02d', $index))]);
        }

        $presented = ComponentUsageLocations::present($locations);

        self::assertCount(50, $presented);
        self::assertSame('/00', $presented[0]['componentPosition']);
        self::assertSame('/49', $presented[49]['componentPosition']);
    }

    public function test_a_location_keeps_its_fields_and_gains_its_rendered_on_count(): void
    {
        $layout = ['iri' => '/layout', 'reference' => 'main'];

        self::assertSame([[
            'componentPosition' => '/position',
            'pageDataProperty' => 'component',
            'pageData' => null,
            'property' => null,
            'layout' => $layout,
            'renderedOn' => [],
            'renderedOnCount' => 0,
        ]], ComponentUsageLocations::present([[
            'componentPosition' => '/position',
            'pageDataProperty' => 'component',
            'pageData' => null,
            'property' => null,
            'layout' => $layout,
            'renderedOn' => [],
        ]]));
    }

    /**
     * @param array{iri: string, reference: string}|null                   $layout
     * @param list<array{iri: string, title: ?string, routePath: ?string}> $renderedOn
     *
     * @return array{componentPosition: ?string, pageDataProperty: ?string, pageData: ?string, property: ?string, layout: ?array{iri: string, reference: string}, renderedOn: list<array{iri: string, title: ?string, routePath: ?string}>}
     */
    private static function position(string $iri, ?array $layout, array $renderedOn): array
    {
        return ['componentPosition' => $iri, 'pageDataProperty' => null, 'pageData' => null, 'property' => null, 'layout' => $layout, 'renderedOn' => $renderedOn];
    }

    /**
     * @param list<array{iri: string, title: ?string, routePath: ?string}> $renderedOn
     *
     * @return array{componentPosition: ?string, pageDataProperty: ?string, pageData: ?string, property: ?string, layout: ?array{iri: string, reference: string}, renderedOn: list<array{iri: string, title: ?string, routePath: ?string}>}
     */
    private static function pageDataUse(string $iri, string $property, array $renderedOn): array
    {
        return ['componentPosition' => null, 'pageDataProperty' => null, 'pageData' => $iri, 'property' => $property, 'layout' => null, 'renderedOn' => $renderedOn];
    }

    /**
     * @return array{iri: string, title: ?string, routePath: ?string}
     */
    private static function page(string $iri, ?string $title, ?string $routePath): array
    {
        return ['iri' => $iri, 'title' => $title, 'routePath' => $routePath];
    }
}
