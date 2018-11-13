<?php

namespace Silverback\ApiComponentBundle\Serializer\Middleware;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use Silverback\ApiComponentBundle\Entity\Component\Collection\Collection;
use Symfony\Component\HttpFoundation\Request;

class CollectionMiddleware extends AbstractMiddleware
{
    public function process($collectionEntity, array $context = array())
    {
        /** @var ContextAwareCollectionDataProviderInterface $dataProvider */
        $dataProvider = $this->container->get(ContextAwareCollectionDataProviderInterface::class);
        $dataProviderContext = [];
        $collection = $dataProvider->getCollection($collectionEntity->getResource(), Request::METHOD_GET, $dataProviderContext);
        $collectionEntity->setCollection($collection);
    }

    public function supportsData($data): bool
    {
        return $data instanceof Collection;
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . ContextAwareCollectionDataProviderInterface::class
        ];
    }
}
