<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Concurrency;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Utility\VersionedInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\BeforeTransactionCallback;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait ConcurrencyTrait
{
    use ResourceTrait;

    protected static function createComponent(string $reference = 'original', int $version = 1): DummyPublishableComponent
    {
        $component = new DummyPublishableComponent();
        $component->reference = $reference;
        static::persistAndFlush($component);
        if (1 !== $version) {
            static::setVersionInDatabase($component, $version);
        }

        return $component;
    }

    protected static function setVersionInDatabase(AbstractComponent|AbstractPageData $resource, int $version): void
    {
        [$table, $column, $id] = static::versionColumnOf($resource);
        static::getEntityManager()->getConnection()->update($table, [$column => $version], ['id' => $id]);
    }

    protected static function setVersionBeforeTheNextWrite(AbstractComponent|AbstractPageData $resource, int $version): void
    {
        [$table, $column, $id] = static::versionColumnOf($resource);
        BeforeTransactionCallback::once(static function () use ($table, $column, $id, $version): void {
            static::getEntityManager()->getConnection()->update($table, [$column => $version], ['id' => $id]);
        });
    }

    protected static function insertDraftBeforeTheNextWrite(DummyPublishableComponent $published, string $reference, int $version): void
    {
        $manager = static::getEntityManager();
        $metadata = $manager->getClassMetadata(DummyPublishableComponent::class);
        $root = $manager->getClassMetadata($metadata->rootEntityName);
        $rootTable = $root->getTableName();
        $table = $metadata->getTableName();
        $discriminator = $metadata->discriminatorValue;
        $versionColumn = $root->getColumnName(VersionedInterface::VERSION_FIELD);
        $referenceColumn = $metadata->getColumnName('reference');
        $publishedColumn = $metadata->getSingleAssociationJoinColumnName('publishedResource');
        $publishedId = (string) $published->getId();
        BeforeTransactionCallback::once(static function () use ($rootTable, $table, $discriminator, $versionColumn, $referenceColumn, $publishedColumn, $publishedId, $reference, $version): void {
            $connection = static::getEntityManager()->getConnection();
            $id = '7d1c2b6e-5f4a-4c3b-9a8d-1e2f3a4b5c6d';
            $connection->insert($rootTable, ['id' => $id, 'dtype' => $discriminator, $versionColumn => $version]);
            $connection->insert($table, ['id' => $id, $referenceColumn => $reference, $publishedColumn => $publishedId]);
        });
    }

    protected static function versionInDatabase(AbstractComponent|AbstractPageData $resource): int
    {
        [$table, $column, $id] = static::versionColumnOf($resource);

        return (int) static::getEntityManager()->getConnection()->fetchOne(\sprintf('SELECT %s FROM %s WHERE id = ?', $column, $table), [$id]);
    }

    /**
     * @return array<mixed>
     */
    protected static function bodyOf(ResponseInterface $response): array
    {
        return json_decode($response->getContent(false), true, 512, \JSON_THROW_ON_ERROR);
    }

    protected static function versionOf(ResponseInterface $response): ?int
    {
        return static::bodyOf($response)['_metadata']['version'] ?? null;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function versionColumnOf(AbstractComponent|AbstractPageData $resource): array
    {
        $manager = static::getEntityManager();
        $root = $manager->getClassMetadata($manager->getClassMetadata($resource::class)->rootEntityName);

        return [$root->getTableName(), $root->getColumnName(VersionedInterface::VERSION_FIELD), (string) $resource->getId()];
    }
}
