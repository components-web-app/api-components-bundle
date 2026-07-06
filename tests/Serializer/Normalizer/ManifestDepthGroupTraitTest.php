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
        // route → pageData → page → componentGroup → position → component
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
            [$this->n('/_/routes/home',
                $this->n('/page_data/pd1',
                    $this->n('/_/pages/p1',
                        $this->n('/_/component_groups/cg1',
                            $this->n('/_/component_positions/cp1',
                                $this->n('/component/dummy/c1')))))), ],
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
        // layout is a string IRI (not an embedded object) — must appear as a child node
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
        // AP4 blank-node resources (/.well-known/genid/...) must not contribute
        // their string-valued properties to the tree — only real API resources should appear
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
        // parentPage must appear BEFORE 'page' in the array to verify continue (not break) is used.
        // If break were used instead, 'page' would never be visited.
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
        // String values under @-prefixed keys (e.g. @type) must not be collected
        $resource = [
            '@id' => '/_/routes/home',
            '@type' => '/some-vocabulary-type',
        ];

        $this->assertSame([$this->n('/_/routes/home')], $this->subject->groups($resource));
    }

    public function test_non_path_string_property_is_not_collected(): void
    {
        // String values that do not start with '/' are not IRIs and must not be collected
        $resource = [
            '@id' => '/_/routes/home',
            'title' => 'My Page Title',
        ];

        $this->assertSame([$this->n('/_/routes/home')], $this->subject->groups($resource));
    }
}
