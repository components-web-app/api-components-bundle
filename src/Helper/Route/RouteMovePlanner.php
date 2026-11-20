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
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Validator\Constraints\RoutePathStart;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @phpstan-import-type CascadeNode from RouteCascadePlanner
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class RouteMovePlanner
{
    public const string MESSAGE = 'Moving this page would change the route "%s" to "%s". %s';

    public function __construct(
        private RoutePrefixResolver $routePrefixResolver,
        private RouteCascadePlanner $cascadePlanner,
    ) {
    }

    /**
     * @return list<CascadeNode>
     */
    public function plan(AbstractPage $moved, ?AbstractPage $originalParent, EntityManagerInterface $em): array
    {
        $oldAncestorPath = $this->routePrefixResolver->ancestorPathFrom($originalParent, $moved);
        $route = $moved->getRoute();
        if (null === $route) {
            return $this->cascadePlanner->planNearestRoutedDescendants(
                $moved,
                fn (AbstractPage $descendant, Route $descendantRoute): string => $this->newPath($descendant, $descendantRoute, $oldAncestorPath),
                $em,
            );
        }

        $newPath = $this->newPath($moved, $route, $oldAncestorPath);
        if ($newPath === $route->getPath()) {
            return [];
        }

        return [$this->cascadePlanner->node($route, $newPath, $em)];
    }

    /**
     * @param list<CascadeNode> $nodes
     */
    public function violations(array $nodes, object $root, string $propertyPath, EntityManagerInterface $em): ConstraintViolationListInterface
    {
        $violations = $this->cascadePlanner->violations($nodes, $root, $propertyPath, self::MESSAGE, $em);
        $constraint = new RoutePathStart();
        foreach ($nodes as $node) {
            if ('/' === $node['newPath'] || !str_ends_with($node['newPath'], '/')) {
                continue;
            }
            $violations->add(new ConstraintViolation(
                \sprintf(self::MESSAGE, $node['oldPath'], $node['newPath'], str_replace('{{ start }}', $node['newPath'], $constraint->message)),
                null,
                [],
                $root,
                $propertyPath,
                $node['newPath'],
                null,
                RoutePathStart::PATH_OUTSIDE_START_ERROR,
                $constraint,
            ));
        }

        return $violations;
    }

    public static function movedPath(string $oldPath, ?string $oldStart, ?string $newStart): string
    {
        $oldStart ??= '/';
        $rest = str_starts_with($oldPath, $oldStart) ? substr($oldPath, \strlen($oldStart)) : '';
        if ('' === $rest) {
            $segments = explode('/', $oldPath);
            $rest = array_pop($segments);
        }

        return ($newStart ?? '/') . $rest;
    }

    private function newPath(AbstractPage $routable, Route $route, ?string $oldAncestorPath): string
    {
        return self::movedPath(
            $route->getPath(),
            $this->routePrefixResolver->requiredStartBelow($routable, $oldAncestorPath),
            $this->routePrefixResolver->requiredStart($routable),
        );
    }
}
