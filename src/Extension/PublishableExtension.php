<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Security;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableExtension implements QueryItemExtensionInterface
{
    private Security $security;
    private string $permission;

    public function __construct(Security $security, string $permission)
    {
        $this->security = $security;
        $this->permission = $permission;
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, string $operationName = null, array $context = [])
    {
        if (!is_a($resourceClass, PublishableInterface::class, true)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        if (!$this->security->isGranted(new Expression($this->permission))) {
            // User has no access to draft object
            $queryBuilder
                ->andWhere("$alias.publishedAt IS NOT NULL")
                ->andWhere("$alias.publishedAt >= :currentTime");

            return;
        }

        // Reset queryBuilder to prevent an invalid DQL
        $queryBuilder->where('1 = 1');
        $publishedResourceAlias = $queryNameGenerator->generateJoinAlias('publishedResource');
        $queryBuilder->leftJoin("$alias.publishedResource", $publishedResourceAlias);

        foreach ($identifiers as $identifier) {
            // (o.id = :id AND o.publishedAt IS NOT NULL AND o.publishedAt <= :currentTime)
            // OR ((o.publishedAt IS NULL OR o.publishedAt > :currentTime) AND o.publishedResource = :id)
            $queryBuilder->orWhere(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq("$alias.$identifier", ":id_$identifier"),
                    $queryBuilder->expr()->isNotNull("$alias.publishedAt"),
                    $queryBuilder->expr()->lte("$alias.publishedAt", ':currentTime'),
                ),
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->isNull("$alias.publishedAt"),
                        $queryBuilder->expr()->gt("$alias.publishedAt", ':currentTime'),
                    ),
                    $queryBuilder->expr()->eq("$publishedResourceAlias.id", ":id_$identifier"),
                )
            )->setParameter('currentTime', new \DateTimeImmutable());
        }
    }
}
