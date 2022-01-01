<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\DataProvider\Item;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private const ALREADY_CALLED = 'ROUTE_DATA_PROVIDER_ALREADY_CALLED';

    private RouteRepository $routeRepository;
    private ItemDataProviderInterface $defaultProvider;

    public function __construct(RouteRepository $routeRepository, ItemDataProviderInterface $defaultProvider)
    {
        $this->routeRepository = $routeRepository;
        $this->defaultProvider = $defaultProvider;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Route::class === $resourceClass && !isset($context[self::ALREADY_CALLED]);
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?object
    {
        $context[self::ALREADY_CALLED] = true;
        if (!\is_string($id)) {
            return $this->defaultProvider->getItem($resourceClass, $id, $operationName, $context);
        }

        return $this->routeRepository->findOneByIdOrPath($id);
    }
}
