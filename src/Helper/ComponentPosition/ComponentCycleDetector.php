<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentPosition;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ComponentCycleDetector
{
    use ClassMetadataTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly PublishableAttributeReader $publishableAttributeReader,
    ) {
        $this->initRegistry($registry);
    }

    public function isPlacementCyclic(AbstractComponent $component, ComponentGroup $group): bool
    {
        $family = $this->family($component);
        foreach ($this->groupsUpwardFrom([$group]) as $ancestorGroup) {
            foreach ($ancestorGroup->components as $owner) {
                if (\in_array($owner, $family, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isOwnershipCyclic(AbstractComponent $owner, ComponentGroup $group): bool
    {
        $family = $this->family($owner);
        foreach ($group->componentPositions as $position) {
            if (\in_array($position->component, $family, true)) {
                return true;
            }
        }

        foreach ($this->groupsUpwardFrom($this->groupsHolding($family)) as $ancestorGroup) {
            if ($ancestorGroup === $group) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<ComponentGroup> $queue
     *
     * @return \Generator<ComponentGroup>
     */
    private function groupsUpwardFrom(array $queue): \Generator
    {
        $visitedGroups = new \SplObjectStorage();

        while ($queue) {
            $group = array_shift($queue);
            if ($visitedGroups->offsetExists($group)) {
                continue;
            }
            $visitedGroups->offsetSet($group);

            yield $group;

            foreach ($group->components as $owner) {
                array_push($queue, ...$this->groupsHolding($this->family($owner)));
            }
        }
    }

    /**
     * @param list<AbstractComponent> $components
     *
     * @return list<ComponentGroup>
     */
    private function groupsHolding(array $components): array
    {
        $groups = [];
        foreach ($components as $component) {
            foreach ($component->getComponentPositions() as $position) {
                if ($position->componentGroup) {
                    $groups[] = $position->componentGroup;
                }
            }
        }

        return $groups;
    }

    /**
     * @return list<AbstractComponent>
     */
    private function family(AbstractComponent $component): array
    {
        if (!$this->publishableAttributeReader->isConfigured($component)) {
            return [$component];
        }

        $configuration = $this->publishableAttributeReader->getConfiguration($component);
        $published = $this->associated($component, $configuration->associationName) ?? $component;
        $family = [$published];
        foreach ([$component, $this->associated($published, $configuration->reverseAssociationName)] as $member) {
            if ($member instanceof AbstractComponent && !\in_array($member, $family, true)) {
                $family[] = $member;
            }
        }

        return $family;
    }

    private function associated(AbstractComponent $component, string $association): ?AbstractComponent
    {
        $entityManager = $this->getEntityManager($component);
        if ($entityManager->isUninitializedObject($component)) {
            $entityManager->initializeObject($component);
        }
        $associated = $this->getClassMetadata($component)->getFieldValue($component, $association);

        return $associated instanceof AbstractComponent ? $associated : null;
    }
}
