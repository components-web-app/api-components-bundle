<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedResource;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

class OrphanedResourceDetector
{
    use ClassMetadataTrait;

    public const string COMPONENT_GROUPS = 'componentGroups';
    public const string COMPONENT_POSITIONS = 'componentPositions';
    public const string COMPONENTS = 'components';

    public function __construct(
        ManagerRegistry $registry,
        private readonly PublishableAttributeReader $publishableAttributeReader,
        private readonly IriConverterInterface $iriConverter,
    ) {
        $this->initRegistry($registry);
    }

    public function detect(): OrphanedResourceReport
    {
        $orphans = $this->findOrphans();

        return new OrphanedResourceReport(
            new \DateTimeImmutable(),
            array_keys($orphans[self::COMPONENT_GROUPS]),
            array_keys($orphans[self::COMPONENT_POSITIONS]),
            array_keys($orphans[self::COMPONENTS]),
        );
    }

    /**
     * @return array{componentGroups: array<string, array{class-string, mixed}>, componentPositions: array<string, array{class-string, mixed}>, components: array<string, array{class-string, mixed}>}
     */
    public function findOrphans(): array
    {
        $entityManager = $this->getEntityManager(AbstractComponent::class);

        return [
            self::COMPONENT_GROUPS => $this->findOrphanedComponentGroups($entityManager),
            self::COMPONENT_POSITIONS => $this->findEmptyComponentPositions($entityManager),
            self::COMPONENTS => $this->findUnusedComponents($entityManager),
        ];
    }

    /**
     * @return array<string, array{class-string, mixed}>
     */
    private function findOrphanedComponentGroups(EntityManagerInterface $entityManager): array
    {
        $ids = $entityManager->createQueryBuilder()
            ->select('g.id')
            ->from(ComponentGroup::class, 'g')
            ->andWhere('g.pages IS EMPTY')
            ->andWhere('g.layouts IS EMPTY')
            ->andWhere('g.components IS EMPTY')
            ->getQuery()
            ->getResult();

        return $this->toSortedReferences($entityManager, array_map(static fn (array $row) => [ComponentGroup::class, $row['id']], $ids));
    }

    /**
     * @return array<string, array{class-string, mixed}>
     */
    private function findEmptyComponentPositions(EntityManagerInterface $entityManager): array
    {
        $ids = $entityManager->createQueryBuilder()
            ->select('p.id')
            ->from(ComponentPosition::class, 'p')
            ->andWhere('p.component IS NULL')
            ->andWhere('p.pageDataProperty IS NULL')
            ->getQuery()
            ->getResult();

        return $this->toSortedReferences($entityManager, array_map(static fn (array $row) => [ComponentPosition::class, $row['id']], $ids));
    }

    /**
     * @return array<string, array{class-string, mixed}>
     */
    private function findUnusedComponents(EntityManagerInterface $entityManager): array
    {
        $componentClasses = array_values($entityManager->getClassMetadata(AbstractComponent::class)->discriminatorMap);
        usort($componentClasses, static fn (string $a, string $b) => \count(class_parents($b)) <=> \count(class_parents($a)));
        $type = 'CASE';
        foreach ($componentClasses as $index => $componentClass) {
            $type .= \sprintf(' WHEN c INSTANCE OF %s THEN %d', $componentClass, $index);
        }
        $type .= \sprintf(' ELSE %d END', \count($componentClasses) - 1);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('c.id AS id', $type . ' AS type')
            ->from(AbstractComponent::class, 'c');
        $this->excludeReferenced($queryBuilder, ComponentPosition::class, 'component');

        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass) {
                continue;
            }
            if (is_a($metadata->getName(), AbstractPageData::class, true)) {
                foreach ($this->getOwnComponentAssociations($metadata) as $field) {
                    $this->excludeReferenced($queryBuilder, $metadata->getName(), $field);
                }
            }
            if (is_a($metadata->getName(), AbstractComponent::class, true) && $this->publishableAttributeReader->isConfigured($metadata->getName())) {
                $field = $this->publishableAttributeReader->getConfiguration($metadata->getName())->associationName;
                if ($metadata->hasAssociation($field) && !$metadata->isInheritedAssociation($field)) {
                    $alias = 'd' . \count($queryBuilder->getDQLPart('where')?->getParts() ?? []);
                    $queryBuilder->andWhere(\sprintf('c.id NOT IN (SELECT %1$s.id FROM %2$s %1$s WHERE %1$s.%3$s IS NOT NULL)', $alias, $metadata->getName(), $field));
                }
            }
        }

        return $this->toSortedReferences($entityManager, array_map(
            static fn (array $row) => [$componentClasses[(int) $row['type']], $row['id']],
            $queryBuilder->getQuery()->getResult()
        ));
    }

    /**
     * @return list<string>
     */
    private function getOwnComponentAssociations(ClassMetadata $metadata): array
    {
        $fields = [];
        foreach ($metadata->getAssociationNames() as $field) {
            if (
                $metadata->isAssociationWithSingleJoinColumn($field)
                && !$metadata->isInheritedAssociation($field)
                && is_a($metadata->getAssociationTargetClass($field), AbstractComponent::class, true)
            ) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function excludeReferenced(QueryBuilder $queryBuilder, string $class, string $field): void
    {
        $alias = 'r' . \count($queryBuilder->getDQLPart('where')?->getParts() ?? []);
        $queryBuilder->andWhere(\sprintf('c.id NOT IN (SELECT IDENTITY(%1$s.%2$s) FROM %3$s %1$s WHERE %1$s.%2$s IS NOT NULL)', $alias, $field, $class));
    }

    /**
     * @param array<array{class-string, mixed}> $references
     *
     * @return array<string, array{class-string, mixed}>
     */
    private function toSortedReferences(EntityManagerInterface $entityManager, array $references): array
    {
        $sorted = [];
        foreach ($references as $reference) {
            $sorted[$this->iriConverter->getIriFromResource($entityManager->getReference($reference[0], $reference[1]))] = $reference;
        }
        ksort($sorted, \SORT_STRING);

        return $sorted;
    }
}
