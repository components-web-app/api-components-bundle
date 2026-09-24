<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\Normalizer;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\Trait\ManifestDepthGroupTrait;

class ConcreteManifestDepthGroup
{
    use ManifestDepthGroupTrait;

    public function groups(array $resource): array
    {
        return $this->buildDepthGroups($resource);
    }
}

class ManifestDepthGroupTraitTest extends TestCase
{
    private ConcreteManifestDepthGroup $subject;

    protected function setUp(): void
    {
        $this->subject = new ConcreteManifestDepthGroup();
    }

    /**
     * Build an expected nested node { iri, children }.
     */
    private function n(string $iri, array ...$children): array
    {
        return ['iri' => $iri, 'children' => $children];
    }

    public function test_flat_resource_returns_single_depth_tree(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'page' => ['@id' => '/_/pages/abc'],
        ];

        $this->assertSame(
            [$this->n('/_/routes/home', $this->n('/_/pages/abc'))],
            $this->subject->groups($resource)
        );
    }

    public function test_resource_with_parent_page_returns_two_trees_root_first(): void
    {
        $resource = [
            '@id' => '/_/abstract_page_data/child-uuid',
            'page' => ['@id' => '/_/pages/child-page-uuid'],
            'parentPage' => [
                '@id' => '/_/pages/parent-uuid',
                'route' => ['@id' => '/_/routes/conference'],
            ],
        ];

        $this->assertSame(
            [
                $this->n('/_/pages/parent-uuid', $this->n('/_/routes/conference')),
                $this->n('/_/abstract_page_data/child-uuid', $this->n('/_/pages/child-page-uuid')),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_resource_with_parent_page_data_returns_two_trees_root_first(): void
    {
        $resource = [
            '@id' => '/_/abstract_page_data/child-uuid',
            'parentPageData' => [
                '@id' => '/_/abstract_page_data/parent-uuid',
                'route' => ['@id' => '/_/routes/conference'],
            ],
        ];

        $this->assertSame(
            [
                $this->n('/_/abstract_page_data/parent-uuid', $this->n('/_/routes/conference')),
                $this->n('/_/abstract_page_data/child-uuid'),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_two_level_nesting_returns_three_trees(): void
    {
        $resource = [
            '@id' => '/_/abstract_page_data/child-uuid',
            'parentPageData' => [
                '@id' => '/_/abstract_page_data/parent-uuid',
                'parentPage' => [
                    '@id' => '/_/pages/grandparent-uuid',
                ],
            ],
        ];

        $this->assertSame(
            [
                $this->n('/_/pages/grandparent-uuid'),
                $this->n('/_/abstract_page_data/parent-uuid'),
                $this->n('/_/abstract_page_data/child-uuid'),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_well_known_iris_are_filtered_out(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            '_metadata' => ['@id' => '/.well-known/genid/abc123'],
        ];

        $this->assertSame([$this->n('/_/routes/home')], $this->subject->groups($resource));
    }

    public function test_resource_metadata_collection_iri_is_filtered_out(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'something' => ['@id' => '/_/resource_metadatas'],
        ];

        $this->assertSame([$this->n('/_/routes/home')], $this->subject->groups($resource));
    }

    public function test_duplicate_iris_within_depth_tree_are_deduplicated(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'items' => [
                ['@id' => '/_/pages/abc'],
                ['@id' => '/_/pages/abc'],
            ],
        ];

        $this->assertSame(
            [$this->n('/_/routes/home', $this->n('/_/pages/abc'))],
            $this->subject->groups($resource)
        );
    }

    public function test_nested_arrays_of_sub_resources_are_walked(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'componentGroups' => [
                ['@id' => '/_/component_groups/cg1'],
                ['@id' => '/_/component_groups/cg2'],
            ],
        ];

        $this->assertSame(
            [$this->n('/_/routes/home', $this->n('/_/component_groups/cg1'), $this->n('/_/component_groups/cg2'))],
            $this->subject->groups($resource)
        );
    }

    public function test_containment_is_preserved_as_nesting(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'pageData' => [
                '@id' => '/page_data/pd1',
                'page' => [
                    '@id' => '/_/pages/p1',
                    'componentGroups' => [
                        [
                            '@id' => '/_/component_groups/cg1',
                            'componentPositions' => [
                                [
                                    '@id' => '/_/component_positions/cp1',
                                    'component' => '/component/dummy/c1',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(
            [$this->n(
                '/_/routes/home',
                $this->n(
                    '/page_data/pd1',
                    $this->n(
                        '/_/pages/p1',
                        $this->n(
                            '/_/component_groups/cg1',
                            $this->n(
                                '/_/component_positions/cp1',
                                $this->n('/component/dummy/c1')
                            )
                        )
                    )
                )
            ), ],
            $this->subject->groups($resource)
        );
    }

    public function test_parent_iri_does_not_appear_in_child_tree(): void
    {
        $resource = [
            '@id' => '/_/abstract_page_data/child-uuid',
            'parentPage' => ['@id' => '/_/pages/parent-uuid'],
        ];

        $this->assertSame(
            [
                $this->n('/_/pages/parent-uuid'),
                $this->n('/_/abstract_page_data/child-uuid'),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_string_iri_property_value_is_collected(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'layout' => '/_/layouts/abc',
        ];

        $this->assertSame(
            [$this->n('/_/routes/home', $this->n('/_/layouts/abc'))],
            $this->subject->groups($resource)
        );
    }

    public function test_blank_node_string_iri_properties_are_not_collected(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'metadata' => [
                '@id' => '/.well-known/genid/abc123',
                'something' => '/_/page_data_metadatas/uuid',
            ],
        ];

        $this->assertSame([$this->n('/_/routes/home')], $this->subject->groups($resource));
    }

    public function test_keys_after_parent_page_in_iteration_order_are_still_collected(): void
    {
        $resource = [
            '@id' => '/_/abstract_page_data/child-uuid',
            'parentPage' => ['@id' => '/_/pages/parent-uuid'],
            'page' => ['@id' => '/_/pages/child-page-uuid'],
        ];

        $this->assertSame(
            [
                $this->n('/_/pages/parent-uuid'),
                $this->n('/_/abstract_page_data/child-uuid', $this->n('/_/pages/child-page-uuid')),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_at_prefixed_key_string_value_is_not_collected(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            '@type' => '/some-vocabulary-type',
        ];

        $this->assertSame([$this->n('/_/routes/home')], $this->subject->groups($resource));
    }

    public function test_non_path_string_property_is_not_collected(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'title' => 'My Page Title',
        ];

        $this->assertSame([$this->n('/_/routes/home')], $this->subject->groups($resource));
    }

    public function test_a_layout_shared_with_a_shallower_depth_is_listed_only_at_the_shallowest_depth(): void
    {
        $layout = [
            '@id' => '/_/layouts/shared',
            'componentGroups' => [['@id' => '/_/component_groups/top', 'componentPositions' => [['@id' => '/_/component_positions/p1', 'component' => '/component/navs/n1']]]],
        ];
        $resource = [
            '@id' => '/_/pages/child',
            'layout' => $layout,
            'parentPage' => ['@id' => '/_/pages/parent', 'layout' => $layout],
        ];

        $this->assertSame(
            [
                $this->n('/_/pages/parent', $this->n('/_/layouts/shared', $this->n('/_/component_groups/top', $this->n('/_/component_positions/p1', $this->n('/component/navs/n1'))))),
                $this->n('/_/pages/child'),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_a_layout_referenced_by_iri_at_a_deeper_depth_is_skipped_too(): void
    {
        $resource = [
            '@id' => '/_/pages/child',
            'layout' => '/_/layouts/shared',
            'parentPage' => ['@id' => '/_/pages/parent', 'layout' => ['@id' => '/_/layouts/shared']],
        ];

        $this->assertSame(
            [
                $this->n('/_/pages/parent', $this->n('/_/layouts/shared')),
                $this->n('/_/pages/child'),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_a_different_layout_at_each_depth_is_listed_at_each_depth(): void
    {
        $resource = [
            '@id' => '/_/pages/child',
            'layout' => ['@id' => '/_/layouts/child'],
            'parentPage' => ['@id' => '/_/pages/parent', 'layout' => ['@id' => '/_/layouts/parent']],
        ];

        $this->assertSame(
            [
                $this->n('/_/pages/parent', $this->n('/_/layouts/parent')),
                $this->n('/_/pages/child', $this->n('/_/layouts/child')),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_a_resource_other_than_a_layout_shared_across_depths_is_listed_at_each_depth(): void
    {
        $resource = [
            '@id' => '/_/abstract_page_data/child',
            'page' => ['@id' => '/_/pages/template'],
            'parentPageData' => ['@id' => '/_/abstract_page_data/parent', 'page' => ['@id' => '/_/pages/template']],
        ];

        $this->assertSame(
            [
                $this->n('/_/abstract_page_data/parent', $this->n('/_/pages/template')),
                $this->n('/_/abstract_page_data/child', $this->n('/_/pages/template')),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_the_parent_is_found_inside_a_nested_resource_and_a_list(): void
    {
        $resource = [
            '@id' => '/_/routes/child',
            'pageData' => [
                '@id' => '/_/abstract_page_data/child',
                'items' => [['@id' => '/_/pages/x', 'parentPageData' => ['@id' => '/_/abstract_page_data/parent']]],
            ],
        ];

        $this->assertSame(
            [
                $this->n('/_/abstract_page_data/parent'),
                $this->n('/_/routes/child', $this->n('/_/abstract_page_data/child', $this->n('/_/pages/x'))),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_a_layout_from_the_root_is_still_skipped_two_depths_down_when_the_middle_depth_has_its_own_layout(): void
    {
        $resource = [
            '@id' => '/_/pages/grandchild',
            'layout' => ['@id' => '/_/layouts/root'],
            'parentPage' => [
                '@id' => '/_/pages/child',
                'layout' => ['@id' => '/_/layouts/middle'],
                'parentPage' => ['@id' => '/_/pages/root', 'layout' => ['@id' => '/_/layouts/root']],
            ],
        ];

        $this->assertSame(
            [
                $this->n('/_/pages/root', $this->n('/_/layouts/root')),
                $this->n('/_/pages/child', $this->n('/_/layouts/middle')),
                $this->n('/_/pages/grandchild'),
            ],
            $this->subject->groups($resource)
        );
    }

    public function test_a_depth_whose_resource_contributes_no_node_keeps_its_own_iri_as_the_root(): void
    {
        $this->assertSame(
            [['iri' => '/.well-known/genid/abc', 'children' => []]],
            $this->subject->groups(['@id' => '/.well-known/genid/abc'])
        );
    }
}
