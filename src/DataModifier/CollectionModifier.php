<?php

namespace Silverback\ApiComponentBundle\DataModifier;

use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Paginator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Entity\Component\Collection\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CollectionModifier extends AbstractModifier
{
    private $resourceMetadataFactory;
    private $operationPathResolver;
    private $itemNormalizer;
    private $requestStack;

    public function __construct(
        ContainerInterface $container,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        OperationPathResolverInterface $operationPathResolver,
        NormalizerInterface $itemNormalizer,
        RequestStack $requestStack
    )
    {
        parent::__construct($container);
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->operationPathResolver = $operationPathResolver;
        $this->itemNormalizer = $itemNormalizer;
        $this->requestStack = $requestStack;
    }

    /**
     * @param Collection $collectionEntity
     * @param array $context
     * @param null|string $format
     * @return object|void
     */
    public function process($collectionEntity, array $context = array(), ?string $format = null)
    {
        $resourceMetadata = $this->resourceMetadataFactory->create($collectionEntity->getResource());
        $requestUri = null;

        $collectionOperations = $resourceMetadata->getCollectionOperations();
        if ($collectionOperations) {
            $collectionOperations = array_change_key_case($collectionOperations, CASE_LOWER);
            $baseRoute = trim(trim($resourceMetadata->getAttribute('route_prefix', '')), '/');
            $methods = ['post', 'get'];
            foreach ($methods as $method) {
                if (array_key_exists($method, $collectionOperations)) {
                    $path = $baseRoute . $this->operationPathResolver->resolveOperationPath(
                            $resourceMetadata->getShortName(),
                            $collectionOperations[$method],
                            OperationType::COLLECTION,
                            $method);
                    $finalPath = preg_replace('/{_format}$/', $format, $path);
                    $collectionEntity->addCollectionRoute(
                        $method,
                        $finalPath
                    );
                    if ($method === 'get') {
                        $requestUri = $finalPath;
                    }
                }
            }
        }
        $collectionResourceAttributes = $resourceMetadata->getAttributes();

        /** @var ContextAwareCollectionDataProviderInterface $dataProvider */
        $dataProvider = $this->container->get(ContextAwareCollectionDataProviderInterface::class);
        $dataProviderContext = [
            'filters' => [
                'pagination' => true,
                '_page' => 1
            ]];
        $request = $apiPagination = null;
        if ($collectionEntity->getPerPage() && ($request = $this->requestStack->getCurrentRequest())) {
            $apiPagination = $request->attributes->get('_api_pagination');
            $originalPerPage = $apiPagination['itemsPerPage'];
            $apiPagination['itemsPerPage'] = $collectionEntity->getPerPage();
            $request->attributes->set('_api_pagination', $apiPagination);
            $apiPagination['itemsPerPage'] = $originalPerPage;
        }

        /** @var Paginator $collection */
        $collection = $dataProvider->getCollection($collectionEntity->getResource(), Request::METHOD_GET, $dataProviderContext);

        if ($request && $apiPagination) {
            $request->attributes->set('_api_pagination', $apiPagination);
        }

        $forcedContext = [
            'resource_class' => Collection::class,
            'request_uri' => $requestUri,
            'jsonld_has_context' => false,
            'api_sub_level' => null
        ];
        $mergedContext = array_merge($context, $forcedContext);
        $normalizedCollection = $this->itemNormalizer->normalize(
            $collection,
            $format,
            $mergedContext
        );

        $collectionEntity->setCollection($normalizedCollection);
    }

    public function supportsData($data): bool
    {
        return $data instanceof Collection;
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . ContextAwareCollectionDataProviderInterface::class,
            RequestStack::class
        ];
    }
}
