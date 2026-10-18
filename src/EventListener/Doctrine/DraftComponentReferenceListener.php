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
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;

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
    }

    /**
     * @param array<string, mixed>|null $changeSet
     */
    private function referencePublishedComponents(EntityManagerInterface $entityManager, object $entity, ?array $changeSet): void
    {
        if (!$entity instanceof ComponentPosition && !$entity instanceof AbstractPageData) {
            return;
        }
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

    /**
     * @return list<string>
     */
    private function componentFields(ClassMetadata $metadata): array
    {
        $class = $metadata->getName();
        if (isset($this->componentFields[$class])) {
            return $this->componentFields[$class];
        }
        $fields = [];
        foreach ($metadata->getAssociationNames() as $field) {
            if ($metadata->isAssociationWithSingleJoinColumn($field) && is_a($metadata->getAssociationTargetClass($field), AbstractComponent::class, true)) {
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
