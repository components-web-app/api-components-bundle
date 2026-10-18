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
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CacheTagsTraitTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use ManifestTrait;
    use SiteTrait;

    public function test_a_write_purges_the_tags_of_what_it_wrote(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $response = self::requestJsonLd($client, 'POST', '/_/site_config_parameters', ['key' => 'site_name', 'value' => 'CWA']);

        self::assertResponseStatusCodeSame(201);
        self::assertCacheTagPurged('cwa-html');
        self::assertCacheTagNotPurged('/_/layouts');
        self::assertResponseCarriesNoCacheTags();
        self::assertSame('/_/site_config_parameters/site_name', $response->toArray()['@id']);
    }

    public function test_asserting_a_tag_was_purged_fails_when_it_was_not(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_/health');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The cache tag cwa-html was not purged.');

        self::assertCacheTagPurged('cwa-html');
    }

    public function test_a_manifest_response_carries_the_manifest_tag_of_its_page(): void
    {
        $client = static::createClient();
        $created = self::createRoutedPageWithComponent('/tagged');

        $client->request('GET', self::manifestUrlOf($created['page']), ['headers' => ['accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('manifest:' . self::iriOf($created['page']), self::manifestCacheTag(self::iriOf($created['page'])));
        self::assertResponseHasCacheTag(self::manifestCacheTag(self::iriOf($created['page'])));
        self::assertResponseLacksCacheTag(self::iriOf($created['component']));
        self::assertContains(self::manifestCacheTag(self::iriOf($created['page'])), self::responseCacheTags());
    }

    public function test_a_route_written_outside_an_http_request_purges_its_route_tags(): void
    {
        $collector = self::traceOutsideAnHttpRequest(static function (ContainerInterface $container): void {
            $route = (new Route())->setPath('/outside')->setName('/outside');
            $container->get(TimestampedDataPersister::class)->persistTimestampedFields($route, true);
            $manager = $container->get('doctrine')->getManager();
            $manager->persist($route);
            $manager->flush();
        });

        self::assertCacheTagPurged('/_/routes//outside', $collector);
        self::assertCacheTagPurgedTimes('/_/routes//outside', 1, $collector);
        self::assertContains('/_/routes', self::purgedCacheTags($collector));
        self::assertCacheTagNotPurged('/_/routes//elsewhere', $collector);
    }

    public function test_nothing_outside_an_http_request_sends_no_purge(): void
    {
        $collector = self::traceOutsideAnHttpRequest(static function (): void {
        });

        self::assertNoCachePurgeRequestSent($collector);
        self::assertSame([], self::purgedCacheTags($collector));
        self::assertOnlyCacheTagsPurged([], $collector);
    }
}
