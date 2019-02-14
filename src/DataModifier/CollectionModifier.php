<?php

namespace Silverback\ApiComponentBundle\DataModifier;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Entity\Component\Collection\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CollectionModifier extends AbstractModifier
{
    private $enabledParameterName;
    private $itemsPerPageParameterName;

    public function __construct(ContainerInterface $container, string $enabledParameterName = 'pagination', string $itemsPerPageParameterName = 'itemsPerPage')
    {
        parent::__construct($container);

        $this->enabledParameterName = $enabledParameterName;
        $this->itemsPerPageParameterName = $itemsPerPageParameterName;
    }

    /**
     * @param Collection $collectionEntity
     * @param array $context
     * @return object|void
     */
    public function process($collectionEntity, array $context = array())
    {
        /** @var ContextAwareCollectionDataProviderInterface $dataProvider */
        $dataProvider = $this->container->get(ContextAwareCollectionDataProviderInterface::class);
        $dataProviderContext = [];

        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get(RequestStack::class);
        $originalRequest = $requestStack->getCurrentRequest();
        $dummyRequest = new Request();
        $dummyRequest->request->set($this->enabledParameterName, true);
        $dummyRequest->attributes->set('_api_pagination', [ $this->itemsPerPageParameterName => $collectionEntity->getPerPage() ]);
        $requestStack->push($dummyRequest);
        $collection = $dataProvider->getCollection($collectionEntity->getResource(), Request::METHOD_GET, $dataProviderContext);
        $collectionEntity->setCollection($collection);
        if ($originalRequest) {
            $requestStack->push($originalRequest);
        }
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
