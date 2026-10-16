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
use Silverback\ApiComponentsBundle\ApiResource\ComponentGroupOrder;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\ComponentGroupOrderNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ComponentGroupOrderNormalizerTest extends TestCase
{
    public function test_it_normalizes_an_order_once(): void
    {
        $normalizer = new ComponentGroupOrderNormalizer();
        $order = new ComponentGroupOrder('1', new ComponentGroup());

        self::assertTrue($normalizer->supportsNormalization($order));
        self::assertFalse($normalizer->supportsNormalization(new ComponentGroup()));
        self::assertFalse($normalizer->supportsNormalization($order, null, ['COMPONENT_GROUP_ORDER_NORMALIZER_ALREADY_CALLED' => true]));
        self::assertSame([ComponentGroupOrder::class => false], $normalizer->getSupportedTypes(null));
    }

    public function test_the_order_is_tagged_with_every_position_it_lists(): void
    {
        $resources = new \ArrayObject(['/group' => '/group']);
        $order = new ComponentGroupOrder('1', new ComponentGroup(), ['/position/a' => 0, '/position/b' => 3]);

        $normalized = $this->normalizer(['@id' => '/group/order'])->normalize($order, 'jsonld', ['resources' => $resources]);

        self::assertSame(['/group' => '/group', '/position/a' => '/position/a', '/position/b' => '/position/b'], $resources->getArrayCopy());
        self::assertSame('/group/order', $normalized['@id']);
        self::assertSame(['/position/a' => 0, '/position/b' => 3], $normalized['sortValues']->getArrayCopy());
    }

    public function test_an_empty_order_is_an_empty_object(): void
    {
        $normalized = $this->normalizer(['sortValues' => []])->normalize(new ComponentGroupOrder('1', new ComponentGroup()));

        self::assertInstanceOf(\ArrayObject::class, $normalized['sortValues']);
        self::assertSame('{}', json_encode($normalized['sortValues']));
    }

    public function test_a_normalization_that_is_not_an_array_is_returned_as_it_is(): void
    {
        self::assertSame('/group/order', $this->normalizer('/group/order')->normalize(new ComponentGroupOrder('1', new ComponentGroup())));
    }

    private function normalizer(mixed $inner): ComponentGroupOrderNormalizer
    {
        $decorated = $this->createMock(NormalizerInterface::class);
        $decorated->expects(self::once())->method('normalize')->willReturnCallback(static function (mixed $data, ?string $format, array $context) use ($inner): mixed {
            self::assertTrue($context['COMPONENT_GROUP_ORDER_NORMALIZER_ALREADY_CALLED']);

            return $inner;
        });
        $normalizer = new ComponentGroupOrderNormalizer();
        $normalizer->setNormalizer($decorated);

        return $normalizer;
    }
}
