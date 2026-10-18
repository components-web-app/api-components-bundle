<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;

final class DraftComponentReferenceListener
{
    /**
     * @var array<class-string, list<string>>
     */
    private array $componentFields = [];

    public function __construct(private readonly PublishableAttributeReader $publishableAttributeReader)
    {
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->referencePublishedComponents($entityManager, $entity, null);
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->referencePublishedComponents($entityManager, $entity, $unitOfWork->getEntityChangeSet($entity));
        }
        $gainedGroups = [];
        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $published = $this->movePublishedOwnersGroups($entityManager, $collection);
            if (null !== $published) {
                $gainedGroups[spl_object_id($published)] = $published;
            }
        }
        foreach ($gainedGroups as $published) {
            $unitOfWork->computeChangeSet($entityManager->getClassMetadata($published::class), $published);
        }
    }

    /**
     * @param array<string, mixed>|null $changeSet
     */
    private function referencePublishedComponents(EntityManagerInterface $entityManager, object $entity, ?array $changeSet): void
    {
        $metadata = $entityManager->getClassMetadata($entity::class);
        $changed = false;
        foreach ($this->componentFields($metadata) as $field) {
            if (null !== $changeSet && !\array_key_exists($field, $changeSet)) {
                continue;
            }
            $component = $metadata->getFieldValue($entity, $field);
            if (!$component instanceof AbstractComponent) {
                continue;
            }
            $published = $this->publishedComponentOf($entityManager, $component);
            if (null === $published) {
                continue;
            }
            $metadata->setFieldValue($entity, $field, $published);
            $changed = true;
        }
        if ($changed) {
            $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $entity);
        }
    }

    private function movePublishedOwnersGroups(EntityManagerInterface $entityManager, PersistentCollection $collection): ?AbstractComponent
    {
        $draft = $collection->getOwner();
        if (!$draft instanceof AbstractComponent || 'componentGroups' !== $collection->getMapping()->fieldName) {
            return null;
        }
        $published = $this->publishedComponentOf($entityManager, $draft);
        if (null === $published) {
            return null;
        }
        $moved = false;
        foreach ($collection->getInsertDiff() as $group) {
            if (!$group instanceof ComponentGroup || $published->getComponentGroups()->contains($group)) {
                continue;
            }
            $draft->removeComponentGroup($group);
            $published->addComponentGroup($group);
            $this->replaceInverseOwner($group, $draft, $published);
            $moved = true;
        }

        return $moved ? $published : null;
    }

    private function replaceInverseOwner(ComponentGroup $group, AbstractComponent $draft, AbstractComponent $published): void
    {
        $owners = $group->components;
        if ($owners instanceof PersistentCollection && !$owners->isDirty()) {
            return;
        }
        $owners->removeElement($draft);
        if (!$owners->contains($published)) {
            $owners->add($published);
        }
    }

    /**
     * @return list<string>
     */
    private function componentFields(ClassMetadata $metadata): array
    {
        $class = $metadata->getName();
        if (isset($this->componentFields[$class])) {
            return $this->componentFields[$class];
        }
        $ownPublishedAssociation = $this->publishableAttributeReader->isConfigured($class) ? $this->publishableAttributeReader->getConfiguration($class)->associationName : null;
        $fields = [];
        foreach ($metadata->getAssociationNames() as $field) {
            if ($field !== $ownPublishedAssociation && $metadata->isAssociationWithSingleJoinColumn($field) && is_a($metadata->getAssociationTargetClass($field), AbstractComponent::class, true)) {
                $fields[] = $field;
            }
        }

        return $this->componentFields[$class] = $fields;
    }

    private function publishedComponentOf(EntityManagerInterface $entityManager, AbstractComponent $component): ?AbstractComponent
    {
        $metadata = $entityManager->getClassMetadata($component::class);
        $class = $metadata->getName();
        if (!$this->publishableAttributeReader->isConfigured($class)) {
            return null;
        }
        $entityManager->initializeObject($component);
        $published = $metadata->getFieldValue($component, $this->publishableAttributeReader->getConfiguration($class)->associationName);

        return $published instanceof AbstractComponent ? $published : null;
    }
}
