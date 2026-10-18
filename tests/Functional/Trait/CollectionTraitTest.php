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
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyResourceWithFilters;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyResourceWithPagination;

final class CollectionTraitTest extends FunctionalTestCase
{
    use CollectionTrait;

    public function test_resources_are_created_in_number(): void
    {
        static::createClient();

        self::assertCount(3, self::createDummyComponents(3));
        self::createDummyResourcesWithPagination(2);
        self::createDummyResourcesWithFilters(2);
        self::createDummyComponents(0);

        self::assertSame(3, self::countEntities(DummyComponent::class));
        self::assertSame(2, self::countEntities(DummyResourceWithPagination::class));
        self::assertSame(['reference 0', 'reference 1'], array_map(static fn (DummyResourceWithFilters $resource): string => $resource->reference, self::getEntityManager()->getRepository(DummyResourceWithFilters::class)->findBy([], ['reference' => 'ASC'])));
    }

    public function test_a_collection_component_lists_its_resources(): void
    {
        $client = static::createClient();
        self::createDummyComponents(2);
        $collection = self::createCollection(defaultQueryParameters: ['perPage' => 40]);

        $response = $client->request('GET', self::iriOf($collection), ['headers' => ['accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('/component/dummy_components', $response->toArray()['resourceIri']);
        $stored = self::findByIri(self::iriOf($collection));
        self::assertInstanceOf(Collection::class, $stored);
        self::assertSame(['perPage' => 40], $stored->getDefaultQueryParameters());
    }
}
