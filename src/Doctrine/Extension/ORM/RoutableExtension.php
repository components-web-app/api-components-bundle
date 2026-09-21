<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Doctrine\Extension\ORM;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Doctrine\ORM\QueryBuilder;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Helper\Route\RouteAncestorGateResolver;
use Silverback\ApiComponentsBundle\Utility\PublicationDate;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RoutableExtension implements QueryCollectionExtensionInterface
{
    private ?string $securityStr;
    private ResourceAccessCheckerInterface $resourceAccessChecker;

    public function __construct(?string $securityStr, ResourceAccessCheckerInterface $resourceAccessChecker, private readonly RouteAncestorGateResolver $routeAncestorGateResolver)
    {
        $this->securityStr = $securityStr;
        $this->resourceAccessChecker = $resourceAccessChecker;
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!$this->securityStr) {
            return;
        }

        $refl = new \ReflectionClass($resourceClass);
        if (!$refl->implementsInterface(RoutableInterface::class)) {
            return;
        }

        if ($this->resourceAccessChecker->isGranted($resourceClass, $this->securityStr)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $routeAlias = $queryNameGenerator->generateJoinAlias('route');
        $queryBuilder->innerJoin("$alias.route", $routeAlias);
        PublicationDate::andWhereActive($queryBuilder, $routeAlias, 'liveAt');
        $this->routeAncestorGateResolver->andWhereNotGated($queryBuilder, $queryNameGenerator, $routeAlias);
    }
}
