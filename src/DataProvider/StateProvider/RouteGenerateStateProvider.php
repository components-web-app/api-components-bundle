<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<object>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class RouteGenerateStateProvider implements ProviderInterface
{
    public const string OPERATION_NAME = '_api_/routes/generate{._format}_post';

    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private ProviderInterface $decorated,
        private RouteGeneratorInterface $routeGenerator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        if (!$data instanceof Route || self::OPERATION_NAME !== $operation->getName()) {
            return $data;
        }

        $page = $data->getPageData() ?? $data->getPage();
        if (!$page) {
            throw new \LogicException('Validation should have already checked if the pageData or page values are set.');
        }

        $route = $this->routeGenerator->create($page, $data);
        $request = $context['request'] ?? null;
        if ($request instanceof Request) {
            $request->attributes->set('data', $route);
        }

        return $route;
    }
}
