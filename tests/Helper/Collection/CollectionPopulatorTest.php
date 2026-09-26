<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Collection;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\SerializerContextBuilderInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Exception\OutOfBoundsException;
use Silverback\ApiComponentsBundle\Helper\Collection\CollectionPopulator;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CollectionPopulatorTest extends TestCase
{
    private ?Request $mainRequest;
    /** @var list<array<string, mixed>> */
    private array $providedContexts = [];
    /** @var list<Request> */
    private array $parameterRequests = [];
    private mixed $collectionData = [];

    protected function setUp(): void
    {
        $this->mainRequest = Request::create('/page', 'GET', ['search' => 'term']);
        $this->mainRequest->setRequestFormat('jsonld');
    }

    public function test_the_collection_is_filled_with_the_normalized_items_the_get_collection_operation_provides(): void
    {
        $item = new DummyComponent();
        $this->collectionData = [$item];
        $collection = $this->collection();

        $result = $this->populator()->populate($collection);

        self::assertSame($collection, $result);
        self::assertSame([['normalized' => 1, 'format' => 'jsonld', 'group' => 'read']], $collection->getCollection());
        self::assertSame(['perPage' => 5, 'order' => 'asc', 'search' => 'term'], $this->providedContexts[0]['filters']);
        self::assertInstanceOf(GetCollection::class, $this->providedContexts[0]['operation']);
        self::assertSame(DummyComponent::class, $this->providedContexts[0]['resource_class']);
        self::assertSame('read', $this->providedContexts[0]['group']);
    }

    public function test_a_default_query_parameter_reaches_the_parameter_provider_unless_the_request_sets_it(): void
    {
        $collection = $this->collection()->setDefaultQueryParameters(['order' => 'asc', 'search' => 'default']);

        $this->populator()->populate($collection);

        self::assertSame(['search' => 'term', 'order' => 'asc'], $this->parameterRequests[0]->query->all());
        self::assertSame(['search' => 'term', 'order' => 'asc'], $this->parameterRequests[0]->attributes->get('_api_query_parameters'));
        self::assertSame(['search' => 'term'], $this->mainRequest->query->all());
        self::assertSame(['perPage' => 5, 'order' => 'asc', 'search' => 'term'], $this->providedContexts[0]['filters']);
    }

    public function test_request_filters_already_parsed_by_api_platform_are_used(): void
    {
        $this->mainRequest->attributes->set('_api_filters', ['search' => '']);
        $collection = $this->collection()->setPerPage(null)->setDefaultQueryParameters(null);

        $this->populator()->populate($collection);

        self::assertSame(['search' => ''], $this->providedContexts[0]['filters']);
        self::assertFalse($this->parameterRequests[0]->attributes->has('_api_query_parameters'));
    }

    public function test_an_empty_traversable_result_is_normalized_as_an_empty_list(): void
    {
        $this->collectionData = new \ArrayIterator([]);
        $collection = $this->collection();

        $this->populator()->populate($collection);

        self::assertSame([], $collection->getCollection());
    }

    public function test_a_traversable_result_with_items_is_normalized(): void
    {
        $this->collectionData = new \ArrayIterator([new DummyComponent(), new DummyComponent()]);
        $collection = $this->collection();

        $this->populator()->populate($collection);

        self::assertSame([['normalized' => 2, 'format' => 'jsonld', 'group' => 'read']], $collection->getCollection());
    }

    public function test_a_result_that_is_neither_a_list_nor_traversable_is_an_error(): void
    {
        $this->collectionData = new DummyComponent();

        $this->expectException(OutOfBoundsException::class);
        $this->populator()->populate($this->collection());
    }

    public function test_the_collection_is_left_empty_outside_a_request(): void
    {
        $this->mainRequest = null;
        $collection = $this->collection();

        $this->populator()->populate($collection);

        self::assertNull($collection->getCollection());
        self::assertSame([], $this->providedContexts);
    }

    public function test_the_collection_is_left_empty_when_the_resource_has_no_get_collection_operation(): void
    {
        $collection = $this->collection();

        $this->populator(new Operations(['post' => new Post(), 'get' => new Get()]))->populate($collection);

        self::assertNull($collection->getCollection());
        self::assertSame([], $this->providedContexts);
    }

    private function collection(): Collection
    {
        return (new Collection())
            ->setResourceIri('/component/dummy_components')
            ->setPerPage(5)
            ->setDefaultQueryParameters(['order' => 'asc']);
    }

    private function populator(?Operations $operations = null): CollectionPopulator
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('match')->willReturn(['_api_resource_class' => DummyComponent::class, '_api_operation_name' => 'get_collection']);

        $requestStack = new RequestStack();
        if ($this->mainRequest) {
            $requestStack->push($this->mainRequest);
        }

        $contextBuilder = $this->createStub(SerializerContextBuilderInterface::class);
        $contextBuilder->method('createFromRequest')->willReturn(['group' => 'read']);

        $normalizer = $this->createStub(NormalizerInterface::class);
        $normalizer->method('normalize')->willReturnCallback(static fn (mixed $items, ?string $format, array $context): array => [] === $items ? [] : [['normalized' => iterator_count(\is_array($items) ? new \ArrayIterator($items) : $items), 'format' => $format, 'group' => $context['group']]]);

        $metadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadataFactory->method('create')->willReturn(new ResourceMetadataCollection(DummyComponent::class, [
            (new ApiResource(class: DummyComponent::class))->withOperations($operations ?? new Operations(['get' => new Get(), 'get_collection' => new GetCollection()])),
        ]));

        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('provide')->willReturnCallback(function ($operation, array $uriVariables, array $context): mixed {
            $this->providedContexts[] = $context;

            return $this->collectionData;
        });
        $parameterProvider = $this->createStub(ProviderInterface::class);
        $parameterProvider->method('provide')->willReturnCallback(function ($operation, array $uriVariables, array $context): null {
            $this->parameterRequests[] = $context['request'];

            return null;
        });

        return new CollectionPopulator(
            new ApiResourceRouteFinder($router),
            $provider,
            $requestStack,
            $contextBuilder,
            $normalizer,
            new SerializeFormatResolver($requestStack),
            $metadataFactory,
            $parameterProvider,
            'perPage',
        );
    }
}
