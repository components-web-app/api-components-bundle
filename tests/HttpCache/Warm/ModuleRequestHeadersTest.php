<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\HttpCache\Warm;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\HttpCache\Warm\ModuleRequestHeaders;

class ModuleRequestHeadersTest extends TestCase
{
    private const string NEXT = '/componentGroups/*/componentPositions/*/component';

    #[DataProvider('preloads')]
    public function test_the_preload_is_the_modules_for_the_iris_resource_type(string $iri, string $apiPrefix, ?string $preload): void
    {
        self::assertSame($preload, ModuleRequestHeaders::preload($iri, $apiPrefix));
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function preloads(): iterable
    {
        $page = '/layout' . self::NEXT . self::NEXT . ',' . self::NEXT . self::NEXT;

        yield 'prefixed page' => ['/_api/_/pages/p1', '/_api', $page];
        yield 'page without the prefix it was given' => ['/_/pages/p1', '/_api', $page];
        yield 'page with no prefix' => ['/_/pages/p1', '', $page];
        yield 'the bare collection path of a type' => ['/_api/_/layouts', '/_api', self::NEXT . self::NEXT];
        yield 'a collection path one character short' => ['/_api/_/layout', '/_api', null];
        yield 'a manifest' => ['/_api/_/resource_manifest//about', '/_api', null];
        yield 'a component' => ['/_api/component/dummy_components/c1', '/_api', self::NEXT];
        yield 'a component collection' => ['/_api/component', '/_api', self::NEXT];
    }

    public function test_only_a_page_data_iri_is_page_data(): void
    {
        self::assertTrue(ModuleRequestHeaders::isPageData('/_api/page_data/conference_data/d1', '/_api'));
        self::assertFalse(ModuleRequestHeaders::isPageData('/_api/_/pages/p1', '/_api'));
    }

    public function test_the_route_and_manifest_iris_carry_the_prefix(): void
    {
        self::assertSame('/_api/_/routes/', ModuleRequestHeaders::routeIriPrefix('/_api'));
        self::assertSame('/_api/_/resource_manifest//about', ModuleRequestHeaders::manifestIri('/_api', '/about'));
    }
}
