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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\EmbeddedRouteDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[CoversClass(EmbeddedRouteDenormalizer::class)]
class EmbeddedRouteDenormalizerTest extends TestCase
{
    private array $calls = [];

    private ?object $result = null;

    /**
     * @param list<string> $takenNames
     */
    private function buildDenormalizer(array $takenNames = []): EmbeddedRouteDenormalizer
    {
        $repository = $this->createStub(RouteRepository::class);
        $repository->method('count')->willReturnCallback(static fn (array $criteria): int => array_keys($criteria) === ['name'] && \in_array($criteria['name'], $takenNames, true) ? 1 : 0);

        $denormalizer = new EmbeddedRouteDenormalizer($repository);
        $test = $this;
        $denormalizer->setDenormalizer(new class($this->calls, $test) implements DenormalizerInterface {
            public function __construct(private array &$calls, private EmbeddedRouteDenormalizerTest $test)
            {
            }

            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
            {
                $this->calls[] = [$data, $type, $format, $context];

                return $this->test->denormalized();
            }

            public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            public function getSupportedTypes(?string $format): array
            {
                return ['*' => false];
            }
        });

        return $denormalizer;
    }

    public function denormalized(): ?object
    {
        return $this->result;
    }

    public function test_a_missing_name_is_derived_from_the_path(): void
    {
        $this->result = new Page();

        $this->buildDenormalizer()->denormalize(['reference' => 'home', 'route' => ['path' => '/about/team']], Page::class, 'jsonld', ['key' => 'value']);

        self::assertCount(1, $this->calls);
        [$data, $type, $format, $context] = $this->calls[0];
        self::assertSame(['reference' => 'home', 'route' => ['path' => '/about/team', 'name' => 'about-team']], $data);
        self::assertSame(Page::class, $type);
        self::assertSame('jsonld', $format);
        self::assertSame('value', $context['key']);
    }

    public function test_a_null_name_is_derived_from_the_path(): void
    {
        $this->buildDenormalizer()->denormalize(['route' => ['path' => '/about', 'name' => null]], Page::class);

        self::assertSame(['route' => ['path' => '/about', 'name' => 'about']], $this->calls[0][0]);
    }

    public function test_a_taken_derived_name_takes_the_first_free_suffix(): void
    {
        $this->buildDenormalizer(['about-team', 'about-team-1'])->denormalize(['route' => ['path' => '/about/team']], Page::class);

        self::assertSame(['route' => ['path' => '/about/team', 'name' => 'about-team-2']], $this->calls[0][0]);
    }

    public function test_a_given_name_is_passed_on_unchanged(): void
    {
        $this->buildDenormalizer(['mine'])->denormalize(['route' => ['path' => '/about', 'name' => 'mine']], Page::class);

        self::assertSame(['route' => ['path' => '/about', 'name' => 'mine']], $this->calls[0][0]);
    }

    public function test_a_route_with_no_string_path_is_left_for_validation(): void
    {
        $this->buildDenormalizer()->denormalize(['route' => ['path' => 5]], Page::class);

        self::assertSame(['route' => ['path' => 5]], $this->calls[0][0]);
    }

    public function test_a_route_iri_is_passed_on_unchanged_with_the_groups_untouched(): void
    {
        $this->buildDenormalizer()->denormalize(['route' => '/_/routes/about'], Page::class, null, ['groups' => ['PageData:cwa_resource:write']]);

        [$data, , , $context] = $this->calls[0];
        self::assertSame(['route' => '/_/routes/about'], $data);
        self::assertSame(['PageData:cwa_resource:write'], $context['groups']);
    }

    public function test_an_embedded_route_adds_the_embedded_write_group_to_existing_groups(): void
    {
        $this->buildDenormalizer()->denormalize(['route' => ['path' => '/about', 'name' => 'about']], Page::class, null, ['groups' => ['PageData:cwa_resource:write']]);

        self::assertSame(['PageData:cwa_resource:write', Route::EMBEDDED_WRITE_GROUP], $this->calls[0][3]['groups']);
    }

    public function test_an_embedded_route_adds_no_group_when_the_context_has_none(): void
    {
        $this->buildDenormalizer()->denormalize(['route' => ['path' => '/about', 'name' => 'about']], Page::class, null, ['groups' => []]);
        $this->buildDenormalizer()->denormalize(['route' => ['path' => '/about', 'name' => 'about']], Page::class);

        self::assertSame([], $this->calls[0][3]['groups']);
        self::assertArrayNotHasKey('groups', $this->calls[1][3]);
    }

    public function test_an_unowned_route_is_given_the_page(): void
    {
        $page = new Page();
        $route = new Route();
        $page->setRoute($route);
        $this->result = $page;

        $result = $this->buildDenormalizer()->denormalize([], Page::class);

        self::assertSame($page, $result);
        self::assertSame($page, $route->getPage());
        self::assertNull($route->getPageData());
    }

    public function test_an_unowned_route_is_given_the_page_data(): void
    {
        $pageData = new EmbeddedRouteDenormalizerPageData();
        $route = new Route();
        $pageData->setRoute($route);
        $this->result = $pageData;

        $this->buildDenormalizer()->denormalize([], EmbeddedRouteDenormalizerPageData::class);

        self::assertSame($pageData, $route->getPageData());
        self::assertNull($route->getPage());
    }

    public function test_a_route_owned_by_another_page_keeps_its_owner(): void
    {
        $owner = new Page();
        $route = new Route();
        $route->setPage($owner);
        $page = new Page();
        $page->setRoute($route);
        $this->result = $page;

        $this->buildDenormalizer()->denormalize([], Page::class);

        self::assertSame($owner, $route->getPage());
    }

    public function test_a_route_owned_by_page_data_gets_no_page(): void
    {
        $owner = new EmbeddedRouteDenormalizerPageData();
        $route = new Route();
        $route->setPageData($owner);
        $page = new Page();
        $page->setRoute($route);
        $this->result = $page;

        $this->buildDenormalizer()->denormalize([], Page::class);

        self::assertNull($route->getPage());
        self::assertSame($owner, $route->getPageData());
    }

    public function test_a_page_with_no_route_is_returned_as_it_is(): void
    {
        $page = new Page();
        $this->result = $page;

        self::assertSame($page, $this->buildDenormalizer()->denormalize([], Page::class));
        self::assertNull($page->getRoute());
    }

    public function test_it_supports_routable_arrays_once_per_denormalization(): void
    {
        $denormalizer = $this->buildDenormalizer();

        self::assertTrue($denormalizer->supportsDenormalization([], Page::class));
        self::assertTrue($denormalizer->supportsDenormalization([], EmbeddedRouteDenormalizerPageData::class));
        self::assertFalse($denormalizer->supportsDenormalization([], Layout::class));
        self::assertFalse($denormalizer->supportsDenormalization('/_/pages/1', Page::class));

        $denormalizer->denormalize([], Page::class, null, []);
        self::assertFalse($denormalizer->supportsDenormalization([], Page::class, null, $this->calls[0][3]));
    }

    public function test_it_declares_objects_without_caching(): void
    {
        self::assertSame(['object' => false], $this->buildDenormalizer()->getSupportedTypes(null));
    }
}

class EmbeddedRouteDenormalizerPageData extends AbstractPageData
{
}
