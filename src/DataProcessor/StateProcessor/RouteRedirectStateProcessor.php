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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @phpstan-type CascadeNode array{route: Route, oldPath: string, newPath: string, children: list<mixed>}
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
        private ValidatorInterface $validator,
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
        $this->validateCascade($data, $cascade);

        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        $entityManager->persist($this->routeGenerator->createRedirect($previousPath, $data));
        $this->applyCascade($cascade, $entityManager);
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

        $visited = [spl_object_id($pageOrPageData) => $pageOrPageData];

        return $this->planFromPage($pageOrPageData, $oldParentPath, $parentRoute->getPath(), $em, $visited);
    }

    /**
     * @param array<int, AbstractPage> $visited
     *
     * @return list<CascadeNode>
     */
    private function planFromPage(AbstractPage $page, string $oldParentPath, string $newParentPath, EntityManagerInterface $em, array &$visited): array
    {
        $nodes = [];
        $unroutedChildren = [];

        foreach ($this->findDirectChildren($page, $em) as $child) {
            $childId = spl_object_id($child);
            if (isset($visited[$childId])) {
                continue;
            }
            $visited[$childId] = $child;

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
            $nodes[] = [
                'route' => $childRoute,
                'oldPath' => $oldChildPath,
                'newPath' => $newChildPath,
                'children' => $this->planFromPage($child, $oldChildPath, $newChildPath, $em, $visited),
            ];
        }

        foreach ($unroutedChildren as $child) {
            array_push($nodes, ...$this->planFromPage($child, $oldParentPath, $newParentPath, $em, $visited));
        }

        return $nodes;
    }

    /**
     * @param list<CascadeNode> $nodes
     */
    private function validateCascade(Route $parentRoute, array $nodes): void
    {
        $violations = new ConstraintViolationList();
        $this->collectViolations($parentRoute, $nodes, $violations);
        if (\count($violations) > 0) {
            throw new ValidationException($violations);
        }
    }

    /**
     * @param list<CascadeNode> $nodes
     */
    private function collectViolations(Route $parentRoute, array $nodes, ConstraintViolationList $violations): void
    {
        foreach ($nodes as $node) {
            foreach ($this->validator->validatePropertyValue(Route::class, 'path', $node['newPath']) as $violation) {
                $violations->add(new ConstraintViolation(
                    \sprintf('The path of the descendant route "%s" would become "%s". %s', $node['oldPath'], $node['newPath'], $violation->getMessage()),
                    null,
                    [],
                    $parentRoute,
                    'path',
                    $node['newPath'],
                    null,
                    $violation->getCode(),
                    $violation->getConstraint(),
                ));
            }
            $this->collectViolations($parentRoute, $node['children'], $violations);
        }
    }

    /**
     * @param list<CascadeNode> $nodes
     */
    private function applyCascade(array $nodes, EntityManagerInterface $em): void
    {
        if ([] === $nodes) {
            return;
        }

        foreach ($nodes as $node) {
            $node['route']->setPath($node['newPath'])->setName($node['newPath']);
        }

        $em->flush();

        foreach ($nodes as $node) {
            $em->persist($this->routeGenerator->createRedirect($node['oldPath'], $node['route']));
            $this->applyCascade($node['children'], $em);
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
