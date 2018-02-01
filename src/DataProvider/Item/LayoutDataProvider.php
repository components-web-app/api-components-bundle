<?php

namespace Silverback\ApiComponentBundle\DataProvider\Item;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;

final class LayoutDataProvider implements ItemDataProviderInterface
{
    /**
     * @var ObjectRepository
     */
    private $managerRegistry;

    /**
     * LayoutDataProvider constructor.
     *
     * @param ManagerRegistry $managerRegistry
     */
    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @param string      $resourceClass
     * @param int|string  $id
     * @param string|null $operationName
     * @param array       $context
     *
     * @return Layout|null
     * @throws ResourceClassNotSupportedException
     */
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = [])
    {
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if (
            null === $manager ||
            Layout::class !== $resourceClass ||
            $id !== 'default'
        ) {
            throw new ResourceClassNotSupportedException();
        }
        $repository = $manager->getRepository($resourceClass);
        return $repository->findOneBy(['default' => true]);
    }
}
