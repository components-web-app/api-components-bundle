<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Route;

use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class RoutePurgeTagsTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use SiteTrait;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel(['routePrefix' => '/_api']);
    }

    public function test_creating_a_route_purges_the_prefixed_route_collection_and_the_routes_own_iri(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $page = self::createPage();

        self::requestJsonLd($client, 'POST', '/_api/_/routes', ['path' => '/my-route', 'name' => 'my-route', 'page' => self::iriOf($page)]);

        self::assertResponseStatusCodeSame(201);
        self::assertCacheTagPurged('/_api/_/routes');
        self::assertCacheTagPurged('/_api/_/routes//my-route');
        self::assertCacheTagNotPurged('/_/routes');
    }

    public function test_changing_a_routes_path_purges_the_prefixed_route_collection_and_the_iris_of_both_paths(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createRouteWithPage('/my-route');

        self::requestJsonLd($client, 'PATCH', '/_api/_/routes//my-route', ['path' => '/renamed-route']);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged('/_api/_/routes');
        self::assertCacheTagPurged('/_api/_/routes//renamed-route');
        self::assertCacheTagPurged('/_api/_/routes//my-route');
    }

    public function test_deleting_a_route_purges_the_prefixed_route_collection_and_the_routes_own_iri(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createRouteWithPage('/my-route');

        self::requestJsonLd($client, 'DELETE', '/_api/_/routes//my-route');

        self::assertResponseStatusCodeSame(204);
        self::assertCacheTagPurged('/_api/_/routes');
        self::assertCacheTagPurged('/_api/_/routes//my-route');
    }

    public function test_a_route_written_outside_an_http_request_purges_the_same_prefixed_tags(): void
    {
        $collector = self::traceOutsideAnHttpRequest(static function (ContainerInterface $container): void {
            $route = (new Route())->setPath('/cli-route')->setName('/cli-route');
            $container->get(TimestampedDataPersister::class)->persistTimestampedFields($route, true);
            $manager = $container->get('doctrine')->getManager();
            $manager->persist($route);
            $manager->flush();
            $manager->clear();
        });

        self::assertCacheTagPurged('/_api/_/routes', $collector);
        self::assertCacheTagPurged('/_api/_/routes//cli-route', $collector);
        self::assertCacheTagNotPurged('/_/routes', $collector);
    }
}
