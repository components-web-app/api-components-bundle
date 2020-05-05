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

namespace Silverback\ApiComponentsBundle\DataTransformer;

use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Core\Util\AttributesExtractor;
use ApiPlatform\Core\Util\RequestParser;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Exception\OutOfBoundsException;
use Silverback\ApiComponentsBundle\Helper\Collection\CollectionHelper;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class CollectionOutputDataTransformer implements DataTransformerInterface
{
    private CollectionHelper $iriConverter;
    private CollectionDataProviderInterface $collectionDataProvider;
    private RequestStack $requestStack;
    private SerializerContextBuilderInterface $serializerContextBuilder;
    private NormalizerInterface $itemNormalizer;
    private SerializeFormatResolver $serializeFormatResolver;
    private string $itemsPerPageParameterName;
    private string $paginationEnabledParameterName;

    public function __construct(CollectionHelper $iriConverter, CollectionDataProviderInterface $collectionDataProvider, RequestStack $requestStack, SerializerContextBuilderInterface $serializerContextBuilder, NormalizerInterface $itemNormalizer, SerializeFormatResolver $serializeFormatResolver, string $itemsPerPageParameterName, string $paginationEnabledParameterName)
    {
        $this->iriConverter = $iriConverter;
        $this->collectionDataProvider = $collectionDataProvider;
        $this->requestStack = $requestStack;
        $this->serializerContextBuilder = $serializerContextBuilder;
        $this->itemNormalizer = $itemNormalizer;
        $this->serializeFormatResolver = $serializeFormatResolver;
        $this->itemsPerPageParameterName = $itemsPerPageParameterName;
        $this->paginationEnabledParameterName = $paginationEnabledParameterName;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return $data instanceof Collection && Collection::class === $to;
    }

    /**
     * @param Collection $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $parameters = $this->iriConverter->getRouterParametersFromIri($object->getResourceIri());
        $attributes = AttributesExtractor::extractAttributes($parameters);
        $request = $this->requestStack->getMasterRequest();

        if (!$this->collectionDataProvider instanceof ContextAwareCollectionDataProviderInterface) {
            $collectionData = $this->collectionDataProvider->getCollection($attributes['resource_class'], $attributes['collection_operation_name']);
        } else {
            $filters = [];
            if ($perPage = $object->getPerPage()) {
                $filters[$this->itemsPerPageParameterName] = $perPage;
            }
            if ($request) {
                if ($requestFilters = $this->getFilters($object, $request)) {
                    $filters = array_merge($filters, $requestFilters);
                }
            }

            if (isset($filters[$this->itemsPerPageParameterName]) && $filters[$this->itemsPerPageParameterName] <= 0) {
                $filters[$this->paginationEnabledParameterName] = false;
            }

            $collectionContext = ['filters' => $filters];
            if ($request) {
                // Comment copied from ApiPlatform\Core\EventListener\ReadListener
                // Builtin data providers are able to use the serialization context to automatically add join clauses
                $normalizationContext = $this->serializerContextBuilder->createFromRequest(
                    $request,
                    true,
                    $attributes
                );
                $collectionContext += $normalizationContext;
            }

            $collectionData = $this->collectionDataProvider->getCollection($attributes['resource_class'], Request::METHOD_GET, $collectionContext);
        }

        // Pagination disabled
        if (\is_array($collectionData)) {
            $collection = $collectionData;
        } else {
            if (!$collectionData instanceof \Traversable) {
                throw new OutOfBoundsException('$collectionData should be Traversable');
            }
            $collection = iterator_count($collectionData) ? $collectionData : null;
        }

        $format = $request ? $this->serializeFormatResolver->getFormatFromRequest($request) : null;
        $normalizerContext = [
            'resource_class' => $attributes['resource_class'],
            'request_uri' => $object->getResourceIri(),
            'jsonld_has_context' => false,
            'api_sub_level' => null,
            'subresource_operation_name' => Request::METHOD_GET,
        ];
        $normalizedCollection = $this->itemNormalizer->normalize($collection, $format, $normalizerContext);

        $object->setCollection($normalizedCollection);

        return $object;
    }

    private function getFilters(Collection $object, Request $request)
    {
        if ($queryParams = $object->getDefaultQueryParameters()) {
            $request = clone $request;
            foreach ($queryParams as $defaultKey => $defaultValue) {
                if ($request->query->has($defaultKey)) {
                    continue;
                }
                $request->query->set($defaultKey, $defaultValue);
            }
            $request->server->set('QUERY_STRING', http_build_query($request->query->all()));
        }

        $queryString = RequestParser::getQueryString($request);

        return $queryString ? RequestParser::parseRequestParams($queryString) : null;
    }
}
