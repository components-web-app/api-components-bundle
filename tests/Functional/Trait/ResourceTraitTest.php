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

use PHPUnit\Framework\AssertionFailedError;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class ResourceTraitTest extends FunctionalTestCase
{
    use ResourceTrait;

    public function test_persisted_entities_are_timestamped_and_found_by_their_iri(): void
    {
        $page = new Page();
        $page->reference = 'home';
        $page->isTemplate = false;
        $parameter = (new SiteConfigParameter())->setKey('site_name')->setValue('CWA');

        self::persistAndFlush($page, $parameter);

        $iri = self::iriOf($page);
        self::assertSame('/_/pages/' . $page->getId(), $iri);
        $found = self::findByIri($iri);
        self::assertInstanceOf(Page::class, $found);
        self::assertNotSame($page, $found);
        self::assertSame('home', $found->reference);
        self::assertNotNull($found->getCreatedAt());
        self::assertNotNull($found->getModifiedAt());
        self::assertSame(1, self::countEntities(Page::class));
        self::assertSame(1, self::countEntities(SiteConfigParameter::class));
        self::assertResourceExists($iri);
    }

    public function test_a_resource_that_was_removed_no_longer_exists(): void
    {
        $page = new Page();
        $page->reference = 'removed';
        $page->isTemplate = false;
        self::persistAndFlush($page);
        $iri = self::iriOf($page);

        $manager = self::getEntityManager();
        $manager->remove($manager->find(Page::class, $page->getId()));
        $manager->flush();

        self::assertNull(self::findByIri($iri));
        self::assertResourceNotExists($iri);
        self::assertSame(0, self::countEntities(Page::class));
    }

    public function test_an_iri_on_a_kernel_with_a_route_prefix_carries_the_prefix(): void
    {
        self::bootKernel(['routePrefix' => '/_api']);
        $client = static::createClient();
        $page = new Page();
        $page->reference = 'prefixed';
        $page->isTemplate = false;

        self::persistAndFlush($page);

        $iri = self::iriOf($page);
        self::assertSame('/_api/_/pages/' . $page->getId(), $iri);
        self::assertResourceExists($iri);
        $client->request('GET', '/_api/_/routes', ['headers' => ['accept' => 'application/ld+json']]);
        self::assertResponseStatusCodeSame(200);
    }

    public function test_asserting_a_missing_resource_exists_fails(): void
    {
        self::bootKernel();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('cannot be found');

        self::assertResourceExists('/_/pages/0190c1b6-0000-7000-8000-000000000000');
    }

    public function test_asserting_an_existing_resource_does_not_exist_fails(): void
    {
        $page = new Page();
        $page->reference = 'kept';
        $page->isTemplate = false;
        self::persistAndFlush($page);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('can still be found');

        self::assertResourceNotExists(self::iriOf($page));
    }
}
