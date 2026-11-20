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
use ApiPlatform\Validator\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Route\RouteCascadePlanner;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @phpstan-import-type CascadeNode from RouteCascadePlanner
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class RouteRedirectStateProcessor implements ProcessorInterface
{
    private const string DESCENDANT_MESSAGE = 'The path of the descendant route "%s" would become "%s". %s';

    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private RouteGeneratorInterface $routeGenerator,
        private ManagerRegistry $registry,
        private RouteCascadePlanner $cascadePlanner,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $previousRoute = $context['previous_data'] ?? null;
        if (
            !$data instanceof Route
            || !$previousRoute instanceof Route
            || !$operation instanceof HttpOperation
            || !\in_array($operation->getMethod(), [HttpOperation::METHOD_PUT, HttpOperation::METHOD_PATCH], true)
            || $previousRoute->getPath() === $data->getPath()
        ) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $entityManager = $this->registry->getManagerForClass(Route::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new InvalidArgumentException(\sprintf('Could not find entity manager for %s', Route::class));
        }

        $previousPath = $previousRoute->getPath();
        $cascade = $data->cascadeChildPaths ? $this->planCascade($data, $previousPath, $entityManager) : [];
        $violations = $this->cascadePlanner->violations($cascade, $data, 'path', self::DESCENDANT_MESSAGE, $entityManager);
        if (\count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        $entityManager->persist($this->routeGenerator->createRedirect($previousPath, $data));
        $this->cascadePlanner->apply($cascade, $entityManager);
        $entityManager->flush();

        return $result;
    }

    /**
     * @return list<CascadeNode>
     */
    private function planCascade(Route $parentRoute, string $oldParentPath, EntityManagerInterface $em): array
    {
        $pageOrPageData = $parentRoute->getPage() ?? $parentRoute->getPageData();
        if (null === $pageOrPageData) {
            return [];
        }

        return $this->cascadePlanner->planDescendants($pageOrPageData, $oldParentPath, $parentRoute->getPath(), $em);
    }
}
