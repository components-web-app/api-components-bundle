<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Collection;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Exception\InvalidIdentifierException;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Util\AttributesExtractor;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\SerializerContextBuilderInterface;
use ApiPlatform\State\UriVariablesResolverTrait;
use ApiPlatform\State\Util\RequestParser;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Exception\OutOfBoundsException;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class CollectionPopulator
{
    use UriVariablesResolverTrait;

    public function __construct(
        private readonly ApiResourceRouteFinder $resourceRouteFinder,
        private readonly ProviderInterface $provider,
        private readonly RequestStack $requestStack,
        private readonly SerializerContextBuilderInterface $serializerContextBuilder,
        private readonly NormalizerInterface $itemNormalizer,
        private readonly SerializeFormatResolver $serializeFormatResolver,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly ?ProviderInterface $parameterProvider,
        private readonly string $itemsPerPageParameterName,
    ) {
    }

    public function populate(Collection $object): Collection
    {
        $parameters = $this->resourceRouteFinder->findByIri($object->getResourceIri());
        $attributes = AttributesExtractor::extractAttributes($parameters);
        $request = $this->requestStack->getMainRequest();
        if (!$request) {
            return $object;
        }
        $resourceClass = $attributes['resource_class'];

        $getCollectionOperation = $this->findGetCollectionOperation($resourceClass);
        if (!$getCollectionOperation) {
            return $object;
        }

        $collectionContext = ['operation' => $getCollectionOperation, 'resource_class' => $resourceClass];

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
            $filters = array_merge($filters, $requestFilters);
        }

        $collectionContext['filters'] = $filters;

        $collectionContext += $normalizationContext = $this->serializerContextBuilder->createFromRequest($request, true, $attributes);
        try {
            $uriVariables = $this->getOperationUriVariables($getCollectionOperation, $parameters, $resourceClass);
            $clonedRequest = clone $request;
            if ($defaultQueryParams) {
                foreach ($defaultQueryParams as $key => $defaultQueryParam) {
                    if (!$clonedRequest->query->has($key)) {
                        $clonedRequest->query->set($key, $defaultQueryParam);
                    }
                }
                $clonedRequest->attributes->set('_api_query_parameters', $clonedRequest->query->all());
            }
            $this->parameterProvider->provide($getCollectionOperation, $uriVariables, [...$collectionContext, 'request' => $clonedRequest, 'uri_variables' => $uriVariables]);
            $collectionData = $this->provider->provide($getCollectionOperation, $uriVariables, $collectionContext);
        } catch (InvalidIdentifierException $e) {
            throw new NotFoundHttpException('Invalid identifier value or configuration.', $e);
        }

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
