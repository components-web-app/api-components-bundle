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

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteStateProvider implements ProviderInterface
{
    private const ALREADY_CALLED = 'ROUTE_DATA_PROVIDER_ALREADY_CALLED';

    private RouteRepository $routeRepository;
    private ProviderInterface $defaultProvider;

    public function __construct(RouteRepository $routeRepository, ProviderInterface $defaultProvider)
    {
        $this->routeRepository = $routeRepository;
        $this->defaultProvider = $defaultProvider;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $id = $uriVariables['id'];

        $context[self::ALREADY_CALLED] = true;
        if (!\is_string($id)) {
            return $this->defaultProvider->provide($operation, $uriVariables, $context);
        }

        return $this->routeRepository->findOneByIdOrPath($id);
    }

    public function supports(string $resourceClass, array $uriVariables = [], ?string $operationName = null, array $context = []): bool
    {
        /** @var Operation */
        $operation = $context['operation'];

        return Route::class === $resourceClass && !$operation instanceof CollectionOperationInterface && !isset($context[self::ALREADY_CALLED]);
    }
}
