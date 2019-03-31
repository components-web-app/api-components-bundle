<?php

namespace Silverback\ApiComponentBundle\DataProvider\Item;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Repository\RouteRepository;

class RouteDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private $routeRepository;

    public function __construct(RouteRepository $routeRepository)
    {
        $this->routeRepository = $routeRepository;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = [])
    {
        return $this->routeRepository->findOneByIdOrRoute($id);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return $resourceClass === Route::class;
    }
}
