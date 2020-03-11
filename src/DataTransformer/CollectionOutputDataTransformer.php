<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DataTransformer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Paginator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use ApiPlatform\Core\Util\RequestParser;
use Silverback\ApiComponentBundle\Action\AbstractAction;
use Silverback\ApiComponentBundle\Dto\Collection;
use Silverback\ApiComponentBundle\Entity\Component\Collection as CollectionResource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class CollectionOutputDataTransformer implements DataTransformerInterface
{
    private array $transformed = [];
    private RequestStack $requestStack;
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private OperationPathResolverInterface $operationPathResolver;
    private ContextAwareCollectionDataProviderInterface $dataProvider;
    private IriConverterInterface $iriConverter;
    private NormalizerInterface $itemNormalizer;

    public function __construct(RequestStack $requestStack, ResourceMetadataFactoryInterface $resourceMetadataFactory, OperationPathResolverInterface $operationPathResolver, ContextAwareCollectionDataProviderInterface $dataProvider, IriConverterInterface $iriConverter, NormalizerInterface $itemNormalizer)
    {
        $this->requestStack = $requestStack;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->operationPathResolver = $operationPathResolver;
        $this->dataProvider = $dataProvider;
        $this->iriConverter = $iriConverter;
        $this->itemNormalizer = $itemNormalizer;
    }

    /**
     * {@inheritdoc}
     *
     * @param CollectionResource $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $this->transformed[] = $data->getId();

        $collection = new Collection($data);

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return $collection;
        }
        $format = AbstractAction::getRequestFormat($request);

        $this->addEndpoints($collection, $format);
        $this->addCollection($collection, $format, $request);

        return $collection;
    }

    private function addCollection(Collection $object, string $format, Request $request): void
    {
        $collectionResource = $object->getResource();
        $filters = $this->getFilters($collectionResource, $request);
        $dataProviderContext = $this->getDataProviderContext($collectionResource, $request, $filters);
        $resourceClass = $collectionResource->getResourceClass();

        /** @var Paginator $paginator */
        $paginator = $this->dataProvider->getCollection($resourceClass, Request::METHOD_GET, $dataProviderContext);

        $resources = array_map(function ($object) {
            return $this->iriConverter->getIriFromItem($object);
        }, (array) $paginator->getIterator());
        $request->attributes->set('_resources', $request->attributes->get('_resources', []) + $resources);

        $endpoints = $object->getEndpoints();
        $forcedContext = [
            'resource_class' => $resourceClass,
            'request_uri' => $endpoints ? $endpoints->get('get') : null,
            'jsonld_has_context' => false,
            'api_sub_level' => null,
            'subresource_operation_name' => 'get',
        ];
        $normalizerContext = array_merge([], $forcedContext);
        $normalizedCollection = $this->itemNormalizer->normalize(
            $paginator,
            $format,
            $normalizerContext
        );
        if (\is_array($normalizedCollection)) {
            $object->setCollection($normalizedCollection);
        }
    }

    private function getDataProviderContext(CollectionResource $collectionResource, Request $request, array $filters): array
    {
        $itemsPerPage = $collectionResource->getPerPage();
        $isPaginated = (bool) $itemsPerPage;
        $dataProviderContext = ['filters' => $filters];
        if ($isPaginated) {
            // perPage is configured in dependency injection for client controlled per page parameter.
            // we should really be reading this config parameter into the class in case a user chooses
            // a different pagination querystring parameter
            $dataProviderContext['filters'] = $dataProviderContext['filters'] ?? [];
            $dataProviderContext['filters'] = array_merge($dataProviderContext['filters'], [
                'pagination' => true,
                'perPage' => $itemsPerPage,
                '_page' => 1,
            ]);
            $request->attributes->set('_api_pagination', [
                'pagination' => 'true',
                'perPage' => $itemsPerPage,
            ]);
        }

        return $dataProviderContext;
    }

    private function getFilters(CollectionResource $object, Request $request): array
    {
        if (null === $filters = $request->attributes->get('_api_filters')) {
            $defaultQueryString = $object->getDefaultQueryParameters();
            $setDefaultQuery = $defaultQueryString && !$request->server->get('QUERY_STRING');
            if ($setDefaultQuery) {
                $request->server->set('QUERY_STRING', http_build_query($defaultQueryString));
            }
            $queryString = RequestParser::getQueryString($request);
            $filters = $queryString ? RequestParser::parseRequestParams($queryString) : null;
            if ($setDefaultQuery) {
                $request->server->set('QUERY_STRING', '');
            }
        }
        return $filters;
    }

    private function addEndpoints(Collection $object, string $format): void
    {
        $resourceMetadata = $this->resourceMetadataFactory->create($object->getResource()->getResourceClass());
        $collectionOperations = array_change_key_case($resourceMetadata->getCollectionOperations() ?? [], CASE_LOWER);
        if (!empty($collectionOperations) && ($shortName = $resourceMetadata->getShortName())) {
            $baseRoute = trim($resourceMetadata->getAttribute('route_prefix', ''), ' /');
            $methods = array_map(static function ($str) { return strtolower($str); }, [Request::METHOD_GET, Request::METHOD_POST]);
            foreach ($methods as $method) {
                if (!\array_key_exists($method, $collectionOperations)) {
                    continue;
                }
                $path = $baseRoute .
                    /** @scrutinizer ignore-call */
                    $this->operationPathResolver->resolveOperationPath(
                        $shortName,
                        $collectionOperations[$method],
                        OperationType::COLLECTION,
                        $method
                    );
                $finalPath = preg_replace('/{_format}$/', $format, $path);
                $object->addEndpoint($method, $finalPath);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return $data instanceof CollectionResource &&
            Collection::class === $to &&
            !\in_array($data->getId(), $this->transformed, true);
    }
}
