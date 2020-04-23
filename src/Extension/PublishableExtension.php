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

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Annotation\Publishable;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableExtension implements QueryItemExtensionInterface, ContextAwareQueryCollectionExtensionInterface
{
    private AuthorizationCheckerInterface $authorizationChecker;
    private Reader $reader;
    private ManagerRegistry $registry;
    private RequestStack $requestStack;
    private string $permission;
    private ?Publishable $configuration;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, Reader $reader, ManagerRegistry $registry, RequestStack $requestStack, string $permission)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->reader = $reader;
        $this->registry = $registry;
        $this->requestStack = $requestStack;
        $this->permission = $permission;
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, string $operationName = null, array $context = []): void
    {
        $configuration = $this->getConfiguration($resourceClass);
        if (!$configuration || !($request = $this->requestStack->getCurrentRequest())) {
            return;
        }

        if (!$this->isAllowed($context)) {
            // User has no access to draft object
            $this->updateQueryBuilderForUnauthorizedUsers($queryBuilder, $configuration);

            return;
        }

        // Reset queryBuilder to prevent an invalid DQL
        $queryBuilder->where('1 = 1');

        $alias = $queryBuilder->getRootAliases()[0];
        foreach ($identifiers as $identifier) {
            // (o.id = :id AND o.publishedAt IS NOT NULL AND o.publishedAt <= :currentTime)
            // OR ((o.publishedAt IS NULL OR o.publishedAt > :currentTime) AND o.publishedResource = :id)
            $queryBuilder->orWhere(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq("$alias.$identifier", ":id_$identifier"),
                    $queryBuilder->expr()->isNotNull("$alias.$configuration->fieldName"),
                    $queryBuilder->expr()->lte("$alias.$configuration->fieldName", ':currentTime'),
                ),
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->isNull("$alias.$configuration->fieldName"),
                        $queryBuilder->expr()->gt("$alias.$configuration->fieldName", ':currentTime'),
                    ),
                    $queryBuilder->expr()->eq("$alias.$configuration->associationName", ":id_$identifier"),
                )
            )->setParameter('currentTime', new \DateTimeImmutable())
            ->setParameter("id_$identifier", $identifier);
        }
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        if (!$configuration = $this->getConfiguration($resourceClass)) {
            return;
        }

        $configuration = $this->getConfiguration($resourceClass);
        if (!$this->isAllowed($context)) {
            // User has no access to draft object
            $this->updateQueryBuilderForUnauthorizedUsers($queryBuilder, $configuration);

            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $publishedResourceAlias = $queryNameGenerator->generateJoinAlias($configuration->associationName);
        $queryBuilder->leftJoin("$alias.$configuration->associationName", $publishedResourceAlias);

        // (o.publishedAt IS NOT NULL AND o.publishedAt <= :currentTime) OR (o.publishedAt IS NULL OR o.publishedAt > :currentTime)
        $queryBuilder->orWhere(
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->isNotNull("$alias.$configuration->fieldName"),
                $queryBuilder->expr()->lte("$alias.$configuration->fieldName", ':currentTime'),
            ),
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull("$alias.$configuration->fieldName"),
                $queryBuilder->expr()->gt("$alias.$configuration->fieldName", ':currentTime'),
            ),
        )->setParameter('currentTime', new \DateTimeImmutable());
    }

    private function isAllowed(array $context): bool
    {
        return $this->authorizationChecker->isGranted(new Expression($this->permission)) && false === ($context['filters']['published'] ?? false);
    }

    private function updateQueryBuilderForUnauthorizedUsers(QueryBuilder $queryBuilder, Publishable $configuration): void
    {
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere("$alias.$configuration->fieldName IS NOT NULL")
            ->andWhere("$alias.$configuration->fieldName >= :currentTime")
            ->setParameter('currentTime', new \DateTimeImmutable());
    }

    private function getConfiguration(string $resourceClass): ?Publishable
    {
        if (!$this->configuration && ($em = $this->registry->getManagerForClass($resourceClass))) {
            $this->configuration = $this->reader->getClassAnnotation($em->getClassMetadata($resourceClass)->getReflectionClass(), Publishable::class);
        }

        return $this->configuration;
    }
}
