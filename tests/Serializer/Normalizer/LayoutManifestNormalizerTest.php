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

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\LayoutManifestNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class LayoutManifestNormalizerTest extends TestCase
{
    private const array MANIFEST = [CwaTagCollector::MANIFEST_CONTEXT_KEY => true];

    public function test_it_supports_a_layout_only_in_a_manifest_and_only_once(): void
    {
        $normalizer = new LayoutManifestNormalizer();

        self::assertTrue($normalizer->supportsNormalization(new Layout(), 'jsonld', self::MANIFEST));
        self::assertFalse($normalizer->supportsNormalization(new Layout(), 'jsonld', []));
        self::assertFalse($normalizer->supportsNormalization(new Layout(), 'jsonld', [CwaTagCollector::MANIFEST_CONTEXT_KEY => false]));
        self::assertFalse($normalizer->supportsNormalization(new Page(), 'jsonld', self::MANIFEST));
        self::assertFalse($normalizer->supportsNormalization(new Layout(), 'jsonld', self::MANIFEST + ['LAYOUT_MANIFEST_NORMALIZER_ALREADY_CALLED' => true]));
        self::assertSame([Layout::class => false], $normalizer->getSupportedTypes('jsonld'));
    }

    public function test_the_layout_component_groups_are_normalised_as_embedded_resources_of_the_manifest(): void
    {
        $top = new ComponentGroup();
        $bottom = new ComponentGroup();
        $layout = new Layout();
        $layout->setComponentGroups([$top, $bottom]);

        $context = self::MANIFEST + [
            'resource_class' => Layout::class,
            'operation' => 'layout operation',
            'operation_name' => 'layout_get',
            'uri_variables' => ['id' => 'x'],
            'iri' => '/_/layouts/x',
            'item_uri_template' => '/_/layouts/{id}',
            'groups' => ['Route:manifest:read'],
        ];

        $calls = [];
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(static function (object $object, ?string $format, array $context) use (&$calls, $top) {
            $calls[] = [$object, $format, $context];
            if ($object instanceof Layout) {
                return ['@id' => '/_/layouts/x', 'componentGroups' => ['/_/component_groups/top', '/_/component_groups/bottom']];
            }

            return ['@id' => $object === $top ? '/_/component_groups/top' : '/_/component_groups/bottom', 'componentPositions' => []];
        });

        $normalizer = new LayoutManifestNormalizer();
        $normalizer->setNormalizer($inner);

        self::assertSame([
            '@id' => '/_/layouts/x',
            'componentGroups' => [
                ['@id' => '/_/component_groups/top', 'componentPositions' => []],
                ['@id' => '/_/component_groups/bottom', 'componentPositions' => []],
            ],
        ], $normalizer->normalize($layout, 'jsonld', $context));

        self::assertCount(3, $calls);
        self::assertSame($layout, $calls[0][0]);
        self::assertTrue($calls[0][2]['LAYOUT_MANIFEST_NORMALIZER_ALREADY_CALLED']);

        [$group, $format, $groupContext] = $calls[1];
        self::assertSame($top, $group);
        self::assertSame('jsonld', $format);
        self::assertSame(ComponentGroup::class, $groupContext['resource_class']);
        self::assertTrue($groupContext['api_sub_level']);
        self::assertTrue($groupContext[CwaTagCollector::MANIFEST_CONTEXT_KEY]);
        self::assertSame(['Route:manifest:read'], $groupContext['groups']);
        foreach (['operation', 'operation_name', 'uri_variables', 'iri', 'item_uri_template', 'LAYOUT_MANIFEST_NORMALIZER_ALREADY_CALLED'] as $removed) {
            self::assertArrayNotHasKey($removed, $groupContext);
        }
        self::assertSame($bottom, $calls[2][0]);
    }

    public function test_a_layout_normalised_to_something_other_than_an_array_is_returned_unchanged(): void
    {
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturn('/_/layouts/x');

        $normalizer = new LayoutManifestNormalizer();
        $normalizer->setNormalizer($inner);

        self::assertSame('/_/layouts/x', $normalizer->normalize(new Layout(), 'jsonld', self::MANIFEST));
    }

    public function test_component_groups_stay_a_list_when_the_collection_has_gaps_in_its_keys(): void
    {
        $layout = new Layout();
        (new \ReflectionProperty(Layout::class, 'componentGroups'))->setValue($layout, new ArrayCollection([2 => new ComponentGroup(), 5 => new ComponentGroup()]));

        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(static fn (object $object) => $object instanceof Layout ? ['@id' => '/_/layouts/x'] : ['@id' => '/_/component_groups/g']);

        $normalizer = new LayoutManifestNormalizer();
        $normalizer->setNormalizer($inner);

        $data = $normalizer->normalize($layout, 'jsonld', self::MANIFEST);
        self::assertSame([0, 1], array_keys($data['componentGroups']));
    }
}
