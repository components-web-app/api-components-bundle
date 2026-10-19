<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentUsage;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

/**
 * @phpstan-import-type Location from ComponentUsageLocations
 * @phpstan-import-type PresentedLocation from ComponentUsageLocations
 * @phpstan-import-type RenderedOn from ComponentUsageLocations
 * @phpstan-import-type LayoutUse from ComponentUsageLocations
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class ComponentUsageLocator
{
    use ClassMetadataTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly PublishableAttributeReader $publishableAttributeReader,
        private readonly IriConverterInterface $iriConverter,
    ) {
        $this->initRegistry($registry);
    }

    /**
     * @param list<ComponentPosition>                                   $positions
     * @param list<array{pageData: AbstractPageData, property: string}> $pageDataUses
     *
     * @return list<PresentedLocation>
     */
    public function locate(array $positions, array $pageDataUses): array
    {
        $entityManager = $this->getEntityManager(ComponentPosition::class);

        $graph = [] === $positions ? new ComponentUsageGraph() : $this->loadGraph($entityManager);
        $walks = [];
        $groups = [];
        $components = [];
        foreach ($positions as $index => $position) {
            $walk = $graph->walkUpFrom((string) $position->componentGroup->getId());
            $walks[$index] = $walk;
            array_push($groups, ...$walk['groups']);
            array_push($components, ...$walk['components']);
        }
        $groups = array_values(array_unique($groups));
        $components = array_values(array_unique($components));

        $pagesByGroup = $this->findPagesByGroup($entityManager, $groups);
        $layoutsByGroup = $this->findLayoutsByGroup($entityManager, $groups);
        $templates = [];
        foreach ($pagesByGroup as $pages) {
            foreach ($pages as $page) {
                if ($page['isTemplate']) {
                    $templates[$page['id']] = $page['id'];
                }
            }
        }
        $pageDataByTemplate = $this->findPageDataByTemplate($entityManager, array_values($templates));
        $pageDataIdsByComponent = $this->findPageDataIdsByComponent($entityManager, $components);

        $detailIds = [];
        foreach ($pageDataIdsByComponent as $ids) {
            array_push($detailIds, ...$ids);
        }
        foreach ($pageDataUses as $use) {
            $detailIds[] = (string) $use['pageData']->getId();
        }
        $pageDataDetails = $this->findPageData($entityManager, array_values(array_unique($detailIds)));

        $locations = [];
        foreach ($positions as $index => $position) {
            $renderedOn = [];
            $layouts = [];
            foreach ($walks[$index]['groups'] as $group) {
                foreach ($pagesByGroup[$group] ?? [] as $page) {
                    $renderedOn[] = $page['renderedOn'];
                    if ($page['isTemplate']) {
                        array_push($renderedOn, ...$pageDataByTemplate[$page['id']] ?? []);
                    }
                }
                array_push($layouts, ...$layoutsByGroup[$group] ?? []);
            }
            foreach ($walks[$index]['components'] as $component) {
                foreach ($pageDataIdsByComponent[$component] ?? [] as $pageDataId) {
                    $renderedOn[] = $pageDataDetails[$pageDataId];
                }
            }
            usort($layouts, static fn (array $a, array $b): int => strcmp($a['reference'], $b['reference']) ?: strcmp($a['iri'], $b['iri']));

            $locations[] = [
                'componentPosition' => $this->iriConverter->getIriFromResource($position),
                'pageDataProperty' => $position->pageDataProperty,
                'pageData' => null,
                'property' => null,
                'layout' => $layouts[0] ?? null,
                'renderedOn' => $renderedOn,
            ];
        }

        foreach ($pageDataUses as $use) {
            $locations[] = [
                'componentPosition' => null,
                'pageDataProperty' => null,
                'pageData' => $this->iriConverter->getIriFromResource($use['pageData']),
                'property' => $use['property'],
                'layout' => null,
                'renderedOn' => [$pageDataDetails[(string) $use['pageData']->getId()]],
            ];
        }

        return ComponentUsageLocations::present($locations);
    }

    private function loadGraph(EntityManagerInterface $entityManager): ComponentUsageGraph
    {
        $ownerQuery = $entityManager->createQueryBuilder()
            ->select('g.id AS grp', 'c.id AS owner')
            ->from(AbstractComponent::class, 'c')
            ->innerJoin('c.componentGroups', 'g');
        $positionQuery = $entityManager->createQueryBuilder()
            ->select('IDENTITY(p.component) AS component', 'IDENTITY(p.componentGroup) AS grp')
            ->from(ComponentPosition::class, 'p');
        $holdsAnOwner = [\sprintf('IDENTITY(p.component) IN (SELECT o.id FROM %s o WHERE o.componentGroups IS NOT EMPTY)', AbstractComponent::class)];

        $publishedAliases = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || !is_a($metadata->getName(), AbstractComponent::class, true) || !$this->publishableAttributeReader->isConfigured($metadata->getName())) {
                continue;
            }
            $field = $this->publishableAttributeReader->getConfiguration($metadata->getName())->associationName;
            if (!$metadata->hasAssociation($field) || $metadata->isInheritedAssociation($field)) {
                continue;
            }
            $alias = 'published' . \count($publishedAliases);
            $publishedAliases[] = $alias;
            $ownerQuery->addSelect(\sprintf('(SELECT IDENTITY(o%1$s.%2$s) FROM %3$s o%1$s WHERE o%1$s.id = c.id) AS %1$s', $alias, $field, $metadata->getName()));
            $holdsAnOwner[] = \sprintf('IDENTITY(p.component) IN (SELECT IDENTITY(%1$s.%2$s) FROM %3$s %1$s WHERE %1$s.componentGroups IS NOT EMPTY AND %1$s.%2$s IS NOT NULL)', 'd' . $alias, $field, $metadata->getName());
        }

        $positionQuery->where(implode(' OR ', $holdsAnOwner));

        $ownersByGroup = [];
        $publishedByComponent = [];
        foreach ($ownerQuery->getQuery()->getResult() as $row) {
            $owner = (string) $row['owner'];
            $ownersByGroup[(string) $row['grp']][] = $owner;
            foreach ($publishedAliases as $alias) {
                if (null !== $row[$alias]) {
                    $publishedByComponent[$owner] = (string) $row[$alias];
                }
            }
        }

        $groupsByComponent = [];
        foreach ($positionQuery->getQuery()->getResult() as $row) {
            $groupsByComponent[(string) $row['component']][] = (string) $row['grp'];
        }

        return new ComponentUsageGraph($ownersByGroup, $publishedByComponent, $groupsByComponent);
    }

    /**
     * @param list<string> $groups
     *
     * @return array<string, list<array{id: string, isTemplate: bool, renderedOn: RenderedOn}>>
     */
    private function findPagesByGroup(EntityManagerInterface $entityManager, array $groups): array
    {
        if ([] === $groups) {
            return [];
        }

        $rows = $entityManager->createQueryBuilder()
            ->select('g.id AS grp', 'pg.id AS id', 'pg.isTemplate AS isTemplate', 'pg.title AS title', 'r.path AS routePath')
            ->from(Page::class, 'pg')
            ->innerJoin('pg.componentGroups', 'g')
            ->leftJoin('pg.route', 'r')
            ->where('g.id IN (:groups)')
            ->setParameter('groups', $groups, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();

        $pagesByGroup = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $pagesByGroup[(string) $row['grp']][] = [
                'id' => $id,
                'isTemplate' => (bool) $row['isTemplate'],
                'renderedOn' => $this->renderedOn(Page::class, $id, $row['title'], $row['routePath']),
            ];
        }

        return $pagesByGroup;
    }

    /**
     * @param list<string> $groups
     *
     * @return array<string, list<LayoutUse>>
     */
    private function findLayoutsByGroup(EntityManagerInterface $entityManager, array $groups): array
    {
        if ([] === $groups) {
            return [];
        }

        $rows = $entityManager->createQueryBuilder()
            ->select('g.id AS grp', 'l.id AS id', 'l.reference AS reference')
            ->from(Layout::class, 'l')
            ->innerJoin('l.componentGroups', 'g')
            ->where('g.id IN (:groups)')
            ->setParameter('groups', $groups, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();

        $layoutsByGroup = [];
        foreach ($rows as $row) {
            $layoutsByGroup[(string) $row['grp']][] = [
                'iri' => $this->iri(Layout::class, (string) $row['id']),
                'reference' => (string) $row['reference'],
            ];
        }

        return $layoutsByGroup;
    }

    /**
     * @param list<string> $templates
     *
     * @return array<string, list<RenderedOn>>
     */
    private function findPageDataByTemplate(EntityManagerInterface $entityManager, array $templates): array
    {
        if ([] === $templates) {
            return [];
        }

        $byTemplate = [];
        foreach ($this->queryPageData($entityManager, 'IDENTITY(pd.page) IN (:ids)', $templates, 'IDENTITY(pd.page) AS template') as $row) {
            $byTemplate[(string) $row['template']][] = $row['renderedOn'];
        }

        return $byTemplate;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, RenderedOn>
     */
    private function findPageData(EntityManagerInterface $entityManager, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $byId = [];
        foreach ($this->queryPageData($entityManager, 'pd.id IN (:ids)', $ids) as $row) {
            $byId[$row['id']] = $row['renderedOn'];
        }

        return $byId;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array{id: string, template: mixed, renderedOn: RenderedOn}>
     */
    private function queryPageData(EntityManagerInterface $entityManager, string $condition, array $ids, ?string $extraSelect = null): array
    {
        $classes = $this->concreteClasses($entityManager->getClassMetadata(AbstractPageData::class));
        $type = 'CASE';
        foreach ($classes as $index => $class) {
            $type .= \sprintf(' WHEN pd INSTANCE OF %s THEN %d', $class, $index);
        }
        $type .= \sprintf(' ELSE %d END', \count($classes) - 1);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('pd.id AS id', $type . ' AS type', 'pd.title AS title', 'r.path AS routePath')
            ->from(AbstractPageData::class, 'pd')
            ->leftJoin('pd.route', 'r')
            ->where($condition)
            ->setParameter('ids', $ids, ArrayParameterType::STRING);
        if (null !== $extraSelect) {
            $queryBuilder->addSelect($extraSelect);
        }

        $rows = [];
        foreach ($queryBuilder->getQuery()->getResult() as $row) {
            $id = (string) $row['id'];
            $rows[] = [
                'id' => $id,
                'template' => $row['template'] ?? null,
                'renderedOn' => $this->renderedOn($classes[(int) $row['type']], $id, $row['title'], $row['routePath']),
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $components
     *
     * @return array<string, list<string>>
     */
    private function findPageDataIdsByComponent(EntityManagerInterface $entityManager, array $components): array
    {
        if ([] === $components) {
            return [];
        }

        $byComponent = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || !is_a($metadata->getName(), AbstractPageData::class, true)) {
                continue;
            }
            $fields = $this->ownComponentAssociations($metadata);
            if ([] === $fields) {
                continue;
            }
            $queryBuilder = $entityManager->createQueryBuilder()
                ->select('pd.id AS id')
                ->from($metadata->getName(), 'pd')
                ->setParameter('components', $components, ArrayParameterType::STRING);
            $holdsAComponent = [];
            foreach ($fields as $index => $field) {
                $queryBuilder->addSelect(\sprintf('IDENTITY(pd.%s) AS field%d', $field, $index));
                $holdsAComponent[] = \sprintf('IDENTITY(pd.%s) IN (:components)', $field);
            }
            $queryBuilder->where(implode(' OR ', $holdsAComponent));
            foreach ($queryBuilder->getQuery()->getResult() as $row) {
                foreach (array_keys($fields) as $index) {
                    $component = $row['field' . $index];
                    if (null !== $component && \in_array((string) $component, $components, true)) {
                        $byComponent[(string) $component][] = (string) $row['id'];
                    }
                }
            }
        }

        return $byComponent;
    }

    /**
     * @return list<string>
     */
    private function ownComponentAssociations(ClassMetadata $metadata): array
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

    /**
     * @return list<class-string>
     */
    private function concreteClasses(ClassMetadata $metadata): array
    {
        $classes = array_values(array_unique(array_values($metadata->discriminatorMap)));
        usort($classes, static fn (string $a, string $b): int => \count(class_parents($b)) <=> \count(class_parents($a)) ?: strcmp($a, $b));

        return $classes;
    }

    /**
     * @param class-string $class
     *
     * @return RenderedOn
     */
    private function renderedOn(string $class, string $id, mixed $title, mixed $routePath): array
    {
        return [
            'iri' => $this->iri($class, $id),
            'title' => null === $title ? null : (string) $title,
            'routePath' => null === $routePath ? null : (string) $routePath,
        ];
    }

    /**
     * @param class-string $class
     */
    private function iri(string $class, string $id): string
    {
        $reference = $this->getEntityManager($class)->getReference($class, Uuid::fromString($id));

        return (string) $this->iriConverter->getIriFromResource($reference);
    }
}
