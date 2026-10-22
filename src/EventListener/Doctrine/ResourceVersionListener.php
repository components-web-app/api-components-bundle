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

use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\OptimisticLockException;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Utility\VersionedInterface;

final class ResourceVersionListener
{
    /** @var \WeakMap<VersionedInterface, int> */
    private \WeakMap $expectedVersions;

    public function __construct(private readonly PublishableAttributeReader $publishableAttributeReader)
    {
        $this->expectedVersions = new \WeakMap();
    }

    public function expectVersion(VersionedInterface $resource, int $version): void
    {
        $this->expectedVersions[$resource] = $version;
    }

    public function reset(): void
    {
        $this->expectedVersions = new \WeakMap();
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof VersionedInterface) {
                $metadata = $entityManager->getClassMetadata($entity::class);
                $metadata->setFieldValue($entity, VersionedInterface::VERSION_FIELD, $this->initialVersion($entityManager, $entity));
                $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
            }
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof VersionedInterface) {
                $version = $this->claim($entityManager, $entity) ?? $this->increment($entityManager, $entity);
                $entityManager->getClassMetadata($entity::class)->setFieldValue($entity, VersionedInterface::VERSION_FIELD, $version);
                $unitOfWork->setOriginalEntityProperty(spl_object_id($entity), VersionedInterface::VERSION_FIELD, $version);
            }
        }
        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof VersionedInterface) {
                $this->claim($entityManager, $entity);
            }
        }
    }

    private function initialVersion(EntityManagerInterface $entityManager, VersionedInterface $entity): int
    {
        if (!$this->publishableAttributeReader->isConfigured($entity)) {
            return 1;
        }
        $associationName = $this->publishableAttributeReader->getConfiguration($entity)->associationName;
        $published = $entityManager->getClassMetadata($entity::class)->getFieldValue($entity, $associationName);
        if (!$published instanceof VersionedInterface) {
            return 1;
        }
        if ($entityManager->getUnitOfWork()->isScheduledForInsert($published)) {
            return $published->getResourceVersion() + 1;
        }
        [$table, $column, $idColumn, $id, $idType] = $this->rowOf($entityManager, $published);
        $idField = $entityManager->getClassMetadata($published::class)->getSingleIdentifierFieldName();
        $entityManager->getUnitOfWork()->getEntityPersister($published::class)->lock([$idField => $id], LockMode::PESSIMISTIC_WRITE);
        $stored = (int) $entityManager->getConnection()->fetchOne(\sprintf('SELECT %s FROM %s WHERE %s = ?', $column, $table, $idColumn), [$id], [$idType]);
        if (isset($this->expectedVersions[$published]) && $stored !== $this->expectedVersions[$published]) {
            throw OptimisticLockException::lockFailed($published);
        }

        return $stored + 1;
    }

    private function claim(EntityManagerInterface $entityManager, VersionedInterface $entity): ?int
    {
        if (!isset($this->expectedVersions[$entity])) {
            return null;
        }
        $expected = $this->expectedVersions[$entity];
        $version = max($expected, $entity->getResourceVersion()) + 1;
        [$table, $column, $idColumn, $id, $idType] = $this->rowOf($entityManager, $entity);
        $updated = $entityManager->getConnection()->executeStatement(
            \sprintf('UPDATE %s SET %s = ? WHERE %s = ? AND %s = ?', $table, $column, $idColumn, $column),
            [$version, $id, $expected],
            [ParameterType::INTEGER, $idType, ParameterType::INTEGER],
        );
        if (1 !== (int) $updated) {
            throw OptimisticLockException::lockFailed($entity);
        }
        $this->expectedVersions[$entity] = $version;

        return $version;
    }

    private function increment(EntityManagerInterface $entityManager, VersionedInterface $entity): int
    {
        [$table, $column, $idColumn, $id, $idType] = $this->rowOf($entityManager, $entity);
        $connection = $entityManager->getConnection();
        $current = $entity->getResourceVersion();
        $connection->executeStatement(
            \sprintf('UPDATE %1$s SET %2$s = CASE WHEN %2$s > ? THEN %2$s ELSE ? END + 1 WHERE %3$s = ?', $table, $column, $idColumn),
            [$current, $current, $id],
            [ParameterType::INTEGER, ParameterType::INTEGER, $idType],
        );

        return (int) $connection->fetchOne(\sprintf('SELECT %s FROM %s WHERE %s = ?', $column, $table, $idColumn), [$id], [$idType]);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: mixed, 4: string}
     */
    private function rowOf(EntityManagerInterface $entityManager, VersionedInterface $entity): array
    {
        $root = $entityManager->getClassMetadata($entityManager->getClassMetadata($entity::class)->rootEntityName);
        $idField = $root->getSingleIdentifierFieldName();

        return [
            $root->getTableName(),
            $root->getColumnName(VersionedInterface::VERSION_FIELD),
            $root->getColumnName($idField),
            $root->getFieldValue($entity, $idField),
            (string) $root->getTypeOfField($idField),
        ];
    }
}
