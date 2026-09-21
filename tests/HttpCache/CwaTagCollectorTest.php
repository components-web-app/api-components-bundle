<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\HttpCache;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;

class CwaTagCollectorTest extends TestCase
{
    private CwaTagCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new CwaTagCollector();
    }

    public function test_the_resource_iri_is_collected_exactly_as_the_default_normalizer_would(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect($this->context('/_/pages/abc', new Page(), $resources));

        self::assertSame(['/_/pages/abc' => '/_/pages/abc'], $resources->getArrayCopy());
    }

    public function test_collecting_the_same_iri_twice_yields_one_entry(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect($this->context('/_/pages/abc', new Page(), $resources));
        $this->collector->collect($this->context('/_/pages/abc', new Page(), $resources));

        self::assertSame(['/_/pages/abc' => '/_/pages/abc'], $resources->getArrayCopy());
    }

    public function test_a_blank_node_iri_is_never_collected(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect($this->context('/.well-known/genid/1234', new Page(), $resources));

        self::assertSame([], $resources->getArrayCopy());
    }

    public function test_the_resource_metadatas_iri_is_never_collected(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect($this->context('/_/resource_metadatas', new Page(), $resources));

        self::assertSame([], $resources->getArrayCopy());
    }

    public function test_nothing_is_collected_when_the_context_carries_no_resource_list(): void
    {
        $this->expectNotToPerformAssertions();

        $this->collector->collect(['iri' => '/_/pages/abc', 'object' => new Page()]);
    }

    public function test_nothing_is_collected_when_the_context_carries_no_iri(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect(['resources' => $resources, 'object' => new Page()]);

        self::assertSame([], $resources->getArrayCopy());
    }

    public function test_a_manifest_operation_collects_a_grouping_key_for_a_page(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect($this->manifestContext('/_/pages/abc', new Page(), $resources));

        self::assertSame(['manifest:/_/pages/abc' => 'manifest:/_/pages/abc'], $resources->getArrayCopy());
    }

    public function test_a_manifest_operation_collects_a_grouping_key_for_page_data(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect($this->manifestContext('/page_data/page_datas/abc', new PageData(), $resources));

        self::assertSame(['manifest:/page_data/page_datas/abc' => 'manifest:/page_data/page_datas/abc'], $resources->getArrayCopy());
    }

    public function test_a_manifest_operation_collects_nothing_for_a_member_resource(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect($this->manifestContext('/_/component_groups/abc', new ComponentGroup(), $resources));
        $this->collector->collect($this->manifestContext('/_/routes//my-route', new Route(), $resources));
        $this->collector->collect($this->manifestContext('/component/dummy_components/abc', new \stdClass(), $resources));

        self::assertSame([], $resources->getArrayCopy());
    }

    public function test_a_manifest_operation_collects_nothing_when_the_context_carries_no_object(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect([
            'resources' => $resources,
            'iri' => '/_/pages/abc',
            CwaTagCollector::MANIFEST_CONTEXT_KEY => true,
        ]);

        self::assertSame([], $resources->getArrayCopy());
    }

    public function test_a_manifest_operation_collects_one_grouping_key_for_each_depth(): void
    {
        $resources = new \ArrayObject();

        $this->collector->collect($this->manifestContext('/_/pages/abc', new Page(), $resources));
        $this->collector->collect($this->manifestContext('/_/pages/def', new Page(), $resources));

        self::assertSame(
            [
                'manifest:/_/pages/abc' => 'manifest:/_/pages/abc',
                'manifest:/_/pages/def' => 'manifest:/_/pages/def',
            ],
            $resources->getArrayCopy()
        );
    }

    private function context(string $iri, object $object, \ArrayObject $resources): array
    {
        return [
            'resources' => $resources,
            'iri' => $iri,
            'object' => $object,
        ];
    }

    private function manifestContext(string $iri, object $object, \ArrayObject $resources): array
    {
        return $this->context($iri, $object, $resources) + [CwaTagCollector::MANIFEST_CONTEXT_KEY => true];
    }
}
