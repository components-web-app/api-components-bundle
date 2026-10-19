<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\Normalizer;

use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteLiveResolver;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\RouteNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RouteNormalizerTest extends TestCase
{
    private const string FUTURE = '2999-01-01T00:00:00+00:00';

    private ?Page $seenPage = null;

    private ?PageData $seenPageData = null;

    public function test_a_redirecting_route_carries_its_final_path(): void
    {
        $route = $this->route('/old')->setRedirect($this->route('/new'));

        self::assertSame('/new', $this->normalize($route)['redirectPath']);
    }

    public function test_a_redirecting_route_in_a_manifest_carries_no_redirect_path(): void
    {
        $route = $this->route('/old')->setRedirect($this->route('/new'));

        self::assertArrayNotHasKey('redirectPath', $this->normalize($route, context: [CwaTagCollector::MANIFEST_CONTEXT_KEY => true]));
    }

    public function test_a_route_which_does_not_redirect_carries_no_redirect_path(): void
    {
        self::assertArrayNotHasKey('redirectPath', $this->normalize($this->route('/page')));
    }

    public function test_a_route_which_does_not_redirect_is_normalized_with_its_own_page(): void
    {
        $page = new Page();
        $route = $this->route('/page')->setPage($page);

        $this->normalize($route);

        self::assertSame($page, $this->seenPage);
        self::assertNull($this->seenPageData);
    }

    public function test_a_route_redirecting_to_a_live_target_is_normalized_with_the_targets_page_and_page_data_for_an_anonymous_caller(): void
    {
        $page = new Page();
        $pageData = new PageData();
        $target = $this->route('/new')->setPage($page)->setPageData($pageData);
        $route = $this->route('/old')->setRedirect($target);

        $this->normalize($route);

        self::assertSame($page, $this->seenPage);
        self::assertSame($pageData, $this->seenPageData);
    }

    public function test_the_target_page_and_page_data_are_taken_off_the_redirecting_route_after_normalization(): void
    {
        $target = $this->route('/new')->setPage(new Page())->setPageData(new PageData());
        $route = $this->route('/old')->setRedirect($target);

        $this->normalize($route);

        self::assertNull($route->getPage());
        self::assertNull($route->getPageData());
    }

    public function test_a_route_redirecting_to_a_gated_target_is_normalized_without_the_targets_page_and_page_data_for_an_anonymous_caller(): void
    {
        $target = $this->route('/new', new \DateTimeImmutable(self::FUTURE))->setPage(new Page())->setPageData(new PageData());
        $route = $this->route('/old')->setRedirect($target);

        $this->normalize($route);

        self::assertNull($this->seenPage);
        self::assertNull($this->seenPageData);
    }

    public function test_a_route_redirecting_to_a_gated_target_is_normalized_with_the_targets_page_and_page_data_for_an_admin(): void
    {
        $page = new Page();
        $pageData = new PageData();
        $target = $this->route('/new', new \DateTimeImmutable(self::FUTURE))->setPage($page)->setPageData($pageData);
        $route = $this->route('/old')->setRedirect($target);

        $this->normalize($route, admin: true);

        self::assertSame($page, $this->seenPage);
        self::assertSame($pageData, $this->seenPageData);
        self::assertNull($route->getPage());
        self::assertNull($route->getPageData());
    }

    public function test_a_redirecting_route_keeps_its_own_page_and_page_data_over_the_targets(): void
    {
        $ownPage = new Page();
        $ownPageData = new PageData();
        $target = $this->route('/new')->setPage(new Page())->setPageData(new PageData());
        $route = $this->route('/old')->setPage($ownPage)->setPageData($ownPageData)->setRedirect($target);

        $this->normalize($route);

        self::assertSame($ownPage, $this->seenPage);
        self::assertSame($ownPageData, $this->seenPageData);
        self::assertSame($ownPage, $route->getPage());
        self::assertSame($ownPageData, $route->getPageData());
    }

    public function test_an_admin_is_given_the_effective_go_live_date(): void
    {
        $metadataProvider = new ResourceMetadataProvider();
        $route = $this->route('/page', new \DateTimeImmutable(self::FUTURE));

        $this->normalize($route, admin: true, metadataProvider: $metadataProvider);

        self::assertSame(self::FUTURE, $metadataProvider->findResourceMetadata($route)->getEffectiveLiveAt());
    }

    public function test_an_anonymous_caller_is_not_given_the_effective_go_live_date(): void
    {
        $metadataProvider = new ResourceMetadataProvider();
        $route = $this->route('/page', new \DateTimeImmutable(self::FUTURE));

        $this->normalize($route, metadataProvider: $metadataProvider);

        self::assertFalse($metadataProvider->resourceMetadataExists($route));
    }

    public function test_a_circular_redirect_names_the_routes_in_the_order_they_redirect(): void
    {
        $first = $this->route('/first');
        $second = $this->route('/second');
        $first->setRedirect($second);
        $second->setRedirect($first);

        $this->expectException(CircularReferenceException::class);
        $this->expectExceptionMessage(\sprintf('The redirect routes result in a circular reference: %s -> %s', $first->getId(), $second->getId()));

        $this->normalize($first);
    }

    public function test_gated_routes_are_pruned_from_the_redirect_tree_at_any_depth_for_an_anonymous_caller(): void
    {
        $target = $this->route('/target');
        $this->route('/middle', new \DateTimeImmutable(self::FUTURE))->setRedirect($target);
        $live = $this->route('/live')->setRedirect($target);
        $this->route('/under-live', new \DateTimeImmutable(self::FUTURE))->setRedirect($live);

        $normalized = $this->normalize($target, normalized: [
            'path' => '/target',
            'redirectedFrom' => [
                '/_/routes/an-iri',
                ['path' => '/middle', 'redirectedFrom' => []],
                ['path' => '/live', 'redirectedFrom' => [['path' => '/under-live']]],
            ],
        ]);

        self::assertSame([
            'path' => '/target',
            'redirectedFrom' => [
                '/_/routes/an-iri',
                ['path' => '/live', 'redirectedFrom' => []],
            ],
        ], $normalized);
    }

    public function test_an_unsaved_gated_route_is_pruned_from_the_redirect_tree_for_an_anonymous_caller(): void
    {
        $target = $this->route('/target');
        (new Route())->setPath('/unsaved')->setName('/unsaved')->setLiveAt(null)->setRedirect($target);

        self::assertSame(['redirectedFrom' => []], $this->normalize($target, normalized: ['redirectedFrom' => [['path' => '/unsaved']]]));
    }

    public function test_an_admin_is_given_no_effective_go_live_date_for_a_draft_route(): void
    {
        $metadataProvider = new ResourceMetadataProvider();
        $route = $this->route('/page', null);

        $this->normalize($route, admin: true, metadataProvider: $metadataProvider);

        self::assertNull($metadataProvider->findResourceMetadata($route)->getEffectiveLiveAt());
    }

    public function test_gated_routes_are_kept_in_the_redirect_tree_for_an_admin(): void
    {
        $target = $this->route('/target');
        $this->route('/middle', new \DateTimeImmutable(self::FUTURE))->setRedirect($target);
        $tree = ['path' => '/target', 'redirectedFrom' => [['path' => '/middle']]];

        self::assertSame($tree, $this->normalize($target, admin: true, normalized: $tree));
    }

    public function test_a_normalized_route_without_a_redirect_tree_is_returned_as_it_is(): void
    {
        $target = $this->route('/target');
        $this->route('/middle', new \DateTimeImmutable(self::FUTURE))->setRedirect($target);

        self::assertSame(['path' => '/target'], $this->normalize($target, normalized: ['path' => '/target']));
    }

    public function test_a_route_is_supported_once_per_context(): void
    {
        $normalizer = new RouteNormalizer(new RouteLiveResolver(), $this->createStub(ResourceAccessCheckerInterface::class), "is_granted('ROLE_ADMIN')");

        self::assertTrue($normalizer->supportsNormalization(new Route()));
        self::assertFalse($normalizer->supportsNormalization(new \stdClass()));
        self::assertFalse($normalizer->supportsNormalization(new Route(), null, ['ROUTE_NORMALIZER_ALREADY_CALLED' => true]));
        self::assertSame([Route::class => false], $normalizer->getSupportedTypes(null));
    }

    private function normalize(Route $route, bool $admin = false, array $context = [], ?ResourceMetadataProvider $metadataProvider = null, array $normalized = ['@id' => '/_/routes/x']): array
    {
        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturn($admin);
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(function (Route $object) use ($normalized): array {
            $this->seenPage = $object->getPage();
            $this->seenPageData = $object->getPageData();

            return $normalized;
        });

        $normalizer = new RouteNormalizer(new RouteLiveResolver(), $accessChecker, "is_granted('ROLE_ADMIN')", $metadataProvider ?? new ResourceMetadataProvider());
        $normalizer->setNormalizer($inner);

        return $normalizer->normalize($route, 'jsonld', $context);
    }

    private function route(string $path, ?\DateTimeImmutable $liveAt = new \DateTimeImmutable('2000-01-01T00:00:00+00:00')): Route
    {
        $route = (new Route())->setPath($path)->setName($path)->setLiveAt($liveAt);
        (new \ReflectionProperty(Route::class, 'id'))->setValue($route, Uuid::uuid4());

        return $route;
    }
}
