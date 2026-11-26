<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPageDataVariant;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithVariants;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class PageDataCollectionAssociationTest extends FunctionalTestCase
{
    use ApiRequestTrait;

    use SiteTrait;

    public function test_writing_an_entity_held_by_a_to_many_page_data_association_does_not_throw(): void
    {
        self::bootKernel();
        $page = self::newPage('variants page', true);
        self::persistAndFlush($page);
        $pageData = new PageDataWithVariants();
        $pageData->page = self::getEntityManager()->getReference(Page::class, $page->getId());
        self::persistAndFlush($pageData);
        $pageData = self::findByIri(self::iriOf($pageData));
        self::assertInstanceOf(PageDataWithVariants::class, $pageData);
        $manager = self::getEntityManager();

        $variant = new DummyPageDataVariant();
        $variant->pageData = $pageData;
        $pageData->variants->add($variant);
        $manager->persist($variant);
        $manager->flush();
        $variant->stock = 5;
        $manager->flush();

        $stored = self::findByIri(self::iriOf($variant));
        self::assertInstanceOf(DummyPageDataVariant::class, $stored);
        self::assertSame(5, $stored->stock);
    }

    public function test_a_to_many_association_is_not_a_page_data_property(): void
    {
        self::assertNotContains('variants', self::pageDataPropertyNames());
    }

    public function test_an_association_to_a_class_that_is_not_an_api_resource_is_not_a_page_data_property(): void
    {
        self::assertNotContains('supplier', self::pageDataPropertyNames());
    }

    /**
     * @return list<string>
     */
    private static function pageDataPropertyNames(): array
    {
        $client = static::createClient();

        $response = self::requestJsonLd($client, 'GET', '/_/page_data_metadatas/' . rawurlencode(PageDataWithVariants::class));

        self::assertResponseStatusCodeSame(200);

        return array_column($response->toArray()['properties'], 'property');
    }
}
