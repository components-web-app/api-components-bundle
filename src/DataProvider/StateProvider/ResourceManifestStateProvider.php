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
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ResourceManifestStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly RouteRepository $routeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ResourceManifest
    {
        $id = $uriVariables['id'];

        // Route paths always start with '/', UUIDs do not
        if (str_starts_with($id, '/')) {
            $entity = $this->routeRepository->findOneByIdOrPath($id);
        } else {
            $entity = $this->entityManager->find(Page::class, $id)
                ?? $this->entityManager->find(AbstractPageData::class, $id);
        }

        if (!$entity) {
            return null;
        }

        $manifest = new ResourceManifest();
        $manifest->entity = $entity;

        return $manifest;
    }
}
