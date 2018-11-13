<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DataProvider\Item;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Repository\LayoutRepository;

final class LayoutDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private $repository;

    /**
     * LayoutDataProvider constructor.
     *
     * @param LayoutRepository $repository
     */
    public function __construct(LayoutRepository $repository)
    {
        $this->repository = $repository;
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
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?Layout
    {
        if ($id !== 'default') {
            throw new ResourceClassNotSupportedException('LayoutDataProvider only supports the id `default`');
        }
        return $this->repository->findOneBy(['default' => true]);
    }
}
