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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\SerializerContextBuilderInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\CollectionSerializeStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Helper\Collection\CollectionPopulator;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CollectionSerializeStateProcessorTest extends TestCase
{
    public function test_a_collection_component_is_filled_before_it_is_serialized(): void
    {
        $collection = (new Collection())->setResourceIri('/component/dummy_components');
        $operation = new Get();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($collection, $operation, ['id' => 1], ['a' => 'b'])->willReturnCallback(static fn (Collection $serialized): ?array => $serialized->getCollection());

        $result = (new CollectionSerializeStateProcessor($inner, $this->populator()))->process($collection, $operation, ['id' => 1], ['a' => 'b']);

        self::assertSame([['normalized']], $result);
    }

    public function test_anything_else_is_serialized_unchanged(): void
    {
        $component = new DummyComponent();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($component)->willReturn('serialized');

        self::assertSame('serialized', (new CollectionSerializeStateProcessor($inner, $this->populator()))->process($component, new Get()));
    }

    private function populator(): CollectionPopulator
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('match')->willReturn(['_api_resource_class' => DummyComponent::class, '_api_operation_name' => 'get_collection']);
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/page'));
        $normalizer = $this->createStub(NormalizerInterface::class);
        $normalizer->method('normalize')->willReturn([['normalized']]);
        $metadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadataFactory->method('create')->willReturn(new ResourceMetadataCollection(DummyComponent::class, [
            (new ApiResource(class: DummyComponent::class))->withOperations(new Operations(['get_collection' => new GetCollection()])),
        ]));
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('provide')->willReturn([new DummyComponent()]);

        return new CollectionPopulator(
            new ApiResourceRouteFinder($router),
            $provider,
            $requestStack,
            $this->createStub(SerializerContextBuilderInterface::class),
            $normalizer,
            new SerializeFormatResolver($requestStack),
            $metadataFactory,
            $this->createStub(ProviderInterface::class),
            'perPage',
        );
    }
}
