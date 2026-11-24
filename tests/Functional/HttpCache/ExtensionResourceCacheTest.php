<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\HttpCache;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyResourceWithFilters;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyVisitorDependentResource;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ExtensionResourceCacheTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use CacheTagsTrait;
    use ResourceTrait;

    public function test_an_anonymous_read_of_a_resource_that_is_not_cwa_content_and_asks_not_to_be_stored_is_private(): void
    {
        $client = static::createClient();
        $resource = new DummyVisitorDependentResource();
        self::persistAndFlush($resource);

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        $cacheControl = (string) $client->getResponse()?->getHeaders(false)['cache-control'][0];
        $directives = array_map('trim', explode(',', $cacheControl));
        self::assertContains('private', $directives, $cacheControl);
        self::assertContains('no-store', $directives, $cacheControl);
        self::assertStringNotContainsString('s-maxage', $cacheControl);
        self::assertNotContains('public', $directives, $cacheControl);
    }

    public function test_a_doctrine_write_to_an_api_resource_that_is_not_cwa_content_purges_its_iri(): void
    {
        static::createClient();
        $resource = new DummyResourceWithFilters();
        $resource->reference = 'before';
        self::persistAndFlush($resource);
        $iri = self::iriOf($resource);

        $collector = self::traceOutsideAnHttpRequest(static function (ContainerInterface $container) use ($resource): void {
            $manager = $container->get('doctrine')->getManager();
            $stored = $manager->find(DummyResourceWithFilters::class, $resource->getId());
            \assert($stored instanceof DummyResourceWithFilters);
            $stored->reference = 'after';
            $manager->flush();
        });

        self::assertCacheTagPurged($iri, $collector);
    }
}
