<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;

trait ResourceTrait
{
    protected static function persistAndFlush(object ...$entities): void
    {
        $manager = static::getEntityManager();
        $timestamped = static::getContainer()->get(TimestampedDataPersister::class);
        foreach ($entities as $entity) {
            if (!$manager->contains($entity) && $timestamped->isConfigured($entity)) {
                $timestamped->persistTimestampedFields($entity, true);
            }
        }
        foreach ($entities as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        static::rebootKernel();
    }

    protected static function iriOf(object $resource): string
    {
        return static::getContainer()->get(IriConverterInterface::class)->getIriFromResource($resource);
    }

    protected static function findByIri(string $iri): ?object
    {
        static::getEntityManager()->clear();
        try {
            return static::getContainer()->get(IriConverterInterface::class)->getResourceFromIri($iri);
        } catch (ItemNotFoundException) {
            return null;
        }
    }

    protected static function assertResourceExists(string $iri): void
    {
        self::assertNotNull(static::findByIri($iri), \sprintf('The resource %s cannot be found.', $iri));
    }

    protected static function assertResourceNotExists(string $iri): void
    {
        self::assertNull(static::findByIri($iri), \sprintf('The resource %s can still be found.', $iri));
    }

    /**
     * @param class-string $class
     */
    protected static function countEntities(string $class): int
    {
        $manager = static::getEntityManager();
        $manager->clear();

        return $manager->getRepository($class)->count([]);
    }
}
