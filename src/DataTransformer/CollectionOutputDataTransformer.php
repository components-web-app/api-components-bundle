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
use Silverback\ApiComponentsBundle\ApiPlatform\CollectionHelper;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Exception\OutOfBoundsException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class CollectionOutputDataTransformer implements DataTransformerInterface
{
    private CollectionHelper $iriConverter;
    private CollectionDataProviderInterface $collectionDataProvider;
    private RequestStack $requestStack;
    private SerializerContextBuilderInterface $serializerContextBuilder;

    public function __construct(CollectionHelper $iriConverter, CollectionDataProviderInterface $collectionDataProvider, RequestStack $requestStack, SerializerContextBuilderInterface $serializerContextBuilder)
    {
        $this->iriConverter = $iriConverter;
        $this->collectionDataProvider = $collectionDataProvider;
        $this->requestStack = $requestStack;
        $this->serializerContextBuilder = $serializerContextBuilder;
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
        if (!$request) {
            $filters = null;
        } else {
            $queryString = RequestParser::getQueryString($request);
            $filters = $queryString ? RequestParser::parseRequestParams($queryString) : null;
        }

        if ($this->collectionDataProvider instanceof ContextAwareCollectionDataProviderInterface) {
            $collectionContext = null === $filters ? [] : ['filters' => $filters];

            // Comment copied from ApiPlatform\Core\EventListener\ReadListener
            // Builtin data providers are able to use the serialization context to automatically add join clauses
            $collectionContext += $normalizationContext = $this->serializerContextBuilder->createFromRequest(
                $request,
                true,
                $attributes
            );
            $request->attributes->set('_api_normalization_context', $normalizationContext);

            $collectionData = $this->collectionDataProvider->getCollection($attributes['resource_class'], $attributes['collection_operation_name'], $collectionContext);
        } else {
            $collectionData = $this->collectionDataProvider->getCollection($attributes['resource_class'], $attributes['collection_operation_name']);
        }
        if (!$collectionData instanceof \Traversable) {
            throw new OutOfBoundsException('$collectionData should be Traversable');
        }
        $object->setCollection(iterator_count($collectionData) ? $collectionData : null);

        return $object;
    }
}
