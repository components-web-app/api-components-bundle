<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DataTransformer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Paginator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use ApiPlatform\Core\Util\RequestParser;
use Silverback\ApiComponentBundle\Entity\Component\Collection\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CollectionDataTransformer implements DataTransformerInterface
{
    private $requestStack;
    private $resourceMetadataFactory;
    private $operationPathResolver;
    private $dataProvider;
    private $itemNormalizer;
    private $iriConverter;
    private $itemsPerPageParameterName;

    public function __construct(
        RequestStack $requestStack,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        OperationPathResolverInterface $operationPathResolver,
        ContextAwareCollectionDataProviderInterface $dataProvider,
        NormalizerInterface $itemNormalizer,
        IriConverterInterface $iriConverter,
        string $itemsPerPageParameterName = 'itemsPerPage'
    ) {
        $this->requestStack = $requestStack;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->operationPathResolver = $operationPathResolver;
        $this->dataProvider = $dataProvider;
        $this->itemNormalizer = $itemNormalizer;
        $this->iriConverter = $iriConverter;
        $this->itemsPerPageParameterName = $itemsPerPageParameterName;
    }

    /**
     * @param Collection $object
     * @param array $context
     * @return object|void
     */
    public function transform($object, array $context = [])
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $format = $request->getRequestFormat();
        $collectionRoutes = $this->addCollectionRoutes($object, $format);

        $itemsPerPage = $object->getPerPage();
        $isPaginated = (bool) $itemsPerPage;
        $filters = $this->getFilters($object, $request);

        $dataProviderContext = null === $filters ? [] : ['filters' => $filters];
        if ($isPaginated) {
            $dataProviderContext['filters'] = $dataProviderContext['filters'] ?? [];
            $dataProviderContext['filters'] = array_merge($dataProviderContext['filters'], [
                'pagination' => true,
                $this->itemsPerPageParameterName => $itemsPerPage,
                '_page' => 1
            ]);
            $request->attributes->set('_api_pagination', [
                'pagination' => 'true',
                $this->itemsPerPageParameterName => $itemsPerPage
            ]);
        }

        /** @var Paginator $collection */
        $collection = $this->dataProvider->getCollection($object->getResource(), Request::METHOD_GET, $dataProviderContext);

        $forcedContext = [
            'resource_class' => $object->getResource(),
            'request_uri' => $collectionRoutes ? $collectionRoutes->first() : null,
            'jsonld_has_context' => false,
            'api_sub_level' => null,
            'subresource_operation_name' => 'get'
        ];
        $mergedContext = array_merge($context, $forcedContext);
        $normalizedCollection = $this->itemNormalizer->normalize(
            $collection,
            $format,
            $mergedContext
        );
        if (\is_array($normalizedCollection)) {
            $object->setCollection($normalizedCollection);
        }

        $resources = array_map(function ($object) {
            return $this->iriConverter->getIriFromItem($object);
        }, (array)$collection->getIterator());
        $request->attributes->set('_resources', $request->attributes->get('_resources', []) + $resources);

        return $object;
    }

    private function getFilters(Collection $object, Request $request): ?array
    {
        $filters = null;

        $resetQueryString = false;
        // Set the default querystring for the RequestParser class if we have not passed one in the request
        if ($defaultQueryString = $object->getDefaultQueryString()) {
            $qs = $request->server->get('QUERY_STRING');
            if (!$qs) {
                $resetQueryString = true;
                $request->server->set('QUERY_STRING', $defaultQueryString);
            }
        }

        if (null === $filters = $request->attributes->get('_api_filters')) {
            $queryString = RequestParser::getQueryString($request);
            $filters = $queryString ? RequestParser::parseRequestParams($queryString) : null;
        }

        if ($resetQueryString) {
            $request->server->set('QUERY_STRING', '');
        }

        return $filters;
    }

    private function addCollectionRoutes(Collection $object, string $format)
    {
        $resourceMetadata = $this->resourceMetadataFactory->create($object->getResource());
        $collectionOperations = $resourceMetadata->getCollectionOperations();
        if ($collectionOperations && ($shortName = $resourceMetadata->getShortName())) {
            $collectionOperations = array_change_key_case($collectionOperations, CASE_LOWER);
            $baseRoute = trim(trim($resourceMetadata->getAttribute('route_prefix', '')), '/');
            $methods = ['post', 'get'];
            foreach ($methods as $method) {
                if (array_key_exists($method, $collectionOperations)) {
                    $path = $baseRoute . $this->operationPathResolver->resolveOperationPath(
                        $shortName,
                        $collectionOperations[$method],
                        OperationType::COLLECTION //,
                        // $method
                    );
                    $finalPath = preg_replace('/{_format}$/', $format, $path);
                    $object->addCollectionRoute(
                        $method,
                        $finalPath
                    );
                }
            }
        }
        return $object->getCollectionRoutes();
    }

    public function supportsTransformation($data, array $context = []): bool
    {
        return $data instanceof Collection;
    }
}
