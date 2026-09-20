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
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\ApiResource\ResourceManifest;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\EventListener\Api\UnpublishedRouteExceptionListener;
use Silverback\ApiComponentsBundle\Helper\Route\RouteLiveResolver;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ResourceManifestStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly RouteRepository $routeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouteLiveResolver $routeLiveResolver = new RouteLiveResolver(),
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ResourceManifest
    {
        $id = $uriVariables['id'];

        if (str_starts_with($id, '/')) {
            $entity = $this->routeRepository->findOneByIdOrPath($id);
        } else {
            $entity = $this->entityManager->find(Page::class, $id)
                ?? $this->entityManager->find(AbstractPageData::class, $id);
        }

        if (!$entity) {
            return null;
        }

        $gatingRoute = $entity instanceof Route ? $entity : $entity->getRoute();
        if ($gatingRoute && !$this->routeLiveResolver->isLive($gatingRoute)) {
            ($context['request'] ?? null)?->attributes->set(UnpublishedRouteExceptionListener::REQUEST_ATTRIBUTE, true);
        }

        $manifest = new ResourceManifest();
        $manifest->entity = $entity;

        return $manifest;
    }
}
