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

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\ApiResource\RouteChildren;
use Silverback\ApiComponentsBundle\ApiResource\RouteChildrenNode;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteChildrenStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly RouteRepository $routeRepository,
        private readonly ManagerRegistry $registry,
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RouteChildren
    {
        $result = new RouteChildren();

        $route = $this->routeRepository->findOneByIdOrPath($uriVariables['id'] ?? '');
        if (null === $route) {
            return $result;
        }

        $em = $this->registry->getManagerForClass(Route::class);
        $result->children = $this->buildChildNodes($route, $em);

        return $result;
    }

    private function buildChildNodes(Route $route, object $em): array
    {
        $pageOrPageData = $route->getPage() ?? $route->getPageData();
        if (null === $pageOrPageData) {
            return [];
        }

        $nodes = [];
        foreach ($this->findDirectChildren($pageOrPageData, $em) as $child) {
            $childRoute = $child->getRoute();
            if (null === $childRoute) {
                continue;
            }

            $node = new RouteChildrenNode();
            $node->route = $this->iriConverter->getIriFromResource($childRoute);
            $node->path = $childRoute->getPath();
            $node->children = $this->buildChildNodes($childRoute, $em);
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @return AbstractPage[]
     */
    private function findDirectChildren(AbstractPage $parent, object $em): array
    {
        $field = $parent instanceof Page ? 'parentPage' : 'parentPageData';

        return array_merge(
            $em->getRepository(Page::class)->findBy([$field => $parent]),
            $em->getRepository(AbstractPageData::class)->findBy([$field => $parent]),
        );
    }
}
