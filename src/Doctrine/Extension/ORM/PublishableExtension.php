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
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableExtension implements QueryItemExtensionInterface, QueryCollectionExtensionInterface
{
    private PublishableStatusChecker $publishableStatusChecker;
    private RequestStack $requestStack;
    private ManagerRegistry $registry;

    public function __construct(PublishableStatusChecker $publishableStatusChecker, RequestStack $requestStack, ManagerRegistry $registry)
    {
        $this->publishableStatusChecker = $publishableStatusChecker;
        $this->requestStack = $requestStack;
        $this->registry = $registry;
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, Operation $operation = null, array $context = []): void
    {
        $configuration = $this->getConfiguration($resourceClass);

        if (!$configuration || !$this->requestStack->getCurrentRequest()) {
            return;
        }

        if (!$this->isDraftRequest($resourceClass, $context)) {
            // User has no access to draft object
            $this->updateQueryBuilderForUnauthorizedUsers($queryBuilder, $configuration);

            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $classMetadata = $this->registry->getManagerForClass($resourceClass)->getClassMetadata($resourceClass);

        // (o.publishedResource = :id OR o.id = :id) ORDER BY o.publishedResource IS NULL LIMIT 1
        $criteriaReset = false;
        $altQueryNameGenerator = new QueryNameGenerator();
        foreach ($identifiers as $identifier => $value) {
            $placeholder = $altQueryNameGenerator->generateParameterName($identifier);

            $predicates = $queryBuilder->expr()->orX($queryBuilder->expr()->eq("$alias.$configuration->associationName", ":$placeholder"), $queryBuilder->expr()->eq("$alias.$identifier", ":$placeholder"));

            // Reset queryBuilder to prevent an invalid DQL
            if (!$criteriaReset) {
                $queryBuilder->where($predicates);
                $criteriaReset = true;
            } else {
                $queryBuilder->andWhere($predicates);
            }
            $queryBuilder->setParameter($placeholder, $value, $classMetadata->getTypeOfField($identifier));
        }

        $queryBuilder->addSelect("CASE WHEN $alias.$configuration->associationName IS NULL THEN 1 ELSE 0 END AS HIDDEN assocNameSort");
        $queryBuilder->orderBy('assocNameSort', 'ASC');
        $queryBuilder->setMaxResults(1);
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (!$configuration = $this->getConfiguration($resourceClass)) {
            return;
        }

        if (!$this->isDraftRequest($resourceClass, $context)) {
            // User has no access to draft object
            $this->updateQueryBuilderForUnauthorizedUsers($queryBuilder, $configuration);

            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $identifiers = $this->registry->getManagerForClass($resourceClass)->getClassMetadata($resourceClass)->getIdentifier();
        $dql = $this->getDQL($configuration, $resourceClass);

        // o.id NOT IN (SELECT p.publishedResource FROM {table} t WHERE t.publishedResource IS NOT NULL)
        foreach ($identifiers as $identifier) {
            $queryBuilder->andWhere($queryBuilder->expr()->notIn("$alias.$identifier", $dql));
        }
    }

    private function getDQL(Publishable $configuration, string $resourceClass): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->registry->getManagerForClass($resourceClass)->getRepository($resourceClass);
        $queryBuilder = $repository->createQueryBuilder('o2');

        return $queryBuilder
            ->select("IDENTITY(o2.$configuration->associationName)")
            ->where($queryBuilder->expr()->isNotNull("o2.$configuration->associationName"))
            ->getDQL();
    }

    private function isDraftRequest(string $resourceClass, array $context): bool
    {
        return $this->publishableStatusChecker->isGranted($resourceClass) && false === ($context['filters']['published'] ?? false);
    }

    private function updateQueryBuilderForUnauthorizedUsers(QueryBuilder $queryBuilder, Publishable $configuration): void
    {
        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere("$alias.$configuration->fieldName IS NOT NULL")
            ->andWhere("$alias.$configuration->fieldName <= :currentTime")
            ->setParameter('currentTime', new \DateTimeImmutable());
    }

    private function getConfiguration(string $resourceClass): ?Publishable
    {
        if (!$this->publishableStatusChecker->getAnnotationReader()->isConfigured($resourceClass)) {
            return null;
        }

        return $this->publishableStatusChecker->getAnnotationReader()->getConfiguration($resourceClass);
    }
}
