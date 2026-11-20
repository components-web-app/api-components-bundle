<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Route;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @phpstan-type CascadeNode array{route: Route, oldPath: string, newPath: string, children: list<mixed>}
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class RouteCascadePlanner
{
    public const string TAKEN_MESSAGE = 'This path is already in use.';

    public function __construct(
        private RouteGeneratorInterface $routeGenerator,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @return CascadeNode
     */
    public function node(Route $route, string $newPath, EntityManagerInterface $em): array
    {
        $oldPath = $route->getPath();
        $page = $route->getPage() ?? $route->getPageData();

        return [
            'route' => $route,
            'oldPath' => $oldPath,
            'newPath' => $newPath,
            'children' => null === $page ? [] : $this->planDescendants($page, $oldPath, $newPath, $em),
        ];
    }

    /**
     * @return list<CascadeNode>
     */
    public function planDescendants(AbstractPage $page, string $oldPath, string $newPath, EntityManagerInterface $em): array
    {
        $visited = [spl_object_id($page) => $page];

        return $this->planFromPage($page, self::underPath($oldPath, $newPath), $em, $visited);
    }

    /**
     * @param \Closure(AbstractPage, Route): string $newPathOf
     *
     * @return list<CascadeNode>
     */
    public function planNearestRoutedDescendants(AbstractPage $page, \Closure $newPathOf, EntityManagerInterface $em): array
    {
        $visited = [spl_object_id($page) => $page];

        return $this->planFromPage($page, static function (AbstractPage $child, Route $route) use ($newPathOf): ?string {
            $newPath = $newPathOf($child, $route);

            return $newPath === $route->getPath() ? null : $newPath;
        }, $em, $visited);
    }

    /**
     * @param list<CascadeNode> $nodes
     */
    public function violations(array $nodes, object $root, string $propertyPath, string $messageFormat, EntityManagerInterface $em): ConstraintViolationListInterface
    {
        $violations = new ConstraintViolationList();
        $this->collectViolations($nodes, $root, $propertyPath, $messageFormat, $em, $violations);

        return $violations;
    }

    /**
     * @param list<CascadeNode> $nodes
     */
    public function apply(array $nodes, EntityManagerInterface $em): void
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
            /** @var list<CascadeNode> $children */
            $children = $node['children'];
            $this->apply($children, $em);
        }
    }

    /**
     * @return \Closure(AbstractPage, Route): ?string
     */
    private static function underPath(string $oldParentPath, string $newParentPath): \Closure
    {
        return static function (AbstractPage $child, Route $route) use ($oldParentPath, $newParentPath): ?string {
            $oldChildPath = $route->getPath();
            if (!str_starts_with($oldChildPath, $oldParentPath . '/')) {
                return null;
            }

            return $newParentPath . substr($oldChildPath, \strlen($oldParentPath));
        };
    }

    /**
     * @param \Closure(AbstractPage, Route): ?string $newPathOf
     * @param array<int, AbstractPage>               $visited
     *
     * @return list<CascadeNode>
     */
    private function planFromPage(AbstractPage $page, \Closure $newPathOf, EntityManagerInterface $em, array &$visited): array
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

            $newChildPath = $newPathOf($child, $childRoute);
            if (null === $newChildPath) {
                continue;
            }

            $oldChildPath = $childRoute->getPath();
            $nodes[] = [
                'route' => $childRoute,
                'oldPath' => $oldChildPath,
                'newPath' => $newChildPath,
                'children' => $this->planFromPage($child, self::underPath($oldChildPath, $newChildPath), $em, $visited),
            ];
        }

        foreach ($unroutedChildren as $child) {
            array_push($nodes, ...$this->planFromPage($child, $newPathOf, $em, $visited));
        }

        return $nodes;
    }

    /**
     * @param list<CascadeNode> $nodes
     */
    private function collectViolations(array $nodes, object $root, string $propertyPath, string $messageFormat, EntityManagerInterface $em, ConstraintViolationList $violations): void
    {
        foreach ($nodes as $node) {
            foreach ($this->pathViolations($node['newPath'], $em) as [$message, $code, $constraint]) {
                $violations->add(new ConstraintViolation(
                    \sprintf($messageFormat, $node['oldPath'], $node['newPath'], $message),
                    null,
                    [],
                    $root,
                    $propertyPath,
                    $node['newPath'],
                    null,
                    $code,
                    $constraint,
                ));
            }
            /** @var list<CascadeNode> $children */
            $children = $node['children'];
            $this->collectViolations($children, $root, $propertyPath, $messageFormat, $em, $violations);
        }
    }

    /**
     * @return list<array{string|\Stringable, ?string, mixed}>
     */
    private function pathViolations(string $path, EntityManagerInterface $em): array
    {
        $found = [];
        foreach ($this->validator->validatePropertyValue(Route::class, 'path', $path) as $violation) {
            $found[] = [$violation->getMessage(), $violation->getCode(), $violation->getConstraint()];
        }
        if ($em->getRepository(Route::class)->count(['path' => $path]) > 0) {
            $found[] = [self::TAKEN_MESSAGE, null, null];
        }

        return $found;
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
