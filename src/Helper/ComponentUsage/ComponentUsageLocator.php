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
use Ramsey\Uuid\UuidInterface;
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
 * @phpstan-import-type Walk from ComponentUsageGraph
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
        $properties = [];
        foreach ($positions as $position) {
            $walk = $graph->walkUpFrom($position->componentGroup->getId()->toString());
            $walks[] = $walk;
            array_push($groups, ...$walk['groups']);
            array_push($components, ...$walk['components']);
            array_push($properties, ...$walk['pageDataProperties']);
            if (null !== $position->pageDataProperty) {
                $properties[] = $position->pageDataProperty;
            }
        }

        $pagesByGroup = $this->findPagesByGroup($entityManager, $groups);
        $layoutsByGroup = $this->findLayoutsByGroup($entityManager, $groups);
        $templates = [];
        foreach ($pagesByGroup as $pages) {
            foreach ($pages as $page) {
                if ($page['isTemplate']) {
                    $templates[] = $page['id'];
                }
            }
        }
        $pageDataByTemplate = $this->findPageDataByTemplate($entityManager, $templates);
        $pageDataIdsByComponent = $this->findPageDataIdsByComponent($entityManager, $components);

        $detailIds = [];
        foreach ($pageDataIdsByComponent as $ids) {
            array_push($detailIds, ...$ids);
        }
        foreach ($pageDataUses as $use) {
            $detailIds[] = $use['pageData']->getId()->toString();
        }
        $pageDataDetails = $this->findPageData($entityManager, $detailIds);

        $candidates = array_map(static fn (array $details): string => $details['class'], $pageDataDetails);
        foreach ($pageDataByTemplate as $pageData) {
            foreach ($pageData as $details) {
                $candidates[$details['id']] = $details['class'];
            }
        }
        $overrides = [] === $properties ? [] : $this->findOverriddenProperties($entityManager, $candidates);

        $locations = [];
        foreach ($positions as $index => $position) {
            $walksFor = $this->walksForPageData($graph, $position, $walks[$index]);
            $renderedOn = [];
            $layouts = [];
            foreach ($walks[$index]['groups'] as $group) {
                foreach ($pagesByGroup[$group] ?? [] as $page) {
                    $renderedOn[] = $page['renderedOn'];
                    foreach ($pageDataByTemplate[$page['id']] ?? [] as $pageData) {
                        if (\in_array($group, $walksFor($overrides[$pageData['id']] ?? [])['groups'], true)) {
                            $renderedOn[] = $pageData['renderedOn'];
                        }
                    }
                }
                array_push($layouts, ...$layoutsByGroup[$group] ?? []);
            }
            foreach ($walks[$index]['components'] as $component) {
                foreach ($pageDataIdsByComponent[$component] ?? [] as $pageDataId) {
                    if (\in_array($component, $walksFor($overrides[$pageDataId] ?? [])['components'], true)) {
                        $renderedOn[] = $pageDataDetails[$pageDataId]['renderedOn'];
                    }
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
                'renderedOn' => [$pageDataDetails[$use['pageData']->getId()->toString()]['renderedOn']],
            ];
        }

        return ComponentUsageLocations::present($locations);
    }

    /**
     * @param Walk $walk
     *
     * @return \Closure(list<string>): Walk
     */
    private function walksForPageData(ComponentUsageGraph $graph, ComponentPosition $position, array $walk): \Closure
    {
        $memo = ['' => $walk];
        $group = $position->componentGroup->getId()->toString();

        return static function (array $overridden) use (&$memo, $graph, $position, $group): array {
            if (\in_array($position->pageDataProperty, $overridden, true)) {
                return ['groups' => [], 'components' => [], 'pageDataProperties' => []];
            }

            return $memo[implode(' ', $overridden)] ??= $graph->walkUpFrom($group, $overridden);
        };
    }

    /**
     * @param array<string, class-string> $candidates
     *
     * @return array<string, list<string>>
     */
    private function findOverriddenProperties(EntityManagerInterface $entityManager, array $candidates): array
    {
        $overridden = [];
        foreach ($this->pageDataComponentAssociations($entityManager) as $class => $fields) {
            $pageDataIds = array_keys($candidates, $class, true);
            if ([] === $pageDataIds) {
                continue;
            }
            $queryBuilder = $entityManager->createQueryBuilder()
                ->select('pd.id AS id')
                ->from($class, 'pd')
                ->where('pd.id IN (:ids)')
                ->setParameter('ids', $pageDataIds, ArrayParameterType::STRING);
            foreach ($fields as $index => $field) {
                $queryBuilder->addSelect(\sprintf('IDENTITY(pd.%s) AS field%d', $field, $index));
            }
            foreach ($queryBuilder->getQuery()->getResult() as $row) {
                foreach ($fields as $index => $field) {
                    if (null !== $row['field' . $index]) {
                        $overridden[$row['id']->toString()][] = $field;
                    }
                }
            }
        }

        return $overridden;
    }

    private function loadGraph(EntityManagerInterface $entityManager): ComponentUsageGraph
    {
        $ownerQuery = $entityManager->createQueryBuilder()
            ->select('g.id AS grp', 'c.id AS owner')
            ->from(AbstractComponent::class, 'c')
            ->innerJoin('c.componentGroups', 'g');
        $holdsAnOwner = [\sprintf('IDENTITY(p.component) IN (SELECT o.id FROM %s o WHERE o.componentGroups IS NOT EMPTY)', AbstractComponent::class)];

        $publishedAliases = [];
        foreach ($this->publishableComponentAssociations($entityManager) as $class => $field) {
            $alias = 'published' . \count($publishedAliases);
            $publishedAliases[] = $alias;
            $ownerQuery->addSelect(\sprintf('(SELECT IDENTITY(o%1$s.%2$s) FROM %3$s o%1$s WHERE o%1$s.id = c.id) AS %1$s', $alias, $field, $class));
            $holdsAnOwner[] = \sprintf('IDENTITY(p.component) IN (SELECT IDENTITY(d%1$s.%2$s) FROM %3$s d%1$s WHERE d%1$s.componentGroups IS NOT EMPTY)', $alias, $field, $class);
        }

        $ownersByGroup = [];
        $publishedByComponent = [];
        foreach ($ownerQuery->getQuery()->getResult() as $row) {
            $owner = $row['owner']->toString();
            $ownersByGroup[$row['grp']->toString()][] = $owner;
            foreach ($publishedAliases as $alias) {
                if (null !== $row[$alias]) {
                    $publishedByComponent[$owner] = $row[$alias];
                }
            }
        }

        $holdersByComponent = [];
        $positionRows = $entityManager->createQueryBuilder()
            ->select('IDENTITY(p.component) AS component', 'IDENTITY(p.componentGroup) AS grp', 'p.pageDataProperty AS pageDataProperty')
            ->from(ComponentPosition::class, 'p')
            ->where(implode(' OR ', $holdsAnOwner))
            ->getQuery()
            ->getResult();
        foreach ($positionRows as $row) {
            $holdersByComponent[$row['component']][] = ['group' => $row['grp'], 'pageDataProperty' => $row['pageDataProperty']];
        }

        return new ComponentUsageGraph($ownersByGroup, $publishedByComponent, $holdersByComponent);
    }

    /**
     * @return array<class-string, string>
     */
    private function publishableComponentAssociations(EntityManagerInterface $entityManager): array
    {
        $associations = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $class = $metadata->getName();
            if ($metadata->isMappedSuperclass || !is_a($class, AbstractComponent::class, true) || !$this->publishableAttributeReader->isConfigured($class)) {
                continue;
            }
            $field = $this->publishableAttributeReader->getConfiguration($class)->associationName;
            if ($metadata->hasAssociation($field) && !$metadata->isInheritedAssociation($field)) {
                $associations[$class] = $field;
            }
        }

        return $associations;
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
            $pagesByGroup[$row['grp']->toString()][] = [
                'id' => $row['id']->toString(),
                'isTemplate' => $row['isTemplate'],
                'renderedOn' => $this->renderedOn(Page::class, $row),
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
            $layoutsByGroup[$row['grp']->toString()][] = [
                'iri' => $this->iri(Layout::class, $row['id']),
                'reference' => $row['reference'],
            ];
        }

        return $layoutsByGroup;
    }

    /**
     * @param list<string> $templates
     *
     * @return array<string, list<array{id: string, class: class-string, renderedOn: RenderedOn}>>
     */
    private function findPageDataByTemplate(EntityManagerInterface $entityManager, array $templates): array
    {
        $byTemplate = [];
        foreach ($this->queryPageData($entityManager, 'IDENTITY(pd.page)', $templates) as $row) {
            $byTemplate[$row['key']][] = ['id' => $row['id'], 'class' => $row['class'], 'renderedOn' => $row['renderedOn']];
        }

        return $byTemplate;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, array{class: class-string, renderedOn: RenderedOn}>
     */
    private function findPageData(EntityManagerInterface $entityManager, array $ids): array
    {
        $byId = [];
        foreach ($this->queryPageData($entityManager, 'pd.id', $ids) as $row) {
            $byId[$row['id']] = ['class' => $row['class'], 'renderedOn' => $row['renderedOn']];
        }

        return $byId;
    }

    /**
     * @param list<string> $values
     *
     * @return list<array{id: string, key: string, class: class-string, renderedOn: RenderedOn}>
     */
    private function queryPageData(EntityManagerInterface $entityManager, string $column, array $values): array
    {
        if ([] === $values) {
            return [];
        }

        $classes = $this->deepestClassesFirst($entityManager->getClassMetadata(AbstractPageData::class));
        $type = 'CASE';
        foreach ($classes as $index => $class) {
            $type .= \sprintf(' WHEN pd INSTANCE OF %s THEN %d', $class, $index);
        }
        $type .= ' ELSE 0 END';

        $rows = $entityManager->createQueryBuilder()
            ->select('pd.id AS id', $type . ' AS type', 'pd.title AS title', 'r.path AS routePath', \sprintf('%s AS keyed', $column))
            ->from(AbstractPageData::class, 'pd')
            ->leftJoin('pd.route', 'r')
            ->where(\sprintf('%s IN (:values)', $column))
            ->setParameter('values', $values, ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();

        return array_map(fn (array $row): array => [
            'id' => $row['id']->toString(),
            'key' => (string) $row['keyed'],
            'class' => $classes[$row['type']],
            'renderedOn' => $this->renderedOn($classes[$row['type']], $row),
        ], $rows);
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
        foreach ($this->pageDataComponentAssociations($entityManager) as $class => $fields) {
            $queryBuilder = $entityManager->createQueryBuilder()
                ->select('pd.id AS id')
                ->from($class, 'pd')
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
                    if (\in_array($component, $components, true)) {
                        $byComponent[$component][] = $row['id']->toString();
                    }
                }
            }
        }

        return $byComponent;
    }

    /**
     * @return array<class-string, non-empty-list<string>>
     */
    private function pageDataComponentAssociations(EntityManagerInterface $entityManager): array
    {
        $associations = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || !is_a($metadata->getName(), AbstractPageData::class, true)) {
                continue;
            }
            foreach ($metadata->getAssociationNames() as $field) {
                if ($metadata->isAssociationWithSingleJoinColumn($field) && is_a($metadata->getAssociationTargetClass($field), AbstractComponent::class, true)) {
                    $associations[$metadata->getName()][] = $field;
                }
            }
        }

        return $associations;
    }

    /**
     * @return list<class-string>
     */
    private function deepestClassesFirst(ClassMetadata $metadata): array
    {
        $classes = $metadata->discriminatorMap;
        usort($classes, static fn (string $a, string $b): int => \count(class_parents($b)) <=> \count(class_parents($a)));

        return $classes;
    }

    /**
     * @param class-string                                                 $class
     * @param array{id: UuidInterface, title: ?string, routePath: ?string} $row
     *
     * @return RenderedOn
     */
    private function renderedOn(string $class, array $row): array
    {
        return [
            'iri' => $this->iri($class, $row['id']),
            'title' => $row['title'],
            'routePath' => $row['routePath'],
        ];
    }

    /**
     * @param class-string $class
     */
    private function iri(string $class, UuidInterface $id): string
    {
        return (string) $this->iriConverter->getIriFromResource($this->getEntityManager($class)->getReference($class, $id));
    }
}
