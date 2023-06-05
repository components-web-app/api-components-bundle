<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use ApiPlatform\Exception\InvalidIdentifierException;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\UriVariablesResolverTrait;
use ApiPlatform\Util\AttributesExtractor;
use ApiPlatform\Util\RequestParser;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Exception\OutOfBoundsException;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class CollectionApiEventListener
{
    use UriVariablesResolverTrait;

    private ApiResourceRouteFinder $resourceRouteFinder;
    private ProviderInterface $provider;
    private RequestStack $requestStack;
    private SerializerContextBuilderInterface $serializerContextBuilder;
    private NormalizerInterface $itemNormalizer;
    private SerializeFormatResolver $serializeFormatResolver;
    private string $itemsPerPageParameterName;
    private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory;

    public function __construct(
        ApiResourceRouteFinder $resourceRouteFinder,
        ProviderInterface $provider,
        RequestStack $requestStack,
        SerializerContextBuilderInterface $serializerContextBuilder,
        NormalizerInterface $itemNormalizer,
        SerializeFormatResolver $serializeFormatResolver,
        ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        string $itemsPerPageParameterName
    ) {
        $this->resourceRouteFinder = $resourceRouteFinder;
        $this->provider = $provider;
        $this->requestStack = $requestStack;
        $this->serializerContextBuilder = $serializerContextBuilder;
        $this->itemNormalizer = $itemNormalizer;
        $this->serializeFormatResolver = $serializeFormatResolver;
        $this->itemsPerPageParameterName = $itemsPerPageParameterName;
        $this->resourceMetadataCollectionFactory = $resourceMetadataCollectionFactory;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return $data instanceof Collection && Collection::class === $to;
    }

    public function onPreSerialize(ViewEvent $event)
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data)
            || !$data instanceof Collection
        ) {
            return;
        }
        $this->transform($data);
    }

    private function transform(Collection $object): Collection
    {
        $parameters = $this->resourceRouteFinder->findByIri($object->getResourceIri());
        $attributes = AttributesExtractor::extractAttributes($parameters);
        $request = $this->requestStack->getMainRequest();
        if (!$request) {
            return $object;
        }
        // Fetch the collection with computed context
        $resourceClass = $attributes['resource_class'];

        $getCollectionOperation = $this->findGetCollectionOperation($resourceClass);
        if (!$getCollectionOperation) {
            return $object;
        }

        // Build context
        $collectionContext = ['operation' => $getCollectionOperation];

        // Build filters
        $filters = [];
        if (($perPage = $object->getPerPage()) !== null) {
            $filters[$this->itemsPerPageParameterName] = $perPage;
        }
        if (($defaultQueryParams = $object->getDefaultQueryParameters()) !== null) {
            $filters += $defaultQueryParams;
        }
        if (null === $requestFilters = $request->attributes->get('_api_filters')) {
            $queryString = RequestParser::getQueryString($request);
            $requestFilters = $queryString ? RequestParser::parseRequestParams($queryString) : null;
        }
        if ($requestFilters) {
            // not += because we want to overwrite with an empty string if provided in querystring.
            // e.g. a default search value could be overridden by no search value
            $filters = array_merge($filters, $requestFilters);
        }
        $collectionContext['filters'] = $filters;

        // Compose context for provider
        $collectionContext += $normalizationContext = $this->serializerContextBuilder->createFromRequest($request, true, $attributes);

        try {
            $uriVariables = $this->getOperationUriVariables($getCollectionOperation, $parameters, $resourceClass);
            // Operation $operation, array $uriVariables = [], array $context = []
            $collectionData = $this->provider->provide($getCollectionOperation, $uriVariables, $collectionContext);
        } catch (InvalidIdentifierException $e) {
            throw new NotFoundHttpException('Invalid identifier value or configuration.', $e);
        }

        // Normalize the collection into an array
        // Pagination disabled
        if (\is_array($collectionData)) {
            $collection = $collectionData;
        } else {
            if (!$collectionData instanceof \Traversable) {
                throw new OutOfBoundsException('$collectionData should be Traversable');
            }
            $collection = iterator_count($collectionData) ? $collectionData : [];
        }
        $format = $this->serializeFormatResolver->getFormatFromRequest($request);
        $normalizedCollection = $this->itemNormalizer->normalize($collection, $format, $normalizationContext);

        // Update the original collection resource
        $object->setCollection($normalizedCollection);

        return $object;
    }

    private function findGetCollectionOperation(string $resourceClass): ?HttpOperation
    {
        $metadata = $this->resourceMetadataCollectionFactory->create($resourceClass);
        $it = $metadata->getIterator();
        /** @var ApiResource $apiResource */
        foreach ($it as $apiResource) {
            $operations = $apiResource->getOperations();
            if ($operations) {
                /** @var Operation $operation */
                foreach ($operations as $operation) {
                    if (
                        $operation instanceof CollectionOperationInterface
                        && $operation instanceof HttpOperation
                        && HttpOperation::METHOD_GET === $operation->getMethod()
                    ) {
                        return $operation;
                    }
                }
            }
        }

        return null;
    }
}
