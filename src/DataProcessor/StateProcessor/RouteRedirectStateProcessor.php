<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class RouteRedirectStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private RouteGeneratorInterface $routeGenerator,
        private ManagerRegistry $registry,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        $previousRoute = $context['previous_data'] ?? null;
        if (
            !$data instanceof Route
            || !$previousRoute instanceof Route
            || !$operation instanceof HttpOperation
            || !\in_array($operation->getMethod(), [HttpOperation::METHOD_PUT, HttpOperation::METHOD_PATCH], true)
        ) {
            return $result;
        }

        $previousPath = $previousRoute->getPath();
        if ($previousPath === $data->getPath()) {
            return $result;
        }

        $entityManager = $this->registry->getManagerForClass(Route::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new InvalidArgumentException(\sprintf('Could not find entity manager for %s', Route::class));
        }

        $entityManager->persist($this->routeGenerator->createRedirect($previousPath, $data));
        if ($data->cascadeChildPaths) {
            $this->cascadeChildPaths($data, $previousPath, $entityManager);
        }
        $entityManager->flush();

        return $result;
    }

    private function cascadeChildPaths(Route $parentRoute, string $oldParentPath, EntityManagerInterface $em): void
    {
        $pageOrPageData = $parentRoute->getPage() ?? $parentRoute->getPageData();
        if (null === $pageOrPageData) {
            return;
        }

        $this->cascadeFromPage($pageOrPageData, $oldParentPath, $parentRoute->getPath(), $em);
    }

    private function cascadeFromPage(AbstractPage $page, string $oldParentPath, string $newParentPath, EntityManagerInterface $em): void
    {
        $childUpdates = [];
        $unroutedChildren = [];

        foreach ($this->findDirectChildren($page, $em) as $child) {
            $childRoute = $child->getRoute();
            if (null === $childRoute) {
                $unroutedChildren[] = $child;
                continue;
            }

            $oldChildPath = $childRoute->getPath();
            if (!str_starts_with($oldChildPath, $oldParentPath . '/')) {
                continue;
            }

            $newChildPath = $newParentPath . substr($oldChildPath, \strlen($oldParentPath));
            $childRoute->setPath($newChildPath)->setName($newChildPath);
            $childUpdates[$oldChildPath] = [$child, $childRoute];
        }

        foreach ($unroutedChildren as $child) {
            $this->cascadeFromPage($child, $oldParentPath, $newParentPath, $em);
        }

        if (empty($childUpdates)) {
            return;
        }

        $em->flush();

        foreach ($childUpdates as $oldChildPath => [$child, $childRoute]) {
            $redirect = $this->routeGenerator->createRedirect((string) $oldChildPath, $childRoute);
            $em->persist($redirect);
            $this->cascadeFromPage($child, (string) $oldChildPath, $childRoute->getPath(), $em);
        }
    }

    /**
     * @return AbstractPage[]
     */
    private function findDirectChildren(AbstractPage $parent, EntityManagerInterface $em): array
    {
        $field = $parent instanceof Page ? 'parentPage' : 'parentPageData';

        return array_merge(
            $em->getRepository(Page::class)->findBy([$field => $parent]),
            $em->getRepository(AbstractPageData::class)->findBy([$field => $parent]),
        );
    }
}
