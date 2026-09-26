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

        $groups = $this->findUnanchoredGroups($entityManager);
        $positions = $this->findCandidatePositions($entityManager);
        [$componentClasses, $components] = $this->findCandidateComponents($entityManager);

        $reaches = [];
        $live = [];
        foreach ($groups as $group => ['owners' => $owners]) {
            foreach ($owners as $owner) {
                if (isset($components[$owner])) {
                    $reaches['c' . $owner][] = 'g' . $group;
                } else {
                    $live['g' . $group] = true;
                }
            }
        }
        foreach ($components as $component => ['published' => $published]) {
            if (null !== $published && isset($components[$published])) {
                $reaches['c' . $published][] = 'c' . $component;
            } elseif (null !== $published) {
                $live['c' . $component] = true;
            }
        }
        foreach ($positions as ['group' => $group, 'component' => $component]) {
            if (null !== $component && isset($components[$component])) {
                $reaches['g' . $group][] = 'c' . $component;
            }
        }

        $queue = array_keys($live);
        while (null !== ($node = array_pop($queue))) {
            foreach ($reaches[$node] ?? [] as $reached) {
                if (!isset($live[$reached])) {
                    $live[$reached] = true;
                    $queue[] = $reached;
                }
            }
        }

        $orphanedGroups = [];
        foreach ($groups as $group => ['id' => $id]) {
            if (!isset($live['g' . $group])) {
                $orphanedGroups[] = [ComponentGroup::class, $id];
            }
        }
        $orphanedPositions = [];
        foreach ($positions as ['id' => $id, 'group' => $group, 'empty' => $empty]) {
            if ($empty || (isset($groups[$group]) && !isset($live['g' . $group]))) {
                $orphanedPositions[] = [ComponentPosition::class, $id];
            }
        }
        $orphanedComponents = [];
        foreach ($components as $component => ['id' => $id, 'type' => $type, 'published' => $published]) {
            if (null === $published && !isset($live['c' . $component])) {
                $orphanedComponents[] = [$componentClasses[$type], $id];
            }
        }

        return [
            self::COMPONENT_GROUPS => $this->toSortedReferences($entityManager, $orphanedGroups),
            self::COMPONENT_POSITIONS => $this->toSortedReferences($entityManager, $orphanedPositions),
            self::COMPONENTS => $this->toSortedReferences($entityManager, $orphanedComponents),
        ];
    }

    /**
     * @return array<string, array{id: mixed, owners: list<string>}>
     */
    private function findUnanchoredGroups(EntityManagerInterface $entityManager): array
    {
        $rows = $entityManager->createQueryBuilder()
            ->select('g.id AS id', 'o.id AS owner')
            ->from(ComponentGroup::class, 'g')
            ->leftJoin('g.components', 'o')
            ->andWhere('g.pages IS EMPTY')
            ->andWhere('g.layouts IS EMPTY')
            ->getQuery()
            ->getResult();

        $groups = [];
        foreach ($rows as $row) {
            $groups[(string) $row['id']]['id'] = $row['id'];
            $groups[(string) $row['id']]['owners'] ??= [];
            if (null !== $row['owner']) {
                $groups[(string) $row['id']]['owners'][] = (string) $row['owner'];
            }
        }

        return $groups;
    }

    /**
     * @return list<array{id: mixed, group: string, component: ?string, empty: bool}>
     */
    private function findCandidatePositions(EntityManagerInterface $entityManager): array
    {
        $rows = $entityManager->createQueryBuilder()
            ->select('p.id AS id', 'IDENTITY(p.componentGroup) AS grp', 'IDENTITY(p.component) AS component', 'CASE WHEN p.component IS NULL AND p.pageDataProperty IS NULL THEN 1 ELSE 0 END AS empty')
            ->from(ComponentPosition::class, 'p')
            ->innerJoin('p.componentGroup', 'g')
            ->andWhere('(g.pages IS EMPTY AND g.layouts IS EMPTY) OR (p.component IS NULL AND p.pageDataProperty IS NULL)')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row) => [
            'id' => $row['id'],
            'group' => (string) $row['grp'],
            'component' => null === $row['component'] ? null : (string) $row['component'],
            'empty' => 1 === (int) $row['empty'],
        ], $rows);
    }

    /**
     * @return array{list<class-string>, array<string, array{id: mixed, type: int, published: ?string}>}
     */
    private function findCandidateComponents(EntityManagerInterface $entityManager): array
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
            ->from(AbstractComponent::class, 'c')
            ->andWhere(\sprintf('c.id NOT IN (SELECT IDENTITY(ap.component) FROM %s ap JOIN ap.componentGroup ag WHERE ap.component IS NOT NULL AND (ag.pages IS NOT EMPTY OR ag.layouts IS NOT EMPTY))', ComponentPosition::class));

        $published = [];
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
                    $alias = 'published' . \count($published);
                    $published[] = $alias;
                    $queryBuilder->addSelect(\sprintf('(SELECT IDENTITY(d%1$s.%2$s) FROM %3$s d%1$s WHERE d%1$s.id = c.id) AS %1$s', $alias, $field, $metadata->getName()));
                }
            }
        }

        $components = [];
        foreach ($queryBuilder->getQuery()->getResult() as $row) {
            $publishedId = null;
            foreach ($published as $alias) {
                $publishedId ??= $row[$alias];
            }
            $components[(string) $row['id']] = [
                'id' => $row['id'],
                'type' => (int) $row['type'],
                'published' => null === $publishedId ? null : (string) $publishedId,
            ];
        }

        return [$componentClasses, $components];
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
