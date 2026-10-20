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

use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\QueryCountingMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableWithValidation;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheControlTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class ManifestPublishableTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheControlTrait;
    use JsonTrait;
    use PublishableTrait;
    use SiteTrait;

    public function test_an_anonymous_manifest_of_a_page_of_publishable_components_lists_the_published_components(): void
    {
        $client = static::createClient();
        $site = self::createPublishablePage('/news', 4, 2);

        $response = self::requestJsonLd($client, 'GET', '/_/resource_manifest//news');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::expectedTree($site, $site['published']), $response->toArray()['resource_iris']);
    }

    public function test_an_admin_manifest_of_a_page_of_publishable_components_lists_each_draft_in_place_of_its_published_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPublishablePage('/news', 4, 2);
        $shown = $site['published'];
        foreach ($site['drafts'] as $index => $draft) {
            $shown[$index] = $draft;
        }

        $response = self::requestJsonLd($client, 'GET', '/_/resource_manifest//news');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::expectedTree($site, $shown), $response->toArray()['resource_iris']);
    }

    public function test_an_anonymous_manifest_with_a_scheduled_component_expires_when_it_is_published(): void
    {
        $client = static::createClient();
        $site = self::createPublishablePage('/news', 2, 0);
        $scheduled = static::newPublishable(new \DateTimeImmutable('+60 seconds'));
        $page = static::getEntityManager()->find(Page::class, $site['page']->getId());
        $position = static::newComponentPosition($page->getComponentGroups()->first(), $scheduled, 2);
        static::persistAndFlush($scheduled, $position);
        static::rebootKernel();

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//news');

        self::assertResponseStatusCodeSame(200);
        $expiresIn = strtotime((string) self::getClient()->getResponse()->headers->get('expires')) - time();
        self::assertGreaterThanOrEqual(50, $expiresIn);
        self::assertLessThanOrEqual(60, $expiresIn);
        self::assertSharedMaxAgeAtMost(60);
        self::assertSharedMaxAgeAtLeast(50);
    }

    public function test_an_anonymous_manifest_of_a_page_of_publishable_components_counts_no_locations(): void
    {
        $client = static::createClient();
        self::createPublishablePage('/news', 10, 3);
        QueryCountingMiddleware::reset();

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//news');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], self::countQueries());
    }

    public function test_an_admin_manifest_of_a_page_of_publishable_components_neither_counts_locations_nor_validates_for_publishing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createPublishablePage('/news', 10, 3);
        QueryCountingMiddleware::reset();

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//news');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], self::countQueries());
        self::assertSame([], array_values(array_filter(QueryCountingMiddleware::queries(), static fn (string $sql): bool => 1 === preg_match('/FROM _acb_component_position t0 WHERE t0\.component_id = \?/', $sql))));
    }

    public function test_a_publishable_component_fetched_by_an_admin_after_a_manifest_still_has_its_location_count(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPublishablePage('/news', 1, 0);

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//news');
        self::requestJsonLd($client, 'GET', self::iriOf($site['published'][0]));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertJsonNodeSame('_metadata.publishable.locationCount', 1);
    }

    public function test_a_draft_fetched_by_an_admin_still_has_the_violations_that_would_stop_it_being_published(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(null, new DummyPublishableWithValidation());

        self::requestJsonLd($client, 'GET', self::iriOf($draft));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.publishable.published', false);
        self::assertJsonNodeSame('_metadata.violations.violations[0].propertyPath', 'name');
    }

    /**
     * @return list<string>
     */
    private static function countQueries(): array
    {
        return array_values(array_filter(QueryCountingMiddleware::queries(), static fn (string $sql): bool => 1 === preg_match('/\bCOUNT\s*\(/i', $sql)));
    }

    /**
     * @param array{route: Route, layout: Layout, page: Page, group: ComponentGroup, positions: list<ComponentPosition>} $site
     * @param array<int, DummyPublishableComponent>                                                                      $components
     */
    private static function expectedTree(array $site, array $components): array
    {
        $node = static fn (object $resource, array $children = []): array => ['iri' => self::iriOf($resource), 'children' => $children];
        $positions = [];
        foreach ($site['positions'] as $index => $position) {
            $positions[] = $node($position, [$node($components[$index])]);
        }

        return [
            $node($site['route'], [
                $node($site['page'], [
                    $node($site['layout']),
                    $node($site['group'], $positions),
                ]),
            ]),
        ];
    }

    /**
     * @return array{route: Route, layout: Layout, page: Page, group: ComponentGroup, positions: list<ComponentPosition>, published: list<DummyPublishableComponent>, drafts: array<int, DummyPublishableComponent>}
     */
    private static function createPublishablePage(string $path, int $components, int $drafts): array
    {
        $layout = static::newLayout('publishable-layout');
        $page = static::newPage('publishable-page');
        $page->uiComponent = 'TestComponent';
        $page->layout = $layout;
        $route = static::newRoute($path)->setPage($page);
        $group = static::newComponentGroup('publishable-page-group');
        $page->addComponentGroup($group);
        $published = [];
        $draftResources = [];
        $positions = [];
        for ($i = 0; $i < $components; ++$i) {
            $published[] = $component = static::newPublishable(new \DateTimeImmutable('-10 seconds'));
            $positions[] = static::newComponentPosition($group, $component, $i);
            if ($i < $drafts) {
                $draftResources[$i] = $draft = static::newPublishable(null, null, true);
                $draft->setPublishedResource($component);
            }
        }

        static::persistAndFlush($layout, $page, $route, $group, ...$published, ...array_values($draftResources), ...$positions);

        return ['route' => $route, 'layout' => $layout, 'page' => $page, 'group' => $group, 'positions' => $positions, 'published' => $published, 'drafts' => $draftResources];
    }
}
