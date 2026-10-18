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

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheControlTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class CacheHeadersTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheControlTrait;
    use PublishableTrait;
    use SiteTrait;

    #[DataProvider('authenticatedAffectedResources')]
    public function test_authenticated_gets_of_affected_resource_types_are_marked_non_cacheable(string $resource): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $url = self::createAffectedResource($resource);

        self::requestJsonLd($client, 'GET', $url);

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('private', self::cacheControlHeader());
        self::assertStringContainsString('no-store', self::cacheControlHeader());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function authenticatedAffectedResources(): iterable
    {
        yield 'a route' => ['route'];
        yield 'a resource manifest' => ['manifest'];
        yield 'a component position' => ['position'];
        yield 'a publishable resource with a scheduled draft' => ['publishable'];
    }

    #[DataProvider('anonymousAffectedResources')]
    public function test_anonymous_gets_of_affected_resource_types_stay_publicly_cacheable(string $resource): void
    {
        $client = static::createClient();
        $url = self::createAffectedResource($resource);

        self::requestJsonLd($client, 'GET', $url);

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('public', self::cacheControlHeader());
        self::assertStringNotContainsString('no-store', self::cacheControlHeader());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function anonymousAffectedResources(): iterable
    {
        yield 'a route' => ['route'];
        yield 'a resource manifest' => ['manifest'];
        yield 'a component position' => ['position'];
    }

    public function test_an_authenticated_get_of_an_unaffected_resource_type_stays_publicly_cacheable(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layout = self::createLayout();

        self::requestJsonLd($client, 'GET', self::iriOf($layout));

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('public', self::cacheControlHeader());
        self::assertStringNotContainsString('no-store', self::cacheControlHeader());
    }

    public function test_an_anonymous_denial_for_a_route_which_is_not_yet_live_is_never_stored_by_a_shared_cache(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable('2999-01-01T00:00:00+00:00'));

        self::requestJsonLd($client, 'GET', '/_/routes//launch');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('no-store', self::cacheControlHeader());
    }

    public function test_the_anonymous_route_collection_cannot_be_cached_beyond_the_next_go_live_moment(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable('+60 seconds'));

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertSharedMaxAgeAtMost(60);
    }

    public function test_a_scheduled_go_live_caps_a_manifest_which_has_no_relationship_to_the_scheduled_route(): void
    {
        $client = static::createClient();
        self::createPageDataWithRoute('/my-route');
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable('+60 seconds'));

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertSharedMaxAgeAtMost(60);
    }

    public function test_a_pending_publication_caps_the_shared_cache_lifetime_not_just_the_expires_header(): void
    {
        $client = static::createClient();
        [$published] = self::createPublishedWithDraft(new \DateTimeImmutable('+60 seconds'));

        self::requestJsonLd($client, 'GET', self::iriOf($published));

        self::assertResponseStatusCodeSame(200);
        self::assertMatchesRegularExpression('/GMT$/', (string) self::getClient()->getResponse()->headers->get('expires'));
        self::assertSharedMaxAgeAtMost(60);
    }

    public function test_a_response_with_no_pending_transition_keeps_its_configured_shared_cache_lifetime(): void
    {
        $client = static::createClient();
        self::createPageDataWithRoute('/my-route');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertSharedMaxAgeAtLeast(600);
    }

    private static function createAffectedResource(string $resource): string
    {
        if ('route' === $resource) {
            self::createRouteWithPage('/contact');

            return '/_/routes//contact';
        }
        if ('manifest' === $resource) {
            self::createPageDataWithRoute('/my-route');

            return '/_/resource_manifest//my-route';
        }
        if ('position' === $resource) {
            return self::iriOf(self::createComponentGroupWithComponents(1)['positions'][0]);
        }

        return self::iriOf(self::createPublishedWithDraft(new \DateTimeImmutable('2999-12-31T23:59:59+00:00'))[0]);
    }

    private static function cacheControlHeader(): string
    {
        return (string) self::getClient()->getResponse()->headers->get('cache-control');
    }
}
