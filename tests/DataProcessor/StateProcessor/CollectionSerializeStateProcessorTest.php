<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProcessorInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\CollectionSerializeStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\EventListener\Api\CollectionApiEventListener;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class CollectionSerializeStateProcessorTest extends TestCase
{
    public function test_a_collection_component_is_filled_before_it_is_serialized(): void
    {
        $collection = new Collection();
        $calls = [];
        $listener = $this->createMock(CollectionApiEventListener::class);
        $listener->expects(self::once())->method('transform')->with($collection)->willReturnCallback(static function (Collection $collection) use (&$calls): Collection {
            $calls[] = 'fill';

            return $collection;
        });
        $operation = new Get();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($collection, $operation, ['id' => 1], ['a' => 'b'])->willReturnCallback(static function () use (&$calls): string {
            $calls[] = 'serialize';

            return 'serialized';
        });

        self::assertSame('serialized', (new CollectionSerializeStateProcessor($inner, $listener))->process($collection, $operation, ['id' => 1], ['a' => 'b']));
        self::assertSame(['fill', 'serialize'], $calls);
    }

    public function test_anything_else_is_serialized_unchanged(): void
    {
        $component = new DummyComponent();
        $listener = $this->createMock(CollectionApiEventListener::class);
        $listener->expects(self::never())->method('transform');
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($component)->willReturn('serialized');

        self::assertSame('serialized', (new CollectionSerializeStateProcessor($inner, $listener))->process($component, new Get()));
    }
}
