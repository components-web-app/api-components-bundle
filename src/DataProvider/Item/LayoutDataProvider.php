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

namespace Silverback\ApiComponentBundle\DataProvider\Item;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use Silverback\ApiComponentBundle\Entity\Core\Layout;
use Silverback\ApiComponentBundle\Repository\Core\LayoutRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class LayoutDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private LayoutRepository $repository;

    public function __construct(LayoutRepository $repository)
    {
        $this->repository = $repository;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Layout::class === $resourceClass;
    }

    /** @throws ResourceClassNotSupportedException */
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?Layout
    {
        if ('default' !== $id) {
            throw new ResourceClassNotSupportedException('LayoutDataProvider only supports the id `default`');
        }

        return $this->repository->findOneBy(['default' => true]);
    }
}
