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

use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyResourceWithFilters;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyResourceWithPagination;

trait CollectionTrait
{
    use ResourceTrait;

    /**
     * @return list<DummyComponent>
     */
    protected static function createDummyComponents(int $count): array
    {
        $components = array_map(static fn (): DummyComponent => new DummyComponent(), array_fill(0, $count, null));
        static::persistAndFlush(...$components);

        return $components;
    }

    protected static function createDummyResourcesWithPagination(int $count): void
    {
        static::persistAndFlush(...array_map(static fn (): DummyResourceWithPagination => new DummyResourceWithPagination(), array_fill(0, $count, null)));
    }

    protected static function createDummyResourcesWithFilters(int $count): void
    {
        static::persistAndFlush(...array_map(static function (int $i): DummyResourceWithFilters {
            $resource = new DummyResourceWithFilters();
            $resource->reference = 'reference ' . $i;

            return $resource;
        }, array_keys(array_fill(0, $count, null))));
    }

    /**
     * @param array<string, mixed>|null $defaultQueryParameters
     */
    protected static function createCollection(string $resourceIri = '/component/dummy_components', ?array $defaultQueryParameters = null): Collection
    {
        $collection = new Collection();
        $collection->setResourceIri($resourceIri);
        $collection->setDefaultQueryParameters($defaultQueryParameters);
        static::persistAndFlush($collection);

        return $collection;
    }
}
