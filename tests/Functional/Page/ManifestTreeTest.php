<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;

final class ManifestTreeTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use JsonTrait;
    use PageSiteTrait;

    public function test_a_flat_manifest_nests_each_resource_under_the_resource_that_holds_it(): void
    {
        $client = static::createClient();
        $site = self::createRoutedPageWithComponent('/my-route');
        $nested = self::giveComponentAGroupHoldingAComponent($site['component'], 'nested');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            self::node(self::iriOf($site['route']), [
                self::node(self::iriOf($site['page']), [
                    self::node(self::iriOf($site['layout'])),
                    self::node(self::iriOf($site['group']), [
                        self::node(self::iriOf($site['position']), [
                            self::node(self::iriOf($site['component']), [
                                self::node(self::iriOf($nested['group']), [
                                    self::node(self::iriOf($nested['positions'][0]), [
                                        self::node(self::iriOf($nested['components'][0])),
                                    ]),
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ], self::jsonNode('resource_iris'));
    }

    public function test_a_nested_manifest_has_one_tree_per_rendering_depth_rooted_at_the_ancestor_first(): void
    {
        $client = static::createClient();
        $site = self::createNestedPages('/conference/programme', '/conference');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//conference/programme');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            self::node(self::iriOf($site['parentPage']), [
                self::node(self::iriOf($site['parentRoute'])),
            ]),
            self::node(self::iriOf($site['route']), [
                self::node(self::iriOf($site['page'])),
            ]),
        ], self::jsonNode('resource_iris'));
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array{iri: string, children: list<array<string, mixed>>}
     */
    private static function node(string $iri, array $children = []): array
    {
        return ['iri' => $iri, 'children' => $children];
    }
}
