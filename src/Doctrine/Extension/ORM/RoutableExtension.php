<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Doctrine\Extension\ORM;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Symfony\Security\ResourceAccessCheckerInterface;
use Doctrine\ORM\QueryBuilder;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RoutableExtension implements QueryCollectionExtensionInterface
{
    private ?string $securityStr;
    private ResourceAccessCheckerInterface $resourceAccessChecker;

    public function __construct(?string $securityStr, ResourceAccessCheckerInterface $resourceAccessChecker)
    {
        $this->securityStr = $securityStr;
        $this->resourceAccessChecker = $resourceAccessChecker;
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
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

        // we may want to include pages which are routable - but if they are included in a routable page data with a
        // publicly accessible route... should we not be trying to restrict routes further to what the routevoter says?
        // .. or the route extension... ??
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->isNotNull("$alias.route")
            );
    }
}
