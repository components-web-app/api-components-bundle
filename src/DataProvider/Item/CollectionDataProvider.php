<?php

namespace Silverback\ApiComponentBundle\DataProvider\Item;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use Doctrine\Common\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Entity\Content\Component\Collection\Collection;

final class CollectionDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    /** @var ManagerRegistry */
    private $managerRegistry;
    /** @var ContextAwareCollectionDataProviderInterface  */
    private $collectionDataProvider;

    /**
     * LayoutDataProvider constructor.
     *
     * @param ManagerRegistry $managerRegistry
     * @param ContextAwareCollectionDataProviderInterface $collectionDataProvider
     */
    public function __construct(ManagerRegistry $managerRegistry, ContextAwareCollectionDataProviderInterface $collectionDataProvider)
    {
        $this->managerRegistry = $managerRegistry;
        $this->collectionDataProvider = $collectionDataProvider;
    }

    /**
     * @param string $resourceClass
     * @param string|null $operationName
     * @param array $context
     * @return bool
     */
    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return $resourceClass === Collection::class;
    }

    /**
     * @param string $resourceClass
     * @param int|string $id
     * @param string|null $operationName
     * @param array $context
     * @return Collection|null
     * @throws ResourceClassNotSupportedException
     */
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = [])
    {
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if (null === $manager) {
            throw new ResourceClassNotSupportedException(sprintf('No manager for the class `%s`', $resourceClass));
        }
        $repository = $manager->getRepository($resourceClass);
        /** @var Collection|null $collection */
        $collection = $repository->find($id);
        if ($collection) {
            // In future we should find the resource's data provider in case it isn't default
            $collection->setCollection($this->collectionDataProvider->getCollection($collection->getResource(), $operationName, $context));
        }
        return $collection;
    }
}
