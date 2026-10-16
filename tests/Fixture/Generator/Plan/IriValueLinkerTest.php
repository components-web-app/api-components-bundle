<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Plan;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\IriValueLinker;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\NotReproducedReport;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\PropertyValue;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteSnapshot;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;

class IriValueLinkerTest extends TestCase
{
    private const string COLLECTION = 'App\Entity\Collection';

    private NotReproducedReport $report;

    /** @var list<string> */
    private array $resolved = [];

    private int $iriCalls = 0;

    public function test_a_whole_value_that_is_the_iri_of_a_page_becomes_a_link_and_no_value(): void
    {
        [$value, $calls] = $this->linker()->link(self::COLLECTION, $this->field('parentPage', '/_/pages/blog'));

        self::assertNull($value);
        self::assertSame(["linkIri('parentPage', page: 'blog')"], $calls);
        self::assertSame([], $this->report->all());
    }

    public function test_an_iri_at_a_key_becomes_a_link_and_null_in_the_value_written(): void
    {
        $field = $this->field('defaultQueryParameters', ['pagination' => false, 'parentPage' => '/_/pages/blog'], 'setDefaultQueryParameters');

        [$value, $calls] = $this->linker()->link(self::COLLECTION, $field);

        self::assertInstanceOf(PropertyValue::class, $value);
        self::assertSame(['pagination' => false, 'parentPage' => null], $value->value);
        self::assertSame(PropertyValue::FIELD, $value->kind);
        self::assertSame('defaultQueryParameters', $value->property);
        self::assertSame('setDefaultQueryParameters', $value->setter);
        self::assertTrue($value->writable);
        self::assertSame(["linkIri('defaultQueryParameters', key: 'parentPage', page: 'blog')"], $calls);
    }

    public function test_an_iri_deeper_in_the_value_links_by_its_key_path(): void
    {
        $field = $this->field('defaultQueryParameters', ['filters' => [['route' => '/_/routes/about'], ['page' => '/_/layouts/main']]]);

        [$value, $calls] = $this->linker()->link(self::COLLECTION, $field);

        self::assertSame(['filters' => [['route' => null], ['page' => null]]], $value?->value);
        self::assertSame([
            "linkIri('defaultQueryParameters', key: ['filters', 0, 'route'], route: 'about')",
            "linkIri('defaultQueryParameters', key: ['filters', 1, 'page'], layout: 'main')",
        ], $calls);
    }

    public function test_an_iri_with_the_api_prefix_matches_an_iri_without_it_and_the_other_way_round(): void
    {
        [, $prefixedValue] = $this->linker()->link(self::COLLECTION, $this->field('parentPage', '/_api/_/pages/blog'));
        [, $unprefixedValue] = $this->linker('/_api')->link(self::COLLECTION, $this->field('parentPage', '/_/pages/blog'));
        [, $bothPrefixed] = $this->linker('/_api')->link(self::COLLECTION, $this->field('parentPage', '/_api/_/pages/blog'));

        self::assertSame(["linkIri('parentPage', page: 'blog')"], $prefixedValue);
        self::assertSame(["linkIri('parentPage', page: 'blog')"], $unprefixedValue);
        self::assertSame(["linkIri('parentPage', page: 'blog')"], $bothPrefixed);
    }

    public function test_nested_pages_routed_page_data_and_redirects_are_linked(): void
    {
        $value = ['child' => '/_/pages/child', 'second' => '/_/pages/about-page', 'event' => '/page_data/Event', 'old' => '/_/routes/old-about'];

        [, $calls] = $this->linker()->link(self::COLLECTION, $this->field('filters', $value));

        self::assertSame([
            "linkIri('filters', key: 'child', page: 'child')",
            "linkIri('filters', key: 'second', page: 'about-page')",
            "linkIri('filters', key: 'event', pageData: 'event')",
            "linkIri('filters', key: 'old', route: 'old-about')",
        ], $calls);
        self::assertSame([], $this->report->all());
    }

    public function test_an_iri_of_something_outside_the_site_is_reported_and_copied(): void
    {
        $value = ['component' => '/component/dummy_components/1', 'gone' => '/_/pages/gone', 'prefixed' => '/_api/component/dummy_components/2', 'draft' => '/page_data/Draft'];

        [$written, $calls] = $this->linker()->link(self::COLLECTION, $this->field('filters', $value));

        self::assertSame($value, $written?->value);
        self::assertSame([], $calls);
        self::assertSame([
            self::COLLECTION . '::$filters["component"] holds the IRI "/component/dummy_components/1", which is not a page, layout, routed page data or route of the generated site, so it is copied as it is.',
            self::COLLECTION . '::$filters["gone"] holds the IRI "/_/pages/gone", which is not a page, layout, routed page data or route of the generated site, so it is copied as it is.',
            self::COLLECTION . '::$filters["prefixed"] holds the IRI "/_api/component/dummy_components/2", which is not a page, layout, routed page data or route of the generated site, so it is copied as it is.',
            self::COLLECTION . '::$filters["draft"] holds the IRI "/page_data/Draft", which is not a page, layout, routed page data or route of the generated site, so it is copied as it is.',
        ], $this->report->all());
    }

    public function test_a_value_that_is_not_an_item_iri_is_copied_without_a_report(): void
    {
        $value = ['collection' => '/_/pages', 'text' => 'hello', 'spaced' => '/_/pages/blog and more', 'query' => '/_/pages/blog?x=1', 'path' => '/about', 'trailing' => '/about/', 'number' => 3, 'empty' => '', 'root' => '/'];

        [$written, $calls] = $this->linker()->link(self::COLLECTION, $this->field('filters', $value));

        self::assertSame($value, $written?->value);
        self::assertSame([], $calls);
        self::assertSame([], $this->report->all());
        self::assertSame(['/_/pages', '/pages', '/about', '/about/'], $this->resolved);
    }

    public function test_a_value_with_no_strings_is_returned_as_it_is(): void
    {
        $field = $this->field('perPage', 10);

        [$value, $calls] = $this->linker()->link(self::COLLECTION, $field);

        self::assertSame($field, $value);
        self::assertSame([], $calls);
        self::assertSame([], $this->resolved);
    }

    public function test_the_site_is_indexed_once(): void
    {
        $linker = $this->linker();
        $linker->link(self::COLLECTION, $this->field('parentPage', '/_/pages/blog'));
        $indexed = $this->iriCalls;

        [, $calls] = $linker->link(self::COLLECTION, $this->field('parentPage', '/_/pages/blog'));

        self::assertGreaterThan(0, $indexed);
        self::assertSame($indexed, $this->iriCalls);
        self::assertSame(["linkIri('parentPage', page: 'blog')"], $calls);
    }

    public function test_a_site_entity_with_no_iri_is_left_out_of_what_can_be_linked(): void
    {
        $layout = new Layout();
        $layout->reference = 'main';
        $page = new Page();
        $page->reference = 'blog';
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (object|string $resource): ?string => match (true) {
            $resource instanceof Layout => throw new InvalidArgumentException('Unable to generate an IRI.'),
            $resource instanceof Page => null,
            default => '/x',
        });
        $iriConverter->method('getResourceFromIri')->willThrowException(new InvalidArgumentException('No route matches.'));
        $this->report = new NotReproducedReport($iriConverter);
        $linker = new IriValueLinker(new SiteSnapshot(layouts: [$layout], pages: [$page]), $iriConverter, new PhpValueExporter(), $this->report);

        [, $calls] = $linker->link(self::COLLECTION, $this->field('parentPage', '/_/pages/blog'));

        self::assertSame([], $calls);
    }

    private function field(string $property, mixed $value, ?string $setter = null): PropertyValue
    {
        return new PropertyValue(PropertyValue::FIELD, $property, $value, true, $setter);
    }

    private function linker(string $prefix = ''): IriValueLinker
    {
        $layout = new Layout();
        $layout->reference = 'main';
        $blog = $this->page('blog', '/blog', 'blog');
        $child = $this->page('child', null, null);
        $child->setParentPage($blog);
        $event = new PageData();
        $event->setTitle('Event');
        $eventRoute = (new Route())->setPath('/event')->setName('event');
        $eventRoute->setPageData($event);
        $event->setRoute($eventRoute);
        $draft = new PageData();
        $draft->setTitle('Draft');
        $about = $blog->getRoute();
        $old = (new Route())->setPath('/old-about')->setName('old-about');
        $old->setRedirect((new Route())->setPath('/about')->setName('about'));
        $aboutPage = $this->page('about-page', '/about', 'about');

        $site = new SiteSnapshot(
            layouts: [$layout],
            pages: [$blog, $aboutPage],
            pageData: [$draft, $event],
            children: [spl_object_id($blog) => [$child]],
            routes: array_values(array_filter([$about, $aboutPage->getRoute(), $eventRoute])),
            redirects: [$old],
        );

        $component = new DummyComponent();
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(function (object|string $resource) use ($prefix): string {
            ++$this->iriCalls;

            return $prefix . match (true) {
                $resource instanceof Layout => '/_/layouts/' . $resource->reference,
                $resource instanceof Page => '/_/pages/' . $resource->reference,
                $resource instanceof Route => '/_/routes/' . $resource->getName(),
                $resource instanceof PageData => '/page_data/' . $resource->getTitle(),
                default => '/_/resources/' . spl_object_id($resource),
            };
        });
        $iriConverter->method('getResourceFromIri')->willReturnCallback(function (string $iri) use ($component, $draft): object {
            $this->resolved[] = $iri;

            return match ($iri) {
                '/component/dummy_components/1', '/component/dummy_components/2' => $component,
                '/page_data/Draft' => $draft,
                '/_/pages/gone' => throw new ItemNotFoundException('Not found.'),
                '/_/pages' => throw new InvalidArgumentException('The iri "/_/pages" references a collection not an item.'),
                default => throw new InvalidArgumentException(\sprintf('No route matches "%s".', $iri)),
            };
        });
        $this->report = new NotReproducedReport($iriConverter);

        return new IriValueLinker($site, $iriConverter, new PhpValueExporter(), $this->report);
    }

    private function page(string $reference, ?string $path, ?string $routeName): Page
    {
        $page = new Page();
        $page->reference = $reference;
        if (null !== $path) {
            $route = (new Route())->setPath($path)->setName((string) $routeName);
            $route->setPage($page);
            $page->setRoute($route);
        }

        return $page;
    }
}
