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

    public function test_flat_resource_returns_single_depth_group(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'page' => ['@id' => '/_/pages/abc'],
        ];

        $this->assertSame(
            [['/_/routes/home', '/_/pages/abc']],
            $this->subject->groups($resource)
        );
    }

    public function test_resource_with_parent_page_returns_two_groups_root_first(): void
    {
        $resource = [
            '@id' => '/_/abstract_page_data/child-uuid',
            'page' => ['@id' => '/_/pages/child-page-uuid'],
            'parentPage' => [
                '@id' => '/_/pages/parent-uuid',
                'route' => ['@id' => '/_/routes/conference'],
            ],
        ];

        $groups = $this->subject->groups($resource);

        $this->assertCount(2, $groups);
        $this->assertContains('/_/pages/parent-uuid', $groups[0]);
        $this->assertContains('/_/routes/conference', $groups[0]);
        $this->assertContains('/_/abstract_page_data/child-uuid', $groups[1]);
        $this->assertContains('/_/pages/child-page-uuid', $groups[1]);
        $this->assertNotContains('/_/pages/parent-uuid', $groups[1]);
    }

    public function test_resource_with_parent_page_data_returns_two_groups_root_first(): void
    {
        $resource = [
            '@id' => '/_/abstract_page_data/child-uuid',
            'parentPageData' => [
                '@id' => '/_/abstract_page_data/parent-uuid',
                'route' => ['@id' => '/_/routes/conference'],
            ],
        ];

        $groups = $this->subject->groups($resource);

        $this->assertCount(2, $groups);
        $this->assertContains('/_/abstract_page_data/parent-uuid', $groups[0]);
        $this->assertContains('/_/abstract_page_data/child-uuid', $groups[1]);
    }

    public function test_two_level_nesting_returns_three_groups(): void
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

        $groups = $this->subject->groups($resource);

        $this->assertCount(3, $groups);
        $this->assertContains('/_/pages/grandparent-uuid', $groups[0]);
        $this->assertContains('/_/abstract_page_data/parent-uuid', $groups[1]);
        $this->assertContains('/_/abstract_page_data/child-uuid', $groups[2]);
    }

    public function test_well_known_iris_are_filtered_out(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            '_metadata' => ['@id' => '/.well-known/genid/abc123'],
        ];

        $groups = $this->subject->groups($resource);

        $this->assertSame([['/_/routes/home']], $groups);
    }

    public function test_resource_metadata_collection_iri_is_filtered_out(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'something' => ['@id' => '/_/resource_metadatas'],
        ];

        $groups = $this->subject->groups($resource);

        $this->assertSame([['/_/routes/home']], $groups);
    }

    public function test_duplicate_iris_within_depth_group_are_deduplicated(): void
    {
        $resource = [
            '@id' => '/_/routes/home',
            'items' => [
                ['@id' => '/_/pages/abc'],
                ['@id' => '/_/pages/abc'],
            ],
        ];

        $groups = $this->subject->groups($resource);

        $this->assertSame([['/_/routes/home', '/_/pages/abc']], $groups);
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

        $groups = $this->subject->groups($resource);

        $this->assertSame([[
            '/_/routes/home',
            '/_/component_groups/cg1',
            '/_/component_groups/cg2',
        ]], $groups);
    }

    public function test_parent_iri_does_not_appear_in_child_group(): void
    {
        $resource = [
            '@id' => '/_/abstract_page_data/child-uuid',
            'parentPage' => ['@id' => '/_/pages/parent-uuid'],
        ];

        $groups = $this->subject->groups($resource);

        $this->assertNotContains('/_/pages/parent-uuid', $groups[1] ?? []);
        $this->assertContains('/_/pages/parent-uuid', $groups[0]);
    }
}
