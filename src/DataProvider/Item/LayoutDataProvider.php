<?php

namespace Silverback\ApiComponentBundle\DataProvider\Item;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use Doctrine\Common\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;

final class LayoutDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    /**
     * @var ManagerRegistry
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
     * @param string $resourceClass
     * @param string|null $operationName
     * @param array $context
     * @return bool
     */
    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return $resourceClass === Layout::class;
    }

    /**
     * @param string $resourceClass
     * @param int|string $id
     * @param string|null $operationName
     * @param array $context
     * @return Layout|null
     * @throws ResourceClassNotSupportedException
     */
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = [])
    {
        if ($id !== 'default') {
            throw new ResourceClassNotSupportedException('LayoutDataProvider only supports the id `default`');
        }
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if (null === $manager) {
            throw new ResourceClassNotSupportedException(sprintf('No manager for the class `%s`', $resourceClass));
        }
        // Ideally we should probably just get the id of the default layout and then forward that onto the default data provider
        // But we don't want to do an un-necessary database lookup - perhaps as the default layout is saved we update a persistent variable somewhere...
        $repository = $manager->getRepository($resourceClass);
        return $repository->findOneBy(['default' => true]);
    }
}
